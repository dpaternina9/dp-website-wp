<?php
/**
 * The shared harness for the contact form's integration tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Contact;

use DP\Core\Contact\Capability;
use DP\Core\Contact\Handler;
use DP\Core\Contact\Rejection;
use DP\Core\Contact\Stamp;
use DP\Core\Contact\Submission;
use WP_UnitTestCase;

/**
 * Everything the gate tests need, and one thing they must not fake.
 *
 * **`wp_mail()` is watched, not replaced.** The acceptance criterion is that the
 * mailer is never reached on a refusal and reached exactly once on a success,
 * and the only place that is observable is core's own send. So `pre_wp_mail`
 * counts the calls and short-circuits the delivery — the container has no mail
 * transport, and a test that depended on one would be testing Docker. Every
 * assertion about "did it send" reads that counter rather than a double, so a
 * refactor that stopped calling `wp_mail()` would fail rather than pass quietly.
 *
 * **The capability is registered.** Without `Capability::register()` a
 * logged-out visitor holds nothing, every submission is refused for want of
 * `dp_send_message`, and each of the other five gate tests would pass while
 * proving nothing about its own gate. The default here is therefore an open
 * form, and the capability test is the one that closes it.
 *
 * **Nonce and stamp are real.** Both are cheap to mint correctly and both are
 * the thing under test in one case each; standing either of them down for the
 * other tests would mean the "valid submission" case never exercised the code
 * that issues them.
 */
abstract class ContactTestCase extends WP_UnitTestCase {

	/**
	 * How many times `wp_mail()` has been reached this test.
	 *
	 * @var int
	 */
	protected int $mail_calls = 0;

	/**
	 * The messages `wp_mail()` was handed, in order.
	 *
	 * @var list<array<string, mixed>>
	 */
	protected array $mail = array();

	/**
	 * What `wp_mail()` should answer. False is a transport failure.
	 *
	 * @var bool
	 */
	protected bool $mail_succeeds = true;

	/**
	 * Every refusal the log recorded this test, oldest first.
	 *
	 * @var list<string>
	 */
	protected array $refusals = array();

	/**
	 * The request method as the harness found it.
	 *
	 * `WP_UnitTestCase` empties `$_GET` and `$_POST` between tests but leaves
	 * `$_SERVER` alone, so a test that posts would otherwise leave every test
	 * after it looking like a POST.
	 *
	 * @var string|null
	 */
	private ?string $request_method = null;

	/**
	 * Grant the capability, watch the mailer, and listen to the log.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: null;

		$this->mail_calls    = 0;
		$this->mail          = array();
		$this->mail_succeeds = true;
		$this->refusals      = array();

		( new Capability() )->register();

		add_filter( 'pre_wp_mail', $this->intercept_mail( ... ), 10, 2 );
		add_action( 'dp_core_contact_refused', $this->remember_refusal( ... ) );
	}

	/**
	 * Put the request method back.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		parent::tear_down();
	}

	/**
	 * Count one send and answer it without a transport.
	 *
	 * @param mixed $short_circuit Whatever an earlier filter decided.
	 * @param mixed $attributes    The arguments `wp_mail()` was called with.
	 * @return bool
	 */
	public function intercept_mail( mixed $short_circuit, mixed $attributes ): bool {
		unset( $short_circuit );

		++$this->mail_calls;

		if ( is_array( $attributes ) ) {
			$this->mail[] = $attributes;
		}

		return $this->mail_succeeds;
	}

	/**
	 * Record a refusal the way a monitor would.
	 *
	 * @param mixed $rejection The gate that closed.
	 * @return void
	 */
	public function remember_refusal( mixed $rejection ): void {
		$this->refusals[] = $rejection instanceof Rejection ? $rejection->value : '';
	}

	/**
	 * One string field of the first message that was sent.
	 *
	 * `wp_mail()`'s arguments arrive as `array<string, mixed>`, so every
	 * assertion about them needs the type narrowed first. Doing it once here
	 * keeps the tests reading as assertions rather than as casts.
	 *
	 * @param string $field The key: `to`, `subject`, `message`.
	 * @return string
	 */
	protected function sent( string $field ): string {
		$this->assertArrayHasKey( 0, $this->mail );

		$value = $this->mail[0][ $field ] ?? null;

		$this->assertIsString( $value, sprintf( 'wp_mail() was given no string %s.', $field ) );

		return $value;
	}

	/**
	 * The headers the first message was sent with.
	 *
	 * @return list<string>
	 */
	protected function headers(): array {
		$this->assertArrayHasKey( 0, $this->mail );

		$headers = $this->mail[0]['headers'] ?? array();

		$this->assertIsArray( $headers );

		$strings = array();

		foreach ( $headers as $header ) {
			$this->assertIsString( $header );

			$strings[] = $header;
		}

		return $strings;
	}

	/**
	 * A submission that passes every gate, with any field overridden.
	 *
	 * @param array<string, string> $overrides Field name to value.
	 * @return Submission
	 */
	protected function submission( array $overrides = array() ): Submission {
		$fields = array_merge(
			array(
				'name'     => 'Someone Reading',
				'email'    => 'someone@example.com',
				'message'  => 'A short note about espresso.',
				'honeypot' => '',
				'stamp'    => $this->stamp( 10 ),
				'nonce'    => wp_create_nonce( Handler::ACTION ),
			),
			$overrides
		);

		return new Submission(
			$fields['name'],
			$fields['email'],
			$fields['message'],
			$fields['honeypot'],
			$fields['stamp'],
			$fields['nonce']
		);
	}

	/**
	 * A signed stamp issued a given number of seconds ago.
	 *
	 * @param int $seconds_ago How long the form has been on screen.
	 * @return string
	 */
	protected function stamp( int $seconds_ago ): string {
		return ( new Stamp( time() - $seconds_ago ) )->issue();
	}

	/**
	 * Assert the mailer was never reached.
	 *
	 * @return void
	 */
	protected function assertNothingWasSent(): void {
		$this->assertSame( 0, $this->mail_calls, 'wp_mail() was called on a refused submission.' );
	}

	/**
	 * Assert the mailer was reached exactly this many times.
	 *
	 * @param int $times How many.
	 * @return void
	 */
	protected function assertSendCount( int $times ): void {
		$this->assertSame( $times, $this->mail_calls );
	}
}
