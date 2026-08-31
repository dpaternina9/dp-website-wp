<?php
/**
 * The maintenance switch and the copy on the screen, as settings David edits.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

/**
 * A "Maintenance" section on Settings → General, holding a switch and three strings.
 *
 * `Contact\Settings` and `Watch\Settings` are the pattern and this follows it
 * exactly, for the reason CLAUDE.md rule 2 gives: a screen the public sees is
 * copy, and copy David cannot change from wp-admin is copy this repository has
 * decided on his behalf. Nothing on the rendered document is a literal in PHP —
 * every sentence comes from one of these fields or from WordPress's own site
 * name and tagline.
 *
 * The four are four because they fail differently:
 *
 * - **Enabled** (`dp_maintenance_enabled`) is the switch, and it is the only
 *   field with a behaviour. Off is the shipped state: installing or updating
 *   this plugin changes nothing about a running site.
 * - **Heading** (`dp_maintenance_heading`) becomes the document's single `<h1>`.
 *   It is the one field with a fallback, because a page with no `<h1>` is a
 *   WCAG failure rather than an editorial choice — see `heading()`.
 * - **Message** (`dp_maintenance_message`) is the body, and has no fallback: a
 *   heading on its own is a legitimate screen, so a blank message renders
 *   nothing rather than reverting to the shipped sentence.
 * - **Contact** (`dp_maintenance_contact`) is the only link the screen can
 *   carry. Blank — the shipped state — means no link at all. While the curtain
 *   is down the contact form is behind it, so this is the one way left to reach
 *   a person, and it is an address David publishes deliberately rather than a
 *   fallback to `admin_email`, which is `Contact\Settings`' distinction and the
 *   same one applies here.
 *
 * **The defaults are placeholder copy and must stay that way.** Everything in
 * this repository's seed and design is placeholder; a default that asserted
 * anything about David or his work would be this code inventing a fact.
 *
 * The switch stores `'1'` or `''` rather than a boolean because that is what a
 * checkbox posts and what `update_option()` round-trips without coercion. An
 * unticked box posts nothing at all, and `wp-admin/options.php` answers that by
 * passing `null` to the sanitizer for every registered option the form omitted —
 * which is why `sanitize_switch()` has to read `null` as "off" rather than as
 * "leave it alone".
 */
final class Settings {

	/**
	 * The option holding the switch.
	 *
	 * @var string
	 */
	public const ENABLED = 'dp_maintenance_enabled';

	/**
	 * The option holding the screen's heading.
	 *
	 * @var string
	 */
	public const HEADING = 'dp_maintenance_heading';

	/**
	 * The option holding the screen's body copy.
	 *
	 * @var string
	 */
	public const MESSAGE = 'dp_maintenance_message';

	/**
	 * The option holding the address the screen may publish.
	 *
	 * @var string
	 */
	public const CONTACT = 'dp_maintenance_contact';

	/**
	 * The settings page the section lives on.
	 *
	 * @var string
	 */
	public const PAGE = 'general';

	/**
	 * The section's id.
	 *
	 * @var string
	 */
	public const SECTION = 'dp-maintenance';

