<?php
/**
 * Integration tests for the contact form's seven gates.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Contact;

use DP\Core\Contact\Capability;
use DP\Core\Contact\Handler;
use DP\Core\Contact\RateLimiter;
use DP\Core\Contact\Rejection;
use DP\Core\Contact\Stamp;
use DP\Core\Contact\State;
use DP\Core\Contact\Submission;

/**
 * Each gate closed on its own, against a submission that is otherwise perfect.
 *
 * `docs/plan.md` Phase 7 asks for "each rejection path individually", and the
 * word doing the work is *individually*. A test that posts an empty form and
 * asserts "it did not send" passes whichever gate closed, so it cannot tell a
 * working honeypot from a broken one — and if the nonce check were deleted, it
 * would still be green. So every test below builds a submission that would be
 * accepted, breaks exactly one thing, and asserts on the `Rejection` value.
 *
 * Two properties are asserted on every path rather than once:
 *
 * - **`wp_mail()` is not reached.** A gate that returns the right enum and still
 *   sends the message is a gate that does nothing.
 * - **The gate order holds.** The handler checks nonce and capability first
 *   because they are the cheapest and least forgiving, and the rate limit last
 *   so a mistyped address does not spend one of a real person's three attempts.
 *   Both are behaviour, not implementation detail, and both are tested
 *   directly.
 *
 * The seventh gate, Turnstile, is the only one that is off by default, so its
 * tests come in two halves. Most of this file runs with no challenge
 * configured, which is what a fresh install is and what the container has —
 * and that half is also the assertion that adding the gate changed nothing for
 * a site that did not ask for it. The rest configures one and answers
 * siteverify through `ContactTestCase::siteverify()`.
 */
final class HandlerTest extends ContactTestCase {

	/**
	 * A perfect submission goes, once.
	 *
	 * @return void
	 */
	public function test_a_valid_submission_is_sent(): void {
		$outcome = ( new Handler() )->handle( $this->submission() );

		$this->assertSame( State::Sent, $outcome->state );
		$this->assertNull( $outcome->rejection );
		$this->assertSendCount( 1 );
	}

	/**
	 * The message that goes is the one that was typed.
	 *
	 * @return void
	 */
	public function test_the_sent_message_carries_the_sender_in_reply_to(): void {
		( new Handler() )->handle(
			$this->submission(
				array(
					'name'    => 'Ada Lovelace',
					'email'   => 'ada@example.com',
					'message' => 'The analytical engine has no pretensions.',
				)
			)
		);

		$this->assertSendCount( 1 );

		$this->assertSame( get_option( 'admin_email' ), $this->mail[0]['to'] ?? null );
		$this->assertStringContainsString( 'Ada Lovelace', $this->sent( 'subject' ) );
		$this->assertStringContainsString( 'The analytical engine has no pretensions.', $this->sent( 'message' ) );
		$this->assertContains( 'Reply-To: Ada Lovelace <ada@example.com>', $this->headers() );

		/*
		 * The From address is deliberately not the sender's: a message claiming
		 * to come from a stranger's domain fails SPF and DMARC at the receiving
		 * end, which is how a contact form ends up silently undelivered.
		 */
		foreach ( $this->headers() as $header ) {
			$this->assertStringNotContainsString( 'From: Ada', $header );
		}
	}

	/**
	 * Gate one: no nonce at all.
	 *
	 * @return void
	 */
	public function test_a_missing_nonce_is_refused(): void {
		$this->assertRefusedWith( Rejection::Nonce, array( 'nonce' => '' ) );
	}

	/**
	 * Gate one: a nonce somebody made up.
	 *
	 * @return void
	 */
	public function test_a_forged_nonce_is_refused(): void {
		$this->assertRefusedWith( Rejection::Nonce, array( 'nonce' => 'deadbeef' ) );
	}

