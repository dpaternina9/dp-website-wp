<?php
/**
 * Integration tests for the `dp/contact-form` block's three panels.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Contact;

use DP\Core\Contact\ContactForm;
use DP\Core\Contact\Handler;
use DP\Core\Contact\Outcome;
use DP\Core\Contact\PanelCopy;
use DP\Core\Contact\Rejection;
use DP\Core\Contact\Settings;
use DP\Core\Contact\Submission;
use WP_Block_Type_Registry;

/**
 * The design's `showForm` / `sent` / `failed` branches, rendered.
 *
 * `dpaternina.dc.html`'s contact composition is one card showing one of three
 * faces. The handler decides which; this asserts each is drawn, that the form
 * carries every credential the handler will demand back, and that the two
 * things the panel must never do — publish an address nobody chose, and point
 * at a page that does not exist — it does not do.
 *
 * The honeypot gets its own test because it is the one field whose correctness
 * is invisible: it has to be in the markup, out of the tab order, hidden from
 * assistive technology, and empty. A honeypot a person can see is a honeypot a
 * person fills in, and the refusal that follows is indistinguishable from a bug.
 */
final class ContactFormTest extends ContactTestCase {

	/**
	 * The block, wired to a handler the test drives directly.
	 *
	 * @var ContactForm
	 */
	private ContactForm $block;

