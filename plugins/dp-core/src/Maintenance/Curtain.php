<?php
/**
 * The two hooks that put the screen in front of the public.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

use WP_Error;

/**
 * Two hooks, chosen for what they reach and for what they cannot reach.
 *
 * **`template_redirect`, at priority 0, is the front end.** It is the one hook
 * every request through `wp-blog-header.php` passes, and it fires in
 * `template-loader.php` *before* the branches that dispatch robots.txt, the
 * favicon and — the case that matters for a public site — `do_feed()`. So a feed
 * is curtained by the same line that curtains a page, which is what the brief
 * asks for and is not true of `template_include`, the obvious alternative:
 * `do_feed()` returns before `template_include` is ever applied, so a screen
 * built on that filter would leave every post readable at `/feed/`. Priority 0
 * puts it ahead of `Resume\ResumePdf::maybe_serve()` at 1, and ahead of core's
 * sitemap renderer at 10, so neither serves anything while the site is dark.
 *
 * What that hook cannot reach is the point of it: `wp-login.php`, `wp-admin` and
 * `admin-ajax.php` do not load the template loader at all, so there is no
 * allowlist to write and no way for a mistake in this class to lock David out of
 * the switch. Nor do WP-CLI or `wp-cron.php`. `Gate` names those two anyway,
 * because a 503 to a scheduled job is a silent failure rather than a page.
 *
 * **`rest_authentication_errors` is the REST API**, and it is the conventional
 * hook for this: it runs once per served request, inside
 * `WP_REST_Server::check_authentication()`, before any route is matched, so one
 * answer covers every endpoint including ones other plugins add. It runs late
 * here (priority 1000) so that every authenticator has spoken first — a request
 * that authenticated as an editor is a request `current_user_can()` will let
 * through, and a request core has already refused keeps core's refusal rather
 * than being relabelled.
 *
 * **What it does not cover, deliberately or otherwise.** It does not run for
 * internal dispatches through `rest_do_request()`, because those call
 * `WP_REST_Server::dispatch()` directly — which is why a server-rendered block
 * and the editor's own preview keep working. It does not run for
 * `admin-ajax.php`, which is not the REST API. And it is not a general lock on
 * everything PHP: `xmlrpc.php` and `wp-comments-post.php` load WordPress without
 * either of these hooks, so they answer as usual. Neither publishes content that
 * the front end is hiding — XML-RPC is off on most installs and a comment
 * endpoint takes rather than gives — but "the REST API is shut" is the claim
 * being made here, not "no PHP entry point answers".
 */
final class Curtain {

	/**
	 * The status the screen answers with.
	 *
	 * 503 rather than 200: this is temporary, and a 200 invites a crawler to
	 * index the holding page as the site.
	 *
	 * @var int
	 */
	public const STATUS = 503;

	/**
	 * What `Retry-After` says, in seconds.
	 *
	 * An hour, and a constant rather than a fifth setting: it is a hint to
	 * crawlers and proxies about when to come back, not copy anybody reads, and
	 * a field for it would be a field with no visible effect.
	 *
	 * @var int
	 */
	public const RETRY_AFTER = HOUR_IN_SECONDS;

	/**
	 * The error code an anonymous REST request is refused with.
	 *
	 * @var string
	 */
	public const REST_ERROR = 'dp_core_maintenance';

	/**
	 * Constructor.
	 *
	 * @param Gate   $gate   The decision.
	 * @param Screen $screen The document.
	 */
	public function __construct(
		private readonly Gate $gate,
		private readonly Screen $screen
	) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', $this->intercept( ... ), 0 );
		add_filter( 'rest_authentication_errors', $this->refuse_rest( ... ), 1000 );
	}

	/**
	 * Answer a front-end request with the screen, if it is one the curtain covers.
	 *
	 * @return void
	 */
	public function intercept(): void {
		if ( ! $this->gate->closes() ) {
			return;
		}

		$this->serve();
	}

	/**
	 * Send the screen and stop.
	 *
	 * @return void
	 */
	public function serve(): void {
		$this->send_headers();

		/*
		 * `Screen::render()` escapes every value it interpolates and returns a
		 * whole document; there is nothing left here to escape.
		 */
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->screen->render();

		exit;
	}

	/**
	 * Send the status and the headers that go with it.
	 *
	 * Separate from `serve()` so the status is assertable without the exit.
	 * `nocache_headers()` matters as much as the status does: a 503 cached by an
	 * edge would outlive the switch being turned off.
	 *
	 * `status_header()` is called before the guard rather than after it, because
	 * it guards its own `header()` call and firing the `status_header` filter is
	 * how anything else on the site learns what this response is — including a
	 * test, which cannot watch `header()` at all. `nocache_headers()` is inside
	 * the guard because it answers a late call with `_doing_it_wrong()`.
	 *
	 * @return void
	 */
	public function send_headers(): void {
		status_header( self::STATUS );

		if ( headers_sent() ) {
			return;
		}

		nocache_headers();

		foreach ( $this->headers() as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}

	/**
	 * The headers the screen carries beyond its status.
	 *
	 * A list rather than two `header()` calls because `header()` is a PHP
	 * internal, which neither Brain Monkey nor a CLI-run integration suite can
	 * observe; naming them here is what makes "and it says noindex" a test rather
	 * than a claim.
	 *
	 * `Retry-After` because a 503 without one is an outage of unknown length, and
	 * `X-Robots-Tag` because the status alone does not stop every crawler
	 * remembering the URL — and unlike the `<meta>` in the document, a header
	 * also covers the feeds and images served under the same curtain.
	 *
	 * @return array<string, string>
	 */
	public function headers(): array {
		return array(
			'Retry-After'  => (string) self::RETRY_AFTER,
			'X-Robots-Tag' => 'noindex',
		);
	}

	/**
	 * Refuse an anonymous REST request while the curtain is down.
	 *
	 * The front end being dark and the REST API being open is the same content
	 * published twice: `/wp-json/wp/v2/posts` returns everything the screen is
	 * hiding. Anyone who may see the site may use the API, so the block editor,
	 * which is the reason the API is open at all here, is untouched.
	 *
	 * @param mixed $result Whatever an earlier authenticator decided: null for
	 *                      "nobody has answered", true for "authenticated", or a
	 *                      WP_Error for "already refused".
	 * @return mixed
	 */
	public function refuse_rest( mixed $result ): mixed {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $this->gate->closes() ) {
			return $result;
		}

		return new WP_Error(
			self::REST_ERROR,
			__( 'This site is temporarily unavailable.', 'dp-core' ),
			array( 'status' => self::STATUS )
		);
	}
}
