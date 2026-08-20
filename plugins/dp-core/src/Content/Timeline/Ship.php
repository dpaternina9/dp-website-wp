<?php
/**
 * One shipped thing, ready to draw.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

/**
 * A `dp_ship` with its bar already computed.
 *
 * Digest section 3.4 lists the fields; this is that list, typed, with two
 * additions the design implies rather than stores.
 *
 * `key` is the stable identifier a URL can carry — `dp-ship-kiveo` — and is what
 * makes the design's "clicking a card opens the matching entry on the timeline"
 * work without JavaScript, because the server can be told which entry to render
 * open. The design keys its open state on `lane.org + ship.org`, which is a
 * string built out of display copy: renaming a project would break every link to
 * it. A slug is the same idea attached to something WordPress already keeps
 * stable.
 *
 * `bar` is nullable because a shipped thing with no usable dates is ordinary
 * half-finished data in the admin, and the row should still list rather than
 * fatal or draw a bar at year zero.
 */
final class Ship {

	/**
	 * Constructor.
	 *
	 * @param int                $id             The post ID.
	 * @param string             $key            The stable identifier a URL carries, e.g. `dp-ship-kiveo`.
	 * @param string             $name           The thing's name. The post title.
	 * @param string             $range          The range exactly as it is printed.
	 * @param string             $headline       One line, in the display face, at the top of the panel.
	 * @param string             $detail         What it is and who it is for.
	 * @param array<int, string> $bullets  The constraints that shaped it.
	 * @param string             $role           What David did on it.
	 * @param string             $stack          The mono caps stack line.
	 * @param string             $artifact_label The label above the artifact block.
	 * @param string             $artifact       A preformatted terminal or code sample.
	 * @param string             $stat1          The first statistic.
	 * @param string             $stat1_label    What the first statistic counts.
	 * @param string             $stat2          The second statistic.
	 * @param string             $stat2_label    What the second statistic counts.
	 * @param string             $writeup_url    Permalink of the post that writes this up, or ''.
	 * @param Bar|null           $bar            The computed bar, or null when the dates are unusable.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $key,
		public readonly string $name,
		public readonly string $range,
		public readonly string $headline,
		public readonly string $detail,
		public readonly array $bullets,
		public readonly string $role,
		public readonly string $stack,
		public readonly string $artifact_label,
		public readonly string $artifact,
		public readonly string $stat1,
		public readonly string $stat1_label,
		public readonly string $stat2,
		public readonly string $stat2_label,
		public readonly string $writeup_url,
		public readonly ?Bar $bar
	) {}

	/**
	 * Whether there is a post writing this up.
	 *
	 * @return bool
	 */
	public function has_writeup(): bool {
		return '' !== $this->writeup_url;
	}
}