	/**
	 * The value the switch stores when it is on.
	 *
	 * @var string
	 */
	private const ON = '1';

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
			self::ENABLED,
			array(
				'type'              => 'string',
				'description'       => __( 'Whether the public site is replaced by a maintenance screen.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_switch( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::HEADING,
			array(
				'type'              => 'string',
				'description'       => __( 'The heading on the maintenance screen.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_line( ... ),
				'show_in_rest'      => false,
				'default'           => self::default_heading(),
			)
		);

		register_setting(
			self::PAGE,
			self::MESSAGE,
			array(
				'type'              => 'string',
				'description'       => __( 'The body copy on the maintenance screen.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_body( ... ),
				'show_in_rest'      => false,
				'default'           => self::default_message(),
			)
		);

		register_setting(
			self::PAGE,
			self::CONTACT,
			array(
				'type'              => 'string',
				'description'       => __( 'An email address the maintenance screen may publish.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_address( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Maintenance', 'dp-core' ),
			'__return_null',
			self::PAGE
		);

		add_settings_field(
			self::ENABLED,
			__( 'Maintenance mode', 'dp-core' ),
			$this->switch_field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::ENABLED,
				'help'      => __( 'While this is on, every public page, feed and anonymous REST request answers 503 with the screen below. You stay on the real site, and wp-admin stays reachable, for as long as you are signed in and can edit posts.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::HEADING,
			__( 'Maintenance heading', 'dp-core' ),
			$this->line_field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::HEADING,
				'help'      => __( 'The one heading on the screen. Left empty it falls back to the shipped placeholder, because a page with no heading fails accessibility.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::MESSAGE,
			__( 'Maintenance message', 'dp-core' ),
			$this->body_field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::MESSAGE,
				'help'      => __( 'The copy under the heading. Blank lines start new paragraphs. Leave it empty to show the heading on its own.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::CONTACT,
			__( 'Maintenance contact', 'dp-core' ),
			$this->address_field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::CONTACT,
				'help'      => __( 'Published on the screen as the only link on it. Leave empty to publish no address and no link — the contact form is behind the screen too, so this is the only way left to reach you.', 'dp-core' ),
			)
		);
	}

	/**
	 * Reduce a submitted checkbox to on or off.
	 *
	 * `null` arrives when the box was unticked, because `options.php` hands every
	 * registered option in the group to its sanitizer whether the form posted it
	 * or not. Reading that as "off" is what makes the box switchable at all.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string `'1'` for on, `''` for off.
	 */
	public function sanitize_switch( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return in_array( (string) $value, array( '1', 'on', 'true', 'yes' ), true ) ? self::ON : '';
	}

	/**
	 * Reduce a submitted heading to one line of plain text.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string
	 */
	public function sanitize_line( mixed $value ): string {
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Reduce a submitted message to plain text, newlines kept.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string
	 */
	public function sanitize_body( mixed $value ): string {
		return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Reduce a submitted value to an email address or to nothing.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The address, or '' for "publish none".
	 */
	public function sanitize_address( mixed $value ): string {
		$address = sanitize_email( is_scalar( $value ) ? (string) $value : '' );

		return is_email( $address ) === $address ? $address : '';
	}

	/**
	 * Draw the switch.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function switch_field( array $arguments ): void {
		$option = self::argument( $arguments, 'label_for' );

		if ( '' === $option ) {
			return;
		}

		printf(
			'<input name="%1$s" type="checkbox" id="%1$s" value="1"%2$s>',
			esc_attr( $option ),
			self::is_on() ? ' checked' : ''
		);

		printf(
			'<label for="%1$s">%2$s</label>',
			esc_attr( $option ),
			esc_html__( 'Show a maintenance screen to the public', 'dp-core' )
		);

		self::help( $arguments );
	}

	/**
	 * Draw a one-line text field.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function line_field( array $arguments ): void {
		$option = self::argument( $arguments, 'label_for' );

		if ( '' === $option ) {
			return;
		}

		printf(
			'<input name="%1$s" type="text" id="%1$s" value="%2$s" class="regular-text">',
			esc_attr( $option ),
			esc_attr( self::option( $option ) )
		);

		self::help( $arguments );
	}

	/**
	 * Draw the message textarea.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function body_field( array $arguments ): void {
		$option = self::argument( $arguments, 'label_for' );

		if ( '' === $option ) {
			return;
		}

		printf(
			'<textarea name="%1$s" id="%1$s" rows="4" class="large-text">%2$s</textarea>',
			esc_attr( $option ),
			esc_textarea( self::option( $option ) )
		);

		self::help( $arguments );
	}

	/**
	 * Draw the address field.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function address_field( array $arguments ): void {
		$option = self::argument( $arguments, 'label_for' );

		if ( '' === $option ) {
			return;
		}

		printf(
			'<input name="%1$s" type="email" id="%1$s" value="%2$s" class="regular-text ltr" autocomplete="off">',
			esc_attr( $option ),
			esc_attr( self::contact() )
		);

		self::help( $arguments );
	}

	/**
	 * Whether the maintenance screen is switched on.
	 *
	 * @return bool
	 */
	public static function is_on(): bool {
		return self::ON === self::option( self::ENABLED );
	}

	/**
	 * The heading, never empty.
	 *
	 * The one reader with a fallback. A document needs exactly one `<h1>`, so an
	 * empty heading is a blank to fill rather than an instruction to omit — which
	 * is the case ADR-0018 rule 3 expressly allows ("a derivation fills a blank").
	 * The fallback is placeholder copy, not a claim about anything.
	 *
	 * @return string
	 */
	public static function heading(): string {
		$stored = self::option( self::HEADING );

		return '' !== $stored ? $stored : self::default_heading();
	}

	/**
	 * The body copy, or '' when David wants none.
	 *
	 * No fallback, deliberately: a heading on its own is a screen somebody might
	 * want, and reverting an emptied field to the shipped sentence would be this
	 * code overruling him.
	 *
	 * @return string
	 */
	public static function message(): string {
		return self::option( self::MESSAGE );
	}

	/**
	 * The address the screen may publish, or '' for none.
	 *
	 * Validated on the way out as well as in, because an option can arrive by
	 * routes the sanitizer never saw — WP-CLI, a migration.
	 *
	 * @return string
	 */
	public static function contact(): string {
		$stored = self::option( self::CONTACT );

		return is_email( $stored ) === $stored ? $stored : '';
	}

	/**
	 * The shipped heading. Placeholder copy.
	 *
	 * @return string
	 */
	public static function default_heading(): string {
		return __( 'This site is being set up.', 'dp-core' );
	}

	/**
	 * The shipped message. Placeholder copy.
	 *
	 * @return string
	 */
	public static function default_message(): string {
		return __( 'It will be back shortly. Thank you for your patience.', 'dp-core' );
	}

	/**
	 * Print a field's description, if it was given one.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given.
	 * @return void
	 */
	private static function help( array $arguments ): void {
		$help = self::argument( $arguments, 'help' );

		if ( '' !== $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	/**
	 * One string out of a field's arguments.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given.
	 * @param string               $key       Which argument.
	 * @return string
	 */
	private static function argument( array $arguments, string $key ): string {
		$value = $arguments[ $key ] ?? null;

		return is_string( $value ) ? $value : '';
	}

	/**
	 * One option, as a string or not at all.
	 *
	 * @param string $option The option name.
	 * @return string
	 */
	private static function option( string $option ): string {
		$stored = get_option( $option );

		return is_string( $stored ) ? $stored : '';
	}
}
