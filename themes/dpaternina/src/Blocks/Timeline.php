<?php
/**
 * What the theme adds to `dp/timeline`.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Blocks\Timeline as TimelineBlock;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Timeline\Chart;
use DP\Theme\Theme;
use WP_Post;

/**
 * The timeline's presentation layer: one script, loaded only where the chart is,
 * and the link that turns a `WorkCard` into a way into the chart.
 *
 * The block itself belongs to `dp-core`, because it renders content and a theme
 * that owned it would take the record away with it (CLAUDE.md section 2.1).
 * Everything here is the other half of that same table: front-end JavaScript and
 * the derived href of a link, both of which are this theme's and both of which
 * should disappear if the theme does.
 *
 * **The script is enqueued from the render, not from `wp_enqueue_scripts`.**
 * The chart is on one page. Loading its controller on every page of the site to
 * save a conditional is the pitfall CLAUDE.md names, and `render_block` is the
 * only hook that knows whether the block is actually on the page being drawn.
 * It fires while the template renders, which is before `wp_footer`, so a footer
 * script still has somewhere to be printed.
 *
 * **The card's href is derived, not written.** ADR-0006 section 2 settled the
 * pattern for the whole site: a link says which destination it wants by
 * carrying a class, and the theme supplies the URL at render time. `dp-card-open`
 * is one more of those. The destination happens to be a fragment on the same
 * page rather than a page, so nothing here is a route — and the query arg it
 * carries is what makes the card work with JavaScript off, because the server
 * reads it and renders that entry already open.
 */
final class Timeline {

	/**
	 * The class a `core/post-title` carries to become a link into the chart.
	 */
	public const CARD_LINK_CLASS = 'dp-card-open';

	/**
	 * The script handle.
	 */
	public const SCRIPT_HANDLE = 'dpaternina-timeline';

	/**
	 * The script, relative to the theme root.
	 */
	private const SCRIPT_PATH = 'assets/js/timeline.js';

	/**
	 * Constructor.
	 *
	 * @param Theme $theme The booted theme, for URLs and cache-busting versions.
	 */
	public function __construct( private readonly Theme $theme ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		/*
		 * The hook is named after the plugin's block, so the constant is only
		 * read once the class is there to read it from. Without the guard the
		 * theme fatals on a site where `dp-core` is deactivated — which is not
		 * hypothetical: it is what a fresh `composer test:integration` leaves
		 * behind, and it is the promise ADR-0006 §5 already makes about the
		 * theme's other cross-package block.
		 */
		if ( class_exists( TimelineBlock::class ) ) {
			add_filter( 'render_block_' . TimelineBlock::BLOCK_NAME, $this->enqueue_controller( ... ) );
			add_filter( 'render_block_core/post-title', $this->link_the_card( ... ), 10, 2 );
		}
	}

	/**
	 * Load the controller, because the chart is on this page.
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

		return $content;
	}

	/**
	 * Turn a work card's title into a link to that entry on the timeline.
	 *
	 * @param string               $content The rendered title.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function link_the_card( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || ! str_contains( ' ' . $class_name . ' ', ' ' . self::CARD_LINK_CLASS . ' ' ) ) {
			return $content;
		}

		$key = $this->entry_key();

		if ( '' === $key ) {
			return $content;
		}

		$opening = strpos( $content, '>' );
		$closing = strrpos( $content, '</' );

		if ( false === $opening || false === $closing || $closing <= $opening ) {
			return $content;
		}

		$anchor = sprintf(
			'<a class="%1$s" data-dp-entry="%2$s" href="%3$s">',
			esc_attr( self::CARD_LINK_CLASS ),
			esc_attr( $key ),
			esc_url( add_query_arg( TimelineBlock::OPEN_ARG, $key ) . '#' . $key )
		);

		return substr( $content, 0, $opening + 1 )
			. $anchor
			. substr( $content, $opening + 1, $closing - $opening - 1 )
			. '</a>'
			. substr( $content, $closing );
	}

	/**
	 * The timeline entry the post in the loop belongs to.
	 *
	 * Asked of `dp-core`, never rebuilt here. The key is one seam between the
	 * two packages and a format string copied into a second file is a format
	 * string that will one day disagree with the first.
	 *
	 * @return string The entry key, or '' when there is no entry to link to.
	 */
	private function entry_key(): string {
		if ( ! class_exists( Chart::class ) ) {
			return '';
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || PostTypes::SHIP !== $post->post_type ) {
			return '';
		}

		return Chart::entry_key( $post->post_type, $post->post_name, $post->ID );
	}
}
