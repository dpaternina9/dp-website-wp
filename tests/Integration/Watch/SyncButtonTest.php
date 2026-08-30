<?php
/**
 * Integration tests for the "Sync now" button.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Watch\Settings;
use DP\Core\Watch\SyncButton;
use DP\Core\Watch\TwitchApi;
use DP\Core\Watch\VideoSync;
use DP\Core\Watch\YouTubeApi;
use RuntimeException;
use WPDieException;

/**
 * The button, its two gates, and what it says afterwards.
 *
 * The gates are asserted one at a time against a request that would otherwise
 * be accepted, for the reason `SeriesOrderScreenTest` gives at length: a test
 * that posts nonsense and asserts "nothing happened" passes whichever gate
 * closed, and stays green when one of them is deleted.
 *
 * The last group is about honesty rather than security. A button that quietly
 * does nothing when there are no credentials, or reports a success when it
 * fetched nothing, is worse than no button — so both are assertions.
 */
final class SyncButtonTest extends WatchTestCase {

	/**
	 * The button under test.
	 *
	 * @var SyncButton
	 */
	private SyncButton $button;

	/**
	 * Build the button over the intercepted clients.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->button = new SyncButton( new VideoSync( new TwitchApi(), new YouTubeApi() ) );
	}

	/**
	 * The plugin attached the write path, and gave it no unauthenticated twin.
	 *
	 * @return void
	 */
	public function test_the_plugin_attaches_the_write_path_and_nothing_public(): void {
		$this->assertNotFalse(
			has_action( 'admin_post_' . SyncButton::ACTION ),
			'Plugin::register() no longer wires the Sync now button.'
		);
		$this->assertFalse(
			has_action( 'admin_post_nopriv_' . SyncButton::ACTION ),
			'The sync is reachable without logging in.'
		);
	}

	/**
	 * The button lands in the Watch section on Settings → General.
	 *
	 * Beside the credentials it needs, which is the whole placement argument.
	 *
	 * @return void
	 */
	public function test_the_field_is_registered_in_the_watch_section(): void {
		global $wp_settings_fields;

		( new Settings() )->add();
		$this->button->add_field();

		$this->assertIsArray( $wp_settings_fields );
		$this->assertArrayHasKey( Settings::PAGE, $wp_settings_fields );
		$this->assertArrayHasKey( Settings::SECTION, $wp_settings_fields[ Settings::PAGE ] );
		$this->assertArrayHasKey( SyncButton::FORM_ID, $wp_settings_fields[ Settings::PAGE ][ Settings::SECTION ] );
	}

	/**
	 * **Gate one: the nonce.** A POST without one syncs nothing.
	 *
	 * @return void
	 */
	public function test_a_post_without_a_nonce_is_refused(): void {
		$this->become( 'administrator' );
		$this->configure_twitch();
		$this->stub_one_vod();

		$this->post( '' );

		$refused = false;

		try {
			$this->button->handle();
		} catch ( WPDieException ) {
			$refused = true;
		}

		$this->assertTrue( $refused, 'A POST with no nonce ran a sync.' );
		$this->assertSame( array(), $this->http_requests, 'A refused request still called out to Twitch.' );
	}

	/**
	 * **Gate two: the capability.** A valid nonce is not permission.
	 *
	 * The nonce is minted by the user who then posts it, so it verifies. What
	 * stops the sync is the capability and nothing else.
	 *
	 * @return void
	 */
	public function test_a_post_from_a_user_without_the_capability_is_refused(): void {
		$this->become( 'editor' );
		$this->configure_twitch();
		$this->stub_one_vod();

		$this->post( wp_create_nonce( SyncButton::ACTION ) );

		$refused = false;

		try {
			$this->button->handle();
		} catch ( WPDieException ) {
			$refused = true;
		}

		$this->assertTrue( $refused, 'An editor ran a sync.' );
		$this->assertSame( array(), $this->http_requests, 'A refused request still called out to Twitch.' );
	}

