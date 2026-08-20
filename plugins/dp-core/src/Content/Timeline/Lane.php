<?php
/**
 * One role lane, ready to draw.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

use DP\Core\Content\Tone;

/**
 * A `dp_role` with its bar, its accent and the shipped things hanging off it.
 *
 * The ships are held here rather than queried per lane because the chart is one
 * read: `Chart` fetches every published ship once and groups them, so six lanes
 * cost two queries instead of seven.
 *
 * `accent` is a `Tone` and not a colour. The design's rule — "role bar
 * `lane.accent || var(--dp-teal)`" — is satisfied by a class the stylesheet maps
 * to a token, because CLAUDE.md section 5 keeps `--dp-*` fills and `--hue-*`
 * text apart and a raw colour written into markup is a value nobody can
 * re-check.
 */
final class Lane {

	/**
	 * Constructor.
	 *
	 * @param int              $id     The post ID.
	 * @param string           $key    The stable identifier a URL carries, e.g. `dp-role-fanxie-lab`.
	 * @param string           $org    The organisation. The post title.
	 * @param string           $title  The job title.
	 * @param string           $range  The range exactly as it is printed.
	 * @param string           $detail What the job was and what it owned.
	 * @param string           $stack  The mono caps stack line.
	 * @param Tone|null        $accent An accent this lane owns, or null for the default teal.
	 * @param Bar|null         $bar    The computed bar, or null when the dates are unusable.
	 * @param array<int, Ship> $ships The shipped things that came out of it.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $key,
		public readonly string $org,
		public readonly string $title,
		public readonly string $range,
		public readonly string $detail,
		public readonly string $stack,
		public readonly ?Tone $accent,
		public readonly ?Bar $bar,
		public readonly array $ships
	) {}

	/**
	 * Whether anything shipped out of this role.
	 *
	 * @return bool
	 */
	public function has_ships(): bool {
		return array() !== $this->ships;
	}

	/**
	 * Whether this lane earns a legend swatch of its own.
	 *
	 * The design's own words: "A lane carrying its own `accent` also earns a
	 * legend swatch (e.g. Fanxie Lab = pink)." The point is that a bar drawn in
	 * a colour the legend never explains reads as a mistake.
	 *
	 * @return bool
	 */
	public function earns_a_swatch(): bool {
		return null !== $this->accent;
	}
}
