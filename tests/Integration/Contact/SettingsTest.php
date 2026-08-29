<?php
/**
 * Integration tests for the two contact addresses as settings.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Contact;

use DP\Core\Contact\Handler;
use DP\Core\Contact\Settings;

/**
 * The addresses David sets in wp-admin, and the filters layered on top.
 *
 * Both addresses used to be filters and nothing else, which made the public
 * one dead UI: nothing ever answered it, so the "email instead" fallback could
 * not appear without a code change. These tests hold the new contract in
 * three parts: the options are registered where Settings → General can save
 * them, everything that is not an email address reads as unset — on the way in
 * and on the way out — and the delivery path prefers the option over
 * `admin_email` while still letting the filter override both.
 */
final class SettingsTest extends ContactTestCase {

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
	 * Both options are registered, which is what lets options.php accept them.
	 *
	 * @return void
	 */
	public function test_both_addresses_are_registered_settings(): void {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( Settings::RECIPIENT, $registered );
		$this->assertArrayHasKey( Settings::PUBLIC_ADDRESS, $registered );
		$this->assertSame( 'general', $registered[ Settings::RECIPIENT ]['group'] ?? null );
		$this->assertSame( 'general', $registered[ Settings::PUBLIC_ADDRESS ]['group'] ?? null );
	}

	/**
	 * The sanitizer keeps an address and reduces everything else to unset.
	 *
	 * @return void
	 */
	public function test_the_sanitizer_keeps_only_an_email_address(): void {
		$this->assertSame( 'david@example.com', $this->settings->sanitize( ' david@example.com ' ) );
		$this->assertSame( '', $this->settings->sanitize( 'not-an-address' ) );
		$this->assertSame( '', $this->settings->sanitize( '' ) );
		$this->assertSame( '', $this->settings->sanitize( array( 'david@example.com' ) ) );
	}

	/**
	 * The sanitizer guards the option on the way in through `update_option()`.
	 *
	 * `register_setting()`'s sanitize callback runs on every save of the
	 * option, not only on the settings form, so a bad value cannot arrive by
	 * the ordinary route at all.
	 *
	 * @return void
	 */
	public function test_saving_something_that_is_not_an_address_stores_nothing(): void {
		update_option( Settings::RECIPIENT, 'not-an-address' );

		$this->assertSame( '', get_option( Settings::RECIPIENT ) );
	}

	/**
	 * The readers do not trust the store either.
	 *
	 * An option can arrive without the sanitizer — WP-CLI with the setting
	 * unregistered, a migration writing rows — so what comes back out is
	 * validated again.
	 *
	 * @return void
	 */
	public function test_a_malformed_stored_value_reads_as_unset(): void {
		remove_all_filters( 'sanitize_option_' . Settings::RECIPIENT );
		remove_all_filters( 'sanitize_option_' . Settings::PUBLIC_ADDRESS );

		update_option( Settings::RECIPIENT, 'not-an-address' );
		update_option( Settings::PUBLIC_ADDRESS, 'also-not-an-address' );

		$this->assertSame( '', Settings::recipient() );
		$this->assertSame( '', Settings::public_address() );
	}

	/**
	 * With the option set, delivery goes there rather than to `admin_email`.
	 *
	 * @return void
	 */
	public function test_the_delivery_address_option_routes_the_message(): void {
		update_option( Settings::RECIPIENT, 'inbox@example.com' );

		( new Handler() )->handle( $this->submission() );

		$this->assertSendCount( 1 );
		$this->assertSame( 'inbox@example.com', $this->mail[0]['to'] ?? null );
	}

	/**
	 * With the option unset, delivery falls back to the administration address.
	 *
	 * @return void
	 */
	public function test_delivery_falls_back_to_admin_email(): void {
		delete_option( Settings::RECIPIENT );

		( new Handler() )->handle( $this->submission() );

		$this->assertSendCount( 1 );
		$this->assertSame( get_option( 'admin_email' ), $this->mail[0]['to'] ?? null );
	}

	/**
	 * The filter is an override on top of the option, not a casualty of it.
	 *
	 * @return void
	 */
	public function test_the_recipient_filter_still_overrides_the_option(): void {
		update_option( Settings::RECIPIENT, 'inbox@example.com' );
		add_filter( 'dp_contact_recipient', static fn (): string => 'elsewhere@example.com' );

		( new Handler() )->handle( $this->submission() );

		$this->assertSendCount( 1 );
		$this->assertSame( 'elsewhere@example.com', $this->mail[0]['to'] ?? null );
	}

	/**
	 * The field callback prints a labelled email input holding the option.
	 *
	 * @return void
	 */
	public function test_the_field_renders_the_stored_address(): void {
		update_option( Settings::PUBLIC_ADDRESS, 'hello@example.com' );

		ob_start();
		$this->settings->field(
			array(
				'label_for' => Settings::PUBLIC_ADDRESS,
				'help'      => 'What the panel may print.',
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . Settings::PUBLIC_ADDRESS . '"', $html );
		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'value="hello@example.com"', $html );
		$this->assertStringContainsString( 'What the panel may print.', $html );
	}
}
