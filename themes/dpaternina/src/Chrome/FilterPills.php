<?php
/**
 * The category filter, as links rather than tabs.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

/**
 * Turns `core/categories` into the design's `FilterPills`.
 *
 * `design-source/components/FilterPills.dc.html` draws them with
 * `role="tablist"` because the design tool had a click handler and nothing else.
 * Its own note settles the question for us: *"WP note: these are real links to
 * filtered archive URLs, not JS tabs."* So the markup is `core/categories` — a
 * list of anchors to real archives, which work with JavaScript switched off,
 * which is where CLAUDE.md section 1.7 requires them to work — and everything
 * this class adds is the one thing core's block leaves out.
 *
 * That one thing is the leading **All** pill. `wp_list_categories()` can produce
 * it through `show_option_all`, but `core/categories` never passes the argument
 * and offers no filter over its own arguments, so it is spliced in here. Its
 * href is the posts index resolved from Settings to Reading, and it is marked
 * current on exactly the pages where no category is filtering the list.
 *
 * Nothing is added unless the block asks for it by class, so an ordinary
 * `core/categories` block someone drops on a page is left alone.
 */
final class FilterPills {

	/**
	 * The class a `core/categories` block carries to become a pill row.
	 */
	public const PILLS_CLASS = 'dp-filter-pills';

	/**
	 * Constructor.
	 *
	 * @param Destinations $destinations Resolves the posts index.
	 */
	public function __construct( private readonly Destinations $destinations ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/categories', $this->add_all_pill( ... ), 10, 2 );
	}

	/**
	 * Splice the All pill into a pill row.
	 *
	 * @param string               $content The rendered list.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function add_all_pill( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || ! str_contains( ' ' . $class_name . ' ', ' ' . self::PILLS_CLASS . ' ' ) ) {
			return $content;
		}

		$opening = strpos( $content, '>' );

		if ( ! str_starts_with( ltrim( $content ), '<ul' ) || false === $opening ) {
			return $content;
		}

		$classes = is_category() ? 'cat-item dp-pill-all' : 'cat-item dp-pill-all current-cat';
		$current = is_category() ? '' : ' aria-current="page"';

		$pill = sprintf(
			'<li class="%1$s"><a href="%2$s"%3$s>%4$s</a></li>',
			esc_attr( $classes ),
			esc_url( $this->destinations->posts_index() ),
			$current,
			esc_html__( 'All', 'dpaternina' )
		);

		return substr_replace( $content, $pill, $opening + 1, 0 );
	}
}
