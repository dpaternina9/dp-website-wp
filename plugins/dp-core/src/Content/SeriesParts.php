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
 * The queries the series archive runs against one term, and the numbering.
 *
 * `published()` returns post IDs, because published posts have permalinks and
 * the template needs them. `planned()` returns `PlannedPart` objects, which have
 * no ID at all — see that class for why. `all()` returns both, and exists for
 * the one screen that has to show a reading order spanning the two.
 *
 * **Order is `menu_order` ascending, then the publish date ascending.**
 *
 * Plan section 3.1 chose `menu_order` and ADR-0016 took it away again, on an
 * observation that was true as far as it went: `post` does not declare
 * `page-attributes`, so the Order box is nowhere on the post editor, so the
 * field was zero on every post and the date tiebreak beside it was doing the
 * whole sort. What that missed is the difference between *no screen* and *not
 * writable*. `wp_update_post()` writes `menu_order` whether or not the post type
 * declares support for it; the field was always there, and what was missing was
 * somewhere for David to say what he meant. `DP\Core\Admin\SeriesOrderScreen` is
 * that somewhere.
 *
 * Keeping the date as the tiebreak is what makes this compatible by
 * construction rather than by migration: a series nobody has ever ordered has
 * zero in every row, so the sort falls through to the date and the page draws
 * exactly what it drew before.
 *
 * It has one consequence worth stating rather than discovering. Zero sorts
 * *first*, so a part filed under a series that has already been ordered arrives
 * at the top of it — a new draft appears above the parts already written rather
 * than after them. That is visible on the ordering screen, where the new row is
 * at position one, and one drag settles it. The alternative was a `save_post`
 * hook giving a joining post the next position, which is precisely the invisible
 * computation ADR-0018 rules out: nothing on the screen would say it had
 * happened. A wart you can see beats a mechanism you cannot.
 *
 * **The part number is the position in that list.** It is not stored anywhere.
 * `part_of()` answers "what part is this post" by finding the index of its ID in
 * `published()`, which means the numbers cannot drift from the order the page
 * draws, and a post that moves takes its number with it. The list is memoised
 * per term for the length of the request, so a template that asks on every row
 * of an archive still runs one query.
 *
 * One consequence of storing the order on the post rather than on the pair is
 * worth saying out loud: `menu_order` is a property of a post, not of a post in
 * a series. A post filed under two series would carry one position into both.
 * The design assumes exactly one — `SERIES.parts` is one ordered list, and
 * `series_of()` below already answers with the first term — so this is a
 * limitation the content model had before this field arrived, not one it adds.
 */
final class SeriesParts {

	/**
	 * The object-cache group the ordered ID lists live in.
	 */
	private const CACHE_GROUP = 'dp_series_parts';

	/**
	 * Constructor.
	 *
	 * @param int $limit The most parts any of these queries will return. A series longer
	 *                   than this is a series that wants splitting, not a query that wants
	 *                   unbounding.
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

		foreach ( $this->ids( $term_id, array( 'draft' ) ) as $post_id ) {
			$parts[] = new PlannedPart(
				title: get_the_title( $post_id ),
				note: $this->excerpt( $post_id )
			);
		}

		return $parts;
	}

	/**
	 * Every part of a series that has a place in the reading order.
	 *
	 * Published and planned in one sequence, which is the sequence the ordering
	 * screen has to show and write: a part's position has to survive its going
	 * from draft to published, and it cannot do that if the two lists are ordered
	 * against different things.
	 *
	 * IDs rather than posts, and no memo. The one caller is an admin screen that
	 * renders once per request; caching a list whose whole purpose is to be
	 * rewritten a moment later would be a cache to invalidate for no gain.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<int> Post IDs, in reading order.
	 */
	public function all( int $term_id ): array {
		return $this->ids( $term_id, array( 'publish', 'draft' ) );
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
	 * publishing a post, filing one under a series or moving one on the ordering
	 * screen invalidates the list without this class having to hook anything —
	 * `wp_update_post()` cleans the post cache, which is what bumps the stamp.
	 * Without the memo, an archive of twenty rows asking each row for its number
	 * would be twenty identical queries.
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

		$ids = $this->ids( $term_id, array( 'publish' ) );

		wp_cache_set( $key, $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * Post IDs in one series, in reading order.
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
	 * @param int                $term_id  The `dp_series` term.
	 * @param array<int, string> $statuses The post statuses to include.
	 * @return list<int>
	 */
	private function ids( int $term_id, array $statuses ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => $statuses,
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
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
				),
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