	/**
	 * A request that passes both gates syncs, and reports what it really did.
	 *
	 * @return void
	 */
	public function test_a_valid_post_syncs_and_reports_real_counts(): void {
		$this->become( 'administrator' );
		$this->configure_twitch();
		$this->stub_one_vod();

		$this->post( wp_create_nonce( SyncButton::ACTION ) );

		$location = $this->handle_expecting_a_redirect();

		$this->assertStringContainsString( 'options-general.php', $location );
		$this->assertStringContainsString( SyncButton::NOTICE_ARG . '=1', $location );

		$notice = $this->notice();

		$this->assertTrue( $notice['ok'] ?? false );
		$this->assertStringContainsString( '1 added', is_string( $notice['message'] ?? null ) ? $notice['message'] : '' );

		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type'      => 'dp_video',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
				)
			)
		);
	}

	/**
	 * A run that reached nothing is not dressed up as a success.
	 *
	 * @return void
	 */
	public function test_a_run_that_fetched_nothing_says_so(): void {
		$this->become( 'administrator' );
		$this->configure_twitch();

		$this->http_stubs[ TwitchApi::VIDEOS_URL ] = self::response( 500, '{"error":"Internal Server Error"}' );

		$this->post( wp_create_nonce( SyncButton::ACTION ) );
		$this->handle_expecting_a_redirect();

		$notice = $this->notice();

		$this->assertFalse( $notice['ok'] ?? true, 'A failed sync was reported as a success.' );
		$this->assertStringContainsString(
			'Twitch did not answer',
			is_string( $notice['message'] ?? null ) ? $notice['message'] : ''
		);
	}

	/**
	 * With nothing configured there is no button, and a sentence saying why.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_site_gets_an_explanation_and_no_button(): void {
		$html = $this->field();

		$this->assertStringNotContainsString( '<button', $html );
		$this->assertStringContainsString( 'Nothing can be imported yet', $html );
	}

	/**
	 * Half a configuration is named rather than folded into "not configured".
	 *
	 * It is the state most likely to look configured and do nothing.
	 *
	 * @return void
	 */
	public function test_half_a_configuration_says_which_half_is_missing(): void {
		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::YOUTUBE_KEY, 'AIzaSy_test-key' );

		$html = $this->field();

		$this->assertStringContainsString( 'Twitch is not ready', $html );
		$this->assertStringContainsString( 'YouTube is not ready', $html );
		$this->assertStringNotContainsString( '<button', $html );
	}

	/**
	 * Configured, the row carries a button that reaches the form by id.
	 *
	 * The `form` attribute is the whole reason the button can sit inside
	 * `options-general.php`'s own form without nesting one, so it is asserted
	 * rather than assumed.
	 *
	 * @return void
	 */
	public function test_a_configured_site_gets_a_button_wired_to_the_form(): void {
		$this->configure_twitch();

		$html = $this->field();

		$this->assertStringContainsString( 'form="' . SyncButton::FORM_ID . '"', $html );
		$this->assertStringContainsString( 'Sync now', $html );
		$this->assertStringContainsString( 'Imports videos from Twitch', $html );
	}

	/**
	 * The row reports the last run, which is the only sign the schedule works.
	 *
	 * @return void
	 */
	public function test_the_row_reports_the_last_run(): void {
		$this->configure_twitch();

		update_option(
			VideoSync::LAST_RUN,
			array(
				'time'    => time() - 300,
				'message' => 'Synced from Twitch: 3 added, 0 updated, 0 unchanged, 0 unpublished.',
			)
		);

		$html = $this->field();

		$this->assertStringContainsString( 'Last run', $html );
		$this->assertStringContainsString( '3 added', $html );
	}

	/**
	 * Draw the settings row.
	 *
	 * @return string
	 */
	private function field(): string {
		ob_start();
		$this->button->field();

		return (string) ob_get_clean();
	}

	/**
	 * Become a user with a role.
	 *
	 * @param string $role The role.
	 * @return void
	 */
	private function become( string $role ): void {
		$user = self::factory()->user->create( array( 'role' => $role ) );

		$this->assertIsInt( $user );

		wp_set_current_user( $user );
	}

	/**
	 * Give the site working Twitch credentials and a token to use.
	 *
	 * @return void
	 */
	private function configure_twitch(): void {
		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->http_stubs[ TwitchApi::TOKEN_URL ] = self::response( 200, '{"access_token":"tok","expires_in":5000000,"token_type":"bearer"}' );
		$this->http_stubs[ TwitchApi::USERS_URL ] = self::response( 200, '{"data":[{"id":"141981764","login":"patsypatz"}]}' );
	}

	/**
	 * Answer Twitch's archive endpoint with one VOD.
	 *
	 * @return void
	 */
	private function stub_one_vod(): void {
		$this->http_stubs[ TwitchApi::VIDEOS_URL ] = self::response(
			200,
			'{"data":[{"id":"335921245","title":"One stream","duration":"1h0m0s",'
			. '"published_at":"2026-08-03T21:30:18Z","thumbnail_url":"","type":"archive"}],'
			. '"pagination":{"cursor":""}}'
		);
	}

	/**
	 * Build the POST the button sends.
	 *
	 * @param string $nonce The nonce, or an empty string for none.
	 * @return void
	 */
	private function post( string $nonce ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- this method builds the request the handler is about to check. Verifying it here would be the test verifying itself.
		$_POST = array( 'action' => SyncButton::ACTION );

		if ( '' !== $nonce ) {
			$_POST['_wpnonce'] = $nonce;
		}

		$_REQUEST = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * The outcome waiting for this user, as the notice would read it.
	 *
	 * @return array<mixed, mixed>
	 */
	private function notice(): array {
		$stored = get_transient( SyncButton::NOTICE_TRANSIENT . get_current_user_id() );

		$this->assertIsArray( $stored, 'The run left nothing for the notice to say.' );

		return $stored;
	}

	/**
	 * Run the handler and return where it tried to send the browser.
	 *
	 * `handle()` ends in `exit`, which would take the test runner with it. The
	 * `wp_redirect` filter runs first, so throwing from it stops the handler at
	 * the redirect and hands the location back.
	 *
	 * @return string The redirect location.
	 */
	private function handle_expecting_a_redirect(): string {
		$stop = $this->refuse_to_redirect( ... );

		add_filter( 'wp_redirect', $stop );

		try {
			$this->button->handle();
		} catch ( RuntimeException $stopped ) {
			return $stopped->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		$this->fail( 'The handler did not redirect.' );
	}

	/**
	 * Stop a redirect and report where it was going.
	 *
	 * @param mixed $location Where core was about to send the browser.
	 * @return string
	 *
	 * @throws RuntimeException Whenever core is redirecting anywhere at all.
	 */
	private function refuse_to_redirect( mixed $location ): string {
		if ( is_string( $location ) ) {
			throw new RuntimeException( $location );
		}

		return '';
	}
}
