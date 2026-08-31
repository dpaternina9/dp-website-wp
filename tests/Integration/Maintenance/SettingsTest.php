<?php
/**
 * Integration tests for the maintenance settings.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Maintenance;

use DP\Core\Maintenance\Settings;

/**
 * The four values David sets in wp-admin, round-tripped through Settings → General.
 *
 * `Contact\Settings`' and `Watch\Settings`' contract, held for four more fields:
 * every one is registered where `options.php` can accept it, sanitized on the
 * way in and read defensively on the way out. What is new here is the checkbox,
 * and the checkbox is the field most likely to be got wrong — an unticked box
 * posts nothing at all, so a sanitizer that only handled the values a form can
 * send would give a switch that turns on and never off.
 */
final class SettingsTest extends MaintenanceTestCase {

	/**
	 * The settings under test.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Register the section the way `admin_init` would.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->settings = new Settings();
		$this->settings->add();
	}

	/**
	 * All four options are registered where options.php can accept them.
	 *
	 * @return void
	 */
	public function test_all_four_settings_are_registered(): void {
		$registered = get_registered_settings();
		$groups     = wp_list_pluck( $registered, 'group' );

		$options = array(
			Settings::ENABLED,
			Settings::HEADING,
			Settings::MESSAGE,
			Settings::CONTACT,
		);

		foreach ( $options as $option ) {
			$this->assertArrayHasKey( $option, $registered );
			$this->assertSame( 'general', $groups[ $option ] ?? null );
		}
	}

	/**
	 * The switch stores only its own value, and reads only its own value.
	 *
	 * @return void
	 */
	public function test_the_switch_sanitizer_keeps_only_on_or_off(): void {
		$this->assertSame( '1', $this->settings->sanitize_switch( '1' ) );
		$this->assertSame( '1', $this->settings->sanitize_switch( 'on' ) );
		$this->assertSame( '', $this->settings->sanitize_switch( '0' ) );
		$this->assertSame( '', $this->settings->sanitize_switch( '' ) );
		$this->assertSame( '', $this->settings->sanitize_switch( 'something else' ) );
		$this->assertSame( '', $this->settings->sanitize_switch( array( '1' ) ) );
	}

	/**
	 * An unticked box turns maintenance off.
	 *
	 * `wp-admin/options.php` hands every registered option in the group to its
	 * sanitizer whether the form posted it or not, and passes `null` for the ones
	 * it did not. A sanitizer that read `null` as "leave it alone" would give a
	 * switch that could be turned on and never off from the screen it lives on —
	 * which, since that screen is the only way to reach it, would mean a site
	 * nobody could bring back.
	 *
	 * @return void
	 */
	public function test_an_unticked_box_turns_it_off(): void {
		update_option( Settings::ENABLED, '1' );

		$this->assertTrue( Settings::is_on() );

		update_option( Settings::ENABLED, $this->settings->sanitize_switch( null ) );

		$this->assertFalse( Settings::is_on() );
	}

	/**
	 * The copy sanitizers strip markup and keep what David typed.
	 *
	 * @return void
	 */
	public function test_the_copy_sanitizers_keep_plain_text(): void {
		$this->assertSame( 'Back soon', $this->settings->sanitize_line( '  Back soon  ' ) );
		$this->assertSame( 'Back soon', $this->settings->sanitize_line( '<b>Back soon</b>' ) );
		$this->assertSame( 'One line', $this->settings->sanitize_line( "One\nline" ), 'A heading is one line.' );
		$this->assertSame( '', $this->settings->sanitize_line( array( 'Back soon' ) ) );

		$this->assertSame(
			"First paragraph.\n\nSecond paragraph.",
			$this->settings->sanitize_body( "First paragraph.\n\nSecond paragraph." ),
			'The message keeps its newlines, which is what makes paragraphs possible.'
		);
		$this->assertSame( '', $this->settings->sanitize_body( '<script>alert(1)</script>' ), 'A script element and its contents both go.' );
	}

	/**
	 * The address sanitizer keeps an address and nothing else.
	 *
	 * @return void
	 */
	public function test_the_address_sanitizer_keeps_only_an_address(): void {
		$this->assertSame( 'hello@example.com', $this->settings->sanitize_address( ' hello@example.com ' ) );
		$this->assertSame( '', $this->settings->sanitize_address( 'not an address' ) );
		$this->assertSame( '', $this->settings->sanitize_address( 'https://example.com' ) );
		$this->assertSame( '', $this->settings->sanitize_address( '' ) );
	}

