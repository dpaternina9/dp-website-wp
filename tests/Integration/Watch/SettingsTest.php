<?php
/**
 * Integration tests for the Watch settings.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Watch\Settings;

/**
 * The login and the Helix credentials David sets in wp-admin.
 *
 * Phase 12's rule is that none of these may be a constant or a filter-only
 * value: the login and both credential halves are options on Settings →
 * General, sanitized on the way in, and validated again on the way out —
 * `Contact\Settings`' contract, held for three more fields.
 */
final class SettingsTest extends WatchTestCase {

	/**
	 * The settings under test.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the settings and register the section the way `admin_init` would.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = new Settings();
		$this->settings->add();
	}

	/**
	 * All three options are registered where options.php can accept them.
	 *
	 * @return void
	 */
	public function test_all_three_settings_are_registered(): void {
		$registered = get_registered_settings();
		$groups     = wp_list_pluck( $registered, 'group' );

		foreach ( array( Settings::LOGIN, Settings::CLIENT_ID, Settings::CLIENT_SECRET ) as $option ) {
			$this->assertArrayHasKey( $option, $registered );
			$this->assertSame( 'general', $groups[ $option ] ?? null );
		}
	}

	/**
	 * The login sanitizer keeps a Twitch login and reduces everything else to unset.
	 *
	 * @return void
	 */
	public function test_the_login_sanitizer_keeps_only_a_twitch_login(): void {
		$this->assertSame( 'patsypatz', $this->settings->sanitize_login( ' PatsyPatz ' ) );
		$this->assertSame( 'some_name_1', $this->settings->sanitize_login( 'some_name_1' ) );
		$this->assertSame( '', $this->settings->sanitize_login( 'https://twitch.tv/patsypatz' ), 'A pasted URL is not a login.' );
		$this->assertSame( '', $this->settings->sanitize_login( 'two words' ) );
		$this->assertSame( '', $this->settings->sanitize_login( str_repeat( 'a', 26 ) ) );
		$this->assertSame( '', $this->settings->sanitize_login( array( 'patsypatz' ) ) );
		$this->assertSame( '', $this->settings->sanitize_login( '' ) );
	}

	/**
	 * The credential sanitizer keeps one token and nothing else.
	 *
	 * @return void
	 */
	public function test_the_credential_sanitizer_keeps_only_a_token(): void {
		$this->assertSame( 'abcDEF123', $this->settings->sanitize_credential( ' abcDEF123 ' ) );
		$this->assertSame( '', $this->settings->sanitize_credential( 'two tokens' ) );
		$this->assertSame( '', $this->settings->sanitize_credential( 'token-with-dashes!' ) );
		$this->assertSame( '', $this->settings->sanitize_credential( '' ) );
	}

	/**
	 * The sanitizers guard the options on the way in through `update_option()`.
	 *
	 * @return void
	 */
	public function test_saving_a_malformed_value_stores_nothing(): void {
		update_option( Settings::LOGIN, 'Not A Login' );
		update_option( Settings::CLIENT_ID, 'not a token' );

		$this->assertSame( '', get_option( Settings::LOGIN ) );
		$this->assertSame( '', get_option( Settings::CLIENT_ID ) );
	}

	/**
	 * The readers do not trust the store either.
	 *
	 * @return void
	 */
	public function test_a_malformed_stored_login_reads_as_unset(): void {
		remove_all_filters( 'sanitize_option_' . Settings::LOGIN );

		update_option( Settings::LOGIN, 'Not A Login' );

		$this->assertSame( '', Settings::login() );
	}

	/**
	 * Credentials count only in pairs: the live check and the VOD thumbnails
	 * need both halves, and half a configuration must read as none.
	 *
	 * @return void
	 */
	public function test_credentials_count_only_in_pairs(): void {
		$this->assertFalse( Settings::has_credentials() );

		update_option( Settings::CLIENT_ID, 'abcDEF123' );

		$this->assertFalse( Settings::has_credentials() );

		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->assertTrue( Settings::has_credentials() );
	}

	/**
	 * The secret's field says out loud where it is stored.
	 *
	 * The trade — options API on a single-author site rather than a constant —
	 * is acceptable because it is visible where the secret is typed; a
	 * reworded field that dropped the disclosure would drop the visibility.
	 *
	 * @return void
	 */
	public function test_the_secret_field_discloses_where_it_is_stored(): void {
		ob_start();
		$this->settings->field(
			array(
				'label_for' => Settings::CLIENT_SECRET,
				'type'      => 'password',
				'help'      => __( 'Stored as a plain option in this site\'s database, which is an accepted trade on a single-author site — rotate it from the Twitch console if the database ever leaks.', 'dp-core' ),
			)
		);
		$field = ob_get_clean();

		$this->assertIsString( $field );
		$this->assertStringContainsString( 'type="password"', $field );
		$this->assertStringContainsString( 'Stored as a plain option', $field );
	}
}
