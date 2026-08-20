<?php
/**
 * Removes core's block style variations from the house style's blocks.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

/**
 * Takes away the style variations core ships for the blocks this design owns.
 *
 * The design gives each block in its vocabulary exactly one appearance: one
 * quote, one separator, one table, one image. Core offers alternatives —
 * "Plain" for a quote, "Wide" and "Dotted" for a separator, "Stripes" for a
 * table, "Rounded" for an image — every one of which is a way to leave the
 * design system without meaning to.
 *
 * Those variations are declared in each block's own `block.json`, so they are
 * not in the `WP_Block_Styles_Registry` and `unregister_block_style()` cannot
 * reach them. `block_type_metadata` runs while core reads that JSON, which is
 * the one place they can be removed without JavaScript.
 *
 * This is subtractive on purpose. Registering the design's appearance as a
 * named style instead — `is-style-dp-pull` and friends — would leave the
 * unstyled default sitting next to it in the editor, which is the opposite of
 * what "this system or nothing" means. The house appearance is the block's
 * appearance; it is in theme.json and assets/css/blocks.css, and there is
 * nothing to opt into. See docs/adr/0005.
 */
final class CoreStyles {

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_type_metadata', $this->strip_styles( ... ) );
	}

	/**
	 * Drop the `styles` key from a block this design styles itself.
	 *
	 * @param array<string, mixed> $metadata One block's parsed `block.json`.
	 * @return array<string, mixed> The metadata, without core's style variations.
	 */
	public function strip_styles( array $metadata ): array {
		$name = $metadata['name'] ?? null;

		if ( ! is_string( $name ) || ! in_array( $name, Vocabulary::STYLED_BY_THE_HOUSE, true ) ) {
			return $metadata;
		}

		unset( $metadata['styles'] );

		return $metadata;
	}
}
