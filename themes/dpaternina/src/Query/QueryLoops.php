<?php
/**
 * The query-loop variations the design's bands need.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

use DP\Core\Content\PostTypes;
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
 * The two post types are named through `DP\Core\Content\PostTypes`, guarded by
 * `class_exists()` the way `DP\Theme\Blocks\WorkCardTitle` guards its own —
 * the theme does not repeat a slug `dp-core` owns. **The meta keys are still
 * literals, and deliberately so:** `dp-core` declares no constant for any of the
 * twenty-odd fields in `DP\Core\Content\Meta`, so there is nothing to
 * reference, and inventing one here would be the theme naming a field it does
 * not own — the opposite of the rule. The same three keys appear in
 * `DP\Theme\Chrome\PostPresentation`'s allowlist alongside eight more, so
 * closing this is one change across `dp-core` and both readers, not a rename in
 * this file.
 *
 * The series archive is separate. Its ordering is not a loop variation but a
 * property of the archive itself — a series reads oldest part first, where an
 * archive defaults to newest first — and the main query is where that has to be
 * said.
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
	 * The three cards under a post: "KEEP READING".
	 */
	public const RELATED = 'related';

	/**
	 * The one post the blog index opens on, in a panel of its own.
	 *
	 * Nothing is added to this loop's query vars — `perPage`, `orderBy` and
	 * `order` say all of it, and they are David's to change in the site editor.
	 * The name is here so that `DP\Theme\Query\FeaturedPanel` can find the
	 * block and hold back whatever it selects, which is the only reason the loop
	 * needs a name at all.
	 */
	public const FEATURED = 'featured';

	/**
	 * How many cards the design's related grid holds.
	 */
	public const RELATED_COUNT = 3;

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'query_loop_block_query_vars', $this->query_vars( ... ), 10, 2 );
		add_action( 'pre_get_posts', $this->order_the_series_archive( ... ) );
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
			self::ROLES          => $this->roles( $query ),
			self::FEATURED_SHIPS => $this->featured( $query ),
			self::RELATED        => $this->related( $query ),
			self::FEATURED       => $query,
			default              => $query,
		};
	}

	/**
	 * The three posts the design puts under a post, in the order it puts them.
	 *
	 * `dpaternina.dc.html` states the rule and the reason in one comment:
	 * "Related: same category first, then whatever is newest, never the post you
	 * are on." So it is two lists concatenated and cut to three — the same
	 * category newest-first, then everything else newest-first — and that is not
	 * an ordering `WP_Query` can express, because it is a preference between two
	 * result sets rather than a sort of one.
	 *
	 * It is expressible as an explicit list, though, which is what this does:
	 * two cheap `fields => ids` queries, concatenated, then handed back as
	 * `post__in` with `orderby => post__in` so the loop draws them in exactly
	 * that sequence. An empty result asks for post 0 rather than for nothing,
	 * because a `post__in` of `array()` is ignored and would quietly turn the
	 * grid into "the three newest posts on the site".
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @return array<string, mixed>
	 */
	private function related( array $query ): array {
		$current = get_the_ID();
		$current = false === $current ? 0 : $current;

		$same  = $this->ids( self::RELATED_COUNT, $current, $this->categories_of( $current ) );
		$rest  = $this->ids( self::RELATED_COUNT + count( $same ), $current, array() );
		$order = $same;

		foreach ( $rest as $id ) {
			if ( ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}

		$order = array_slice( $order, 0, self::RELATED_COUNT );

		$query['post_type']      = 'post';
		$query['post_status']    = 'publish';
		$query['post__in']       = array() === $order ? array( 0 ) : $order;
		$query['orderby']        = 'post__in';
		$query['posts_per_page'] = self::RELATED_COUNT;

		unset( $query['offset'] );

		return $query;
	}

	/**
	 * Published post IDs, newest first, optionally narrowed to some categories.
	 *
	 * @param int             $limit    How many at most.
	 * @param int             $exclude  A post to leave out, or 0.
	 * @param array<int, int> $category Category term IDs, or an empty list for all.
	 * @return list<int>
	 */
	private function ids( int $limit, int $exclude, array $category ): array {
		if ( $limit <= 0 ) {
			return array();
		}

		$arguments = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => $limit,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( $exclude > 0 ) {
			$arguments['post__not_in'] = array( $exclude );
		}

		if ( array() !== $category ) {
			$arguments['category__in'] = $category;
		}

		$found = new WP_Query( $arguments );
		$ids   = array();

		foreach ( $found->posts as $id ) {
			if ( is_int( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The categories one post carries.
	 *
	 * @param int $post_id The post.
	 * @return list<int>
	 */
	private function categories_of( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$terms = get_the_terms( $post_id, 'category' );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$ids = array();

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$ids[] = $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * Put the series archive in part order rather than date order.
	 *
	 * "Start with these … newest last" is the design's own wording: a series is
	 * read from its oldest part, which is the opposite of what an archive does by
	 * default.
	 *
	 * `menu_order` first, then the date. The theme is not the authority on either
	 * half of that — it is a transcription of what
	 * `DP\Core\Content\SeriesParts::published()` sorts on, and the two have to
	 * match or the part numbers on the rows would disagree with the order the
	 * rows are drawn in.
	 *
	 * The field went away with ADR-0016 and has come back, for a reason recorded
	 * against `SeriesParts`: nothing could write `menu_order` on a `post` because
	 * there was no screen for it, which is not the same as the field being
	 * unwritable. `dp-core` now ships that screen. The date stays as the tiebreak,
	 * so a series nobody has ordered still draws in publish order.
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
	 * The homepage's quiet record strip: roles, the one that started last first.
	 *
	 * **`dp_start`, not `dp_end`.** This sorted on `dp_end` until now, which is
	 * "the role that finished most recently" — a different list the moment two
	 * roles overlap, which for a founder role running alongside a job is the
	 * normal case rather than the edge one. Three things say `dp_start` and
	 * agree with each other: this class's own docblock, the design
	 * (`LANES.slice().sort((a, b) => b.start - a.start)`), and
	 * `DP\Core\Resume\Ledger::lanes()`, which already sorts the résumé that
	 * way and cites the same line. So the homepage and the résumé were printing
	 * the same roles in two different orders.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @return array<string, mixed>
	 */
	private function roles( array $query ): array {
		if ( ! class_exists( PostTypes::class ) ) {
			return $this->nothing( $query );
		}

		return $this->newest_first( $query, PostTypes::ROLE, 'dp_start' );
	}

	/**
	 * A loop with nothing behind it, because `dp-core` is not active.
	 *
	 * Both variations below name a post type the plugin owns, so both have to say
	 * something when it is gone. Returning the query untouched is the one answer
	 * that must not be given: core drops an unregistered `postType` back to
	 * `post` in `build_query_vars_from_query_block()`, so the record strip would
	 * quietly fill with blog posts. `post__in => array( 0 )` is the same idiom
	 * `related()` uses for the same reason — an empty `post__in` is ignored,
	 * which would be the identical failure one step further along.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @return array<string, mixed>
	 */
	private function nothing( array $query ): array {
		$query['post__in'] = array( 0 );

		return $query;
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
		if ( ! class_exists( PostTypes::class ) ) {
			return $this->nothing( $query );
		}

		$query['post_type']   = PostTypes::SHIP;
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
