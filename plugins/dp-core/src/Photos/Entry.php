<?php
/**
 * One published photo, as the wall indexes it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * The few facts about a photo the whole set is filtered, counted and ordered by.
 *
 * Deliberately not a post: the page reads this for every published photo on
 * every render — to count the index, to date the trips, to find a photo's
 * neighbours across pages — and hydrating hundreds of `WP_Post` objects to learn
 * five scalars each is the cost `Library` exists to avoid. The posts for the
 * page actually being drawn are loaded separately, forty-eight at a time.
 */
final class Entry {

	/**
	 * Constructor.
	 *
	 * @param int             $id     The `dp_photo` post ID.
	 * @param string          $slug   Its `post_name`, which is what `?photo=` carries.
	 * @param string          $date   Its `post_date`, `Y-m-d H:i:s`, site time.
	 * @param int             $trip   Its `dp_trip` term ID, or 0.
	 * @param array<int, int> $topics Its `dp_topic` term IDs.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $slug,
		public readonly string $date,
		public readonly int $trip = 0,
		public readonly array $topics = array()
	) {}

	/**
	 * Whether this photo is in a filter.
	 *
	 * @param Filter $filter The active filter.
	 * @return bool
	 */
	public function in( Filter $filter ): bool {
		return match ( $filter->kind ) {
			Filter::TRIP  => $this->trip === $filter->term_id(),
			Filter::TOPIC => in_array( $filter->term_id(), $this->topics, true ),
			default       => true,
		};
	}
}