	/**
	 * Build the block.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->block = new ContactForm( dirname( __DIR__, 3 ) . '/plugins/dp-core', new Handler() );
	}

	/**
	 * The block is registered under the name the templates use.
	 *
	 * @return void
	 */
	public function test_the_block_is_registered(): void {
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( ContactForm::BLOCK_NAME ),
			'dp/contact-form is not registered; Plugin::register() is where it is attached.'
		);
	}

	/**
	 * The default panel is the form.
	 *
	 * @return void
	 */
	public function test_the_form_is_what_an_untouched_page_draws(): void {
		$html = $this->block->panel( Outcome::form() );

		$this->assertStringContainsString( 'data-dp-contact-state="form"', $html );
		$this->assertStringContainsString( 'Send a note', $html );
		$this->assertStringContainsString( 'Send it', $html );
		$this->assertStringContainsString( 'Goes straight to my inbox. No list, no autoreply.', $html );
	}

	/**
	 * The form posts to the page it is on, and names no endpoint.
	 *
	 * CLAUDE.md section 5.1: the theme and the plugin register no routes for
	 * pages. A form with an action attribute would be one.
	 *
	 * @return void
	 */
	public function test_the_form_has_no_action_attribute(): void {
		$html = $this->block->panel( Outcome::form() );

		$this->assertStringContainsString( '<form class="dp-contact-form" method="post"', $html );
		$this->assertStringNotContainsString( 'action=', $html );
	}

	/**
	 * Every credential the handler demands is in the markup it hands out.
	 *
	 * @return void
	 */
	public function test_the_form_carries_the_marker_the_nonce_and_the_stamp(): void {
		$html = $this->block->panel( Outcome::form() );

		foreach (
			array(
				Submission::MARKER,
				Submission::FIELDS['nonce'],
				Submission::FIELDS['stamp'],
				Submission::FIELDS['name'],
				Submission::FIELDS['email'],
				Submission::FIELDS['message'],
				Submission::FIELDS['honeypot'],
			) as $field
		) {
			$this->assertStringContainsString( 'name="' . $field . '"', $html, $field );
		}

		$this->assertSame( 1, preg_match( '~name="' . Submission::FIELDS['nonce'] . '" value="([^"]+)"~', $html, $nonce ) );
		$this->assertSame( 1, wp_verify_nonce( $nonce[1], Handler::ACTION ) );
	}

	/**
	 * The stamp the form hands out is one this site will accept back.
	 *
	 * @return void
	 */
	public function test_the_stamp_in_the_form_is_one_of_ours(): void {
		$html = $this->block->panel( Outcome::form() );

		$this->assertSame( 1, preg_match( '~name="' . Submission::FIELDS['stamp'] . '" value="([^"]+)"~', $html, $stamp ) );

		$outcome = ( new Handler() )->handle(
			$this->submission(
				array(
					'stamp' => $stamp[1],
				)
			)
		);

		/*
		 * Freshly issued, so it is refused for being too fast — which is the
		 * proof that it verified: an unsigned stamp is refused by the same gate
		 * without ever being aged.
		 */
		$this->assertSame( Rejection::TooFast, $outcome->rejection );
	}

	/**
	 * The honeypot is present, empty, and out of everybody's way.
	 *
	 * @return void
	 */
	public function test_the_honeypot_is_hidden_from_people_and_left_empty(): void {
		$html = $this->block->panel( Outcome::form() );

		$this->assertSame( 1, preg_match( '~<div class="dp-hp"[^>]*>.*?</div>~s', $html, $trap ) );

		$this->assertStringContainsString( 'aria-hidden="true"', $trap[0] );
		$this->assertStringContainsString( 'tabindex="-1"', $trap[0] );
		$this->assertStringContainsString( 'autocomplete="off"', $trap[0] );
		$this->assertStringContainsString( 'name="' . Submission::FIELDS['honeypot'] . '"', $trap[0] );
		$this->assertStringContainsString( 'value=""', $trap[0] );
	}

	/**
	 * The honeypot is styled out of sight, in a file the editor gets too.
	 *
	 * The markup alone does not hide it — `aria-hidden` keeps it away from a
	 * screen reader and `tabindex="-1"` from the keyboard, but a sighted person
	 * with a mouse sees a text input labelled "Leave this field empty" unless a
	 * stylesheet moves it. That stylesheet is the theme's, so this asserts on
	 * the file rather than on the render.
	 *
	 * @return void
	 */
	public function test_the_honeypot_is_taken_off_screen_by_the_stylesheet(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test, not a remote resource.
		$css = file_get_contents( dirname( __DIR__, 3 ) . '/themes/dpaternina/assets/css/components.css' );

		$this->assertIsString( $css );
		$this->assertMatchesRegularExpression( '~\.dp-hp\s*\{~', $css );
	}

	/**
	 * The sent panel is the design's copy, announced politely.
	 *
	 * @return void
	 */
	public function test_the_sent_panel_says_it_landed(): void {
		$html = $this->block->panel( Outcome::sent() );

		$this->assertStringContainsString( 'data-dp-contact-state="sent"', $html );
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertStringContainsString( 'It landed. Thanks.', $html );
		$this->assertStringContainsString( 'Send another', $html );
	}

	/**
	 * The failed panel is the design's copy, announced urgently.
	 *
	 * @return void
	 */
	public function test_the_failed_panel_says_it_did_not_send(): void {
		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Still here.' ) )
		);

		$this->assertStringContainsString( 'data-dp-contact-state="failed"', $html );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'That did not send.', $html );
		$this->assertStringContainsString( 'Try again', $html );
	}

	/**
	 * "Your message is still in the form" is true.
	 *
	 * @return void
	 */
	public function test_the_failed_panel_carries_the_message_back(): void {
		$html = $this->block->panel(
			Outcome::failed(
				Rejection::MailFailed,
				new Submission( 'Someone Reading', 'someone@example.com', 'A note about espresso.' )
			)
		);

		$this->assertStringContainsString( 'value="Someone Reading"', $html );
		$this->assertStringContainsString( 'value="someone@example.com"', $html );
		$this->assertStringContainsString( 'value="A note about espresso."', $html );
	}

	/**
	 * The retry carries fresh credentials, not the ones that were refused.
	 *
	 * @return void
	 */
	public function test_the_failed_panel_issues_a_new_nonce_and_stamp(): void {
		$html = $this->block->panel(
			Outcome::failed( Rejection::TooFast, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertSame( 1, preg_match( '~name="' . Submission::FIELDS['nonce'] . '" value="([^"]+)"~', $html, $nonce ) );
		$this->assertSame( 1, wp_verify_nonce( $nonce[1], Handler::ACTION ) );
		$this->assertStringContainsString( 'name="' . Submission::FIELDS['stamp'] . '"', $html );
	}

	/**
	 * The rate limit is the one refusal the panel explains.
	 *
	 * @return void
	 */
	public function test_the_rate_limited_panel_says_why(): void {
		$limited = $this->block->panel(
			Outcome::failed( Rejection::RateLimited, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertStringContainsString( 'That is a few in a short space of time', $limited );
	}

	/**
	 * Every other refusal gets the design's own failure copy and nothing more.
	 *
	 * Telling a sender which of six checks refused them is telling a spammer
	 * which one to fix, so no rejection value may reach the page.
	 *
	 * @return void
	 */
	public function test_no_other_refusal_names_the_gate_that_closed(): void {
		$lines = array();

		foreach ( Rejection::cases() as $rejection ) {
			if ( Rejection::RateLimited === $rejection ) {
				continue;
			}

			$html = $this->block->panel(
				Outcome::failed( $rejection, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
			);

			$this->assertStringNotContainsString( $rejection->reason(), $html, $rejection->value );

			$this->assertSame( 1, preg_match( '~<p class="dp-contact-result-line">(.*?)</p>~s', $html, $line ) );

			$lines[ $rejection->value ] = $line[1];
		}

		/*
		 * One copy, not six. Compared rather than asserted against a literal, so
		 * a change to the design's failure copy changes one string in the block
		 * and this test still means "every gate says the same thing".
		 */
		$this->assertCount( 1, array_unique( array_values( $lines ) ) );
		$this->assertStringContainsString( 'Something on my end dropped it', (string) reset( $lines ) );
	}

	/**
	 * With no published address there is no "email instead" link.
	 *
	 * The design prints `hello@dpaternina.com` in two places and that is
	 * placeholder copy. Where messages are delivered and what David publishes
	 * are two decisions; only one of them belongs on a public page.
	 *
	 * @return void
	 */
	public function test_no_address_is_published_unless_one_is_given(): void {
		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$delivery = get_option( 'admin_email' );

		$this->assertIsString( $delivery );
		$this->assertStringNotContainsString( 'mailto:', $html );
		$this->assertStringNotContainsString( $delivery, $html );
	}

	/**
	 * Given one, it is offered.
	 *
	 * @return void
	 */
	public function test_a_published_address_becomes_the_fallback(): void {
		add_filter( 'dp_contact_public_address', static fn (): string => 'hello@example.com' );

		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertStringContainsString( 'mailto:hello@example.com', $html );
		$this->assertStringContainsString( 'Email instead', $html );
	}

	/**
	 * An address that is not an address is not published either.
	 *
	 * @return void
	 */
	public function test_a_malformed_published_address_is_dropped(): void {
		add_filter( 'dp_contact_public_address', static fn (): string => 'not-an-address' );

		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	/**
	 * With nothing answering `dp_destination_url`, there is no "read something".
	 *
	 * The plugin may not know where the writing is (CLAUDE.md section 5.1). With
	 * the theme switched off nothing answers, and a link to a guess is worse
	 * than no link.
	 *
	 * @return void
	 */
	public function test_the_sent_panel_omits_a_destination_nothing_answers_for(): void {
		add_filter( 'dp_destination_url', '__return_null', 99 );

		$html = $this->block->panel( Outcome::sent() );

		$this->assertStringNotContainsString( 'Read something', $html );
	}

	/**
	 * With the theme answering, the link is there.
	 *
	 * @return void
	 */
	public function test_the_sent_panel_links_to_wherever_the_writing_is(): void {
		add_filter(
			'dp_destination_url',
			static fn ( mixed $url, mixed $destination ): ?string => 'posts' === $destination
				? 'https://example.org/field-notes/'
				: null,
			10,
			2
		);

		$html = $this->block->panel( Outcome::sent() );

		$this->assertStringContainsString( 'https://example.org/field-notes/', $html );
		$this->assertStringContainsString( 'Read something', $html );
	}

	/**
	 * A closed form renders nothing at all rather than a form that cannot be used.
	 *
	 * @return void
	 */
	public function test_a_closed_form_renders_nothing(): void {
		add_filter( 'dp_contact_form_enabled', '__return_false' );

		$this->assertSame( '', $this->block->render() );
	}

	/**
	 * The enhanced path is answered by the same renderer as the plain one.
	 *
	 * One decision, one renderer, two envelopes: a second code path that had to
	 * agree with this one about six security gates is one code path too many.
	 *
	 * @return void
	 */
	public function test_the_json_path_renders_the_same_panel(): void {
		$outcome = Outcome::sent();

		$this->assertSame(
			$this->block->panel( $outcome ),
			$this->block->panel_for( '', $outcome )
		);
	}

	/**
	 * Anything that is not an outcome gets no panel.
	 *
	 * @return void
	 */
	public function test_the_json_path_ignores_something_that_is_not_an_outcome(): void {
		$this->assertSame( '', $this->block->panel_for( '', 'sent' ) );
	}

	/**
	 * The form speaks with whatever copy the block's attributes carry.
	 *
	 * CLAUDE.md rule 2: the panel's voice is David's to change from the
	 * editor, not a release's. Every string asserted on here is one that used
	 * to be written into the render callback.
	 *
	 * @return void
	 */
	public function test_the_forms_copy_is_the_blocks_to_change(): void {
		$html = $this->block->panel(
			Outcome::form(),
			array(
				'heading'         => 'Say hello.',
				'nameLabel'       => 'Who are you?',
				'namePlaceholder' => 'Ada',
				'submitLabel'     => 'Fire away',
				'note'            => 'Read by a person.',
			)
		);

		$this->assertStringContainsString( 'Say hello.', $html );
		$this->assertStringContainsString( 'Who are you?', $html );
		$this->assertStringContainsString( 'placeholder="Ada"', $html );
		$this->assertStringContainsString( 'Fire away', $html );
		$this->assertStringContainsString( 'Read by a person.', $html );
		$this->assertStringNotContainsString( 'Send a note', $html );
		$this->assertStringNotContainsString( 'Send it', $html );
	}

	/**
	 * The result panels speak with the block's copy too.
	 *
	 * @return void
	 */
	public function test_the_result_panels_copy_is_the_blocks_to_change(): void {
		$attributes = array(
			'sentHeading'      => 'Got it.',
			'sentLine'         => 'Expect a reply on Tuesday.',
			'sendAnotherLabel' => 'One more',
			'failedHeading'    => 'It bounced.',
			'rateLimitedLine'  => 'Too many, too fast.',
			'tryAgainLabel'    => 'Once more',
		);

		$sent = $this->block->panel( Outcome::sent(), $attributes );

		$this->assertStringContainsString( 'Got it.', $sent );
		$this->assertStringContainsString( 'Expect a reply on Tuesday.', $sent );
		$this->assertStringContainsString( 'One more', $sent );
		$this->assertStringNotContainsString( 'It landed. Thanks.', $sent );

		$failed = $this->block->panel(
			Outcome::failed( Rejection::RateLimited, new Submission( 'Someone', 'someone@example.com', 'Again.' ) ),
			$attributes
		);

		$this->assertStringContainsString( 'It bounced.', $failed );
		$this->assertStringContainsString( 'Too many, too fast.', $failed );
		$this->assertStringContainsString( 'Once more', $failed );
		$this->assertStringNotContainsString( 'That did not send.', $failed );
	}

	/**
	 * A blanked control keeps its words; a blanked line disappears.
	 *
	 * A submit button or a field label with no text is a broken control, so a
	 * blank there reads as "nothing chosen" and the default answers. The note
	 * and the result lines are optional prose, and clearing one is a decision
	 * to say nothing.
	 *
	 * @return void
	 */
	public function test_blank_controls_fall_back_and_blank_lines_disappear(): void {
		$html = $this->block->panel(
			Outcome::form(),
			array(
				'submitLabel' => '  ',
				'note'        => '',
			)
		);

		$this->assertStringContainsString( 'Send it', $html );
		$this->assertStringNotContainsString( 'dp-contact-note', $html );
	}

	/**
	 * `block.json` and `PanelCopy` tell the same story.
	 *
	 * The defaults live twice on purpose — `block.json` so the inspector shows
	 * the strings being edited, `PanelCopy::defaults()` so they pass through
	 * `__()` — and twice is only acceptable while they cannot drift.
	 *
	 * @return void
	 */
	public function test_the_block_json_defaults_are_panel_copys_defaults(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test, not a remote resource.
		$json    = file_get_contents( dirname( __DIR__, 3 ) . '/plugins/dp-core/blocks/contact-form/block.json' );
		$decoded = json_decode( is_string( $json ) ? $json : '', true );

		$this->assertIsArray( $decoded );
		$this->assertIsArray( $decoded['attributes'] ?? null );

		$declared = array();

		foreach ( $decoded['attributes'] as $name => $attribute ) {
			$this->assertIsArray( $attribute, (string) $name );

			$declared[ $name ] = $attribute['default'] ?? null;
		}

		$expected = PanelCopy::defaults();

		ksort( $declared );
		ksort( $expected );

		$this->assertSame( $expected, $declared );
	}

	/**
	 * The Public address setting is what publishes the fallback.
	 *
	 * This is the half that used to be dead UI: the filter had no default and
	 * nothing in the project answered it, so "email instead" could never
	 * render without a code change.
	 *
	 * @return void
	 */
	public function test_the_public_address_option_becomes_the_fallback(): void {
		update_option( Settings::PUBLIC_ADDRESS, 'hello@example.com' );

		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertStringContainsString( 'mailto:hello@example.com', $html );
		$this->assertStringContainsString( 'Email instead', $html );
	}

	/**
	 * The filter is layered on top of the option, not replaced by it.
	 *
	 * @return void
	 */
	public function test_the_public_address_filter_overrides_the_option(): void {
		update_option( Settings::PUBLIC_ADDRESS, 'hello@example.com' );
		add_filter( 'dp_contact_public_address', static fn (): string => 'direct@example.com' );

		$html = $this->block->panel(
			Outcome::failed( Rejection::MailFailed, new Submission( 'Someone', 'someone@example.com', 'Again.' ) )
		);

		$this->assertStringContainsString( 'mailto:direct@example.com', $html );
		$this->assertStringNotContainsString( 'hello@example.com', $html );
	}

	/**
	 * The enhanced path reads the copy from the page the form was posted to.
	 *
	 * The `fetch` upgrade is answered from `template_redirect`, before any
	 * block renders, so the attributes have to be found where the block is
	 * saved. A panel that came back in the design's voice after David rewrote
	 * it would be the two paths drifting — the exact thing one renderer was
	 * supposed to prevent.
	 *
	 * @return void
	 */
	public function test_the_json_path_reads_the_copy_from_the_pages_content(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Say hello',
				'post_content' => '<!-- wp:dp/contact-form {"sentHeading":"Page speaking."} /-->',
			)
		);

		$this->assertIsInt( $page_id );

		$this->go_to( (string) get_permalink( $page_id ) );

		$html = $this->block->panel_for( '', Outcome::sent() );

		$this->assertStringContainsString( 'Page speaking.', $html );
		$this->assertStringNotContainsString( 'It landed. Thanks.', $html );
	}

	/**
	 * The enhanced path reads the copy from the page's template as well.
	 *
	 * The shipped placement is `<!-- wp:dp/contact-form /-->` inside the
	 * contact template, so a site-editor edit of the copy lands on the saved
	 * template override, not on the page. The lookup follows the page's
	 * assigned template slug there.
	 *
	 * @return void
	 */
	public function test_the_json_path_reads_the_copy_from_the_pages_template(): void {
		$template_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'dp-copy-fixture',
				'post_status'  => 'publish',
				'post_title'   => 'Copy fixture',
				'post_content' => '<!-- wp:group {"layout":{"type":"default"}} --><div class="wp-block-group">'
					. '<!-- wp:dp/contact-form {"sentHeading":"Template speaking."} /-->'
					. '</div><!-- /wp:group -->',
			)
		);

		$this->assertIsInt( $template_id );

		wp_set_object_terms( $template_id, get_stylesheet(), 'wp_theme' );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Say hello',
			)
		);

		$this->assertIsInt( $page_id );

		update_post_meta( $page_id, '_wp_page_template', 'dp-copy-fixture' );

		$this->go_to( (string) get_permalink( $page_id ) );

		$html = $this->block->panel_for( '', Outcome::sent() );

		$this->assertStringContainsString( 'Template speaking.', $html );
	}
}
