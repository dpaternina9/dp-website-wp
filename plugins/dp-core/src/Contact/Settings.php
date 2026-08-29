<?php
/**
 * The two contact addresses, as settings David edits rather than filters.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * A "Contact form" section on Settings → General, holding two addresses.
 *
 * Both used to exist only as filters, which made one of them dead UI: nothing
 * in this project ever answered `dp_contact_public_address`, so the design's
 * "email me directly" fallback could not appear on a real site without a code
 * change — the exact thing CLAUDE.md rule 2 forbids. They are options now,
 * on the screen that already holds the administration address they relate to.
 *
 * The two are two because they answer different questions:
 *
 * - **Delivery** (`dp_contact_recipient`) is where submissions are sent.
 *   Unset, delivery falls back to Settings → General's administration address,
 *   so the form works on a fresh install with nothing configured.
 * - **Public** (`dp_contact_public_address`) is what the failure panel prints
 *   in a `mailto:`. Unset means unpublished, and the panel offers no address —
 *   deliberately not a fallback to `admin_email`, because where messages are
 *   delivered and what David publishes are two decisions, and only one of them
 *   belongs on a public page.
 *
 * The filters both survive, layered **on top of** the options, so a test
 * double or a site-specific mu-plugin can still override either without
 * touching the database. The option is the default the filter receives.
 *
 * `options-general.php` is capability-gated by core (`manage_options`), and
 * `register_setting()` is what lets `options.php` accept these two names from
 * that form; the sanitizer reduces anything that is not an email address to
 * '', which both readers treat as "unset".
 */
final class Settings {

	/**
	 * The option naming where submissions are delivered.
	 *
	 * @var string
	 */
	public const RECIPIENT = 'dp_contact_recipient';

	/**
	 * The option naming the address the failure panel may publish.
	 *
	 * @var string
	 */
	public const PUBLIC_ADDRESS = 'dp_contact_public_address';

	/**
	 * The settings page both fields live on.
	 *
	 * @var string
	 */
	private const PAGE = 'general';

	/**
	 * The section's id.
	 *
	 * @var string
	 */
	private const SECTION = 'dp-contact';

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', $this->add( ... ) );
	}

	/**
	 * Register the options and draw the section.
	 *
	 * @return void
	 */
	public function add(): void {
		register_setting(
			self::PAGE,
			self::RECIPIENT,
			array(
				'type'              => 'string',
				'description'       => __( 'Where contact form messages are delivered.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::PUBLIC_ADDRESS,
			array(
				'type'              => 'string',
				'description'       => __( 'The email address the contact form may publish as a fallback.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Contact form', 'dp-core' ),
			'__return_null',
			self::PAGE
		);

		add_settings_field(
			self::RECIPIENT,
			__( 'Delivery address', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::RECIPIENT,
				'help'      => __( 'Where messages from the contact form are sent. Leave empty to use the administration email address above.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::PUBLIC_ADDRESS,
			__( 'Public address', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::PUBLIC_ADDRESS,
				'help'      => __( 'Shown as an "email instead" fallback when the form fails. Leave empty to publish no address.', 'dp-core' ),
			)
		);
	}

	/**
	 * Reduce a submitted value to an email address or to nothing.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The address, or '' for "unset".
	 */
	public function sanitize( mixed $value ): string {
		$address = sanitize_email( is_scalar( $value ) ? (string) $value : '' );

		return is_email( $address ) === $address ? $address : '';
	}

	/**
	 * Draw one address field.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function field( array $arguments ): void {
		$option = isset( $arguments['label_for'] ) && is_string( $arguments['label_for'] ) ? $arguments['label_for'] : '';
		$help   = isset( $arguments['help'] ) && is_string( $arguments['help'] ) ? $arguments['help'] : '';

		if ( '' === $option ) {
			return;
		}

		printf(
			'<input name="%1$s" type="email" id="%1$s" value="%2$s" class="regular-text ltr" autocomplete="off">',
			esc_attr( $option ),
			esc_attr( self::address( $option ) )
		);

		if ( '' !== $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	/**
	 * The delivery address, or '' when none is set.
	 *
	 * @return string
	 */
	public static function recipient(): string {
		return self::address( self::RECIPIENT );
	}

	/**
	 * The published address, or '' when David has published none.
	 *
	 * @return string
	 */
	public static function public_address(): string {
		return self::address( self::PUBLIC_ADDRESS );
	}

	/**
	 * One option, validated on the way out as well as on the way in.
	 *
	 * The sanitizer already guards the settings form, but an option can arrive
	 * by other routes — WP-CLI, a migration — so the readers do not trust the
	 * store either. Anything that is not an address reads as unset.
	 *
	 * @param string $option The option name.
	 * @return string
	 */
	private static function address( string $option ): string {
		$stored = get_option( $option );

		if ( ! is_string( $stored ) ) {
			return '';
		}

		return is_email( $stored ) === $stored ? $stored : '';
	}
}
