<?php
/**
 * Every block the plugin renders in PHP has an editor preview.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * The list in the editor bundle is the list of blocks that need it.
 *
 * A block registered in PHP with a render callback and no client-side
 * registration draws in the site editor as core's `core/missing` — "Your site
 * doesn't include support for the dp/timeline block" — inside a template that
 * renders perfectly on the front end. All three of this plugin's dynamic blocks
 * shipped that way, and nothing said so, because the templates were correct and
 * the tests read markup rather than the canvas.
 *
 * So the list of blocks needing a preview is derived from the filesystem — every
 * `block.json` in `plugins/dp-core/blocks/` — and held against the array the
 * editor bundle iterates. A fourth dynamic block added without a preview fails
 * here rather than being discovered by eye, which is the whole point: CLAUDE.md
 * §5 says the editor must look like the front end, and this is the only
 * assertion in the suite that can catch it going wrong.
 *
 * `dp/callout` is deliberately not in that directory. It is a static block with
 * a real `edit` and `save` of its own, registered from `build/callout`, so it
 * has never had this problem.
 */
final class ServerRenderedParityTest extends WP_UnitTestCase {

	/**
	 * The block definitions the plugin renders on the server.
	 *
	 * @return list<string>
	 */
	private function dynamic_blocks(): array {
		$found = glob( $this->plugin_dir() . '/blocks/*/block.json' );

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
	 * Read out of the source rather than out of the build, because the source is
	 * what a change would edit and the build is a copy of it.
	 *
	 * @return list<string>
	 */
	private function previewed_blocks(): array {
		$source = $this->plugin_dir() . '/src/Blocks/js/dynamic/server-rendered.js';

		$this->assertFileExists( $source );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$javascript = file_get_contents( $source );

		$this->assertIsString( $javascript );
		$this->assertSame(
			1,
			preg_match( '~export const SERVER_RENDERED = \[(.*?)\];~s', $javascript, $array )
		);

		preg_match_all( "~'([^']+)'~", $array[1], $names );

		$found = $names[1];

		sort( $found );

		return $found;
	}

	/**
	 * The plugin's directory.
	 *
	 * @return string
	 */
	private function plugin_dir(): string {
		return dirname( __DIR__, 3 ) . '/plugins/dp-core';
	}

	/**
	 * Every server-rendered block has an editor preview, and no other does.
	 *
	 * @return void
	 */
	public function test_the_editor_previews_exactly_the_server_rendered_blocks(): void {
		$this->assertSame(
			$this->dynamic_blocks(),
			$this->previewed_blocks(),
			'A block in plugins/dp-core/blocks/ with no entry in server-rendered.js draws as '
			. '"core/missing" in the site editor. Add it to SERVER_RENDERED and run `npm run build`.'
		);
	}

	/**
	 * Each of them really is server-rendered, so a preview is the right answer.
	 *
	 * @return void
	 */
	public function test_each_of_them_is_registered_with_a_render_callback(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( $this->dynamic_blocks() as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertNotNull( $block, $name . ' is not registered.' );
			$this->assertTrue( $block->is_dynamic(), $name . ' has no render callback.' );
		}
	}

	/**
	 * The compiled bundle carries the preview, so a release ships it.
	 *
	 * `npm run build` is not run by `composer test`, and a source-only change
	 * would otherwise pass every gate and reach David's editor as the bug it was
	 * meant to fix.
	 *
	 * @return void
	 */
	public function test_the_built_bundle_carries_every_name(): void {
		$bundle = $this->plugin_dir() . '/build/callout/index.js';

		$this->assertFileExists( $bundle );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$compiled = file_get_contents( $bundle );

		$this->assertIsString( $compiled );

		foreach ( $this->dynamic_blocks() as $name ) {
			$this->assertStringContainsString(
				$name,
				$compiled,
				$name . ' is not in the built editor bundle. Run `npm run build`.'
			);
		}
	}
}
