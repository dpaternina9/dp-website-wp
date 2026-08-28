<?php
/**
 * Every series, on one page.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Content\SeriesParts;
use DP\Theme\Query\ArchiveFacts;
use WP_Taxonomy;
use WP_Term;

/**
 * The listing behind the `dp-series` template, which is a view nothing had.
 *
 * `register_taxonomy()` creates `/series/{term}/` and nothing else. WordPress
 * has no term-index route for a flat taxonomy and no template in the hierarchy
 * for one, so `/series/` was a 404 on a site whose footer, whose posts and whose
 * own chrome all talk about series in the plural.
 *
 * The answer is the shape CLAUDE.md §5.1 already prescribes for a view that is
 * not in the hierarchy: a custom template David assigns to a page he creates and
 * slugs himself, and a block that does the work. **No rewrite is registered** —
 * `dp_series`' own remains the only page-facing route in the project — and the
 * `dp-` prefix on the template is load-bearing, because a file named
 * `page-series.html` would be applied by the hierarchy to any page slugged
 * `series`, which is the coupling that section exists to prevent.
 *
 * It is a block rather than markup in the template for ADR-0018's second rule:
 * the list cannot be typed, so the thing that computes it announces itself in
 * the inserter under its own title. It reads no queried object and takes no
 * attributes, so the block-renderer route the site editor previews through
 * returns exactly what the page returns — the third rule, satisfied by having
 * nothing to diverge.
 *
 * Three decisions are worth stating rather than reading back out of the code.
 *
 * **A series with no published part is not listed.** The row would link to an
 * archive whose "Start with these" list is empty and whose only content is a
 * "Still to come" column. A series nobody can read yet is not something to send
 * a reader to.
 *
 * **Order is the number of published parts, descending, then the term ID
 * ascending.** Deterministic, and the same rule the deleted
 * `Destinations::featured_series()` used to nominate a series for the front
 * page: the longest series is the one most worth starting.
 *
 * **The row's three strings all come from somewhere that already owns them.**
 * The name and the link are the term's; the deck is the term's `description`,
 * which is where ADR-0017 put a series' deck; and the parts line is
 * `ArchiveFacts::parts_line()` — the same sentence, from the same file, that the
 * series archive prints beside its own badge. Injecting that class is what keeps
 * there being one copy of it.
 *
 * With `dp-core` deactivated there is no `SeriesParts` to ask, so the block
 * renders nothing rather than fatalling — the same promise every other seam in
 * this theme makes about the plugin.
 */
final class SeriesIndex {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/series-index';

	/**
	 * The list's class, which is the one the series archive's parts list carries.
	 *
	 * Nothing here invents a class. `dp-parts`, `dp-part-row`, `dp-part-index`,
	 * `dp-part-body`, `dp-part-title` and `dp-part-note` are the vocabulary
	 * `taxonomy-dp_series.html` already draws a list of parts with, and a list of
	 * series is the same three-column row with a different thing in each column.
	 * `dp-series-written` is likewise the class that already styles this exact
	 * sentence in the series hero.
	 */
	public const LIST_CLASS = 'dp-parts';

