<?php
/**
 * Core's block style variations, and their absence.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Theme\Blocks\Vocabulary;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * The design gives each block one appearance, so the editor offers one.
 *
 * Core ships alternatives — Plain, Wide, Dotted, Stripes, Rounded — every one
 * of which is a way out of the design system by accident. They are declared in
 * core's own block.json, so `unregister_block_style()` cannot see them and
 * `block_type_metadata` is where they come off.
 */
final class CoreStylesTest extends WP_UnitTestCase {

	/**
	 * The blocks the house styles itself offer no style variations.
	 *
	 * @return void
	 */
	public function test_the_house_s_blocks_have_no_style_variations(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( Vocabulary::STYLED_BY_THE_HOUSE as $name ) {
			$type = $registry->get_registered( $name );

			$this->assertNotNull( $type, sprintf( '%s is not registered, so this asserts nothing about it.', $name ) );
			$this->assertSame(
				array(),
				$type->styles,
				sprintf( '%s still offers a core style variation. The design gives it one appearance.', $name )
			);
		}
	}

	/**
	 * Core keeps its variations everywhere else.
	 *
	 * A filter on `block_type_metadata` sees every block there is, including
	 * Stackable's. This is the assertion that it stays hands-off.
	 *
	 * @return void
	 */
	public function test_blocks_outside_the_vocabulary_are_left_alone(): void {
		$button = WP_Block_Type_Registry::get_instance()->get_registered( 'core/button' );

		$this->assertNotNull( $button );
		$this->assertNotEmpty( $button->styles, 'core/button is not in the vocabulary and its styles were removed anyway.' );
	}

	/**
	 * The separator that content already carries still renders as the design's.
	 *
	 * Removing the variations does not remove the classes from posts written
	 * before, so the house rule has to hold for `is-style-wide` too.
	 *
	 * @return void
	 */
	public function test_a_legacy_style_class_still_renders_through_the_house_style(): void {
		$block = "<!-- wp:separator {\"className\":\"is-style-wide\"} -->\n"
			. "<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-wide\"/>\n"
			. '<!-- /wp:separator -->';

		$this->assertStringContainsString( 'wp-block-separator', do_blocks( $block ) );
	}
}
