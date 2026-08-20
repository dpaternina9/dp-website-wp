<?php
/**
 * The pattern category this theme's patterns file themselves under.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

/**
 * One category, so the design's components sit together in the inserter.
 *
 * The patterns themselves are registered by WordPress from `patterns/`, which
 * is the mechanism a block theme is given and needs no code. A category is the
 * one part of it that does: `Categories: dpaternina` in a pattern header refers
 * to a category, and an unregistered one leaves the pattern filed under nothing.
 */
final class Patterns {

	/**
	 * The category slug, matching the `Categories:` header in `patterns/`.
	 */
	public const CATEGORY = 'dpaternina';

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_category( ... ) );
	}

	/**
	 * Register the category.
	 *
	 * @return void
	 */
	public function register_category(): void {
		register_block_pattern_category(
			self::CATEGORY,
			array(
				'label'       => __( 'dPaternina', 'dpaternina' ),
				'description' => __( "The design system's own components.", 'dpaternina' ),
			)
		);
	}
}
