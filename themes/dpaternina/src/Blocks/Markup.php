<?php
/**
 * The two attributes the house style adds to core's rendered markup.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use WP_HTML_Tag_Processor;

/**
 * Adjusts core block output where CSS alone would cost something.
 *
 * Both filters are presentational and both are additive: they set an attribute
 * on markup core has already produced. Nothing here changes what is saved, so
 * switching themes takes these back out and leaves the post untouched.
 */
final class Markup {

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/list', $this->keep_list_semantics( ... ) );
		add_filter( 'render_block_core/code', $this->force_dark( ... ) );
	}

	/**
	 * Put `role="list"` back on a list whose markers this design draws itself.
	 *
	 * The design renders its own markers — an em dash for `ul`, a zero-padded
	 * index for `ol` — in a 28px grid column (digest §5.1). That needs
	 * `list-style: none`, and Safari with VoiceOver stops announcing a list as a
	 * list the moment its list-style is removed. `role="list"` restores what the
	 * CSS took away, which is the accepted fix and costs one attribute.
	 *
	 * @param string $block_content The block's rendered HTML.
	 * @return string
	 */
	public function keep_list_semantics( string $block_content ): string {
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		if ( ! in_array( $processor->get_tag(), array( 'UL', 'OL' ), true ) ) {
			return $block_content;
		}

		$processor->set_attribute( 'role', 'list' );

		return $processor->get_updated_html();
	}

	/**
	 * Mark the code block as a forced-dark island.
	 *
	 * `.dp-dark` re-declares the semantic tokens for its subtree, and the design
	 * puts the code block inside it so the block stays dark even on a light
	 * surface. Light mode is ruled out today (CLAUDE.md §5), so this changes
	 * nothing visible — it is the design's structure, carried, not a toggle.
	 *
	 * @param string $block_content The block's rendered HTML.
	 * @return string
	 */
	public function force_dark( string $block_content ): string {
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'tag_name' => 'PRE' ) ) ) {
			return $block_content;
		}

		$processor->add_class( 'dp-dark' );

		return $processor->get_updated_html();
	}
}
