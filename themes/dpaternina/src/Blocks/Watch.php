<?php
/**
 * What the theme adds to the Watch blocks.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Watch\VideoGrid;
use DP\Core\Watch\WatchFeatured;
use DP\Theme\Theme;

/**
 * Click-to-play: one script, loaded only where a video card is.
 *
 * The blocks belong to `dp-core`, because they render content (CLAUDE.md
 * section 2.1). What is left here is the front-end upgrade: without it every
 * card is a plain link to the video on its host, and with it the press swaps
 * an iframe into the card — the design's "players load only when you press
 * play", which is a privacy and layout property, not a nicety.
 *
 * The same shape as `Blocks\Timeline`: enqueued from the render because the
 * grid is on one page, guarded by `class_exists` so the theme survives a site
 * where `dp-core` is deactivated.
 */
final class Watch {

	/**
	 * The script handle.
	 */
	public const SCRIPT_HANDLE = 'dpaternina-watch';

	/**
	 * The script, relative to the theme root.
	 */
	private const SCRIPT_PATH = 'assets/js/watch.js';

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
		if ( ! class_exists( WatchFeatured::class ) ) {
			return;
		}

		add_filter( 'render_block_' . WatchFeatured::BLOCK_NAME, $this->enqueue_controller( ... ) );
		add_filter( 'render_block_' . VideoGrid::BLOCK_NAME, $this->enqueue_controller( ... ) );
	}

	/**
	 * Load the controller, because a video card is on this page.
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
