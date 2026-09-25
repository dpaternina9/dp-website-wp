<?php
/**
 * What the theme adds to the Photos wall.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Photos\PhotoWall;
use DP\Theme\Theme;

/**
 * The wall's two scripts, loaded only where the wall is.
 *
 * The block belongs to `dp-core`, because it renders content (CLAUDE.md section
 * 2.1); the upgrade is the theme's. Without the scripts the wall is CSS columns
 * of links, the index is links, and an open photo is a panel the server drew —
 * all of it works. With them, `photo-layout.js` places the tiles and
 * `photos.js` does everything else; see the header of each.
 *
 * `Blocks\Watch`'s shape: enqueued from the render, guarded by `class_exists`
 * so the theme survives a site where `dp-core` is deactivated.
 */
final class Photos {

	/**
	 * The controller's handle.
	 */
	public const SCRIPT_HANDLE = 'dpaternina-photos';

	/**
	 * The layout arithmetic's handle.
	 */
	public const LAYOUT_HANDLE = 'dpaternina-photo-layout';

	/**
	 * The controller, relative to the theme root.
	 */
	private const SCRIPT_PATH = 'assets/js/photos.js';

	/**
	 * The layout arithmetic, relative to the theme root.
	 */
	private const LAYOUT_PATH = 'assets/js/photo-layout.js';

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
		if ( ! class_exists( PhotoWall::class ) ) {
			return;
		}

		add_filter( 'render_block_' . PhotoWall::BLOCK_NAME, $this->enqueue_controller( ... ) );
	}

	/**
	 * Load both scripts, because the wall is on this page.
	 *
	 * @param string $content The block's rendered HTML.
	 * @return string The HTML, untouched.
	 */
	public function enqueue_controller( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		// Deferred rather than async: both upgrade markup they have to be able
		// to find, and deferred scripts run in order. CLAUDE.md section 1.7:
		// no render-blocking JS.
		$strategy = array(
			'strategy'  => 'defer',
			'in_footer' => true,
		);

		wp_enqueue_script(
			self::LAYOUT_HANDLE,
			$this->theme->url( self::LAYOUT_PATH ),
			array(),
			$this->theme->asset_version( self::LAYOUT_PATH ),
			$strategy
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->theme->url( self::SCRIPT_PATH ),
			array( self::LAYOUT_HANDLE ),
			$this->theme->asset_version( self::SCRIPT_PATH ),
			$strategy
		);

		return $content;
	}
}
