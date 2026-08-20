<?php
/**
 * Everything the plugin contributes to the block editor.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

/**
 * The plugin's block surface, in one place.
 *
 * Only two things live here, and both are here for the same reason: they carry
 * content. `dp/callout` is a block a post is written with, and the code block's
 * label is a word David typed. If either were registered by the theme,
 * switching themes would turn published posts into invalid blocks — which is
 * the line CLAUDE.md §2.1 draws.
 *
 * Everything about how these look is the theme's: the callout has no stylesheet
 * of its own, and the label bar is drawn by assets/css/blocks.css from a data
 * attribute this plugin supplies.
 */
final class Blocks {

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory, without a trailing slash.
	 */
	public function __construct( private readonly string $plugin_dir ) {}

	/**
	 * Attach the blocks.
	 *
	 * Called from Plugin::register(), which runs on `init` — where block types
	 * have to be registered and where the editor has not yet asked for them.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Callout( $this->plugin_dir ) )->register();
		( new CodeLabel() )->register();
	}
}
