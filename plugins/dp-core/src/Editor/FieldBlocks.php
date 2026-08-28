<?php
/**
 * The blocks the editing form is drawn with.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Editor;

/**
 * Seven blocks that exist only inside a `dp_role`, `dp_ship` or `dp_video`.
 *
 * They are defined in `plugins/dp-core/fields/`, which is a directory of its
 * own and not `plugins/dp-core/blocks/`. That distinction is load-bearing:
 * `ServerRenderedParityTest` globs `blocks/` and asserts every definition in it
 * is server-rendered and has an editor preview, and it is the only thing in the
 * suite that catches a dynamic block arriving in the site editor as
 * `core/missing`. None of these seven is server-rendered — each draws in the
 * canvas and nowhere else — so putting one in that directory would be a false
 * entry in the one test that guards a real failure.
 *
 * Each definition is a `block.json`, so the metadata is stated once and the
 * bundle supplies only what the server cannot: how the block draws.
 * `register_block_type_from_metadata()` bootstraps the rest into `wp.blocks` on
 * every editor screen.
 *
 * **Every one is `inserter: false`.** They are not blocks anybody chooses; they
 * are the form a post type opens with, placed by `register_post_type()`'s
 * `template` and held there by `template_lock => 'all'`. The theme's
 * `AllowedBlocks` admits everything under the `dp/` prefix into the post
 * editor's allowlist, so `inserter: false` is what keeps them out of a post
 * body — an allowlist says what may be there, the inserter is what puts it
 * there.
 *
 * **None of them renders on the front end.** The bundle's `save` returns null in
 * every case, so a block leaves a self-closing comment in `post_content` and no
 * markup at all. That is correct rather than convenient: these three post types
 * are `public => false` and have no single view, so their content is never the
 * subject of a request.
 */
final class FieldBlocks {

	/**
	 * Where the definitions live, relative to the plugin root.
	 *
	 * @var string
	 */
	public const DIRECTORY = 'fields';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory, with a trailing slash.
	 */
	public function __construct( private readonly string $plugin_dir ) {}

	/**
	 * Register every block the form is made of.
	 *
	 * The names are the authority and the directory is checked against them:
	 * a definition on disk that no `Control` names, or a control whose block has
	 * no definition, is a form that cannot be drawn. `FieldBlocksTest` asserts
	 * both directions; this simply skips what is not there rather than emitting
	 * a notice on every request.
	 *
	 * @return list<string> The names that were registered.
	 */
	public function register(): array {
		$registered = array();

		foreach ( self::names() as $name ) {
			$path = $this->plugin_dir . self::DIRECTORY . '/' . $this->folder( $name );

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			if ( false !== register_block_type( $path ) ) {
				$registered[] = $name;
			}
		}

		return $registered;
	}

	/**
	 * Every block name the form is drawn with.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_merge( array( FieldForm::LABEL_BLOCK ), Control::blocks() );
	}

	/**
	 * The directory one block's definition lives in.
	 *
	 * @param string $name The block name, namespaced.
	 * @return string The folder name, which is the block name without its namespace.
	 */
	private function folder( string $name ): string {
		$slash = strpos( $name, '/' );

		return false === $slash ? $name : substr( $name, $slash + 1 );
	}
}
