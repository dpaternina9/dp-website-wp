<?php
/**
 * The pattern category this theme's patterns file themselves under.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

use WP_Block_Patterns_Registry;

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
	 * The pattern the Watch page's body starts from.
	 */
	public const WATCH_GEAR = 'dpaternina/watch-gear';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_category( ... ) );

		/*
		 * `dp-core`'s seeder asks what a seeded Watch page's body should be —
		 * digest section 3.6 makes the gear list editor content, and the seed
		 * has to start it from somewhere without the plugin learning this
		 * theme's pattern slugs or markup (CLAUDE.md section 2.1). The same
		 * seam shape as `dp_seed_chrome_links` and `dp_brand_logo_path`: the
		 * plugin asks, the theme answers, and with the theme switched off
		 * nothing answers and the seeder keeps its own placeholder.
		 */
		add_filter( 'dp_seed_watch_body', $this->watch_body( ... ) );
	}

	/**
	 * Answer the seeder with the gear pattern's own markup.
	 *
	 * Read from the registry rather than from the file so there is exactly one
	 * definition of the gear list, and a seeded body is byte-for-byte what
	 * inserting the pattern in the editor would have produced.
	 *
	 * @param mixed $body Whatever an earlier filter decided — the seeder's own placeholder.
	 * @return mixed The pattern's content, or the placeholder when the pattern is not registered.
	 */
	public function watch_body( mixed $body ): mixed {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( self::WATCH_GEAR );
		$content = is_array( $pattern ) ? ( $pattern['content'] ?? null ) : null;

		return is_string( $content ) && '' !== trim( $content ) ? $content : $body;
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
