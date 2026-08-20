<?php
/**
 * The `dp/callout` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

/**
 * Registers the one custom block the house style needs.
 *
 * The design calls it `note` in components/PostBlocks.dc.html: a label above a
 * single paragraph, on a teal-tinted surface. Static — the markup is written
 * into the post, there is no render callback, and nothing about it needs PHP at
 * display time.
 *
 * The block is registered from the compiled metadata in `build/`, which is
 * where `npm run build` puts a copy of `src/Blocks/js/callout/block.json`
 * alongside the editor bundle it names. Registering from `build/` rather than
 * from `src/` is what makes `file:./index.js` resolve to the compiled script.
 */
final class Callout {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/callout';

	/**
	 * The editor category the block appears under.
	 *
	 * @var string
	 */
	public const CATEGORY = 'dp';

	/**
	 * Path to the compiled block, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const BUILD_PATH = '/build/callout';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory, without a trailing slash.
	 */
	public function __construct( private readonly string $plugin_dir ) {}

	/**
	 * Attach the hooks and register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_categories_all', $this->add_category( ... ) );

		register_block_type( $this->plugin_dir . self::BUILD_PATH );
	}

	/**
	 * Give the plugin's blocks a category of their own.
	 *
	 * Placed first so the house style is the first thing the inserter offers,
	 * rather than the last thing under "Widgets".
	 *
	 * @param array<int, array<string, mixed>> $categories The registered categories.
	 * @return array<int, array<string, mixed>> The categories, with this plugin's added once.
	 */
	public function add_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( ( $category['slug'] ?? null ) === self::CATEGORY ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'dPaternina', 'dp-core' ),
				'icon'  => null,
			)
		);

		return $categories;
	}
}
