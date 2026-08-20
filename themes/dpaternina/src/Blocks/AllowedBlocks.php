<?php
/**
 * The editor allowlist.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use WP_Block_Editor_Context;
use WP_Block_Type_Registry;
use WP_Post;

/**
 * Restricts the post editor to the house style.
 *
 * The Colophon's intent, and docs/plan.md Phase 4: the editor offers this
 * design system or nothing. That is enforced here rather than by asking anyone
 * to remember it.
 *
 * **Scope: posts.** The allowlist applies when the block editor is opened on a
 * `post`, which is what the house style is about — the reference for it is a
 * post, and the limits it carries are stated per post. It deliberately does not
 * apply to pages, to the site editor, or to any other post type:
 *
 * - Pages are David's (CLAUDE.md §5.1). They are built from the patterns Phase
 *   5 ships, which are groups, columns and buttons; restricting pages to the
 *   nine blocks a *post* may use would make those patterns unusable.
 * - The site editor edits this theme's own templates. Cutting it down to nine
 *   blocks would leave no way to place a template part, a query loop, or post
 *   content — it would break the theme rather than constrain it.
 *
 * Everything is filterable through `dp_allowed_block_types`, so widening or
 * narrowing the set is a filter, not a patch.
 */
final class AllowedBlocks {

	/**
	 * The post type the house style governs.
	 *
	 * @var string
	 */
	private const GOVERNED_POST_TYPE = 'post';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'allowed_block_types_all', $this->filter( ... ), 10, 2 );
	}

	/**
	 * Narrow the editor's block list when the house style applies.
	 *
	 * @param bool|string[]           $allowed Whatever an earlier filter decided: true for "all", or a list of names.
	 * @param WP_Block_Editor_Context $context The editor asking.
	 * @return bool|string[] The list this editor may use.
	 */
	public function filter( bool|array $allowed, WP_Block_Editor_Context $context ): bool|array {
		if ( ! $this->governs( $context ) ) {
			return $allowed;
		}

		/**
		 * Filters the blocks a post may be written with.
		 *
		 * @param string[]                $blocks  The house style's block names.
		 * @param WP_Block_Editor_Context $context The editor asking.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is the project's public filter prefix (docs/plan.md; `dp_series_rewrite_slug` is the other one). WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$blocks = apply_filters( 'dp_allowed_block_types', $this->house_style(), $context );

		return is_array( $blocks ) ? array_values( array_unique( array_map( 'strval', $blocks ) ) ) : $allowed;
	}

	/**
	 * Whether this editor is editing a post in the house style.
	 *
	 * @param WP_Block_Editor_Context $context The editor asking.
	 * @return bool
	 */
	public function governs( WP_Block_Editor_Context $context ): bool {
		$post = $context->post;

		return $post instanceof WP_Post && self::GOVERNED_POST_TYPE === $post->post_type;
	}

	/**
	 * The house style's blocks, resolved against what is actually registered.
	 *
	 * The core names are stated; everything under an admitted prefix is
	 * discovered, so `dp/timeline` (Phase 6) and whichever Stackable blocks
	 * David keeps installed need no edit here — and a deactivated Stackable
	 * simply contributes nothing.
	 *
	 * @return string[]
	 */
	public function house_style(): array {
		$blocks = Vocabulary::CORE_BLOCKS;

		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			if ( Vocabulary::is_prefixed( (string) $name ) ) {
				$blocks[] = (string) $name;
			}
		}

		return array_values( array_unique( $blocks ) );
	}
}
