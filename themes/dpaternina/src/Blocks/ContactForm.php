<?php
/**
 * What the theme adds to `dp/contact-form`.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Contact\ContactForm as ContactFormBlock;
use DP\Theme\Theme;

/**
 * One script, loaded only on the page the form is actually on.
 *
 * The block belongs to `dp-core`, because it is a write path and a theme that
 * owned it would take the contact form away with it (CLAUDE.md section 2.1).
 * The front-end JavaScript is the other half of that same table, and it is the
 * theme's: switching themes should take the `fetch` upgrade with it and leave a
 * form that still posts.
 *
 * **Enqueued from the render, not from `wp_enqueue_scripts`.** The form is on
 * one page. Loading its controller on every page of the site to save a
 * conditional is the pitfall CLAUDE.md names, and `render_block` is the only
 * hook that knows whether the block is on the page being drawn. It fires while
 * the template renders, which is before `wp_footer`, so a footer script still
 * has somewhere to be printed. This is the pattern `DP\Theme\Blocks\Timeline`
 * already established.
 *
 * **Turnstile's script is asked for, never named.** When the site is configured
 * for a challenge, the panel carries a `.cf-turnstile` div that only Cloudflare's
 * `api.js` turns into a widget, and that script has to be on the page. Which
 * page, and whether at all, are two facts the plugin holds — so the theme asks
 * through `dp_contact_turnstile_script` and enqueues whatever URL comes back,
 * exactly as it answers `dp_destination_url` in the other direction. Nothing
 * answers on a site without the plugin, or with the plugin unconfigured, and
 * the answer is then '' and nothing is enqueued. This is the one off-origin
 * script this theme will ever load, and it loads on one page, only when David
 * has turned it on (ADR-0023).
 *
 * **The plugin's class name is behind a guard.** Naming it unguarded fatals the
 * theme on a site where `dp-core` is deactivated, which is what a fresh
 * `composer test:integration` leaves behind — it shows up as a 500 on the tests
 * site and an "Unexpected end of JSON input" from `requestUtils.rest`, which
 * names neither cause (ADR-0006 section 5).
 */
final class ContactForm {

	/**
	 * The script handle.
	 */
	public const SCRIPT_HANDLE = 'dpaternina-contact-form';

	/**
	 * The handle Cloudflare's widget script is enqueued under.
	 */
	public const TURNSTILE_HANDLE = 'dpaternina-turnstile';

	/**
	 * The script, relative to the theme root.
	 */
	private const SCRIPT_PATH = 'assets/js/contact-form.js';

	/**
	 * Constructor.
	 *
	 * @param Theme $theme The booted theme, for URLs and cache-busting versions.
	 */
	public function __construct( private readonly Theme $theme ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( class_exists( ContactFormBlock::class ) ) {
			add_filter( 'render_block_' . ContactFormBlock::BLOCK_NAME, $this->enqueue_controller( ... ) );
		}
	}

	/**
	 * Load the controller, because the form is on this page.
	 *
	 * A closed form renders nothing at all, so an empty render is a page with no
	 * form on it and gets no script.
	 *
	 * @param string $content The block's rendered HTML.
	 * @return string The HTML, untouched.
	 */
	public function enqueue_controller( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->theme->url( self::SCRIPT_PATH ),
			array(),
			$this->theme->asset_version( self::SCRIPT_PATH ),
			array(
				// Deferred rather than async: the controller upgrades markup it
				// has to be able to find. CLAUDE.md section 1.7: no render-blocking JS.
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		$this->enqueue_challenge();

		return $content;
	}

	/**
	 * Load Cloudflare's widget script, if this site has a challenge at all.
	 *
	 * The version is `null` on purpose: appending `?ver=6.9` to somebody else's
	 * CDN URL busts their cache for no reason and tells them which WordPress
	 * this is.
	 *
	 * @return void
	 */
	private function enqueue_challenge(): void {
		/**
		 * Filters the URL of the script that draws the contact challenge.
		 *
		 * The theme knows the block is on this page; the plugin knows whether a
		 * challenge is configured and what draws it. This is the seam between
		 * the two, and it names no class on either side. '' means "nothing to
		 * load", which is both the default and what an unconfigured or absent
		 * plugin leaves in place.
		 *
		 * @since 0.1.0
		 *
		 * @param string $url The script URL, or '' for no challenge.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$url = apply_filters( 'dp_contact_turnstile_script', '' );

		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		wp_enqueue_script(
			self::TURNSTILE_HANDLE,
			$url,
			array(),
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- deliberate: see the docblock. The file is Cloudflare's and versioned by Cloudflare; a `?ver=` of ours would bust their cache and advertise this site's WordPress version.
			null,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		add_filter( 'script_loader_tag', $this->mark_async( ... ), 10, 2 );
	}

	/**
	 * Add `async` to the challenge script's tag, beside the `defer` core wrote.
	 *
	 * Cloudflare documents `api.js` as `async defer`, and core's enqueue API
	 * takes one strategy rather than both — `'async'` and `'defer'` are
	 * alternatives to it. `defer` is the one that matters here, because the
	 * widget it draws has to find markup that is already parsed; `async` is the
	 * hint Cloudflare asks for, and adding it in the tag is the only place the
	 * two can be said at once.
	 *
	 * @param string $tag    The script tag core assembled.
	 * @param string $handle Which script it is for.
	 * @return string
	 */
	public function mark_async( string $tag, string $handle ): string {
		if ( self::TURNSTILE_HANDLE !== $handle || str_contains( $tag, ' async' ) ) {
			return $tag;
		}

		return str_replace( ' defer', ' async defer', $tag );
	}
}
