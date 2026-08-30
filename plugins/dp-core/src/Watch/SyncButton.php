<?php
/**
 * "Sync now", beside the credentials it needs.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * A button on Settings → General that runs the Watch sync, and says what it did.
 *
 * **Why here and not on the Videos list table.** The list table was the other
 * candidate and it is a reasonable one — it is where the result appears. It
 * loses on the thing the button is most likely to do wrong: every way this
 * fails is a credential, and the credentials are on this screen. A button that
 * reports "Twitch did not answer" three inches under the client secret it did
 * not like is a button somebody can act on; the same sentence over a list of
 * posts is a sentence that sends you to another screen. The button also carries
 * the last scheduled run's outcome, which belongs with the settings that
 * configure the schedule rather than with the content it produced.
 *
 * **The form is not inside the settings form.** `options-general.php` wraps
 * every registered field in one `<form action="options.php">`, and a form inside
 * a form is not markup a browser will honour. The `<form>` is printed on
 * `admin_notices`, which core fires above `.wrap` and therefore outside it, and
 * the button in the settings row reaches it with HTML5's `form` attribute. No
 * JavaScript, no inline handler, nothing for a strict content policy to object
 * to (CLAUDE.md §1.4) — and the button still sits where the credentials are.
 *
 * The write path is `Admin\SeriesOrderScreen`'s exactly: an `admin_post_` action
 * with no `_nopriv_` twin, `check_admin_referer()` then a capability check, and
 * a redirect back to the screen so a reload cannot run a second sync.
 *
 * **It is honest about doing nothing.** With no platform fully configured there
 * is no button at all, and a sentence saying which halves are missing. A run
 * that reached a platform and got an empty list says that rather than reporting
 * a success — see `SyncReport::summary()`.
 */
final class SyncButton {

	/**
	 * The admin-post action the button submits to, and the stem of its nonce.
	 *
	 * @var string
	 */
	public const ACTION = 'dp_core_watch_sync_now';

	/**
	 * What a user needs to run a sync.
	 *
	 * The same capability that gates the credentials it uses: this is a Settings
	 * → General field, and a settings page is `manage_options`.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The id the button's `form` attribute points at.
	 *
	 * @var string
	 */
	public const FORM_ID = 'dp-watch-sync-now';

	/**
	 * The query variable saying a notice is waiting.
	 *
	 * @var string
	 */
	public const NOTICE_ARG = 'dp-watch-synced';

	/**
	 * Prefix on the transient carrying one user's outcome across the redirect.
	 *
	 * The outcome is a sentence, sometimes a failure reason, so it travels in a
	 * short-lived per-user transient rather than in the URL.
	 *
	 * @var string
	 */
	public const NOTICE_TRANSIENT = 'dp_watch_sync_notice_';

	/**
	 * The screen the button and its notice belong to.
	 *
	 * @var string
	 */
	private const SCREEN = 'options-general';

	/**
	 * How long an outcome waits to be read.
	 *
	 * @var int
	 */
	private const NOTICE_TTL = 2 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param VideoSync $sync What the button runs.
	 */
	public function __construct( private readonly VideoSync $sync ) {}

	/**
	 * Attach the hooks.
	 *
	 * All three are admin-only hooks, so there is no `is_admin()` guard — adding
	 * one would only make the wiring harder to exercise from a test.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, $this->handle( ... ) );
		add_action( 'admin_init', $this->add_field( ... ), 11 );
		add_action( 'admin_notices', $this->notices( ... ) );
	}

	/**
	 * Put the button in the Watch section, under the credentials.
	 *
	 * Priority 11 so `Settings::add()` has created the section first.
	 *
	 * @return void
	 */
	public function add_field(): void {
		add_settings_field(
			self::FORM_ID,
			__( 'Import videos', 'dp-core' ),
			$this->field( ... ),
			Settings::PAGE,
			Settings::SECTION
		);
	}

	/**
	 * Draw the button, or say why there is not one.
	 *
	 * @return void
	 */
	public function field(): void {
		foreach ( $this->half_configured() as $line ) {
			printf( '<p class="description">%s</p>', esc_html( $line ) );
		}

		$twitch  = Settings::has_twitch();
		$youtube = Settings::has_youtube();

		if ( ! $twitch && ! $youtube ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Nothing can be imported yet. Fill in the Twitch login with both Twitch credentials, or the YouTube channel with an API key, and a button appears here.', 'dp-core' )
			);

			return;
		}

