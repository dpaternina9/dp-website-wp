<?php
/**
 * Reading a series' parts.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

use WP_Post;
use WP_Query;
use WP_Term;

/**
 * The two queries the series archive runs against one term, and the numbering.
 *
 * `published()` returns post IDs, because published posts have permalinks and
 * the template needs them. `planned()` returns `PlannedPart` objects, which have
 * no ID at all — see that class for why.
 *
 * **Order is the publish date, ascending, and nothing else.** Plan section 3.1
 * originally chose `menu_order`, on the reasoning that a planned part becoming a
 * published one should keep its place. That reasoning survives; the mechanism did
 * not. `post` does not declare `page-attributes`, so the Order field is not on
 * the post editor at all and `menu_order` is zero on every post David will ever
 * write — which made the tiebreak the whole sort. Oldest first is what the
 * design's series page says out loud ("newest last"), it is what a reader means
 * by part one, and it is a field the editor actually has.
 *
 * **The part number is the position in that list.** It is not stored anywhere.
 * `part_of()` answers "what part is this post" by finding the index of its ID in
 * `published()`, which means the numbers cannot drift from the order the page
 * draws, and a post that moves takes its number with it. The list is memoised
 * per term for the length of the request, so a template that asks on every row
 * of an archive still runs one query.
 */
final class SeriesParts {

	/**
	 * The object-cache group the ordered ID lists live in.
	 */
	private const CACHE_GROUP = 'dp_series_parts';

	/**
	 * Constructor.
	 *
	 * @param int $limit The most parts either query will return. A series longer than this
	 *                   is a series that wants splitting, not a query that wants unbounding.
	 */
	public function __construct( private readonly int $limit = 50 ) {}

	/**
	 * The published parts of a series, oldest part first.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<int> Post IDs, in reading order.
	 */
	public function published( int $term_id ): array {
		return $this->ordered( $term_id );
	}

	/**
	 * The planned parts of a series, in the order they will be written.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<PlannedPart>
	 */
	public function planned( int $term_id ): array {
		$parts = array();

		foreach ( $this->ids( $term_id, 'draft' ) as $post_id ) {
			$parts[] = new PlannedPart(
				title: get_the_title( $post_id ),
				note: $this->excerpt( $post_id )
			);
		}

		return $parts;
	}

	/**
	 * Which part of its series a post is.
	 *
	 * The index of the post in its series' published list, one-based, so part 1
	 * is the oldest. A draft has no number — the design labels every planned part
	 * "DRAFT" and says in its own deck that "they get a number when they go up" —
	 * and neither does a post in no series.
	 *
	 * @param int $post_id The post.
	 * @return int Zero when the post is not a numbered part of anything.
	 */
	public function part_of( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}

		$term = $this->series_of( $post_id );

		if ( null === $term ) {
			return 0;
		}

		$position = array_search( $post_id, $this->ordered( $term->term_id ), true );

		return is_int( $position ) ? $position + 1 : 0;
	}

	/**
	 * The series one post is filed under.
	 *
	 * A post is in at most one series in practice and the design assumes exactly
	 * that — `SERIES.parts` is one ordered list — so the first term is the answer.
	 *
	 * @param int $post_id The post.
	 * @return WP_Term|null
	 */
	public function series_of( int $post_id ): ?WP_Term {
		$terms = get_the_terms( $post_id, Taxonomies::SERIES );

		if ( ! is_array( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * The published IDs of one series, memoised for the request.
	 *
	 * The key carries both `last_changed` stamps core already maintains, so
	 * publishing a post or filing one under a series invalidates the list without
	 * this class having to hook anything. Without the memo, an archive of twenty
	 * rows asking each row for its number would be twenty identical queries.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<int>
	 */
	private function ordered( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$key    = sprintf(
			'published:%d:%d:%s:%s',
			$term_id,
			$this->limit,
			wp_cache_get_last_changed( 'posts' ),
			wp_cache_get_last_changed( 'terms' )
		);
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			$ids = array();

			foreach ( $cached as $id ) {
				if ( is_int( $id ) ) {
					$ids[] = $id;
				}
			}

			return $ids;
		}

		$ids = $this->ids( $term_id, 'publish' );

		wp_cache_set( $key, $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * Post IDs in one series with one status, oldest first.
	 *
	 * `fields => ids` is doing real work: it is what keeps `post_content` out of
	 * the result set for the draft query. Every value `planned()` goes on to read
	 * is fetched by key, so there is no point at which a body is in hand and could
	 * be passed on by accident.
	 *
	 * Drafts are returned regardless of who is asking, which is deliberate:
	 * `WP_Query` only gates protected statuses when `perm` is set, and here the
	 * titles are meant to be public. The series term is the switch — a draft is
	 * announced when David files it under a series, not when he creates it.
	 *
	 * @param int    $term_id The `dp_series` term.
	 * @param string $status  A post status.
	 * @return list<int>
	 */
	private function ids( int $term_id, string $status ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => $status,
				'fields'                 => 'ids',
				// A taxonomy query is the entire purpose of this method, so the
				// performance warning has nothing to tell us.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'              => array(
					array(
						'taxonomy'         => Taxonomies::SERIES,
						'field'            => 'term_id',
						'terms'            => $term_id,
						'include_children' => false,
					),
				),
				'orderby'                => array( 'date' => 'ASC' ),
				'posts_per_page'         => $this->limit,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);

		$ids = array();

		foreach ( $query->posts as $post ) {
			if ( is_int( $post ) ) {
				$ids[] = $post;
			}
		}

		return $ids;
	}

	/**
	 * The line under a planned part's title, which is the draft's own excerpt.
	 *
	 * The stored excerpt and only the stored excerpt. `get_the_excerpt()` falls
	 * back to trimming `post_content`, and a "Still to come" row printing the
	 * opening of an unfinished piece of writing is the exact leak `PlannedPart`
	 * exists to make impossible.
	 *
	 * @param int $post_id The draft.
	 * @return string Empty when David has not written one.
	 */
	private function excerpt( int $post_id ): string {
		$post = get_post( $post_id );

		return $post instanceof WP_Post ? trim( $post->post_excerpt ) : '';
	}
}
