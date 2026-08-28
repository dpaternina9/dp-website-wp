<?php
/**
 * The counts an archive prints about itself.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

use DP\Core\Content\SeriesParts;
use WP_Query;
use WP_Term;

/**
 * A block bindings source for the three strings the design computes from a query.
 *
 * The design writes all three in its own script block and none of them is a
 * field on anything:
 *
 *     pager.range    (start + 1) + '–' + (start + listed.length) + ' OF ' +
 *                    matching.length + (cat === 'ALL' ? ' POSTS' : ' IN ' + cat)
 *     archiveCount   archived.length + (archived.length === 1 ? ' POST' : ' POSTS')
 *     seriesWritten  seriesOut.length + ' PARTS UP · ' + seriesPlanned.length + ' DRAFTED'
 *
 * A template cannot carry a count and core has no block that prints one, so this
 * is the mechanism `SiteFacts` already established for the footer's year: an
 * allowlisted bindings source, one key per fact, and `null` for anything else —
 * which leaves the bound block's own content in place rather than blanking it.
 *
 * **It reads the main query, never a loop's.** Every place the design prints one
 * of these, the number it means is the archive's: "1–10 OF 24 POSTS" is about
 * the page you are on, not about the block the paragraph happens to sit inside.
 * Both templates that use it run their list from the inherited main query, so
 * there is no second query for the two to disagree about.
 *
 * The strings are assembled with the copy in a `text` argument wherever the
 * design puts copy around the number, for the same reason `SiteFacts` does it:
 * the words stay in the template, where they stay translatable and David can
 * change them, and this file only ever supplies the number.
 */
final class ArchiveFacts {

	/**
	 * The bindings source name.
	 */
	public const SOURCE = 'dpaternina/archive';

