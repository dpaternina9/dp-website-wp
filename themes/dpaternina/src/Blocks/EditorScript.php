<?php
/**
 * The one script this theme gives the block editor.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Theme\Theme;

/**
 * Registers the handle every server-rendered theme block names in its `block.json`.
 *
 * ADR-0009's rule — a block rendered in PHP still has to exist in the editor —
 * needs a script, and the theme has never had one to put it in. This registers
 * exactly one handle, against a file that is shipped as written rather than
 * compiled, so the theme still has no build step and the release zip still has
 * no `build/` directory in it.
 *
 * `block.json`'s `editorScript` takes a registered handle as readily as a
 * `file:` path, so the three blocks name this handle and WordPress enqueues it
 * on every block-editor screen where one of them can appear. The priority is
 * ahead of block registration for the obvious reason: a `block.json` naming a
 * handle nothing has registered enqueues nothing, silently.
 */
final class EditorScript {

	/**
	 * The script handle the theme's blocks name.
	 */
	public const HANDLE = 'dpaternina-blocks';

	/**
	 * The file, relative to the theme root.
	 */
	public const PATH = 'assets/js/blocks-editor.js';

	/**
	 * Core script handles the file uses through the `wp` global.
	 *
	 * Declared so WordPress loads them first and so the list is checkable. All
	 * four ship with WordPress; nothing here is bundled and nothing is fetched
	 * from anywhere but this site (CLAUDE.md §1.4).
	 *
	 * @var list<string>
	 */
	private const DEPENDENCIES = array(
		'wp-blocks',
		'wp-block-editor',
		'wp-element',
		'wp-server-side-render',
	);

	/**
	 * Constructor.
	 *
	 * @param Theme $theme The booted theme.
	 */
	public function __construct( private readonly Theme $theme ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_script( ... ), 5 );
	}

	/**
	 * Register the handle.
	 *
	 * @return void
	 */
	public function register_script(): void {
		wp_register_script(
			self::HANDLE,
			$this->theme->url( self::PATH ),
			self::DEPENDENCIES,
			$this->theme->asset_version( self::PATH ),
			true
		);
	}

	/**
	 * The core handles the editor script depends on.
	 *
	 * Exposed so a test can assert the declaration matches what the file
	 * actually reaches for.
	 *
	 * @return list<string>
	 */
	public static function dependencies(): array {
		return self::DEPENDENCIES;
	}
}
