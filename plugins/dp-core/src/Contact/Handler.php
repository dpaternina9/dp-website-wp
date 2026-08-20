<?php
/**
 * The contact form's write path: six gates, then the mailer.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * Accepts a POST to whatever page the form is on, and decides what happened.
 *
 * **It is not a route.** `CLAUDE.md` section 5.1 forbids registering one, and
 * the form does not need one: it posts to the page it is on, which is a URL
 * David already owns, and this listens on `template_redirect` for a POST
 * carrying the form's marker. There is no endpoint to guess, nothing to
 * rewrite, and moving or renaming the contact page moves the form with it.
 *
 * **The `fetch` upgrade is the same request.** Progressive enhancement usually
 * means a second code path — an `admin-ajax` action or a REST route — and two
 * code paths that must agree about six security gates is one code path too
 * many. So the script posts the same body to the same URL with one extra
 * header, and the only difference is that the answer comes back as JSON
 * carrying the rendered panel instead of as a whole page carrying it. One
 * decision, one renderer, two envelopes.
 *
 * **There is no redirect afterwards.** Post/Redirect/Get would need the outcome
 * to survive the redirect, which means either a query argument on David's page
 * — forgeable, so `?sent=1` would draw "It landed" for anyone who typed it —
 * or a cookie, on a site whose Privacy page is about not setting any. The page
 * is rendered in place instead: refreshing re-posts and is caught by the nonce
 * and the rate limiter, and the `fetch` path never gets there at all.
 *
 * The gate order is deliberate. Nonce and capability first, because they are
 * the cheapest and the least forgiving. The rate limiter is second to last, so
 * a mistyped address does not spend one of a real person's three attempts and
 * a flood of forged nonces cannot exhaust the counter for the address it is
 * spoofing.
 */
final class Handler {

	/**
	 * The nonce action.
	 *
	 * @var string
	 */
	public const ACTION = 'dp_contact_send';

	/**
	 * The header the enhanced path sets, and its value.
	 *
	 * @var string
	 */
	public const FETCH_HEADER = 'HTTP_X_DP_CONTACT';

	/**
	 * The outcome of this request, once decided.
	 *
	 * @var Outcome|null
	 */
	private ?Outcome $outcome = null;

	/**
	 * Constructor.
	 *
	 * @param Mailer      $mailer  Hands the message to WordPress.
	 * @param RateLimiter $limiter Counts attempts per sender.
	 * @param Log         $log     Where a refusal is recorded.
	 */
	public function __construct(
		private readonly Mailer $mailer = new Mailer(),
		private readonly RateLimiter $limiter = new RateLimiter(),
		private readonly Log $log = new Log()
	) {}

	/**
	 * Attach the hook.
	 *
	 * Priority 5 on `template_redirect`, before anything that might redirect,
	 * so a POST is decided while the request is still the one that was made.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', $this->maybe_handle( ... ), 5 );
	}

	/**
	 * Decide this request, if it is a submission at all.
	 *
	 * @return void
	 */
	public function maybe_handle(): void {
		if ( ! $this->is_submission() ) {
			return;
		}

		$this->outcome = $this->handle( Submission::from_post() );

		if ( $this->wants_json() ) {
			$this->respond_with_json( $this->outcome );
		}
	}

	/**
	 * What this request decided, for the block to render.
	 *
	 * @return Outcome
	 */
	public function outcome(): Outcome {
		return $this->outcome ?? Outcome::form();
	}

	/**
	 * Run every gate against one submission.
	 *
	 * @param Submission $submission The sanitised POST body.
	 * @return Outcome
	 */
	public function handle( Submission $submission ): Outcome {
		$rejection = $this->refuse( $submission );

		if ( null !== $rejection ) {
			$this->log->refused( $rejection );

			return Outcome::failed( $rejection, $submission->without_credentials() );
		}

		if ( ! $this->mailer->send( $submission, $this->source() ) ) {
			$this->log->refused(
				Rejection::MailFailed,
				array( 'transport' => $this->mailer->failure() )
			);

			return Outcome::failed( Rejection::MailFailed, $submission->without_credentials() );
		}

		return Outcome::sent();
	}

	/**
	 * The first gate that refuses this submission, or null when none does.
	 *
	 * @param Submission $submission The sanitised POST body.
	 * @return Rejection|null
	 */
	private function refuse( Submission $submission ): ?Rejection {
		if ( 1 !== wp_verify_nonce( $submission->nonce, self::ACTION ) ) {
			return Rejection::Nonce;
		}

		if ( ! current_user_can( Capability::SEND ) ) {
			return Rejection::Capability;
		}

		if ( '' !== trim( $submission->honeypot ) ) {
			return Rejection::Honeypot;
		}

		if ( ! ( new Stamp( time() ) )->is_plausible( $submission->stamp ) ) {
			return Rejection::TooFast;
		}

		if ( ! $submission->is_complete() ) {
			return Rejection::Incomplete;
		}

		if ( ! $this->limiter->allow( RateLimiter::fingerprint() ) ) {
			return Rejection::RateLimited;
		}

		return null;
	}

	/**
	 * Whether this request is a POST of this form.
	 *
	 * @return bool
	 */
	private function is_submission(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		return 'POST' === $method && Submission::is_present();
	}

	/**
	 * Whether the caller asked for JSON rather than a page.
	 *
	 * A header rather than a query argument, so the enhanced path cannot be
	 * reached by typing a URL and the plain path cannot accidentally take it.
	 *
	 * @return bool
	 */
	private function wants_json(): bool {
		return isset( $_SERVER[ self::FETCH_HEADER ] );
	}

	/**
	 * Answer the enhanced path and stop.
	 *
	 * @param Outcome $outcome What was decided.
	 * @return void
	 */
	private function respond_with_json( Outcome $outcome ): void {
		/**
		 * Filters the panel markup returned to the enhanced contact form.
		 *
		 * The block attaches the renderer here rather than being called
		 * directly, so this class never has to know how a panel is drawn.
		 *
		 * @since 0.1.0
		 *
		 * @param string  $html    The rendered panel. Empty by default.
		 * @param Outcome $outcome What the handler decided.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$html = (string) apply_filters( 'dp_contact_panel_html', '', $outcome );

		wp_send_json(
			array(
				'state' => $outcome->state->value,
				'html'  => $html,
			),
			State::Sent === $outcome->state ? 200 : 422
		);
	}

	/**
	 * The URL the form was on, for the message's footer.
	 *
	 * @return string
	 */
	private function source(): string {
		$id = get_queried_object_id();

		if ( $id <= 0 ) {
			return home_url( '/' );
		}

		$permalink = get_permalink( $id );

		return is_string( $permalink ) ? $permalink : home_url( '/' );
	}
}
