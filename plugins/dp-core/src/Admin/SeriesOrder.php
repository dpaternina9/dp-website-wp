<?php
/**
 * Reading and writing a series' reading order.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Admin;

use DP\Core\Content\SeriesParts;
use WP_Post;

/**
 * The order of one series, as a list of posts and as something that can be written.
 *
 * Everything the ordering screen knows about the database is here, and the
 * screen itself is markup and hooks. That split is what lets the write path be
 * tested without a browser: `save()` takes the list of IDs a request claimed and
 * returns how many rows it moved, and nothing in it reads a superglobal.
 *
 * **The submitted list is not trusted for membership.** It is intersected with
 * `SeriesParts::all()`, which is the same query the archive draws from — so an
 * ID that is not a `post` carrying this term is dropped before anything is
 * written, whatever the request claimed. Parts the request did not mention keep
 * their relative order and follow the ones it did, which is what happens when a
 * part is filed under the series in another tab while this screen is open.
 *
 * **The write is `wp_update_post()`.** `post` does not declare `page-attributes`
 * and this deliberately does not add it (ADR-0016 rejected that, and rightly:
 * it would put an Order box on the twenty-nine posts that are in no series).
 * The declaration is what draws the box; it is not what permits the write.
 * `wp_update_post()` writes `menu_order` on any post type, which is confirmed
 * against the running container rather than assumed.
 *
 * Two consequences of using the full core API rather than a targeted column
 * update, both accepted deliberately:
 *
 * - `post_modified` moves on every row that moves. Nothing in either package
 *   reads a `post`'s modified date — the résumé's PDF cache keys on `dp_role`
 *   and `dp_ship` — and the alternative is `$wpdb->update()` plus a hand-written
 *   cache flush, which is a worse trade than a column nobody reads.
 * - `save_post` fires, so anything listening sees the change. That is the point
 *   of using the API: the object cache, the term counts and any future listener
 *   stay consistent without this class knowing they exist.
 *
 * Rows that are already in the right place are not written at all, so opening
 * the screen and pressing Save without dragging anything writes nothing.
 */
final class SeriesOrder {

	/**
	 * Constructor.
	 *
	 * @param SeriesParts $parts The queries the archive itself draws from.
	 */
	public function __construct( private readonly SeriesParts $parts = new SeriesParts() ) {}

	/**
	 * The IDs of every part of a series, in the order the site draws them.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<int>
	 */
	public function ids( int $term_id ): array {
		return $this->parts->all( $term_id );
	}

	/**
	 * The parts of a series as posts, in the order the site draws them.
	 *
	 * One query for the objects, on top of the one that established the order.
	 * `post__in` with `orderby => post__in` is what keeps the second query from
	 * having an opinion: the sequence is already decided, and this only fetches
	 * the rows.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return list<WP_Post>
	 */
	public function posts( int $term_id ): array {
		$ids = $this->ids( $term_id );

		if ( array() === $ids ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => array( 'publish', 'draft' ),
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$posts = array();

		foreach ( $found as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}

		return $posts;
	}

	/**
	 * Write a reading order.
	 *
	 * @param int             $term_id   The `dp_series` term.
	 * @param array<int, int> $submitted The IDs a request asked for, in the order it asked for.
	 * @return int How many posts moved. Zero means nothing needed writing.
	 */
	public function save( int $term_id, array $submitted ): int {
		$order = $this->reconcile( $this->ids( $term_id ), $submitted );
		$moved = 0;

		foreach ( $order as $index => $post_id ) {
			$position = $index + 1;
			$post     = get_post( $post_id );

			if ( ! $post instanceof WP_Post || $post->menu_order === $position ) {
				continue;
			}

			/*
			 * The screen has already checked a capability and a nonce. This is the
			 * second gate rather than the first: the list is per-post, so the
			 * question "may this user edit this post" is a per-post question and is
			 * asked here, where the post is in hand.
			 */
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => $position,
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				++$moved;
			}
		}

		return $moved;
	}

	/**
	 * The order to write: what was asked for, filtered to what exists.
	 *
	 * An ID the request sent that is not a part of this series is dropped, and a
	 * part the request did not send follows the ones it did, keeping its own
	 * relative position. Duplicates count once, at their first appearance.
	 *
	 * @param array<int, int> $parts     Every part of the series, in its current order.
	 * @param array<int, int> $submitted The IDs a request asked for.
	 * @return list<int>
	 */
	private function reconcile( array $parts, array $submitted ): array {
		$order = array();

		foreach ( $submitted as $post_id ) {
			if ( in_array( $post_id, $parts, true ) && ! in_array( $post_id, $order, true ) ) {
				$order[] = $post_id;
			}
		}

		foreach ( $parts as $post_id ) {
			if ( ! in_array( $post_id, $order, true ) ) {
				$order[] = $post_id;
			}
		}

		return $order;
	}
}
