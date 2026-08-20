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
 * The one deliberate external origin in this project is the Rybbit analytics
 * endpoint (Phase 8). It is not loaded here and is not exempted here.
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
	 * Drop every resource hint that points somewhere other than this site.
	 *
	 * Allowing the site's own host through keeps a same-origin `preconnect` or
	 * `prefetch` working; everything else is refused by default, so a plugin
	 * cannot quietly re-introduce a third-party origin.
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

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$kept = array();

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

			if ( ! is_string( $hint_host ) || $hint_host === $host ) {
				$kept[] = $url;
			}
		}

		return $kept;
	}
}
