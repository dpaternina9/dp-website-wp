<?php
/**
 * Timeline geometry.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

use DP\Core\Content\Year;
use InvalidArgumentException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- this class is pure PHP
// and is unit-tested with no WordPress loaded, so `esc_html()` is not available to it. The
// messages are read in a terminal or a test report, never in a browser -- the same reasoning
// phpcs.xml.dist already applies to `tests/` and `bin/`.
/**
 * Where every bar on the timeline goes.
 *
 * Transcribed from the POSITIONING block at the bottom of
 * `design-source/components/TimelineChart.dc.html`:
 *
 * ```
 * pos(y)       = ((y - yearStart) / (yearEnd - yearStart + 1)) * 100
 * bar.left     = pos(start)%
 * bar.width    = min(pos(end) - pos(start)%, (100 - pos(start))%)
 * bar.minWidth = 64 (roles) / 40 (ships); maxWidth = (100 - pos(start))%
 * ```
 *
 * The `+ 1` in the denominator is why 2026 does not sit at 100%: the track
 * covers thirteen whole years (2014 through 2026 inclusive), so the last year
 * occupies the final thirteenth rather than terminating the axis. Dropping it —
 * the obvious "simplification" — would silently shift every bar in the chart.
 *
 * Pure, and deliberately free of WordPress: it takes years and returns numbers.
 * Phase 6 renders what this returns and derives nothing of its own, which is the
 * point of computing it here, a phase early, with tests.
 */
final class Geometry {

	/**
	 * The track the design draws, and what `hint-placeholder-count="13"` counts.
	 *
	 * @var int
	 */
	public const DESIGN_FIRST_YEAR = 2014;

	/**
	 * The last labelled year on the design's track.
	 *
	 * @var int
	 */
	public const DESIGN_LAST_YEAR = 2026;

	/**
	 * Constructor.
	 *
	 * @param int $first_year First labelled year on the track, inclusive.
	 * @param int $last_year  Last labelled year on the track, inclusive.
	 *
	 * @throws InvalidArgumentException When the range is empty, inverted, or outside what a Year may hold.
	 */
	public function __construct(
		private readonly int $first_year,
		private readonly int $last_year
	) {
		if ( $last_year <= $first_year ) {
			throw new InvalidArgumentException(
				sprintf( 'A timeline needs at least two years; got %d to %d.', $first_year, $last_year )
			);
		}

		if ( $first_year < Year::MIN_YEAR || $last_year > Year::MAX_YEAR ) {
			throw new InvalidArgumentException(
				sprintf(
					'A timeline must sit inside %d..%d; got %d to %d.',
					Year::MIN_YEAR,
					Year::MAX_YEAR,
					$first_year,
					$last_year
				)
			);
		}
	}

	/**
	 * The track the design ships: 2014 to 2026.
	 *
	 * @return self
	 */
	public static function for_the_design(): self {
		return new self( self::DESIGN_FIRST_YEAR, self::DESIGN_LAST_YEAR );
	}

	/**
	 * How many whole years the track covers.
	 *
	 * This is the `yearEnd - yearStart + 1` of the specification, named so the
	 * `+ 1` has somewhere to be explained instead of looking like an off-by-one.
	 *
	 * @return int
	 */
	public function span(): int {
		return ( $this->last_year - $this->first_year ) + 1;
	}

	/**
	 * The labels along the top of the track.
	 *
	 * @return list<int>
	 */
	public function year_labels(): array {
		return range( $this->first_year, $this->last_year );
	}

	/**
	 * Where a point in time falls along the track.
	 *
	 * Clamped to 0..100. The specification does not state this, because the
	 * fixture never leaves the track — but a role that started before the first
	 * labelled year is ordinary real data, and unclamped it would render off the
	 * left edge. Clamping changes nothing for any value inside the range, which
	 * is what the unit tests pin.
	 *
	 * @param Year $year The point in time.
	 * @return float A percentage in 0..100.
	 */
	public function position( Year $year ): float {
		$raw = ( ( $year->value() - (float) $this->first_year ) / (float) $this->span() ) * 100.0;

		return min( 100.0, max( 0.0, $raw ) );
	}

	/**
	 * The bar for one span of time.
	 *
	 * Three clamps, in the order the design applies them:
	 *
	 * 1. both ends are pulled onto the track by `position()`;
	 * 2. the width may not push the bar past the right-hand edge —
	 *    `min( pos(end) - pos(start), 100 - pos(start) )`;
	 * 3. a width may not be negative. The specification is silent because an end
	 *    before a start is not in the fixture; it is, however, one typo away in
	 *    the admin, and a negative width is a bar that renders inverted rather
	 *    than a bar that renders wrong.
	 *
	 * @param Year    $start When it began.
	 * @param Year    $end   When it ended.
	 * @param BarKind $kind  Role lane or shipped thing.
	 * @return Bar
	 */
	public function bar( Year $start, Year $end, BarKind $kind ): Bar {
		$left      = $this->position( $start );
		$right     = $this->position( $end );
		$max_width = 100.0 - $left;
		$width     = min( max( $right - $left, 0.0 ), $max_width );

		return new Bar( $left, $width, $max_width, $kind );
	}
}
