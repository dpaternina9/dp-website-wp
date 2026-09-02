<?php
/**
 * What a timeline bar represents.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

use DP\Core\Content\Tone;

// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- PHPCompatibility does not model enums, and reads `match ( $this )` in an enum method as
// `$this` outside an object. It is valid PHP 8.1, and this project targets 8.4.
/**
 * A bar is either a role lane or a shipped thing hanging off one.
 *
 * The distinction is not decorative: it sets the minimum width (10px against
 * 8px) and the default colour. The colours are quoted from the geometry block
 * at the bottom of `design-source/components/TimelineChart.dc.html`; the floors
 * deliberately are not -- see `min_width()`.
 */
enum BarKind: string {

	case Role = 'role';
	case Ship = 'ship';

	/**
	 * The floor a bar may not render below, in CSS pixels.
	 *
	 * `TimelineChart.dc.html` says 64 for roles and 40 for ships, and this
	 * returned exactly that until 2026-09-02. Those numbers were sized against
	 * the fixture, where the shortest role is two years and the floor never
	 * binds. On the real record it binds on every sub-year role, and it binds
	 * hard: at the width the work page gives the track, a year is worth about
	 * 64px, so a three-month role was drawn a *year* long. Two consecutive
	 * three-month roles -- April to June and July to December 2019 -- came out
	 * as two year-long bars sixteen pixels apart, reading as almost entirely
	 * concurrent. The positions were right; the widths were fiction.
	 *
	 * The floor's stated reason was that a sliver "would be unclickable". That
	 * was never true of this build. `TimelineRows::summary()` puts the label
	 * column and the track inside one `<summary>`, so the whole row is the
	 * disclosure toggle and the bar is decoration -- a 10px bar is exactly as
	 * clickable as a 64px one. So the floor only has to clear the smallest mark
	 * a reader can see, which is what these numbers are: a visible tick, not a
	 * fake year. A bar's width is now its duration to within about six weeks.
	 *
	 * Roles keep the larger of the two so a role still outranks a ship when
	 * both are floored, which is the one thing the design's ratio was saying.
	 *
	 * @return int
	 */
	public function min_width(): int {
		return match ( $this ) {
			self::Role => 10,
			self::Ship => 8,
		};
	}

	/**
	 * The colour a bar of this kind uses when the lane carries no accent.
	 *
	 * @return Tone
	 */
	public function default_tone(): Tone {
		return match ( $this ) {
			self::Role => Tone::Teal,
			self::Ship => Tone::Gold,
		};
	}
}
