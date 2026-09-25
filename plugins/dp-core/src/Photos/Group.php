<?php
/**
 * One entry in the Photos index.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Term;

/**
 * A trip or a topic, with what the index prints beside it.
 *
 * `first` and `last` are the earliest and latest publish dates among the
 * group's published photos, which is where a trip's "Dec 2017" comes from. The
 * same pair is what the Trips screen in wp-admin prints in its Dates column
 * (`AdminList`), so the computed range is visible where David manages trips —
 * ADR-0018's rule that nothing computed may be invisible in the admin.
 */
final class Group {

	/**
	 * Constructor.
	 *
	 * @param WP_Term $term  The trip or topic.
	 * @param int     $count Its published photos that have an image.
	 * @param string  $first The earliest of their dates, `Y-m-d H:i:s`.
	 * @param string  $last  The latest of their dates, `Y-m-d H:i:s`.
	 */
	public function __construct(
		public readonly WP_Term $term,
		public readonly int $count,
		public readonly string $first,
		public readonly string $last
	) {}

	/**
	 * The date range, as the index prints it: "Dec 2017", "Nov – Dec 2017".
	 *
	 * @return string
	 */
	public function when(): string {
		return DateRange::label( $this->first, $this->last );
	}
}
