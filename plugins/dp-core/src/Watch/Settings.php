<?php
/**
 * The Twitch login and Helix credentials, as settings David edits.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * A "Watch page" section on Settings → General, holding three values.
 *
 * The same pattern as `DP\Core\Contact\Settings`, for the same reason: a value
 * that only a filter could supply is dead UI, and CLAUDE.md rule 2 forbids a
 * behaviour David cannot reach from the admin. Nothing here is a constant and
 * nothing is hardcoded; the fixture's `patsypatz` login exists only in the
 * seeder's world, never in this class.
 *
 * The five are five because they degrade separately:
 *
 * - **Login** (`dp_watch_twitch_login`) names the channel. Without it there is
 *   no live check, no "watch the stream" URL and no Twitch VOD sync — the Watch
 *   page still renders whatever archive it has.
 * - **Client ID + secret** (`dp_watch_twitch_client_id` / `_client_secret`)
 *   are a Twitch application's Helix credentials. Without them the live-now
 *   panel never shows live, Twitch VOD thumbnails stay on their fallback art,
 *   and no VOD is imported. YouTube thumbnails need no credentials and keep
 *   working.
 * - **YouTube channel + API key** (`dp_watch_youtube_channel` /
 *   `dp_watch_youtube_key`) are what `VideoSync` needs to import uploads.
 *   Without them no YouTube video is imported; anything already imported goes on
 *   rendering, because a missing key is not a statement that the videos are gone.
 *
 * Neither pair is required for the site to work. A site with none of the five
 * configured renders every `dp_video` it already has and syncs nothing, which is
 * exactly what a seeded development site is.
 *
 * **The secret lives in `wp_options`, and that is a stated trade.** The résumé
 * renderer keeps its Cloudflare token in `wp-config.php` because that key can
 * spend money; this one can read public stream metadata on a single-author
 * site, and an option David can rotate from the admin beats a constant only a
 * deploy can change. The field's own description says where it is stored so
 * the decision is visible where it is made.
 */
final class Settings {

	/**
	 * The option naming the Twitch channel the Watch page is about.
	 *
	 * @var string
	 */
	public const LOGIN = 'dp_watch_twitch_login';

	/**
	 * The option holding the Twitch application's client ID.
	 *
	 * @var string
	 */
	public const CLIENT_ID = 'dp_watch_twitch_client_id';

	/**
	 * The option holding the Twitch application's client secret.
	 *
	 * @var string
	 */
	public const CLIENT_SECRET = 'dp_watch_twitch_client_secret';

	/**
	 * The option naming the YouTube channel whose uploads are imported.
	 *
	 * @var string
	 */
	public const YOUTUBE_CHANNEL = 'dp_watch_youtube_channel';

	/**
	 * The option holding the YouTube Data API key.
	 *
	 * @var string
	 */
	public const YOUTUBE_KEY = 'dp_watch_youtube_key';

	/**
	 * The settings page the section lives on.
	 *
	 * Public because `SyncButton` adds a field of its own to this section, and a
	 * button that syncs belongs beside the credentials it needs.
	 *
	 * @var string
	 */
	public const PAGE = 'general';

	/**
	 * The section's id.
	 *
	 * @var string
	 */
	public const SECTION = 'dp-watch';

	/**
	 * A Twitch login: 25 characters of lowercase alphanumerics and underscores.
	 *
	 * @var string
	 */
	private const LOGIN_PATTERN = '/\A[a-z0-9_]{1,25}\z/';

	/**
	 * A YouTube channel: either a `UC…` channel id or an `@handle`.
	 *
	 * Both are accepted because both are things David can read off his own
	 * channel page, and `channels.list` takes either — `id` for the first,
	 * `forHandle` for the second. A channel id is exactly `UC` plus 22
	 * URL-safe characters; a handle is 3 to 30 of a narrower set after the `@`.
	 *
	 * @var string
	 */
	private const CHANNEL_PATTERN = '/\A(?:UC[A-Za-z0-9_-]{22}|@[A-Za-z0-9_.-]{3,30})\z/';

