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
 * The distinction is not decorative: it sets the minimum width (64px against
 * 40px) and the default colour, both quoted from the geometry block at the
 * bottom of `design-source/components/TimelineChart.dc.html`.
 */
enum BarKind: string {

	case Role = 'role';
	case Ship = 'ship';

	/**
	 * The floor a bar may not render below, in CSS pixels.
	 *
	 * A three-month engagement is a sliver of a thirteen-year track. Without a
	 * floor it would be unclickable, so the design gives every bar a minimum and
	 * accepts that very short bars overstate their duration slightly.
	 *
	 * @return int
	 */
	public function min_width(): int {
		return match ( $this ) {
			self::Role => 64,
			self::Ship => 40,
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
