<?php
/**
 * Every block the theme renders in PHP has an editor preview.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Theme\Blocks\EditorScript;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * `ServerRenderedParityTest`, for the theme's own blocks.
 *
 * The plugin has had this assertion since ADR-0009 and the theme has not, for
 * one reason: the theme shipped no JavaScript at all, so there was nothing for a
 * `block.json` to name and `dpaternina/series-planned` drew as `core/missing` in
 * the site editor with the gap recorded in the merge queue rather than caught by
 * a test. `DP\Theme\Blocks\EditorScript` closed that, and this is the assertion
 * that stops it re-opening.
 *
 * It is derived from the filesystem, not from a list written here. Every
 * `block.json` under `themes/dpaternina/blocks/` is a block the theme renders on
 * the server and nowhere else, so every one of them needs three things — a
 * registration, a render callback, and an `editorScript` naming a handle that
 * exists — and the file the editor loads has to name all of them and nothing
 * else. A seventh block added without a line in that array fails here rather
 * than in David's canvas.
 */
final class ThemeEditorParityTest extends WP_UnitTestCase {

	/**
	 * The block definitions the theme ships, by name.
	 *
	 * @return list<string>
	 */
	private function theme_blocks(): array {
		$found = glob( $this->theme_dir() . '/blocks/*/block.json' );

		$this->assertIsArray( $found );
		$this->assertNotEmpty( $found );

		$names = array();

		foreach ( $found as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
			$json = file_get_contents( $path );

			$this->assertIsString( $json );

			$definition = json_decode( $json, true );

			$this->assertIsArray( $definition );
			$this->assertIsString( $definition['name'] ?? null );

			$names[] = $definition['name'];
		}

		sort( $names );

		return $names;
	}

	/**
	 * The names the editor bundle registers a preview for.
	 *
	 * @return list<string>
	 */
	private function previewed_blocks(): array {
		$source = $this->theme_dir() . '/' . EditorScript::PATH;

		$this->assertFileExists( $source );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$javascript = file_get_contents( $source );

		$this->assertIsString( $javascript );
		$this->assertSame(
			1,
			preg_match( '~const SERVER_RENDERED = \[(.*?)\];~s', $javascript, $array )
		);

		preg_match_all( "~'([^']+)'~", $array[1], $names );

		$found = $names[1];

		sort( $found );

		return $found;
	}

	/**
	 * The theme's directory.
	 *
	 * @return string
	 */
	private function theme_dir(): string {
		return dirname( __DIR__, 3 ) . '/themes/dpaternina';
	}

	/**
	 * Every block the theme ships has an editor preview, and no other does.
	 *
	 * @return void
	 */
	public function test_the_editor_previews_exactly_the_blocks_the_theme_ships(): void {
		$this->assertSame(
			$this->theme_blocks(),
			$this->previewed_blocks(),
			'A block in themes/dpaternina/blocks/ with no entry in blocks-editor.js draws as '
			. '"core/missing" in the site editor, inside a template that renders perfectly '
			. 'on the front end.'
		);
	}

	/**
	 * Each of them is registered, dynamic, and names a script that exists.
	 *
	 * A `block.json` naming a handle nothing has registered enqueues nothing,
	 * silently — so naming the handle and registering it are one assertion, not
	 * two.
	 *
	 * @return void
	 */
	public function test_each_of_them_is_dynamic_and_names_a_registered_editor_script(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue(
			wp_script_is( EditorScript::HANDLE, 'registered' ),
			'The theme registers no editor script, so every block naming it loads nothing.'
		);

		foreach ( $this->theme_blocks() as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertInstanceOf( WP_Block_Type::class, $block, $name . ' is not registered.' );
			$this->assertTrue( $block->is_dynamic(), $name . ' has no render callback.' );
			$this->assertContains(
				EditorScript::HANDLE,
				(array) $block->editor_script_handles,
				$name . ' has no editor registration, so the site editor draws it as core/missing.'
			);
		}
	}
}