	/**
	 * Gate one: a real nonce, for something else.
	 *
	 * This is the one a nonce check is actually for. A valid nonce lifted from
	 * another form on the same site must not open this one.
	 *
	 * @return void
	 */
	public function test_a_nonce_for_another_action_is_refused(): void {
		$this->assertRefusedWith(
			Rejection::Nonce,
			array( 'nonce' => wp_create_nonce( 'some_other_action' ) )
		);
	}

	/**
	 * Gate two: the site-wide switch is off.
	 *
	 * @return void
	 */
	public function test_a_closed_form_refuses_submissions(): void {
		add_filter( 'dp_contact_form_enabled', '__return_false' );

		$this->assertRefusedWith( Rejection::Capability );
	}

	/**
	 * Gate two: this sender specifically.
	 *
	 * @return void
	 */
	public function test_a_sender_without_the_capability_is_refused(): void {
		add_filter( 'dp_contact_can_send', '__return_false' );

		$this->assertRefusedWith( Rejection::Capability );
	}

	/**
	 * Gate two: an account with the capability explicitly denied.
	 *
	 * The point of making `dp_send_message` a real capability rather than an
	 * exception to CLAUDE.md section 1.4 is that revoking it is a supported act.
	 * This is that act.
	 *
	 * @return void
	 */
	public function test_an_account_denied_the_capability_is_refused(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertIsInt( $user_id );

		$user = get_user_by( 'id', $user_id );

		$this->assertNotFalse( $user );

		$user->add_cap( Capability::SEND, false );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( Capability::SEND ) );
		$this->assertRefusedWith( Rejection::Capability );
	}

	/**
	 * Gate three: the field no person ever sees came back filled in.
	 *
	 * @return void
	 */
	public function test_a_filled_honeypot_is_refused(): void {
		$this->assertRefusedWith( Rejection::Honeypot, array( 'honeypot' => 'https://example.test/' ) );
	}

	/**
	 * Gate three: whitespace in the honeypot is not "filled in".
	 *
	 * A browser that autofills a space, or a sanitiser that leaves one behind,
	 * must not refuse a real person.
	 *
	 * @return void
	 */
	public function test_whitespace_in_the_honeypot_is_not_a_refusal(): void {
		$outcome = ( new Handler() )->handle( $this->submission( array( 'honeypot' => '   ' ) ) );

		$this->assertSame( State::Sent, $outcome->state );
	}

	/**
	 * Gate four: submitted faster than anybody could have typed it.
	 *
	 * @return void
	 */
	public function test_a_form_submitted_too_fast_is_refused(): void {
		$this->assertRefusedWith( Rejection::TooFast, array( 'stamp' => $this->stamp( 0 ) ) );
	}

	/**
	 * Gate four: a timestamp we never issued.
	 *
	 * The unsigned case is the one the gate exists for — without the signature a
	 * bot simply sends a timestamp old enough to look human.
	 *
	 * @return void
	 */
	public function test_an_unsigned_stamp_is_refused(): void {
		$this->assertRefusedWith(
			Rejection::TooFast,
			array( 'stamp' => (string) ( time() - 600 ) )
		);
	}

	/**
	 * Gate four: a form left open longer than the stamp lives.
	 *
	 * @return void
	 */
	public function test_a_stale_stamp_is_refused(): void {
		$this->assertRefusedWith(
			Rejection::TooFast,
			array( 'stamp' => $this->stamp( Stamp::MAX_AGE + 60 ) )
		);
	}

	/**
	 * Gate five: a field that did not survive sanitisation.
	 *
	 * @param array<string, string> $overrides What is wrong with this submission.
	 * @return void
	 *
	 * @dataProvider provide_incomplete_submissions
	 */
	public function test_an_incomplete_submission_is_refused( array $overrides ): void {
		$this->assertRefusedWith( Rejection::Incomplete, $overrides );
	}

	/**
	 * The ways a submission can arrive without enough to send.
	 *
	 * @return array<string, array{array<string, string>}>
	 */
	public static function provide_incomplete_submissions(): array {
		return array(
			'no name'               => array( array( 'name' => '' ) ),
			'name is whitespace'    => array( array( 'name' => "  \t " ) ),
			'no message'            => array( array( 'message' => '' ) ),
			'message is whitespace' => array( array( 'message' => "\n \n" ) ),
			'no address'            => array( array( 'email' => '' ) ),
			'address is not one'    => array( array( 'email' => 'not-an-address' ) ),
			'address has a space'   => array( array( 'email' => 'some one@example.com' ) ),
			'address is markup'     => array( array( 'email' => '<b>a@example.com</b>' ) ),
		);
	}

	/**
	 * Gate five, reached the way a request reaches it: sanitisation emptied the field.
	 *
	 * "Failed sanitisation" is not a separate gate in this design — it is what
	 * makes a field empty. A name of `<b></b>` and a message of `<script></script>`
	 * both survive `$_POST` and both come out of `Submission::from_post()` as the
	 * empty string, which is the refusal. Asserting it through the superglobal is
	 * the only way to exercise the sanitiser rather than assume it.
	 *
	 * @param string $field The POST key to fill with markup.
	 * @return void
	 *
	 * @dataProvider provide_fields_that_sanitise_away
	 */
	public function test_a_field_that_sanitises_to_nothing_is_refused( string $field ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			Submission::MARKER            => '1',
			Submission::FIELDS['name']    => 'Someone Reading',
			Submission::FIELDS['email']   => 'someone@example.com',
			Submission::FIELDS['message'] => 'A short note about espresso.',
			Submission::FIELDS['stamp']   => $this->stamp( 10 ),
			Submission::FIELDS['nonce']   => wp_create_nonce( Handler::ACTION ),
			$field                        => '<b></b>',
		);

		$handler = new Handler();
		$handler->maybe_handle();

		$this->assertSame( State::Failed, $handler->outcome()->state );
		$this->assertSame( Rejection::Incomplete, $handler->outcome()->rejection );
		$this->assertNothingWasSent();
	}

	/**
	 * The fields whose whole value can be markup.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_fields_that_sanitise_away(): array {
		return array(
			'name'    => array( Submission::FIELDS['name'] ),
			'message' => array( Submission::FIELDS['message'] ),
			'email'   => array( Submission::FIELDS['email'] ),
		);
	}

	/**
	 * With no keys, the seventh gate is not there at all.
	 *
	 * The strongest form of "inert": a submission carrying no token whatsoever
	 * sends, and nothing was asked of Cloudflare. If this ever fails, every
	 * site running this plugin without a Turnstile account has a contact form
	 * that refuses everything.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_site_has_no_challenge(): void {
		$outcome = ( new Handler() )->handle( $this->submission( array( 'turnstile' => '' ) ) );

		$this->assertSame( State::Sent, $outcome->state );
		$this->assertSendCount( 1 );
		$this->assertSame( array(), $this->siteverify_calls );
	}

	/**
	 * Gate six: a configured site accepts a token Cloudflare vouches for.
	 *
	 * @return void
	 */
	public function test_a_verified_challenge_lets_the_message_through(): void {
		$this->siteverify( array( 'success' => true ) );

		$handler = new Handler( turnstile: $this->turnstile() );

		$this->assertSame( State::Sent, $handler->handle( $this->submission() )->state );
		$this->assertSendCount( 1 );
		$this->assertCount( 1, $this->siteverify_calls );
	}

	/**
	 * Gate six: a token Cloudflare will not vouch for stops the message.
	 *
	 * @return void
	 */
	public function test_an_unverified_challenge_is_refused(): void {
		$this->siteverify(
			array(
				'success'     => false,
				'error-codes' => array( 'timeout-or-duplicate' ),
			)
		);

		$outcome = ( new Handler( turnstile: $this->turnstile() ) )->handle( $this->submission() );

		$this->assertSame( State::Failed, $outcome->state );
		$this->assertSame( Rejection::Turnstile, $outcome->rejection );
		$this->assertNothingWasSent();
	}

	/**
	 * Gate six: a configured site refuses a submission carrying no token.
	 *
	 * This is the one a visitor with a blocked third-party script hits, and it
	 * is a refusal rather than a pass on purpose. ADR-0023 names the cost.
	 *
	 * @return void
	 */
	public function test_a_configured_site_refuses_a_submission_with_no_token(): void {
		$outcome = ( new Handler( turnstile: $this->turnstile() ) )
			->handle( $this->submission( array( 'turnstile' => '' ) ) );

		$this->assertSame( Rejection::Turnstile, $outcome->rejection );
		$this->assertNothingWasSent();
		$this->assertSame( array(), $this->siteverify_calls, 'An empty token should never be carried to Cloudflare.' );
	}

	/**
	 * Cloudflare being unreachable refuses the message rather than waving it by.
	 *
	 * @return void
	 */
	public function test_a_challenge_that_cannot_be_checked_is_refused(): void {
		$this->siteverify( array( 'success' => true ), 503 );

		$outcome = ( new Handler( turnstile: $this->turnstile() ) )->handle( $this->submission() );

		$this->assertSame( Rejection::Turnstile, $outcome->rejection );
		$this->assertNothingWasSent();
	}

	/**
	 * What Cloudflare called the refusal reaches the log; the token does not.
	 *
	 * @return void
	 */
	public function test_the_refused_challenge_is_logged_with_its_error_codes(): void {
		$context = array();

		add_action(
			'dp_core_contact_refused',
			static function ( mixed $rejection, mixed $detail ) use ( &$context ): void {
				unset( $rejection );

				$context[] = $detail;
			},
			10,
			2
		);

		$this->siteverify(
			array(
				'success'     => false,
				'error-codes' => array( 'invalid-input-response' ),
			)
		);

		( new Handler( turnstile: $this->turnstile() ) )->handle(
			$this->submission( array( 'turnstile' => 'a-token-nobody-should-log' ) )
		);

		$encoded = wp_json_encode( $context );

		$this->assertIsString( $encoded );
		$this->assertStringContainsString( 'invalid-input-response', $encoded );
		$this->assertStringNotContainsString( 'a-token-nobody-should-log', $encoded );
		$this->assertStringNotContainsString( 'test-secret', $encoded );
	}

	/**
	 * The challenge is asked about before the rate limit is spent, not after.
	 *
	 * A form left open over lunch comes back with a token Cloudflare has
	 * expired, and that is a real person making a real mistake. If the limiter
	 * ran first they would have three of those and then be locked out for ten
	 * minutes — which is the same argument that put the limiter last in the
	 * first place, applied to the gate that now sits in front of it.
	 *
	 * @return void
	 */
	public function test_an_expired_challenge_does_not_spend_an_attempt(): void {
		$this->siteverify( array( 'success' => false ) );

		$handler = new Handler( turnstile: $this->turnstile() );

		for ( $attempt = 1; $attempt <= RateLimiter::LIMIT * 2; $attempt++ ) {
			$this->assertSame( Rejection::Turnstile, $handler->handle( $this->submission() )->rejection );
		}

		remove_all_filters( 'pre_http_request' );
		$this->siteverify( array( 'success' => true ) );

		$this->assertSame( State::Sent, $handler->handle( $this->submission() )->state );
	}

	/**
	 * Nothing is asked of Cloudflare for a submission an earlier gate refused.
	 *
	 * The gate sits after the field check so a bot posting an empty body cannot
	 * make this site issue an outbound HTTP request per attempt.
	 *
	 * @return void
	 */
	public function test_an_earlier_refusal_never_reaches_cloudflare(): void {
		$handler = new Handler( turnstile: $this->turnstile() );

		$handler->handle( $this->submission( array( 'nonce' => 'forged' ) ) );
		$handler->handle( $this->submission( array( 'honeypot' => 'x' ) ) );
		$handler->handle( $this->submission( array( 'email' => 'not-an-address' ) ) );

		$this->assertSame( array(), $this->siteverify_calls );
		$this->assertNothingWasSent();
	}

	/**
	 * Gate six: this sender has already sent as many as the window allows.
	 *
	 * @return void
	 */
	public function test_a_sender_over_the_limit_is_refused(): void {
		$handler = new Handler();

		for ( $sent = 1; $sent <= RateLimiter::LIMIT; $sent++ ) {
			$this->assertSame(
				State::Sent,
				$handler->handle( $this->submission() )->state,
				sprintf( 'message %d should have gone', $sent )
			);
		}

		$outcome = $handler->handle( $this->submission() );

		$this->assertSame( State::Failed, $outcome->state );
		$this->assertSame( Rejection::RateLimited, $outcome->rejection );
		$this->assertSendCount( RateLimiter::LIMIT );
	}

	/**
	 * The rate limit is the only refusal the visitor is told about specifically.
	 *
	 * Naming any of the others would be telling a spammer which check to fix.
	 *
	 * @return void
	 */
	public function test_only_the_rate_limit_is_explained_to_the_sender(): void {
		foreach ( Rejection::cases() as $rejection ) {
			$this->assertSame(
				Rejection::RateLimited === $rejection,
				$rejection->is_explained(),
				$rejection->value
			);
		}
	}

	/**
	 * A mistyped address does not spend one of a real person's three attempts.
	 *
	 * This is why the limiter sits second to last. If it ran first, a visitor who
	 * fat-fingered their address three times would be locked out without ever
	 * having sent anything.
	 *
	 * @return void
	 */
	public function test_a_refused_submission_does_not_spend_an_attempt(): void {
		$handler = new Handler();

		for ( $attempt = 1; $attempt <= RateLimiter::LIMIT * 2; $attempt++ ) {
			$handler->handle( $this->submission( array( 'email' => 'not-an-address' ) ) );
		}

		$this->assertSame( State::Sent, $handler->handle( $this->submission() )->state );
	}

	/**
	 * Nor does a flood of forged nonces lock out the address it is spoofing.
	 *
	 * @return void
	 */
	public function test_forged_nonces_do_not_exhaust_the_counter(): void {
		$handler = new Handler();

		for ( $attempt = 1; $attempt <= RateLimiter::LIMIT * 3; $attempt++ ) {
			$handler->handle( $this->submission( array( 'nonce' => 'forged' ) ) );
		}

		$this->assertSame( State::Sent, $handler->handle( $this->submission() )->state );
	}

	/**
	 * Everything passed and the transport still refused it.
	 *
	 * @return void
	 */
	public function test_a_delivery_failure_is_reported_as_such(): void {
		$this->mail_succeeds = false;

		$outcome = ( new Handler() )->handle( $this->submission() );

		$this->assertSame( State::Failed, $outcome->state );
		$this->assertSame( Rejection::MailFailed, $outcome->rejection );
		$this->assertSendCount( 1 );
		$this->assertSame( array( 'mail-failed' ), $this->refusals );
	}

	/**
	 * The cheapest gate wins when several would close.
	 *
	 * @return void
	 */
	public function test_the_first_gate_is_the_one_that_answers(): void {
		$everything_wrong = new Submission( '', 'not-an-address', '', 'filled in', 'nonsense', 'forged' );

		$outcome = ( new Handler() )->handle( $everything_wrong );

		$this->assertSame( Rejection::Nonce, $outcome->rejection );
		$this->assertNothingWasSent();
	}

	/**
	 * With the nonce fixed, the next gate answers, and so on down the list.
	 *
	 * Written as one test rather than six because the claim *is* the ordering:
	 * each step repairs the gate that just closed and asserts the next one does.
	 *
	 * @return void
	 */
	public function test_the_gates_close_in_the_documented_order(): void {
		$handler = new Handler();

		// Everything wrong at once, then repaired one gate at a time.
		$broken = array(
			'nonce'    => 'forged',
			'honeypot' => 'x',
			'stamp'    => $this->stamp( 0 ),
			'email'    => 'not-an-address',
		);

		add_filter( 'dp_contact_can_send', '__return_false' );

		$this->assertSame( Rejection::Nonce, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['nonce'] );
		$this->assertSame( Rejection::Capability, $handler->handle( $this->submission( $broken ) )->rejection );

		remove_filter( 'dp_contact_can_send', '__return_false' );
		$this->assertSame( Rejection::Honeypot, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['honeypot'] );
		$this->assertSame( Rejection::TooFast, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['stamp'] );
		$this->assertSame( Rejection::Incomplete, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['email'] );
		$this->assertSame( State::Sent, $handler->handle( $this->submission( $broken ) )->state );

		$this->assertSendCount( 1 );
		$this->assertSame(
			array( 'nonce', 'capability', 'honeypot', 'too-fast', 'incomplete' ),
			$this->refusals
		);
	}

	/**
	 * The same walk with a challenge in it: Turnstile is sixth, the limit last.
	 *
	 * A second version of the ordering test rather than a rewrite of the first,
	 * because the first is what an unconfigured site does and that is the case
	 * every install starts in. This is the configured one, and the only claim
	 * it adds is where the new gate sits.
	 *
	 * @return void
	 */
	public function test_the_challenge_closes_after_the_field_check(): void {
		$this->siteverify( array( 'success' => false ) );

		$handler = new Handler( turnstile: $this->turnstile() );

		$broken = array(
			'stamp' => $this->stamp( 0 ),
			'email' => 'not-an-address',
		);

		$this->assertSame( Rejection::TooFast, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['stamp'] );
		$this->assertSame( Rejection::Incomplete, $handler->handle( $this->submission( $broken ) )->rejection );

		unset( $broken['email'] );
		$this->assertSame( Rejection::Turnstile, $handler->handle( $this->submission( $broken ) )->rejection );

		$this->assertNothingWasSent();
		$this->assertSame( array( 'too-fast', 'incomplete', 'turnstile' ), $this->refusals );
	}

	/**
	 * A refusal carries the typed message back, and neither credential with it.
	 *
	 * The design's failure copy says "your message is still in the form", which
	 * can only be true if the refused text travels with the refusal. The nonce
	 * and the stamp are deliberately dropped: re-posting a stamp that failed the
	 * timing check would fail it again on its second life.
	 *
	 * @return void
	 */
	public function test_a_refusal_carries_the_message_back_without_its_credentials(): void {
		$outcome = ( new Handler() )->handle(
			$this->submission(
				array(
					'honeypot' => 'caught',
					'name'     => 'Someone Reading',
					'message'  => 'Still in the form.',
				)
			)
		);

		$this->assertInstanceOf( Submission::class, $outcome->submission );
		$this->assertSame( 'Someone Reading', $outcome->submission->name );
		$this->assertSame( 'someone@example.com', $outcome->submission->email );
		$this->assertSame( 'Still in the form.', $outcome->submission->message );
		$this->assertSame( '', $outcome->submission->nonce );
		$this->assertSame( '', $outcome->submission->stamp );
		$this->assertSame( '', $outcome->submission->honeypot );
	}

	/**
	 * Every refusal is announced, so a form failing closed is not also silent.
	 *
	 * @return void
	 */
	public function test_every_refusal_fires_the_log_action(): void {
		$handler = new Handler();

		$handler->handle( $this->submission( array( 'nonce' => 'forged' ) ) );
		$handler->handle( $this->submission( array( 'honeypot' => 'x' ) ) );
		$handler->handle( $this->submission( array( 'stamp' => $this->stamp( 0 ) ) ) );

		$this->assertSame( array( 'nonce', 'honeypot', 'too-fast' ), $this->refusals );
	}

	/**
	 * Nothing the visitor typed reaches the log.
	 *
	 * A site whose Privacy page is about not keeping things does not get to keep
	 * a copy of every refused message in `debug.log`.
	 *
	 * @return void
	 */
	public function test_the_log_never_carries_what_was_written(): void {
		$context = array();

		add_action(
			'dp_core_contact_refused',
			static function ( mixed $rejection, mixed $detail ) use ( &$context ): void {
				unset( $rejection );

				$context[] = $detail;
			},
			10,
			2
		);

		( new Handler() )->handle(
			$this->submission(
				array(
					'honeypot' => 'x',
					'message'  => 'A sentence nobody else should ever read.',
				)
			)
		);

		$encoded = wp_json_encode( $context );

		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'A sentence nobody else should ever read.', $encoded );
		$this->assertStringNotContainsString( 'someone@example.com', $encoded );
	}

	/**
	 * With nothing posted, the handler has decided nothing and the form shows.
	 *
	 * @return void
	 */
	public function test_a_request_that_is_not_a_submission_leaves_the_form_showing(): void {
		$handler = new Handler();

		$handler->maybe_handle();

		$this->assertSame( State::Form, $handler->outcome()->state );
		$this->assertNothingWasSent();
	}

	/**
	 * A GET carrying the form's fields is not a submission.
	 *
	 * @return void
	 */
	public function test_a_get_carrying_the_marker_is_not_a_submission(): void {
		$_SERVER['REQUEST_METHOD']   = 'GET';
		$_POST[ Submission::MARKER ] = '1';

		$handler = new Handler();
		$handler->maybe_handle();

		$this->assertSame( State::Form, $handler->outcome()->state );
		$this->assertNothingWasSent();
	}

	/**
	 * A POST without the marker is somebody else's form.
	 *
	 * @return void
	 */
	public function test_a_post_without_the_marker_is_ignored(): void {
		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_POST[ Submission::FIELDS['name'] ] = 'Someone Reading';

		$handler = new Handler();
		$handler->maybe_handle();

		$this->assertSame( State::Form, $handler->outcome()->state );
		$this->assertNothingWasSent();
	}

	/**
	 * A real POST is read out of the superglobal, sanitised, and sent.
	 *
	 * The rest of this file hands the handler a `Submission` directly, which is
	 * the only way to break one gate at a time. This is the one test that proves
	 * the same object can be built out of an actual request.
	 *
	 * @return void
	 */
	public function test_a_posted_form_is_read_sanitised_and_sent(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			Submission::MARKER             => '1',
			Submission::FIELDS['name']     => '  Someone <b>Reading</b>  ',
			Submission::FIELDS['email']    => 'someone@example.com',
			Submission::FIELDS['message']  => "First line\nSecond line",
			Submission::FIELDS['honeypot'] => '',
			Submission::FIELDS['stamp']    => $this->stamp( 10 ),
			Submission::FIELDS['nonce']    => wp_create_nonce( Handler::ACTION ),
		);

		$handler = new Handler();
		$handler->maybe_handle();

		$this->assertSame( State::Sent, $handler->outcome()->state );
		$this->assertSendCount( 1 );

		$this->assertStringContainsString( 'Someone Reading', $this->sent( 'subject' ) );
		$this->assertStringNotContainsString( '<b>', $this->sent( 'message' ) );
		$this->assertStringContainsString( "First line\nSecond line", $this->sent( 'message' ) );
	}

	/**
	 * Assert one broken field closes one named gate, and nothing goes out.
	 *
	 * @param Rejection             $expected  The gate that should answer.
	 * @param array<string, string> $overrides What is wrong with this submission.
	 * @return void
	 */
	private function assertRefusedWith( Rejection $expected, array $overrides = array() ): void {
		$outcome = ( new Handler() )->handle( $this->submission( $overrides ) );

		$this->assertSame( State::Failed, $outcome->state );
		$this->assertSame( $expected, $outcome->rejection );
		$this->assertSame( array( $expected->value ), $this->refusals );
		$this->assertNothingWasSent();
	}
}
