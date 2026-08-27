<?php
/**
 * The pager bar, and the two blocks that only belong on some pages of it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

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
 * **The bar itself only exists when there is more than one page.** `pager.show`
 * is `matching.length > PER_PAGE`, and `pager.atEnd` adds a closing panel on the
 * last page only. Neither is a thing a template can say, and neither is a thing
 * the block editor can know — the canvas has no page number. So a block asks by
 * class and this answers, which is the same shape as every other derived
 * decision in this theme.
 *
 * The two classes are deliberately about the query rather than about the blog:
 * `dp-when-paginated` and `dp-when-last-page` say what they test, so a template
 * that uses one is readable without opening this file.
 */
final class Pagination {

	/**
	 * A block carrying this renders only when the archive has more than one page.
	 */
	public const WHEN_PAGINATED = 'dp-when-paginated';

	/**
	 * A block carrying this renders only on the last page of a paginated archive.
	 */
	public const WHEN_LAST_PAGE = 'dp-when-last-page';

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
		add_filter( 'render_block', $this->hide_when_the_query_says_so( ... ), 10, 2 );
		add_filter( 'render_block_core/query-pagination', $this->keep_the_dead_steps( ... ), 10, 2 );
	}

	/**
	 * Drop a block whose class names a page state this page is not in.
	 *
	 * @param string               $content The rendered block.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function hide_when_the_query_says_so( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || '' === $class_name ) {
			return $content;
		}

		if ( $this->has_class( $class_name, self::WHEN_PAGINATED ) && ! $this->is_paginated() ) {
			return '';
		}

		if ( $this->has_class( $class_name, self::WHEN_LAST_PAGE ) && ! $this->is_last_page() ) {
			return '';
		}

		return $content;
	}

	/**
	 * Put back the step core dropped because it had nowhere to point.
	 *
	 * Core returns the empty string for the whole pagination block when nothing
	 * inside it rendered, so a one-page archive stays free of a stray rule; this
	 * only ever adds a step to a bar that already exists.
	 *
	 * @param string               $content The rendered pagination.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function keep_the_dead_steps( string $content, array $block ): string {
		unset( $block );

		if ( '' === trim( $content ) ) {
			return $content;
		}

		$labels = $this->step_labels( $content );

		if ( ! str_contains( $content, 'wp-block-query-pagination-previous' ) ) {
			$content = $this->insert_step( $content, 'previous', $labels['previous'], true );
		}

		if ( ! str_contains( $content, 'wp-block-query-pagination-next' ) ) {
			$content = $this->insert_step( $content, 'next', $labels['next'], false );
		}

		return $content;
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
	 * The labels the two steps are written with, read off the one that rendered.
	 *
	 * The words belong to the template — `core/query-pagination-previous` takes a
	 * `label` attribute and David can change it — so the missing step borrows the
	 * wording from its opposite number rather than having a second copy of it
	 * here. On page one of a two-page archive NEXT is present, so PREV is drawn
	 * with the same word core would have used for it; when neither rendered
	 * there is no bar to add to and this is never reached.
	 *
	 * @param string $content The rendered pagination.
	 * @return array{previous: string, next: string}
	 */
	private function step_labels( string $content ): array {
		$labels = array(
			'previous' => __( 'Prev', 'dpaternina' ),
			'next'     => __( 'Next', 'dpaternina' ),
		);

		foreach ( array_keys( $labels ) as $step ) {
			if ( preg_match( '~<a[^>]*wp-block-query-pagination-' . $step . '[^>]*>(.*?)</a>~s', $content, $found ) ) {
				$text = trim( wp_strip_all_tags( $found[1] ) );

				if ( '' !== $text ) {
					$labels[ $step ] = $text;
				}
			}
		}

		return $labels;
	}

	/**
	 * Add one inert step at the start or the end of the bar.
	 *
	 * @param string $content The rendered pagination.
	 * @param string $step    `previous` or `next`.
	 * @param string $label   What it says.
	 * @param bool   $first   Whether it goes before everything else.
	 * @return string
	 */
	private function insert_step( string $content, string $step, string $label, bool $first ): string {
		$markup = sprintf(
			'<span class="wp-block-query-pagination-%1$s %2$s" aria-disabled="true">%3$s</span>',
			esc_attr( $step ),
			esc_attr( self::STEP_DISABLED ),
			esc_html( $label )
		);

		if ( $first ) {
			$opening = strpos( $content, '>' );

			return false === $opening ? $content : substr_replace( $content, $markup, $opening + 1, 0 );
		}

		$closing = strrpos( $content, '</' );

		return false === $closing ? $content : substr_replace( $content, $markup, $closing, 0 );
	}

	/**
	 * The main query, when it is an archive of posts.
	 *
	 * @return WP_Query|null
	 */
	private function archive(): ?WP_Query {
		$query = $GLOBALS['wp_query'] ?? null;

		if ( ! $query instanceof WP_Query || ! ( $query->is_home() || $query->is_archive() ) ) {
			return null;
		}

		return $query;
	}

	/**
	 * Whether a class attribute carries one exact class.
	 *
	 * @param string $attribute The class attribute's value.
	 * @param string $wanted    The class to look for.
	 * @return bool
	 */
	private function has_class( string $attribute, string $wanted ): bool {
		$classes = preg_split( '~\s+~', trim( $attribute ) );

		return is_array( $classes ) && in_array( $wanted, $classes, true );
	}
}
