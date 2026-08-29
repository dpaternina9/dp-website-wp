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
 *
 * The two static methods below are the second part of it, and they exist because
 * a pattern file cannot include another pattern's markup and a `core/query`
 * cannot be split across two of them. `PostRow`'s list variant and the pager bar
 * are each drawn twice — the blog index and the term archive — with the same
 * geometry and different surroundings: the index takes the design's empty
 * *panel* and its end-of-archive note, the archive takes a one-line empty state
 * and a closing rule. Two patterns, so each says what it is; one definition of
 * the row, so the two cannot drift.
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

	/**
	 * One row of `PostRow`'s list variant, as block markup.
	 *
	 * The date is printed twice on purpose. Above 560px the design puts it in the
	 * left column and the category alone on the right; below it, both share one
	 * mono bar over the title. `@container` cannot move an element, so both
	 * positions are in the markup and the container query shows one of them —
	 * see the `dp-rows` block in `assets/css/components.css`.
	 *
	 * @return string Block markup, ready to be echoed into a pattern file.
	 */
	public static function post_row(): string {
		return '<!-- wp:group {"className":"dp-row","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row"><!-- wp:post-date {"format":"M j, Y","className":"dp-row-date"} /-->

<!-- wp:group {"className":"dp-row-body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row-body"><!-- wp:post-title {"level":3,"isLink":true,"className":"dp-row-title"} /-->

<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":24,"className":"dp-row-excerpt"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-row-aside","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row-aside"><!-- wp:post-terms {"term":"category","className":"dp-row-cat"} /-->

<!-- wp:post-date {"format":"M j, Y","className":"dp-row-date"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';
	}

	/**
	 * The design's pager bar, as block markup.
	 *
	 * Three parts: the mono range on the left, bound to
	 * `DP\Theme\Query\ArchiveFacts`; core's three pagination blocks on the
	 * right, drawn as the design's pills; and `dpaternina/page-state`, which is
	 * what makes the whole bar — border and all — exist only when there is more
	 * than one page. `DP\Theme\Query\Pagination` answers both.
	 *
	 * @return string Block markup, ready to be echoed inside a `core/query`.
	 */
	public static function pager(): string {
		return '<!-- wp:dpaternina/page-state {"state":"paginated","className":"dp-pagination"} -->
<!-- wp:paragraph {"className":"dp-pagination-range","metadata":{"bindings":{"content":{"source":"dpaternina/archive","args":{"key":"range"}}}}} -->
<p class="dp-pagination-range"></p>
<!-- /wp:paragraph -->

<!-- wp:query-pagination {"className":"dp-pagination-steps","layout":{"type":"flex"}} -->
<!-- wp:query-pagination-previous {"label":"Prev"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":"Next"} /-->
<!-- /wp:query-pagination -->
<!-- /wp:dpaternina/page-state -->';
	}
}
