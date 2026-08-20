<?php
/**
 * The `dp/callout` block type.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Core\Blocks\Blocks;
use DP\Core\Blocks\Callout;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Registration, attributes, and the reasons the block is in the plugin.
 *
 * The block is registered here rather than being assumed: `Plugin::register()`
 * is not this phase's file to edit, so the entry point is exercised directly.
 * The guard means the same test passes unchanged once that one line is wired.
 */
final class CalloutTest extends WP_UnitTestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( Callout::BLOCK_NAME ) ) {
			( new Blocks( dirname( __DIR__, 3 ) . '/plugins/dp-core' ) )->register();
		}
	}

	/**
	 * The block registers from its compiled metadata.
	 *
	 * A failure here is almost always a missing `npm run build`.
	 *
	 * @return void
	 */
	public function test_the_block_is_registered(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Callout::BLOCK_NAME );

		$this->assertNotNull( $type, 'dp/callout is not registered. Run npm run build.' );
		$this->assertSame( 'Callout', $type->title );
		$this->assertSame( Callout::CATEGORY, $type->category );
	}

	/**
	 * The label is an attribute, and it defaults to the design's word.
	 *
	 * @return void
	 */
	public function test_the_label_defaults_to_note(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Callout::BLOCK_NAME );

		$this->assertNotNull( $type );
		$this->assertIsArray( $type->attributes );
		$this->assertArrayHasKey( 'label', $type->attributes );
		$this->assertSame( 'NOTE', $type->attributes['label']['default'] );
		$this->assertArrayHasKey( 'content', $type->attributes );
	}

	/**
	 * The block is static: no render callback, and no front-end script.
	 *
	 * @return void
	 */
	public function test_the_block_is_static_and_ships_no_front_end_javascript(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Callout::BLOCK_NAME );

		$this->assertNotNull( $type );
		$this->assertFalse( $type->is_dynamic() );
		$this->assertSame( array(), $type->view_script_handles );
		$this->assertSame( array(), $type->script_handles );
		$this->assertNotEmpty( $type->editor_script_handles, 'The editor bundle is the block’s editorScript; without it nothing loads in the editor.' );
	}

	/**
	 * More than one callout is possible. The house limit is a warning.
	 *
	 * `supports.multiple: false` would make it a hard block, which docs/plan.md
	 * Phase 4 rules out: "warnings, not hard blocks".
	 *
	 * @return void
	 */
	public function test_a_second_callout_is_not_prevented(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Callout::BLOCK_NAME );

		$this->assertNotNull( $type );
		$this->assertNotFalse( $type->supports['multiple'] ?? true );
	}

	/**
	 * The plugin's blocks get their own inserter category, once.
	 *
	 * @return void
	 */
	public function test_the_category_is_added_exactly_once(): void {
		$callout = new Callout( dirname( __DIR__, 3 ) . '/plugins/dp-core' );

		$categories = $callout->add_category( $callout->add_category( array() ) );

		$this->assertCount( 1, $categories );
		$this->assertSame( Callout::CATEGORY, $categories[0]['slug'] );
	}

	/**
	 * A saved callout renders with no plugin involvement whatsoever.
	 *
	 * This is the reason the block is static. Deactivating dp-core takes the
	 * editor UI away and leaves every published callout on the page, styled,
	 * because the markup is in the post and the CSS is in the theme.
	 *
	 * @return void
	 */
	public function test_a_saved_callout_renders_even_unregistered(): void {
		$markup = HouseStyleFixture::callout( 'NOTE', 'A caveat.' );

		unregister_block_type( Callout::BLOCK_NAME );

		$html = do_blocks( $markup );

		$this->assertStringContainsString( 'wp-block-dp-callout', $html );
		$this->assertStringContainsString( '<span class="dp-callout-label">NOTE</span>', $html );
		$this->assertStringContainsString( 'A caveat.', $html );
	}
}