		printf(
			'<button type="submit" class="button" form="%1$s">%2$s</button>',
			esc_attr( self::FORM_ID ),
			esc_html__( 'Sync now', 'dp-core' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: one or two platform names, e.g. "Twitch and YouTube". */
					__( 'Imports videos from %s, hourly and on its own. This runs it now. Anything you have edited by hand is left alone for good.', 'dp-core' ),
					$this->platform_names( $twitch, $youtube )
				)
			)
		);

		$last = $this->last_run_line();

		if ( '' !== $last ) {
			printf( '<p class="description">%s</p>', esc_html( $last ) );
		}
	}

	/**
	 * Print the notice, and the form the button submits.
	 *
	 * @return void
	 */
	public function notices(): void {
		if ( ! $this->on_screen() ) {
			return;
		}

		$this->render_notice();

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		printf(
			'<form id="%1$s" method="post" action="%2$s">',
			esc_attr( self::FORM_ID ),
			esc_url( admin_url( 'admin-post.php' ) )
		);

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION ) );

		wp_nonce_field( self::ACTION );

		echo '</form>';
	}

	/**
	 * Run a sync, then send the browser back with the outcome.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to sync the Watch page.', 'dp-core' ),
				'',
				array( 'response' => 403 )
			);
		}

		$report = $this->sync->run();

		set_transient(
			self::transient(),
			array(
				'ok'      => $report->ok(),
				'message' => $report->summary(),
			),
			self::NOTICE_TTL
		);

		wp_safe_redirect(
			add_query_arg( self::NOTICE_ARG, '1', admin_url( self::SCREEN . '.php' ) )
		);

		exit;
	}

	/**
	 * The outcome of the run that just happened, once.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a flag in the URL deciding whether to print a sentence. It writes nothing, and the sentence itself comes from a transient this user's own request wrote.
		if ( ! isset( $_GET[ self::NOTICE_ARG ] ) ) {
			return;
		}

		$stored = get_transient( self::transient() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		delete_transient( self::transient() );

		$message = isset( $stored['message'] ) && is_string( $stored['message'] ) ? $stored['message'] : '';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( ! empty( $stored['ok'] ) ? 'success' : 'warning' ),
			esc_html( $message )
		);
	}

	/**
	 * Sentences about a platform that is half filled in.
	 *
	 * Half a configuration is the state most likely to look configured and do
	 * nothing, so it is named rather than folded into "not configured".
	 *
	 * @return list<string>
	 */
	private function half_configured(): array {
		$lines = array();

		if ( '' !== Settings::login() && ! Settings::has_credentials() ) {
			$lines[] = __( 'Twitch is not ready: it needs a client ID and a client secret as well as the login.', 'dp-core' );
		}

		if ( '' === Settings::login() && Settings::has_credentials() ) {
			$lines[] = __( 'Twitch is not ready: it needs the login as well as the credentials.', 'dp-core' );
		}

		if ( '' !== Settings::youtube_channel() && '' === Settings::youtube_key() ) {
			$lines[] = __( 'YouTube is not ready: it needs an API key as well as the channel.', 'dp-core' );
		}

		if ( '' === Settings::youtube_channel() && '' !== Settings::youtube_key() ) {
			$lines[] = __( 'YouTube is not ready: it needs a channel as well as the API key.', 'dp-core' );
		}

		return $lines;
	}

	/**
	 * The platforms a sync would reach, named.
	 *
	 * @param bool $twitch  Whether Twitch is configured.
	 * @param bool $youtube Whether YouTube is configured.
	 * @return string
	 */
	private function platform_names( bool $twitch, bool $youtube ): string {
		if ( $twitch && $youtube ) {
			return __( 'Twitch and YouTube', 'dp-core' );
		}

		return $twitch ? __( 'Twitch', 'dp-core' ) : __( 'YouTube', 'dp-core' );
	}

	/**
	 * What the last run did, and when.
	 *
	 * This is the only place a scheduled run is visible, which is the whole
	 * reason it is printed: a sync that quietly stopped running looks exactly
	 * like a channel that quietly stopped publishing.
	 *
	 * @return string Empty when nothing has run yet.
	 */
	private function last_run_line(): string {
		$stored = get_option( VideoSync::LAST_RUN );

		if ( ! is_array( $stored ) ) {
			return '';
		}

		$time    = isset( $stored['time'] ) && is_numeric( $stored['time'] ) ? (int) $stored['time'] : 0;
		$message = isset( $stored['message'] ) && is_string( $stored['message'] ) ? $stored['message'] : '';

		if ( $time <= 0 || '' === $message ) {
			return '';
		}

		return sprintf(
			/* translators: 1: a length of time, e.g. "5 mins", 2: a sentence about what the run did. */
			__( 'Last run %1$s ago. %2$s', 'dp-core' ),
			human_time_diff( $time ),
			$message
		);
	}

	/**
	 * Whether the screen being drawn is the one this belongs to.
	 *
	 * @return bool
	 */
	private function on_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return null !== $screen && self::SCREEN === $screen->id;
	}

	/**
	 * The transient this user's outcome waits in.
	 *
	 * Per user, because two people pressing the button would otherwise read each
	 * other's answer.
	 *
	 * @return string
	 */
	private static function transient(): string {
		return self::NOTICE_TRANSIENT . get_current_user_id();
	}
}