	/**
	 * Constructor.
	 *
	 * @param ArchiveFacts $facts Owns the "N parts up · M drafted" sentence.
	 */
	public function __construct( private readonly ArchiveFacts $facts ) {}

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
			get_theme_file_path( 'blocks/series-index' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the list.
	 *
	 * A site with no readable series renders nothing at all — not an empty state
	 * and not a placeholder. The page keeps its heading and its deck, which is
	 * the whole of what there is to say when there is nothing to list.
	 *
	 * @return string
	 */
	public function render(): string {
		$rows = '';

		foreach ( $this->series() as $term ) {
			$rows .= $this->row( $term );
		}

		if ( '' === $rows ) {
			return '';
		}

		return sprintf(
			'<ul %1$s>%2$s</ul>',
			get_block_wrapper_attributes( array( 'class' => self::LIST_CLASS ) ),
			$rows
		);
	}

	/**
	 * One series.
	 *
	 * The deck goes through `wp_kses_post()` rather than `esc_html()` for the
	 * reason `ArchiveFacts::deck()` gives about the same field: a term
	 * description may carry limited HTML, and the archive's own hero renders it
	 * that way. It is a `div` rather than a `p` so that a description which does
	 * carry a block-level tag does not end up nested inside a paragraph; the
	 * stylesheet's `.dp-part-note` and `.dp-part-note p` cover both shapes.
	 *
	 * @param WP_Term $term The series.
	 * @return string Empty when the term has no archive to link to.
	 */
	private function row( WP_Term $term ): string {
		$link = get_term_link( $term );

		if ( ! is_string( $link ) ) {
			return '';
		}

		$line = $this->facts->parts_line( $term->term_id );
		$deck = trim( $term->description );

		return sprintf(
			'<li><div class="dp-part-row">'
				. '<div class="dp-part-index">%1$s</div>'
				. '<div class="dp-part-body">'
					. '<h3 class="dp-part-title"><a href="%2$s">%3$s</a></h3>%4$s'
				. '</div>'
			. '</div></li>',
			null === $line ? '' : '<p class="dp-series-written">' . esc_html( $line ) . '</p>',
			esc_url( $link ),
			esc_html( $term->name ),
			'' === $deck ? '' : '<div class="dp-part-note">' . wp_kses_post( $deck ) . '</div>'
		);
	}

	/**
	 * Every series that has something published in it, longest first.
	 *
	 * The tiebreak is written out rather than left to PHP 8's stable sort,
	 * because "lowest term ID wins a tie" is a decision and not an accident of
	 * the order `get_terms()` happened to return.
	 *
	 * @return list<WP_Term>
	 */
	private function series(): array {
		$terms = $this->terms();

		if ( array() === $terms || ! class_exists( SeriesParts::class ) ) {
			return array();
		}

		$parts     = new SeriesParts();
		$published = array();
		$by_id     = array();

		foreach ( $terms as $term ) {
			$count = count( $parts->published( $term->term_id ) );

			if ( 0 === $count ) {
				continue;
			}

			$published[ $term->term_id ] = $count;
			$by_id[ $term->term_id ]     = $term;
		}

		$counts = $published;

		uksort(
			$published,
			static function ( int $left, int $right ) use ( $counts ): int {
				$by_length = $counts[ $right ] <=> $counts[ $left ];

				return 0 !== $by_length ? $by_length : $left <=> $right;
			}
		);

		$ordered = array();

		foreach ( array_keys( $published ) as $term_id ) {
			$ordered[] = $by_id[ $term_id ];
		}

		return $ordered;
	}

	/**
	 * Every term in every taxonomy whose archive is a reading order.
	 *
	 * The taxonomy is described rather than named, which is the rule the rest of
	 * the theme follows for it: `dp_series`' slug is `dp-core`'s and the theme
	 * never repeats it. `WP_Term_Query` is asked for the terms in term-ID order
	 * so the tiebreak above has a defined input.
	 *
	 * @return list<WP_Term>
	 */
	private function terms(): array {
		$taxonomies = $this->taxonomies();

		if ( array() === $taxonomies ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( $terms, static fn( mixed $term ): bool => $term instanceof WP_Term ) );
	}

	/**
	 * The taxonomies on `post` that order their posts.
	 *
	 * The same description `DP\Theme\Query\ArchiveFacts`,
	 * `DP\Theme\Query\QueryLoops` and `DP\Theme\Blocks\SeriesPlanned` each apply
	 * to a single term, asked here of the whole registry instead — because an
	 * index has no queried term to start from.
	 *
	 * @return list<string>
	 */
	private function taxonomies(): array {
		$found = array();

		foreach ( get_object_taxonomies( 'post', 'objects' ) as $taxonomy ) {
			if ( $taxonomy instanceof WP_Taxonomy && ! $taxonomy->_builtin && ! $taxonomy->hierarchical ) {
				$found[] = $taxonomy->name;
			}
		}

		return $found;
	}
}
