<?php
/**
 * The category filter, as links rather than tabs.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Theme\Chrome\Destinations;
use WP_Term;

/**
 * The design's pill row, rendered whole instead of edited into core's.
 *
 * `design-source/components/FilterPills.dc.html` draws the pills with
 * `role="tablist"` because the design tool had a click handler and nothing else.
 * Its own note settles that for us: *"WP note: these are real links to filtered
 * archive URLs, not JS tabs."* So every pill below is an `<a>` with an `href` to
 * a real archive, and the row works with scripting switched off, which is where
 * CLAUDE.md §1.7 requires it to work.
 *
 * What changed in this phase is who writes the markup. This used to be two
 * filters over `core/categories`' rendered output — one splicing an extra `<li>`
 * in, one moving core's `(3)` text node inside the anchor with a regular
 * expression. Both are the shape ADR-0018 rules out. The trigger was a CSS
 * class, which announces nothing; the editor drew N pills where the front end
 * drew N + 1; and the second was anchored on markup core is free to change, so
 * the day core wraps its count in a `<span>` of its own the row silently loses
 * its colour. A block that renders the whole row has neither problem: nothing
 * parses HTML, so nothing can misparse it, and the editor draws exactly what the
 * page draws.
 *
 * **One block, two variations, not two blocks.** The design has two pill rows —
 * the blog index's filter and the archive's "Other categories" band — and they
 * differ in three coordinated ways: the leading All pill, the count beside each
 * name, and the class the stylesheet hangs on. Three independent booleans would
 * describe eight rows, six of which the design does not draw and none of which
 * the stylesheet is written for; two block types would be two copies of one
 * query and one loop. So there is one attribute naming which of the design's two
 * components this is, and `block.json` offers each of them in the inserter as a
 * variation with its own title. The class is chosen here rather than typed as a
 * `className` for the reason `DerivedLink` gives: `ServerSideRender` wraps the
 * canvas preview in an element that already carries the block's `className`, so
 * a design class set in the template lands twice in the editor and once on the
 * page.
 *
 * The taxonomy is `category` and is named rather than described, unlike the
 * series lookups elsewhere in the theme. `category` is one of WordPress's own,
 * not a slug `dp-core` invented, and the design draws exactly one taxonomy in
 * these two places.
 */
final class FilterPills {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/filter-pills';

	/**
	 * The blog index's filter row: an All pill in front, no counts.
	 */
	public const VARIANT_FILTER = 'filter';

	/**
	 * The archive's "Other categories" band: every category with its count.
	 */
	public const VARIANT_BAND = 'band';

	/**
	 * The class the leading All pill carries.
	 */
	public const ALL_CLASS = 'dp-pill-all';

	/**
	 * The element the count is given, so it can be muted apart from the name.
	 */
	public const COUNT_CLASS = 'dp-cat-count';

	/**
	 * The design class each variation hangs its stylesheet on.
	 *
	 * @var array<string, string>
	 */
	private const DESIGN_CLASS = array(
		self::VARIANT_FILTER => 'dp-filter-pills',
		self::VARIANT_BAND   => 'dp-category-pills',
	);

	/**
	 * The taxonomy the design filters by.
	 */
	private const TAXONOMY = 'category';

	/**
	 * Constructor.
	 *
	 * @param Destinations $destinations Resolves the posts index, which is where the All pill points.
	 */
	public function __construct( private readonly Destinations $destinations ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_block( ... ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type(
			get_theme_file_path( 'blocks/filter-pills' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the row.
	 *
	 * A site with no categories renders nothing at all, including in the
	 * variation that leads with All: a filter offering one choice is not a
	 * filter, and the design draws no empty row.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		$terms = $this->terms();

		if ( array() === $terms ) {
			return '';
		}

		$variant = $this->variant( $attributes );
		$current = $this->current_term_id();
		$items   = self::VARIANT_FILTER === $variant ? $this->all_pill( 0 === $current ) : '';

		foreach ( $terms as $term ) {
			$items .= $this->pill( $term, $term->term_id === $current, self::VARIANT_BAND === $variant );
		}

		return sprintf(
			'<ul %1$s>%2$s</ul>',
			get_block_wrapper_attributes( array( 'class' => self::DESIGN_CLASS[ $variant ] ) ),
			$items
		);
	}

	/**
	 * Which of the design's two rows this is.
	 *
	 * `block.json` declares the attribute with an `enum`, so the REST route the
	 * editor previews through rejects anything else before it reaches here. This
	 * is the same check for the front end, where saved markup is parsed rather
	 * than validated.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	private function variant( array $attributes ): string {
		$variant = $attributes['variant'] ?? self::VARIANT_FILTER;

		return is_string( $variant ) && isset( self::DESIGN_CLASS[ $variant ] ) ? $variant : self::VARIANT_FILTER;
	}

	/**
	 * The categories with something in them, in the order core lists them.
	 *
	 * @return list<WP_Term>
	 */
	private function terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( $terms, static fn( mixed $term ): bool => $term instanceof WP_Term ) );
	}

	/**
	 * The category filtering the list, if one is.
	 *
	 * @return int Zero on any page that is not a category archive.
	 */
	private function current_term_id(): int {
		if ( ! is_category() ) {
			return 0;
		}

		$queried = get_queried_object();

		return $queried instanceof WP_Term && self::TAXONOMY === $queried->taxonomy ? $queried->term_id : 0;
	}

	/**
	 * The leading All pill.
	 *
	 * Its href is the posts index resolved from Settings → Reading, which is
	 * where WordPress records which page David chose and the one answer that is
	 * right whatever he called it.
	 *
	 * @param bool $current Whether nothing is filtering the list.
	 * @return string
	 */
	private function all_pill( bool $current ): string {
		return sprintf(
			'<li class="cat-item %1$s%2$s"><a href="%3$s"%4$s>%5$s</a></li>',
			esc_attr( self::ALL_CLASS ),
			$current ? ' current-cat' : '',
			esc_url( $this->destinations->posts_index() ),
			$current ? ' aria-current="page"' : '',
			esc_html__( 'All', 'dpaternina' )
		);
	}

	/**
	 * One category.
	 *
	 * `cat-item` and `cat-item-{id}` are the classes `wp_list_categories` wrote,
	 * kept because the stylesheet, the e2e suite and every diagnosis already use
	 * them. `current-cat` is the same: the band's stylesheet hides the archive
	 * you are already on with it, and `aria-current` says the same thing to a
	 * screen reader.
	 *
	 * The count is an element rather than a text node — which is the whole
	 * reason the old code reached for a regular expression — so a category
	 * called `Tools (beta)` is just a string that gets escaped and printed.
	 *
	 * @param WP_Term $term       The category.
	 * @param bool    $current    Whether this is the archive being read.
	 * @param bool    $with_count Whether to print how many posts are filed under it.
	 * @return string
	 */
	private function pill( WP_Term $term, bool $current, bool $with_count ): string {
		$link = get_term_link( $term );

		if ( ! is_string( $link ) ) {
			return '';
		}

		$count = $with_count
			? sprintf(
				'<span class="%1$s">%2$s</span>',
				esc_attr( self::COUNT_CLASS ),
				esc_html( number_format_i18n( $term->count ) )
			)
			: '';

		return sprintf(
			'<li class="cat-item cat-item-%1$d%2$s"><a href="%3$s"%4$s>%5$s%6$s</a></li>',
			$term->term_id,
			$current ? ' current-cat' : '',
			esc_url( $link ),
			$current ? ' aria-current="page"' : '',
			esc_html( $term->name ),
			$count
		);
	}
}
