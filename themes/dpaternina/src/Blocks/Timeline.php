<?php
/**
 * What the theme adds to `dp/timeline`.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Blocks\Timeline as TimelineBlock;
use DP\Theme\Theme;

/**
 * The timeline's presentation layer: one script, loaded only where the chart is.
 *
 * The block itself belongs to `dp-core`, because it renders content and a theme
 * that owned it would take the record away with it (CLAUDE.md section 2.1).
 * What is left here is front-end JavaScript, which is this theme's and should
 * disappear if the theme does.
 *
 * **The script is enqueued from the render, not from `wp_enqueue_scripts`.**
 * The chart is on one page. Loading its controller on every page of the site to
 * save a conditional is the pitfall CLAUDE.md names, and `render_block` is the
 * only hook that knows whether the block is actually on the page being drawn.
 * It fires while the template renders, which is before `wp_footer`, so a footer
 * script still has somewhere to be printed.
 *
 * The other half of this class used to be `link_the_card()`, which spliced an
 * `<a>` into a `core/post-title` that carried `dp-card-open`. That is
 * `DP\Theme\Blocks\WorkCardTitle` now, for the reason ADR-0018 gives: the
 * trigger was an invisible class and the editor drew a plain title where the
 * page drew a link.
 */
final class Timeline {

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
	 * Attach the hook.
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
}
