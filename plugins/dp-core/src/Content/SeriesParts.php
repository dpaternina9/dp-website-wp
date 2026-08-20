<?php
/**
 * Reading a series' parts.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

use WP_Query;

/**
 * The two queries the series archive runs against one term.
 *
 * `published()` returns post IDs, because published posts have permalinks and
 * the template needs them. `planned()` returns `PlannedPart` objects, which have
 * no ID at all — see that class for why.
 *
 * Both order by `menu_order`, which is what plan section 3.1 chose: it is the one
 * ordering that survives a planned part becoming a published one, because the
 * post does not change identity when David hits publish. It simply moves from
 * the second list to the first, in the same position.
 */
final class SeriesParts {

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
	 * @return list<int> Post IDs.
	 */
	public function published( int $term_id ): array {
		return $this->ids( $term_id, 'publish' );
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
			$part = $this->number( $post_id, 'dp_series_part' );

			$parts[] = new PlannedPart(
				title: get_the_title( $post_id ),
				years: $this->text( $post_id, 'dp_series_years' ),
				note: $this->text( $post_id, 'dp_series_note' ),
				part: $part > 0 ? $part : null,
			);
		}

		return $parts;
	}

	/**
	 * Post IDs in one series with one status.
	 *
	 * `fields => ids` is doing real work: it is what keeps `post_content` out of
	 * the result set for the draft query. Every value `planned()` goes on to read
	 * is fetched by key from the meta cache, so there is no point at which a body
	 * is in hand and could be passed on by accident.
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
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
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
	 * One meta value, as a string, whatever the database had in it.
	 *
	 * @param int    $post_id  The post.
	 * @param string $meta_key The field.
	 * @return string
	 */
	private function text( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * One meta value, as a whole number, or zero if it is not one.
	 *
	 * @param int    $post_id  The post.
	 * @param string $meta_key The field.
	 * @return int
	 */
	private function number( int $post_id, string $meta_key ): int {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_numeric( $value ) ? (int) $value : 0;
	}
}