	/**
	 * A Google API key: URL-safe characters, unlike Twitch's plain alphanumerics.
	 *
	 * @var string
	 */
	private const API_KEY_PATTERN = '/\A[A-Za-z0-9_-]{1,128}\z/';

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
			self::LOGIN,
			array(
				'type'              => 'string',
				'description'       => __( 'The Twitch channel the Watch page checks for a live stream.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_login( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::CLIENT_ID,
			array(
				'type'              => 'string',
				'description'       => __( 'The Twitch application client ID the Helix API calls identify as.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_credential( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::CLIENT_SECRET,
			array(
				'type'              => 'string',
				'description'       => __( 'The Twitch application client secret the Helix API calls authenticate with.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_credential( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::YOUTUBE_CHANNEL,
			array(
				'type'              => 'string',
				'description'       => __( 'The YouTube channel whose uploads the Watch page imports.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_channel( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			self::YOUTUBE_KEY,
			array(
				'type'              => 'string',
				'description'       => __( 'The YouTube Data API v3 key the import calls authenticate with.', 'dp-core' ),
				'sanitize_callback' => $this->sanitize_api_key( ... ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Watch page', 'dp-core' ),
			'__return_null',
			self::PAGE
		);

		add_settings_field(
			self::LOGIN,
			__( 'Twitch login', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::LOGIN,
				'type'      => 'text',
				'help'      => __( 'The channel name, as it appears in twitch.tv/…. Leave empty and the Watch page never checks whether you are live.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::CLIENT_ID,
			__( 'Twitch client ID', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::CLIENT_ID,
				'type'      => 'text',
				'help'      => __( 'From a Twitch developer application. Used for the live check and for VOD thumbnails; without it both quietly stay off.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::CLIENT_SECRET,
			__( 'Twitch client secret', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::CLIENT_SECRET,
				'type'      => 'password',
				'help'      => __( 'Stored as a plain option in this site\'s database, which is an accepted trade on a single-author site — rotate it from the Twitch console if the database ever leaks.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::YOUTUBE_CHANNEL,
			__( 'YouTube channel', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::YOUTUBE_CHANNEL,
				'type'      => 'text',
				'help'      => __( 'Either the channel ID that starts "UC", or the @handle. Leave empty and no YouTube video is imported.', 'dp-core' ),
			)
		);

		add_settings_field(
			self::YOUTUBE_KEY,
			__( 'YouTube API key', 'dp-core' ),
			$this->field( ... ),
			self::PAGE,
			self::SECTION,
			array(
				'label_for' => self::YOUTUBE_KEY,
				'type'      => 'password',
				'help'      => __( 'A YouTube Data API v3 key, stored as a plain option like the Twitch secret. Restrict it to the YouTube Data API in the Google console — it only ever reads public listings.', 'dp-core' ),
			)
		);
	}

	/**
	 * Reduce a submitted value to a Twitch login or to nothing.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The login, lower case, or '' for "unset".
	 */
	public function sanitize_login( mixed $value ): string {
		$login = strtolower( trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) ) );

		return 1 === preg_match( self::LOGIN_PATTERN, $login ) ? $login : '';
	}

	/**
	 * Reduce a submitted credential to a single token or to nothing.
	 *
	 * Twitch issues both halves as one run of alphanumerics; anything with
	 * whitespace in it is a paste that went wrong, and storing it would only
	 * make every Helix call fail with credentials that look configured.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The credential, or '' for "unset".
	 */
	public function sanitize_credential( mixed $value ): string {
		$credential = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );

		return 1 === preg_match( '/\A[A-Za-z0-9]{1,128}\z/', $credential ) ? $credential : '';
	}

	/**
	 * Reduce a submitted value to a YouTube channel reference or to nothing.
	 *
	 * A pasted channel URL is rejected rather than picked apart. The two forms
	 * the API accepts are both short enough to read off a channel page, and a
	 * parser for every URL YouTube has ever minted would be a second thing to
	 * get wrong on a field that is typed once.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The channel id or handle, or '' for "unset".
	 */
	public function sanitize_channel( mixed $value ): string {
		$channel = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );

		return 1 === preg_match( self::CHANNEL_PATTERN, $channel ) ? $channel : '';
	}

	/**
	 * Reduce a submitted value to a Google API key or to nothing.
	 *
	 * @param mixed $value Whatever options.php was handed.
	 * @return string The key, or '' for "unset".
	 */
	public function sanitize_api_key( mixed $value ): string {
		$key = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );

		return 1 === preg_match( self::API_KEY_PATTERN, $key ) ? $key : '';
	}

	/**
	 * Draw one field.
	 *
	 * @param array<string, mixed> $arguments What `add_settings_field()` was given; `label_for` names the option.
	 * @return void
	 */
	public function field( array $arguments ): void {
		$option = isset( $arguments['label_for'] ) && is_string( $arguments['label_for'] ) ? $arguments['label_for'] : '';
		$type   = isset( $arguments['type'] ) && is_string( $arguments['type'] ) ? $arguments['type'] : 'text';
		$help   = isset( $arguments['help'] ) && is_string( $arguments['help'] ) ? $arguments['help'] : '';

		if ( '' === $option ) {
			return;
		}

		printf(
			'<input name="%1$s" type="%2$s" id="%1$s" value="%3$s" class="regular-text ltr" autocomplete="off">',
			esc_attr( $option ),
			esc_attr( 'password' === $type ? 'password' : 'text' ),
			esc_attr( self::option( $option ) )
		);

		if ( '' !== $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	/**
	 * The configured login, or '' when none is set.
	 *
	 * @return string
	 */
	public static function login(): string {
		$stored = self::option( self::LOGIN );

		return 1 === preg_match( self::LOGIN_PATTERN, $stored ) ? $stored : '';
	}

	/**
	 * The client ID, or '' when none is set.
	 *
	 * @return string
	 */
	public static function client_id(): string {
		return self::option( self::CLIENT_ID );
	}

	/**
	 * The client secret, or '' when none is set.
	 *
	 * @return string
	 */
	public static function client_secret(): string {
		return self::option( self::CLIENT_SECRET );
	}

	/**
	 * Whether both Helix credentials are present.
	 *
	 * @return bool
	 */
	public static function has_credentials(): bool {
		return '' !== self::client_id() && '' !== self::client_secret();
	}

	/**
	 * The YouTube channel id or handle, or '' when none is set.
	 *
	 * @return string
	 */
	public static function youtube_channel(): string {
		$stored = self::option( self::YOUTUBE_CHANNEL );

		return 1 === preg_match( self::CHANNEL_PATTERN, $stored ) ? $stored : '';
	}

	/**
	 * The YouTube Data API key, or '' when none is set.
	 *
	 * @return string
	 */
	public static function youtube_key(): string {
		$stored = self::option( self::YOUTUBE_KEY );

		return 1 === preg_match( self::API_KEY_PATTERN, $stored ) ? $stored : '';
	}

	/**
	 * Whether Twitch is configured well enough to import VODs.
	 *
	 * The login as well as the credentials: the archive endpoint is keyed by
	 * user id, and the only way to a user id is the login David typed.
	 *
	 * @return bool
	 */
	public static function has_twitch(): bool {
		return '' !== self::login() && self::has_credentials();
	}

	/**
	 * Whether YouTube is configured well enough to import uploads.
	 *
	 * @return bool
	 */
	public static function has_youtube(): bool {
		return '' !== self::youtube_channel() && '' !== self::youtube_key();
	}

	/**
	 * One option, as a string or not at all.
	 *
	 * The readers do not trust the store: an option can arrive by routes the
	 * sanitizer never saw — WP-CLI, a migration — so anything that is not a
	 * string reads as unset.
	 *
	 * @param string $option The option name.
	 * @return string
	 */
	private static function option( string $option ): string {
		$stored = get_option( $option );

		return is_string( $stored ) ? $stored : '';
	}
}
