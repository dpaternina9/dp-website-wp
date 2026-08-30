<?php
/**
 * The published Watch entries, in David's order.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\PostTypes;
use WP_Post;
use WP_Query;

/**
 * Reads every published `dp_video` once per request and answers both blocks.
 *
 * The split matters more than the query. The design keeps the live entry out
 * of the archive — "the live card is a separate entry, not the head of the
 * archive, so it disappears entirely when the stream is off instead of sitting
 * there claiming to be live" — so `dp_live` partitions the list: one optional
 * live entry, and the archive.
 *
 * Ordering is `menu_order` then date, the same rule as the timeline's lanes
 * and for the same reason: the sequence is a decision David makes under Page
 * Attributes, and the seed writes the design's `VIDEOS` order into it, so a
 * seeded site reproduces the fixture exactly.
 *
 * **The date tiebreak is newest first**, which is what decides the order of an
 * imported archive. `VideoSync` writes no `menu_order` at all — a position is
 * David's to set and the platforms have no opinion about one — so every synced
 * video shares the default of zero and the date is the whole of their order. The
 * seeded fixture is unaffected: its entries carry distinct positions, so the
 * tiebreak never runs for them.
 */
final class Videos {

	/**
	 * How many entries one page will read. The design has six.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 100;

	/**
	 * The archive: every published entry that is not the live one.
	 *
	 * @return list<Video>
	 */
	public function archive(): array {
		return array_values(
			array_filter( $this->all(), static fn ( Video $video ): bool => ! $video->live )
		);
	}

	/**
	 * The live entry, or null when David has not written one.
	 *
	 * The first published `dp_video` with `dp_live` set. Whether it *renders*
	 * is `LiveStatus`'s question; this only answers whether the copy for it
	 * exists.
	 *
	 * @return Video|null
	 */
	public function live_entry(): ?Video {
		foreach ( $this->all() as $video ) {
			if ( $video->live ) {
				return $video;
			}
		}

		return null;
	}

	/**
	 * Every published entry, in the order the admin put them.
	 *
	 * Deliberately not memoized on the instance: the block objects live as
	 * long as the process does, which is one request under FPM and the whole
	 * suite under PHPUnit — a cache here would be stale content there. The
	 * query itself is answered from the object cache after its first run.
	 *
	 * @return list<Video>
	 */
	public function all(): array {
		$query = new WP_Query(
			array(
				'post_type'              => PostTypes::VIDEO,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_ROWS,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$entries = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$entries[] = Video::from_post( $post );
			}
		}

		return $entries;
	}
}