	/**
	 * The sanitizers guard the options on the way in through `update_option()`.
	 *
	 * @return void
	 */
	public function test_saving_a_malformed_value_stores_nothing(): void {
		update_option( Settings::ENABLED, 'yes please' );
		update_option( Settings::CONTACT, 'not an address' );

		$this->assertSame( '', get_option( Settings::ENABLED ) );
		$this->assertSame( '', get_option( Settings::CONTACT ) );
	}

	/**
	 * The readers do not trust the store either.
	 *
	 * @return void
	 */
	public function test_a_malformed_stored_address_reads_as_unset(): void {
		remove_all_filters( 'sanitize_option_' . Settings::CONTACT );

		update_option( Settings::CONTACT, 'https://example.com' );

		$this->assertSame( '', Settings::contact() );
	}

	/**
	 * A blank heading falls back; a blank message does not.
	 *
	 * The asymmetry is the decision: a document needs exactly one `<h1>`, so an
	 * empty heading is a blank to fill, but a heading on its own is a screen
	 * somebody might want, so an emptied message stays empty rather than being
	 * overruled by the shipped sentence.
	 *
	 * @return void
	 */
	public function test_the_heading_falls_back_and_the_message_does_not(): void {
		update_option( Settings::HEADING, '' );
		update_option( Settings::MESSAGE, '' );

		$this->assertSame( Settings::default_heading(), Settings::heading() );
		$this->assertSame( '', Settings::message() );
	}

	/**
	 * What David sets is what comes back.
	 *
	 * @return void
	 */
	public function test_the_settings_round_trip(): void {
		update_option( Settings::ENABLED, '1' );
		update_option( Settings::HEADING, 'Pardon the dust' );
		update_option( Settings::MESSAGE, "Almost there.\n\nCheck back tomorrow." );
		update_option( Settings::CONTACT, 'hello@example.com' );

		$this->assertTrue( Settings::is_on() );
		$this->assertSame( 'Pardon the dust', Settings::heading() );
		$this->assertSame( "Almost there.\n\nCheck back tomorrow.", Settings::message() );
		$this->assertSame( 'hello@example.com', Settings::contact() );
	}

	/**
	 * The shipped copy asserts nothing about anybody.
	 *
	 * Every string in this repository's design and seed is placeholder, and a
	 * default that named a person, a product or a date would be this code
	 * inventing a fact about David. It is asserted rather than trusted because a
	 * default is exactly the kind of string somebody later makes "nicer".
	 *
	 * @return void
	 */
	public function test_the_shipped_copy_is_placeholder(): void {
		$defaults = Settings::default_heading() . ' ' . Settings::default_message();

		$this->assertStringNotContainsStringIgnoringCase( 'david', $defaults );
		$this->assertStringNotContainsStringIgnoringCase( 'paternina', $defaults );
		$this->assertStringNotContainsStringIgnoringCase( 'dpaternina.com', $defaults );
		$this->assertStringNotContainsString( '@', $defaults, 'No default publishes an address.' );
		$this->assertStringNotContainsString( 'http', $defaults, 'No default carries a link.' );

		$this->assertSame( '', Settings::contact(), 'The address ships blank, so no link is rendered.' );
	}

	/**
	 * The switch's field says what turning it on does.
	 *
	 * It is the one field with a behaviour, and the behaviour — a public 503 and
	 * a REST API that stops answering strangers — is not guessable from a
	 * checkbox labelled "Maintenance mode".
	 *
	 * @return void
	 */
	public function test_the_switch_field_says_what_it_does(): void {
		ob_start();
		$this->settings->switch_field(
			array(
				'label_for' => Settings::ENABLED,
				'help'      => 'Every public page, feed and anonymous REST request answers 503.',
			)
		);
		$field = ob_get_clean();

		$this->assertIsString( $field );
		$this->assertStringContainsString( 'type="checkbox"', $field );
		$this->assertStringContainsString( 'id="' . Settings::ENABLED . '"', $field );
		$this->assertStringContainsString( '503', $field );
	}
}
