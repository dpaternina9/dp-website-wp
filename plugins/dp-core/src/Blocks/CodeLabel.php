<?php
/**
 * The label a code block carries.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

use WP_HTML_Tag_Processor;

/**
 * Puts the `dpLabel` attribute onto the rendered code block as a data attribute.
 *
 * The attribute is declared in JavaScript (src/Blocks/js/house-style/code-label.js)
 * with no `source`, so WordPress keeps it in the block's HTML comment and out of
 * the block's markup. That is deliberate: core's own save() output is left byte
 * for byte intact, so no existing post can ever be invalidated by this plugin
 * being deactivated, updated, or absent.
 *
 * The consequence is that the label is not in the saved HTML either, so it has
 * to be put back at render time. That is all this class does. The bar it is
 * drawn in belongs to the theme, which falls back to "SHELL" when no attribute
 * is present at all.
 */
final class CodeLabel {

	/**
	 * The label used when a code block has never been given one.
	 *
	 * Kept in step with DEFAULT_CODE_LABEL in code-label.js and with the
	 * fallback in the theme's assets/css/blocks.css.
	 *
	 * @var string
	 */
	public const DEFAULT_LABEL = 'SHELL';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/code', $this->add_label( ... ), 10, 2 );
	}

	/**
	 * Add `data-dp-label` to the block's `<pre>`.
	 *
	 * An empty label is honoured rather than replaced: clearing the field is how
	 * David turns the bar off, and the theme hides it for an empty attribute.
	 *
	 * @param string               $block_content The block's rendered HTML.
	 * @param array<string, mixed> $block         The parsed block.
	 * @return string The HTML, with the label attribute set.
	 */
	public function add_label( string $block_content, array $block ): string {
		$attributes = $block['attrs'] ?? array();

		if ( ! is_array( $attributes ) ) {
			return $block_content;
		}

		$label = $attributes['dpLabel'] ?? self::DEFAULT_LABEL;

		if ( ! is_string( $label ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'tag_name' => 'PRE' ) ) ) {
			return $block_content;
		}

		$processor->set_attribute( 'data-dp-label', trim( $label ) );

		return $processor->get_updated_html();
	}
}
