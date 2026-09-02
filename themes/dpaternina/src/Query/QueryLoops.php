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
	 * The most roles the record strip will read to decide its order.
	 */
	private const MAX_ROLES = 100;

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
	 * The homepage's quiet record strip: what David is doing now, then the rest.
	 *
	 * Two keys, in this order.
	 *
	 * **A role he has not left comes first.** The strip is three cards on the
	 * front page and it answers "what does he do", so a job still running
	 * outranks one that ended, whenever it began. Sorting on `dp_start` alone —
	 * which is what this did until 2026-09-02 — buried a founder role begun in
	 * 2016 under three jobs taken since, and that role is the one sentence the
	 * strip most needed to carry.
	 *
	 * **Then the one that started last.** `dp_start`, not `dp_end`: "the role
	 * that finished most recently" is a different list the moment two roles
	 * overlap, which for a founder role running alongside a job is the normal
	 * case. The design sorts this way too (`LANES.slice().sort((a, b) => b.start
	 * - a.start)`), and so does `DP\Core\Resume\Ledger::lanes()`.
	 *
	 * It is an explicit list rather than an `orderby`, for the same reason
	 * `related()` above is: "still going" is not a column. A blank end is stored
	 * as `0` by the field's own sanitiser, so a `meta_value_num` sort descending
	 * puts an ongoing role *last* — the exact inversion of what the strip is
	 * for — and one ascending would order the finished roles backwards. Two
	 * cheap reads and a `usort` say the actual rule instead.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @return array<string, mixed>
	 */
	private function roles( array $query ): array {
		if ( ! class_exists( PostTypes::class ) ) {
			return $this->nothing( $query );
		}

		$order = $this->roles_current_first();

		$query['post_type']   = PostTypes::ROLE;
		$query['post_status'] = 'publish';
		$query['post__in']    = array() === $order ? array( 0 ) : $order;
		$query['orderby']     = 'post__in';

		return $query;
	}

	/**
	 * Every published role, the ones still running first.
	 *
	 * `MAX_ROLES` rather than `-1`: this runs on the front page, and a record
	 * with a thousand roles in it is a query nobody meant to write. The strip
	 * shows three.
	 *
	 * @return list<int>
	 */
	private function roles_current_first(): array {
		$found = new WP_Query(
			array(
				'post_type'              => PostTypes::ROLE,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => self::MAX_ROLES,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$roles = array();

		foreach ( $found->posts as $id ) {
			if ( ! is_int( $id ) ) {
				continue;
			}

			$roles[] = array(
				'id'      => $id,
				'ongoing' => $this->is_ongoing( $id ),
				'start'   => $this->decimal_meta( $id, 'dp_start' ),
			);
		}

		usort(
			$roles,
			static fn ( array $one, array $two ): int =>
				array( $two['ongoing'], $two['start'] ) <=> array( $one['ongoing'], $one['start'] )
		);

		$ids = array();

		foreach ( $roles as $role ) {
			$ids[] = $role['id'];
		}

		return $ids;
	}

	/**
	 * Whether a role is one David has not left.
	 *
	 * The same reading `DP\Core\Content\Timeline\Chart` gives a blank end,
	 * and it has to stay the same one: the field says "Leave it blank for a role
	 * you are still in", and a homepage that disagreed with the chart about which
	 * jobs are current would be the field's promise broken in a second place.
	 * Missing, empty and the sanitiser's `0` all mean the same thing.
	 *
	 * @param int $post_id The role.
	 * @return bool
	 */
	private function is_ongoing( int $post_id ): bool {
		return $this->decimal_meta( $post_id, 'dp_end' ) <= 0.0;
	}

	/**
	 * One decimal-year meta value, or 0.0.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return float
	 */
	private function decimal_meta( int $post_id, string $key ): float {
		$value = get_post_meta( $post_id, $key, true );

		return is_numeric( $value ) ? (float) $value : 0.0;
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
	 * Narrow a loop to the shipped work David marked featured, in his order.
	 *
	 * **The sequence is `menu_order` — Page Attributes, set by hand.** Until
	 * 2026-09-02 it was `dp_end` descending, and that was wrong twice over.
	 *
	 * It was wrong in principle: which three pieces of work lead the work page
	 * is an editorial decision, not a consequence of when they happened. The
	 * featured checkbox already says *whether* a thing appears; nothing said in
	 * what order, so the most recent thing led whether or not it was the
	 * strongest. ADR-0019 settled the same question for a series with the same
	 * answer, and `Timeline\Chart` already reads its lanes this way.
	 *
	 * It was also wrong in fact, from the day a blank "Ended" started meaning
	 * "still going". The old ordering needed a second `meta_query` clause
	 * requiring `dp_end` to EXIST, purely so there was a named clause to sort
	 * on — and `register_post_meta()`'s default is not a row, so a ship whose
	 * end was never saved matched neither the clause nor the sort. An ongoing
	 * featured project therefore either sorted last on a stored `0` or vanished
	 * from the grid entirely. Both failures pointed the same way: the most
	 * current work was the least likely to be shown.
	 *
	 * Dropping that clause is what lets an ongoing project be featured at all,
	 * so the filter is now the one thing it was always meant to be — the
	 * checkbox — and `dp_end` is left to mean what it means everywhere else.
	 *
	 * The tiebreak is newest first, not oldest. Every ship starts at
	 * `menu_order` 0, so on a site where nobody has set an order yet this is the
	 * whole ordering, and "the newest three" is the better default for a
	 * highlight reel. `Timeline\Chart` breaks its tie the other way because a
	 * chronology reads oldest first; the two disagree on purpose.
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

		// The filter is the entire variation; the order is David's.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query['meta_query'] = array(
			'featured' => array(
				'key'     => 'dp_featured',
				'value'   => '1',
				'compare' => '=',
			),
		);

		$query['orderby'] = array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		);

		return $query;
	}
}