	/**
	 * Every key this source will answer.
	 *
	 * @var list<string>
	 */
	private const KEYS = array( 'range', 'count', 'series-written', 'deck' );

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_source( ... ) );
	}

	/**
	 * Register the bindings source.
	 *
	 * @return void
	 */
	public function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Archive facts', 'dpaternina' ),
				'get_value_callback' => $this->value( ... ),
			)
		);
	}

	/**
	 * Resolve one bound value.
	 *
	 * @param array<string, mixed> $arguments The binding's arguments; `key` selects the fact.
	 * @param mixed                $block     The block being rendered. Unused.
	 * @return string|null Null leaves the block's own content in place.
	 */
	public function value( array $arguments, mixed $block = null ): ?string {
		unset( $block );

		$key = isset( $arguments['key'] ) && is_string( $arguments['key'] ) ? $arguments['key'] : '';

		if ( ! in_array( $key, self::KEYS, true ) ) {
			return null;
		}

		$fact = match ( $key ) {
			'range'          => $this->range(),
			'count'          => $this->count(),
			'deck'           => $this->deck(),
			default          => $this->series_written(),
		};

		if ( null === $fact ) {
			return null;
		}

		$template = isset( $arguments['text'] ) && is_string( $arguments['text'] ) ? $arguments['text'] : '';

		if ( '' === $template || 1 !== substr_count( $template, '%s' ) ) {
			return $fact;
		}

		return sprintf( $template, $fact );
	}

	/**
	 * "1–10 OF 24 POSTS", or "1–10 OF 12 IN DEV" on a term archive.
	 *
	 * @return string|null Null when there is no archive to describe.
	 */
	public function range(): ?string {
		$query = $this->archive();

		if ( null === $query ) {
			return null;
		}

		$per_page = $this->number( $query->get( 'posts_per_page' ) );
		$page     = max( 1, $this->number( $query->get( 'paged' ) ) );
		$first    = $per_page > 0 ? ( ( $page - 1 ) * $per_page ) + 1 : 1;
		$shown    = count( $query->posts );

		if ( 0 === $shown ) {
			return null;
		}

		$span = sprintf(
			/* translators: 1: first post shown on this page, 2: last post shown, 3: how many there are in total. */
			__( '%1$s–%2$s of %3$s', 'dpaternina' ),
			number_format_i18n( $first ),
			number_format_i18n( $first + $shown - 1 ),
			number_format_i18n( $query->found_posts )
		);

		$term = $this->term();

		if ( null === $term ) {
			/* translators: %s: a range like "1–10 of 24". */
			return sprintf( __( '%s posts', 'dpaternina' ), $span );
		}

		/* translators: 1: a range like "1–10 of 12", 2: the name of the term being browsed. */
		return sprintf( __( '%1$s in %2$s', 'dpaternina' ), $span, $term->name );
	}

	/**
	 * "24 POSTS", the whole archive rather than this page of it.
	 *
	 * @return string|null Null when nothing is being archived.
	 */
	public function count(): ?string {
		$query = $this->archive();

		if ( null === $query ) {
			return null;
		}

		$total = (int) $query->found_posts;

		return sprintf(
			/* translators: %s: how many posts the archive holds. */
			_n( '%s post', '%s posts', $total, 'dpaternina' ),
			number_format_i18n( $total )
		);
	}

	/**
	 * The standfirst under a series title, which is the term's description.
	 *
	 * The design calls it a deck. The field it lives in is the term's own
	 * `description`, so this returns that and nothing else. It stays a binding
	 * rather than becoming `core/term-description` because the design's hero is
	 * one paragraph with one class, and that block draws a wrapping `div` and
	 * runs the description through `the_content`.
	 *
	 * The value is not escaped here. It is bound into a `core/paragraph`, and
	 * `WP_Block::replace_html()` passes a rich-text binding through
	 * `wp_kses_post()` before it reaches the page — which is the escaping a
	 * description needs, since unlike the meta field it replaced it may carry
	 * limited HTML.
	 *
	 * @return string|null Null when the term has no description, or when what is
	 *                     being viewed is not a term.
	 */
	public function deck(): ?string {
		$term = $this->term();

		if ( null === $term ) {
			return null;
		}

		$deck = trim( $term->description );

		return '' === $deck ? null : $deck;
	}

	/**
	 * "3 PARTS UP · 4 DRAFTED", beside the SERIES badge on a series archive.
	 *
	 * With `dp-core` deactivated there is no `SeriesParts` to ask and the line
	 * renders as whatever the template typed, which is the same promise every
	 * other seam in this theme makes about the plugin.
	 *
	 * @return string|null Null when what is being viewed is not a series.
	 */
	public function series_written(): ?string {
		$term = $this->term();

		if ( null === $term || ! $this->orders_its_posts( $term ) ) {
			return null;
		}

		return $this->parts_line( $term->term_id );
	}

	/**
	 * The same sentence about a series named rather than queried.
	 *
	 * `DP\Theme\Blocks\SeriesIndex` prints one of these per row and this is the
	 * only place the string is written. Keeping it here rather than copying it
	 * there is not tidiness: two copies of a translatable string are two entries
	 * in the `.pot` file, two things to keep in step, and two chances for the
	 * index and the archive to describe the same series differently.
	 *
	 * @param int $term_id A term in a taxonomy whose archive is a reading order.
	 * @return string|null Null when `dp-core` is not there to count with.
	 */
	public function parts_line( int $term_id ): ?string {
		if ( $term_id <= 0 || ! class_exists( SeriesParts::class ) ) {
			return null;
		}

		$parts = new SeriesParts();

		return sprintf(
			/* translators: 1: how many parts are published, 2: how many are drafted. */
			__( '%1$s parts up · %2$s drafted', 'dpaternina' ),
			number_format_i18n( count( $parts->published( $term_id ) ) ),
			number_format_i18n( count( $parts->planned( $term_id ) ) )
		);
	}

	/**
	 * A query variable as a whole number, whatever WordPress had in it.
	 *
	 * @param mixed $value The variable.
	 * @return int
	 */
	private function number( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
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
	 * The term being browsed, when one is.
	 *
	 * @return WP_Term|null
	 */
	private function term(): ?WP_Term {
		$object = get_queried_object();

		return $object instanceof WP_Term ? $object : null;
	}

	/**
	 * Whether a term belongs to a taxonomy whose archive is a reading order.
	 *
	 * The same description `DP\Theme\Query\QueryLoops` and
	 * `DP\Theme\Blocks\SeriesPlanned` both use, and for the same reason: the
	 * theme never repeats a taxonomy slug that `dp-core` owns.
	 *
	 * @param WP_Term $term The queried term.
	 * @return bool
	 */
	private function orders_its_posts( WP_Term $term ): bool {
		$taxonomy = get_taxonomy( $term->taxonomy );

		return false !== $taxonomy
			&& in_array( 'post', (array) $taxonomy->object_type, true )
			&& ! $taxonomy->_builtin
			&& ! $taxonomy->hierarchical;
	}
}
