<?php
/**
 * The query-loop variations the design's bands need.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

use WP_Block;
use WP_Query;
use WP_Taxonomy;
use WP_Term;

/**
 * Query vars for the loops core's own attributes cannot express, plus one
 * ordering correction on the series archive.
 *
 * `core/query` covers post type, taxonomy, author, search and offset. It does
 * not cover ordering by a meta field or filtering on one, and both are needed:
 * the homepage's quiet record strip runs newest role first, which is `dp_start`
 * descending, and the work page's cards are the ships carrying `dp_featured`.
 *
 * `query_loop_block_query_vars` is the filter WordPress provides for exactly
 * this, and `query.namespace` is the attribute it provides for telling one loop
 * from another. Every loop this theme ships names itself; a query block that
 * does not is left entirely alone, which is what keeps a query David builds in
 * the editor out of this file's way.
 *
 * The series archive is separate. Its ordering is not a loop variation but a
 * property of the archive itself — plan section 3.1 settles that a series is
 * ordered by `menu_order`, so that a planned part becoming a published one keeps
 * its place — and the main query is where that has to be said.
 */
final class QueryLoops {

	/**
	 * The key inside a query block's `query` attribute that names the loop.
	 */
	public const KEY = 'dpLoop';

	/**
	 * The quiet strip of past roles on the homepage.
	 */
	public const ROLES = 'roles';

	/**
	 * Featured shipped work, for the design's `WorkCard` grid.
	 */
	public const FEATURED_SHIPS = 'featured-ships';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'query_loop_block_query_vars', $this->query_vars( ... ), 10, 2 );
		add_action( 'pre_get_posts', $this->order_the_series_archive( ... ) );
		add_action( 'pre_get_posts', $this->hold_back_the_featured_post( ... ) );
	}

	/**
	 * Add what `core/query`'s attributes cannot say.
	 *
	 * @param array<string, mixed> $query The query vars core built from the block.
	 * @param WP_Block             $block The query block.
	 * @return array<string, mixed>
	 */
	public function query_vars( array $query, WP_Block $block ): array {
		$context = $block->context['query'] ?? array();
		$loop    = is_array( $context ) && isset( $context[ self::KEY ] ) ? $context[ self::KEY ] : '';

		if ( ! is_string( $loop ) ) {
			return $query;
		}

		return match ( $loop ) {
			self::ROLES          => $this->newest_first( $query, 'dp_role', 'dp_end' ),
			self::FEATURED_SHIPS => $this->featured( $query ),
			default              => $query,
		};
	}

	/**
	 * Put the series archive in part order rather than date order.
	 *
	 * "Start with these … newest last" is the design's own wording, and a series
	 * is a reading order rather than a publication order: part 3 written after
	 * part 5 still reads third. `menu_order` carries that, with the post date as
	 * the tiebreak so a series whose parts were never ordered still comes out in
	 * a stable, sensible sequence instead of an arbitrary one.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return void
	 */
	public function order_the_series_archive( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $this->is_series_archive( $query ) ) {
			return;
		}

		$query->set(
			'orderby',
			array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			)
		);
	}

	/**
	 * Keep the post the blog index features out of the list below it.
	 *
	 * The design's blog index opens on one post in a panel of its own and then
	 * lists everything else: `POSTS.filter(p => p.slug !== POSTS[0].slug)`. The
	 * panel is its own query block, so the only thing that needs saying here is
	 * that the main query skips the same post.
	 *
	 * `post__not_in` rather than `offset`, deliberately. An offset on the main
	 * query is the well-known way to break pagination — WordPress computes the
	 * page's offset itself and the two do not compose — whereas excluding one ID
	 * leaves `found_posts`, the page links and Settings to Reading all correct.
	 *
	 * Feeds are left alone: a reader subscribing to the blog should not silently
	 * lose its most recent entry because a panel on a web page is showing it.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return void
	 */
	public function hold_back_the_featured_post( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() || $query->is_feed() ) {
			return;
		}

		$featured = $this->newest_post();

		if ( $featured > 0 ) {
			$query->set( 'post__not_in', array( $featured ) );
		}
	}

	/**
	 * The post the featured panel will be showing.
	 *
	 * The same order the panel's own query block runs in, which is what keeps
	 * the two agreeing without either of them being told about the other.
	 *
	 * @return int Zero when there is nothing published.
	 */
	private function newest_post(): int {
		$newest = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$first = $newest->posts[0] ?? 0;

		return is_int( $first ) ? $first : 0;
	}

	/**
	 * Whether a query is the archive of a taxonomy whose terms order their posts.
	 *
	 * The taxonomy is described rather than named, so this file never repeats a
	 * slug `dp-core` owns: it is attached to `post`, it is not one of core's own
	 * two, and it is flat. `category` fails the second test and `post_tag` the
	 * second as well, which leaves the series taxonomy and anything a later
	 * phase registers with the same shape — all of which want part order for the
	 * same reason.
	 *
	 * @param WP_Query $query The query.
	 * @return bool
	 */
	private function is_series_archive( WP_Query $query ): bool {
		if ( ! $query->is_tax() ) {
			return false;
		}

		$term = $query->get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return false;
		}

		$taxonomy = get_taxonomy( $term->taxonomy );

		return $taxonomy instanceof WP_Taxonomy
			&& in_array( 'post', (array) $taxonomy->object_type, true )
			&& ! $taxonomy->_builtin
			&& ! $taxonomy->hierarchical;
	}

	/**
	 * Order a loop by a decimal-year meta field, newest first.
	 *
	 * `meta_value_num` rather than `meta_value` matters: the years are decimals,
	 * and a string sort puts 2016 after 2016.5 but before 2009.
	 *
	 * @param array<string, mixed> $query     The query vars.
	 * @param string               $post_type The type to query, restated because core dropped it.
	 * @param string               $meta_key  The field to sort on.
	 * @return array<string, mixed>
	 */
	private function newest_first( array $query, string $post_type, string $meta_key ): array {
		$query['post_type']   = $post_type;
		$query['post_status'] = 'publish';

		// Ordering by a meta field is the entire point of this variation.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query['meta_key'] = $meta_key;
		$query['orderby']  = 'meta_value_num';
		$query['order']    = 'DESC';

		return $query;
	}

	/**
	 * Narrow a loop to the shipped work David marked featured, newest first.
	 *
	 * Both clauses are named, and the ordering names the one it sorts on. A bare
	 * `meta_key` plus `meta_value_num` would not survive here: once a query has
	 * a `meta_query`, `meta_value_num` sorts on that query's *first* clause, so
	 * the cards would come back ordered by the featured flag every one of them
	 * shares.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @return array<string, mixed>
	 */
	private function featured( array $query ): array {
		$query['post_type']   = 'dp_ship';
		$query['post_status'] = 'publish';

		// The filter and the ordering are the entire variation.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query['meta_query'] = array(
			'featured' => array(
				'key'     => 'dp_featured',
				'value'   => '1',
				'compare' => '=',
			),
			'shipped'  => array(
				'key'     => 'dp_end',
				'compare' => 'EXISTS',
				'type'    => 'DECIMAL(10,4)',
			),
		);

		$query['orderby'] = array( 'shipped' => 'DESC' );

		return $query;
	}
}
