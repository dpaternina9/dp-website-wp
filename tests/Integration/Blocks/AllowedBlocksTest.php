<?php
/**
 * The editor allowlist.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Theme\Blocks\AllowedBlocks;
use DP\Theme\Blocks\Vocabulary;
use WP_Block_Editor_Context;
use WP_UnitTestCase;

/**
 * Covers docs/plan.md Phase 4: an explicit allowlist, and its exact scope.
 *
 * The scope is the part worth testing. "Everything else off" applied to the
 * whole editor would take the site editor's template parts and query loops
 * away with it, so the restriction is the post editor's — asserted here from
 * both sides.
 */
final class AllowedBlocksTest extends WP_UnitTestCase {

	/**
	 * A post editor context for a post of the given type.
	 *
	 * @param string $post_type The post type being edited.
	 * @return WP_Block_Editor_Context
	 */
	private function editing( string $post_type ): WP_Block_Editor_Context {
		return new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-post',
				'post' => self::factory()->post->create_and_get( array( 'post_type' => $post_type ) ),
			)
		);
	}

	/**
	 * Writing a post offers the house style and nothing else.
	 *
	 * @return void
	 */
	public function test_a_post_is_restricted_to_the_house_style(): void {
		$allowed = apply_filters( 'allowed_block_types_all', true, $this->editing( 'post' ) );

		$this->assertIsArray( $allowed );

		foreach ( Vocabulary::CORE_BLOCKS as $name ) {
			$this->assertContains( $name, $allowed, sprintf( '%s is in the vocabulary and the editor does not offer it.', $name ) );
		}

		foreach ( array( 'core/buttons', 'core/columns', 'core/html', 'core/embed', 'core/cover', 'core/group', 'core/freeform' ) as $name ) {
			$this->assertNotContains( $name, $allowed, sprintf( '%s is not in the house style and the editor still offers it.', $name ) );
		}
	}

	/**
	 * Pages are David's, and are left alone.
	 *
	 * CLAUDE.md §5.1. Phase 5's page patterns are groups and columns; cutting a
	 * page down to nine blocks would make them uninsertable.
	 *
	 * @return void
	 */
	public function test_a_page_is_not_restricted(): void {
		$this->assertTrue( apply_filters( 'allowed_block_types_all', true, $this->editing( 'page' ) ) );
	}

	/**
	 * The site editor is not restricted either.
	 *
	 * @return void
	 */
	public function test_the_site_editor_is_not_restricted(): void {
		$context = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );

		$this->assertTrue( apply_filters( 'allowed_block_types_all', true, $context ) );
		$this->assertFalse( ( new AllowedBlocks() )->governs( $context ) );
	}

	/**
	 * Blocks under an admitted prefix are discovered, not listed.
	 *
	 * `dp/timeline` arrives in Phase 6 and Stackable's list is David's to
	 * change; neither should need an edit here.
	 *
	 * @return void
	 */
	public function test_our_own_blocks_and_stackable_are_admitted_by_prefix(): void {
		register_block_type( 'dp/example-for-this-test' );
		register_block_type( 'stackable/example-for-this-test' );

		$allowed = ( new AllowedBlocks() )->house_style();

		$this->assertContains( 'dp/example-for-this-test', $allowed );
		$this->assertContains( 'stackable/example-for-this-test', $allowed );

		unregister_block_type( 'dp/example-for-this-test' );
		unregister_block_type( 'stackable/example-for-this-test' );
	}

	/**
	 * Nothing is offered twice, and everything offered is a string.
	 *
	 * @return void
	 */
	public function test_the_list_is_a_clean_set_of_names(): void {
		$allowed = ( new AllowedBlocks() )->house_style();

		$this->assertSame( array_values( array_unique( $allowed ) ), $allowed );
		$this->assertContainsOnly( 'string', $allowed );
	}

	/**
	 * The list is a filter, so widening it is configuration rather than a patch.
	 *
	 * @return void
	 */
	public function test_the_list_can_be_widened_without_editing_the_theme(): void {
		add_filter(
			'dp_allowed_block_types',
			static fn ( array $blocks ): array => array_merge( $blocks, array( 'core/pullquote' ) )
		);

		$allowed = apply_filters( 'allowed_block_types_all', true, $this->editing( 'post' ) );

		$this->assertIsArray( $allowed );
		$this->assertContains( 'core/pullquote', $allowed );
	}

	/**
	 * An earlier filter's decision is respected outside the house style's scope.
	 *
	 * @return void
	 */
	public function test_an_earlier_decision_is_left_alone_on_a_page(): void {
		$narrowed = array( 'core/paragraph' );

		$this->assertSame(
			$narrowed,
			apply_filters( 'allowed_block_types_all', $narrowed, $this->editing( 'page' ) )
		);
	}
}
