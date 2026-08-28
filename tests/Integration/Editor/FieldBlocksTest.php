<?php
/**
 * The seven blocks the editing form is drawn with.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Editor;

use DP\Core\Content\PostTypes;
use DP\Core\Editor\Editor;
use DP\Core\Editor\FieldBlocks;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Registered on both sides, insertable on neither.
 *
 * Two failures are being guarded here and neither announces itself. A block
 * defined in PHP with no client registration arrives in the canvas as
 * `core/missing` — the exact failure `ServerRenderedParityTest` was written for
 * after three dynamic blocks shipped that way. And a block that reaches the
 * inserter is a block David can put in the middle of a post, where it edits meta
 * that post does not have.
 */
final class FieldBlocksTest extends WP_UnitTestCase {

	/**
	 * The plugin's directory.
	 *
	 * @return string
	 */
	private function plugin_dir(): string {
		return dirname( __DIR__, 3 ) . '/plugins/dp-core';
	}

	/**
	 * The names in the definitions on disk.
	 *
	 * @return list<string>
	 */
	private function defined_blocks(): array {
		$found = glob( $this->plugin_dir() . '/' . FieldBlocks::DIRECTORY . '/*/block.json' );

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
	 * The names the editor bundle draws an `edit` for.
	 *
	 * Read out of the source rather than the build, because the source is what a
	 * change would edit; the build is asserted separately.
	 *
	 * @return list<string>
	 */
	private function drawn_blocks(): array {
		$source = $this->plugin_dir() . '/src/Blocks/js/fields/names.js';

		$this->assertFileExists( $source );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$javascript = file_get_contents( $source );

		$this->assertIsString( $javascript );
		$this->assertSame(
			1,
			preg_match( '~export const FIELD_BLOCKS = \[(.*?)\];~s', $javascript, $array )
		);

		preg_match_all( "~'([^']+)'~", $array[1], $names );

		$found = $names[1];

		sort( $found );

		return $found;
	}

	/**
	 * The names, the definitions and the bundle are one list.
	 *
	 * @return void
	 */
	public function test_the_three_sides_name_the_same_blocks(): void {
		$expected = FieldBlocks::names();

		sort( $expected );

		$this->assertSame( $expected, $this->defined_blocks(), 'A definition in fields/ that no control names, or the reverse.' );
		$this->assertSame(
			$expected,
			$this->drawn_blocks(),
			'A block with no `edit` in the bundle draws in the canvas as core/missing. Add it to fields/names.js and run `npm run build`.'
		);
	}

	/**
	 * Every one of them is registered by the time the editor asks.
	 *
	 * @return void
	 */
	public function test_every_field_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( FieldBlocks::names() as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertNotNull( $block, $name . ' is not registered.' );
			$this->assertSame( 3, $block->api_version, $name . ' is on an older block API than the canvas expects.' );
			$this->assertFalse( $block->is_dynamic(), $name . ' has a render callback, so it would render on the front end.' );
		}
	}

	/**
	 * None of them can be put into a post body.
	 *
	 * The theme's `AllowedBlocks` admits everything under the `dp/` prefix into
	 * the post editor's allowlist, deliberately, so that a block this plugin adds
	 * needs no edit there. An allowlist says what may be present; the inserter is
	 * what puts it there, and this is the half that keeps these seven out.
	 *
	 * @return void
	 */
	public function test_none_of_them_is_insertable(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( FieldBlocks::names() as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertNotNull( $block );
			$this->assertFalse(
				$block->supports['inserter'] ?? true,
				$name . ' is offered by the inserter, so it can be dropped into a post.'
			);
		}
	}

	/**
	 * The compiled bundle carries every name, so a release ships them.
	 *
	 * `npm run build` is not run by `composer test`, and a source-only change
	 * would otherwise pass every gate and reach David's editor as seven
	 * `core/missing` panels.
	 *
	 * @return void
	 */
	public function test_the_built_bundle_carries_every_name(): void {
		$bundle = $this->plugin_dir() . '/build/callout/index.js';

		$this->assertFileExists( $bundle );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$compiled = file_get_contents( $bundle );

		$this->assertIsString( $compiled );

		foreach ( FieldBlocks::names() as $name ) {
			$this->assertStringContainsString(
				$name,
				$compiled,
				$name . ' is not in the built editor bundle. Run `npm run build`.'
			);
		}
	}

	/**
	 * The raw Custom Fields panel is off on the three types, and on elsewhere.
	 *
	 * Not tidying: core's `core/post-meta` source refuses to let a bound
	 * paragraph be edited while `enableCustomFields` is true, so a user who had
	 * ever ticked that box would find every bound field read-only with nothing
	 * saying why.
	 *
	 * @return void
	 */
	public function test_the_custom_fields_panel_is_off_where_the_form_is(): void {
		$editor = Editor::create( $this->plugin_dir() . '/dp-core.php', '0.1.0' );

		foreach ( PostTypes::all() as $post_type ) {
			$settings = $editor->settings(
				array( 'enableCustomFields' => true ),
				new WP_Block_Editor_Context(
					array(
						'name' => 'core/edit-post',
						'post' => self::factory()->post->create_and_get( array( 'post_type' => $post_type ) ),
					)
				)
			);

			$this->assertFalse( $settings['enableCustomFields'], $post_type . ' would open with the raw key/value table.' );
		}

		foreach ( array( 'post', 'page' ) as $post_type ) {
			$settings = $editor->settings(
				array( 'enableCustomFields' => true ),
				new WP_Block_Editor_Context(
					array(
						'name' => 'core/edit-post',
						'post' => self::factory()->post->create_and_get( array( 'post_type' => $post_type ) ),
					)
				)
			);

			$this->assertTrue( $settings['enableCustomFields'], $post_type . ' is not ours to change.' );
		}
	}
}
