<?php
/**
 * The pager bar's dead step, and the state its containers ask about.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

use WP_Block;
use WP_Query;

/**
 * Two corrections to `core/query-pagination`, both of them the design's.
 *
 * **A step that cannot be taken is drawn, not dropped.** The design's pager
 * computes `stepStyle(enabled)` and renders PREV on page one anyway, dimmed to
 * `opacity: 0.45` with `cursor: default` — the same treatment ADR-0008 gives a
 * destination David has not created. Core renders nothing at all for a step
 * that has nowhere to go, so the row jumps sideways between page one and page
 * two. This puts the missing step back, as a `<span>` rather than an `<a>`: no
 * href, so it is not focusable and cannot reach a page that does not exist, and
 * `aria-disabled` so it announces as an unavailable control rather than as a
 * stray word.
 *
 * It is done by filtering the **step's own block**, not the bar around it. The
 * first version filtered `core/query-pagination` and spliced a `<span>` into the
 * rendered `<nav>` — reading the label back out with a regular expression over
 * that markup, and guessing the position from the first `>` and the last `</`.
 * Filtering `core/query-pagination-previous` and `-next` instead means the
 * label is read from the attribute the template set, the step lands where the
 * template put it rather than at whichever end of the bar, and no HTML is
 * parsed by anything. `render_block_{$name}` runs whether or not the callback
 * produced anything, so the empty string core returns for a step it cannot
 * offer is exactly the hook this needs.
 *
 * **The bar itself only exists when there is more than one page.** `pager.show`
 * is `matching.length > PER_PAGE`, and `pager.atEnd` adds a closing panel on the
 * last page only. Neither is a thing a template can say, and neither is a thing
 * the block editor can know — the canvas has no page number. Both questions are
 * asked of this class, by `DP\Theme\Blocks\PageState`, which is the block a
 * template wraps those containers in. They used to be asked by a bare CSS class
 * on a `core/group`, which announced nothing; ADR-0018 rule 2 is why they are
 * not any more.
 */
final class Pagination {

	/**
	 * The class the inert step carries, so the stylesheet can dim it.
	 */
	public const STEP_DISABLED = 'dp-page-step-disabled';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/query-pagination-previous', $this->keep_the_previous_step( ... ), 10, 3 );
		add_filter( 'render_block_core/query-pagination-next', $this->keep_the_next_step( ... ), 10, 3 );
	}

	/**
	 * Draw PREV on page one, where core draws nothing.
	 *
	 * @param string               $content  The step's rendered HTML.
	 * @param array<string, mixed> $block    The parsed block.
	 * @param WP_Block|null        $instance The block instance.
	 * @return string
	 */
	public function keep_the_previous_step( string $content, array $block, ?WP_Block $instance = null ): string {
		return $this->keep_the_dead_step( $content, $block, $instance, 'previous' );
	}

	/**
	 * Draw NEXT on the last page, where core draws nothing.
	 *
	 * @param string               $content  The step's rendered HTML.
	 * @param array<string, mixed> $block    The parsed block.
	 * @param WP_Block|null        $instance The block instance.
	 * @return string
	 */
	public function keep_the_next_step( string $content, array $block, ?WP_Block $instance = null ): string {
		return $this->keep_the_dead_step( $content, $block, $instance, 'next' );
	}

	/**
	 * Put back the step core dropped because it had nowhere to point.
	 *
	 * Only for a pagination inheriting the main query, which is what both of
	 * this theme's pagers do. A `core/query` with a query of its own has a page
	 * count nothing here has computed, and inventing a step for it would be
	 * drawing a control whose state we do not know.
	 *
	 * @param string               $content  The step's rendered HTML — the empty string when it has nowhere to go.
	 * @param array<string, mixed> $block    The parsed block.
	 * @param WP_Block|null        $instance The block instance, which carries the query context.
	 * @param string               $step     `previous` or `next`.
	 * @return string
	 */
	private function keep_the_dead_step( string $content, array $block, ?WP_Block $instance, string $step ): string {
		if ( '' !== trim( $content ) || ! $this->inherits_the_main_query( $instance ) || ! $this->is_paginated() ) {
			return $content;
		}

		return sprintf(
			'<span class="wp-block-query-pagination-%1$s %2$s" aria-disabled="true">%3$s</span>',
			esc_attr( $step ),
			esc_attr( self::STEP_DISABLED ),
			esc_html( $this->step_label( $block, $step ) )
		);
	}

	/**
	 * Whether the archive being viewed runs to more than one page.
	 *
	 * @return bool
	 */
	public function is_paginated(): bool {
		$query = $this->archive();

		return null !== $query && $query->max_num_pages > 1;
	}

	/**
	 * Whether this is the last page of a paginated archive.
	 *
	 * @return bool
	 */
	public function is_last_page(): bool {
		$query = $this->archive();

		if ( null === $query || $query->max_num_pages <= 1 ) {
			return false;
		}

		$paged = $query->get( 'paged' );

		return max( 1, is_numeric( $paged ) ? (int) $paged : 0 ) >= (int) $query->max_num_pages;
	}

	/**
	 * The word the missing step is drawn with.
	 *
	 * The words belong to the template — `core/query-pagination-previous` takes
	 * a `label` attribute and David can change it — so this reads the same
	 * attribute core reads, off the same block. The fallbacks are core's own
	 * defaults, said again in this theme's textdomain rather than borrowed from
	 * WordPress's.
	 *
	 * @param array<string, mixed> $block The parsed block.
	 * @param string               $step  `previous` or `next`.
	 * @return string
	 */
	private function step_label( array $block, string $step ): string {
		$attributes = $block['attrs'] ?? array();
		$label      = is_array( $attributes ) ? ( $attributes['label'] ?? '' ) : '';

		if ( is_string( $label ) && '' !== trim( $label ) ) {
			return $label;
		}

		return 'previous' === $step
			? __( 'Previous Page', 'dpaternina' )
			: __( 'Next Page', 'dpaternina' );
	}

	/**
	 * Whether this pagination belongs to a query that inherits the main one.
	 *
	 * @param WP_Block|null $instance The block instance.
	 * @return bool
	 */
	private function inherits_the_main_query( ?WP_Block $instance ): bool {
		if ( ! $instance instanceof WP_Block ) {
			return false;
		}

		$query = $instance->context['query'] ?? null;

		return is_array( $query ) && true === ( $query['inherit'] ?? false );
	}

	/**
	 * The main query, when it is a list of posts.
	 *
	 * A search counts, for the same reason it counts in
	 * `DP\Theme\Query\ArchiveFacts`: `search.html` draws the same pager bar, and
	 * "is there more than one page of this" is as true a question about a set of
	 * search results as about a term archive. Without it a search running to
	 * three pages drew no pager at all, because the bar asks this first.
	 *
	 * @return WP_Query|null
	 */
	private function archive(): ?WP_Query {
		$query = $GLOBALS['wp_query'] ?? null;

		if ( ! $query instanceof WP_Query || ! ( $query->is_home() || $query->is_archive() || $query->is_search() ) ) {
			return null;
		}

		return $query;
	}
}
