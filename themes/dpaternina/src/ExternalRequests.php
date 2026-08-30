<?php
/**
 * Keeps the front end on one origin.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

/**
 * Removes the WordPress output that would fetch from somewhere else.
 *
 * CLAUDE.md §1.4: "nothing enqueues from a CDN". The fonts are self-hosted, but
 * WordPress still ships three things that reach off-origin on their own:
 *
 * 1. `wp-editor-font`, a registered handle pointing at Google Fonts. Core stopped
 *    using it in 5.7 and still registers it, so any plugin that lists it as a
 *    dependency would pull Google in behind our backs.
 * 2. The emoji detection script and its `s.w.org` SVG CDN.
 * 3. `dns-prefetch` hints derived from whatever is enqueued, which would advertise
 *    a third-party host even before anything asked for it.
 *
 * **The analytics preconnect is dropped, and that is the decision.** The one
 * deliberate external origin in this project is the Rybbit analytics endpoint,
 * and Rybbit is David's plugin rather than our code (`docs/plan.md` Phase 8).
 * If that plugin adds a `preconnect` or `dns-prefetch` for its own host, rule 3
 * above removes it, because rule 3 refuses every host but this one. The plan
 * asked that this "should be a decision, not a discovery", so:
 *
 * - **The hint is dropped on purpose.** A resource hint is an advertisement:
 *   it names a third party in the HTML of every page, to every reader, before
 *   anything has asked for it. Refusing it by default is the same posture as
 *   the rest of this class and the same posture CLAUDE.md §1.4 takes.
 * - **Nothing breaks.** A hint is a hint. The analytics script still loads from
 *   wherever the plugin enqueues it; it pays one connection setup it would
 *   otherwise have skipped, on a request that is not on the critical path.
 * - **It costs the CSP nothing.** The headers are David's security plugin's
 *   (CLAUDE.md §1.4). A dropped hint neither needs nor grants an exception.
 *
 * The escape hatch is `dp_resource_hint_hosts`, which takes the allowlist —
 * the site's own host, and nothing else, by default. Adding a host there is how
 * the decision is reversed, without editing this file:
 *
 *     add_filter(
 *         'dp_resource_hint_hosts',
 *         static fn ( array $hosts ): array => array( ...$hosts, 'rybbit.example' )
 *     );
 */
final class ExternalRequests {

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', $this->deregister_core_google_fonts( ... ), 1 );
		add_action( 'admin_enqueue_scripts', $this->deregister_core_google_fonts( ... ), 1 );
		add_action( 'init', $this->disable_emoji( ... ) );
		add_filter( 'wp_resource_hints', $this->filter_resource_hints( ... ) );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Drop core's Google Fonts handle so nothing can depend on it.
	 *
	 * @return void
	 */
	public function deregister_core_google_fonts(): void {
		wp_deregister_style( 'wp-editor-font' );
	}

	/**
	 * Remove the emoji detection script, its styles, and its CDN.
	 *
	 * @return void
	 */
	public function disable_emoji(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', $this->remove_emoji_tinymce_plugin( ... ) );
	}

	/**
	 * Remove the emoji plugin from TinyMCE's plugin list.
	 *
	 * @param mixed $plugins Registered TinyMCE plugins.
	 * @return list<string>
	 */
	public function remove_emoji_tinymce_plugin( mixed $plugins ): array {
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		$kept = array();

		foreach ( $plugins as $plugin ) {
			if ( is_string( $plugin ) && 'wpemoji' !== $plugin ) {
				$kept[] = $plugin;
			}
		}

		return $kept;
	}

	/**
	 * The hosts a resource hint is allowed to name.
	 *
	 * The site's own, and whatever `dp_resource_hint_hosts` adds. Anything the
	 * filter hands back that is not a non-empty string is discarded rather than
	 * trusted, so a plugin returning the wrong shape narrows the allowlist
	 * instead of widening it.
	 *
	 * @return list<string>
	 */
	public function allowed_hosts(): array {
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$hosts = is_string( $host ) && '' !== $host ? array( $host ) : array();

		/**
		 * Filters the hosts a `wp_resource_hints` entry may point at.
		 *
		 * Empty but for the site's own host by default. See the class docblock
		 * for why the analytics endpoint is not on it.
		 *
		 * @param list<string> $hosts Allowed hosts, without scheme or path.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is the project's public filter prefix (docs/plan.md; see `dp_allowed_block_types`). WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$filtered = apply_filters( 'dp_resource_hint_hosts', $hosts );

		if ( ! is_array( $filtered ) ) {
			return $hosts;
		}

		$allowed = array();

		foreach ( $filtered as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$allowed[] = $candidate;
			}
		}

		return $allowed;
	}

	/**
	 * Drop every resource hint that points at a host the site does not allow.
	 *
	 * Allowing the site's own host through keeps a same-origin `preconnect` or
	 * `prefetch` working; everything else is refused unless `dp_resource_hint_hosts`
	 * names it, so a plugin cannot quietly re-introduce a third-party origin.
	 *
	 * Every relation type — `dns-prefetch`, `preconnect`, `prefetch`, `prerender`
	 * — is filtered the same way, so the callback accepts one argument and never
	 * reads which relation it was given.
	 *
	 * @param mixed $urls Resource hints for one relation type.
	 * @return list<array<array-key, mixed>|string>
	 */
	public function filter_resource_hints( mixed $urls ): array {
		if ( ! is_array( $urls ) ) {
			return array();
		}

		$hosts = $this->allowed_hosts();
		$kept  = array();

		foreach ( $urls as $url ) {
			if ( is_string( $url ) ) {
				$href = $url;
			} elseif ( is_array( $url ) ) {
				$href = $url['href'] ?? '';
			} else {
				continue;
			}

			if ( ! is_string( $href ) ) {
				continue;
			}

			$hint_host = wp_parse_url( $href, PHP_URL_HOST );

			if ( ! is_string( $hint_host ) || in_array( $hint_host, $hosts, true ) ) {
				$kept[] = $url;
			}
		}

		return $kept;
	}
}
