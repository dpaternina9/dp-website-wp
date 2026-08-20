<?php
/**
 * A decimal year.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

use InvalidArgumentException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- this class is pure PHP
// and is unit-tested with no WordPress loaded, so `esc_html()` is not available to it. The
// messages are read in a terminal or a test report, never in a browser -- the same reasoning
// phpcs.xml.dist already applies to `tests/` and `bin/`.
/**
 * A point on the timeline, expressed the way the design expresses it.
 *
 * `LANES` in the fixture stores `start` and `end` as decimal years, and digest
 * section 3.3 pins the encoding: **the fractional part is the month**, with
 * `2026.4` reading as May 2026.
 *
 * The arithmetic that satisfies that is `month = floor( fraction * 12 ) + 1`:
 *
 * | value    | fraction x 12 | month |
 * |----------|---------------|-------|
 * | `2026.0` | 0.0           | 1 — January |
 * | `2026.4` | 4.8           | 5 — May |
 * | `2026.6` | 7.2           | 8 — August, which is what the fixture's "now" is |
 *
 * The alternative reading — tenths, so `.4` means the fifth month directly —
 * agrees on `2026.4` and disagrees on `2026.6`, where it would produce July for
 * a fixture authored in August 2026. Twelfths is the encoding that fits both.
 *
 * Everything the timeline draws is derived from these, so the class is
 * deliberately closed: no public constructor, no setters, and no way to hold a
 * value that is not a real point in time. `Timeline\Geometry` does the maths;
 * this only guarantees the input.
 */
final class Year {

	/**
	 * Earliest accepted year.
	 *
	 * Not a taste judgement — it is the boundary that makes a transposed or
	 * unit-confused value (a timestamp, a month number, a percentage) fail loudly
	 * instead of drawing a bar off the left of the chart.
	 *
	 * @var int
	 */
	public const MIN_YEAR = 1900;

	/**
	 * Latest accepted year. Exclusive of `MAX_YEAR + 1`.
	 *
	 * @var int
	 */
	public const MAX_YEAR = 2200;

	/**
	 * How close two decimal years must be to count as the same instant.
	 *
	 * A month is 1/12 = 0.0833, so this is many orders of magnitude below the
	 * resolution the encoding carries. It exists to absorb binary floating-point
	 * error, not to blur real differences.
	 *
	 * @var float
	 */
	private const EPSILON = 0.000000001;

	/**
	 * Months in a year. Named because it appears in the month arithmetic twice
	 * and the number is load-bearing, not incidental.
	 *
	 * @var int
	 */
	private const MONTHS = 12;

	/**
	 * Constructor. Private: use the named constructors, which validate.
	 *
	 * @param float $value The decimal year.
	 */
	private function __construct( private readonly float $value ) {}

	/**
	 * Build from a decimal year, rejecting anything that is not one.
	 *
	 * @param float $value Decimal year, e.g. `2026.4`.
	 * @return self
	 *
	 * @throws InvalidArgumentException When the value is not finite or falls outside MIN_YEAR..MAX_YEAR.
	 */
	public static function from_float( float $value ): self {
		if ( ! is_finite( $value ) ) {
			throw new InvalidArgumentException(
				'A decimal year must be a finite number; got ' . $value . '.'
			);
		}

		if ( $value < (float) self::MIN_YEAR || $value >= (float) ( self::MAX_YEAR + 1 ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'A decimal year must be between %d and %d; got %s.',
					self::MIN_YEAR,
					self::MAX_YEAR,
					$value
				)
			);
		}

		return new self( $value );
	}

	/**
	 * Build from a decimal year, or return null rather than throwing.
	 *
	 * For read paths over stored data, where a bad value should drop one bar
	 * rather than fatal the whole timeline.
	 *
	 * @param float $value Decimal year.
	 * @return self|null
	 */
	public static function try_from_float( float $value ): ?self {
		try {
			return self::from_float( $value );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Build from a calendar year and a 1-based month.
	 *
	 * `from_year_month( 2026, 5 )->month()` is 5. It is not `2026.4` — the
	 * encoding is twelfths, so it is `2026.3333…`. Both read as May.
	 *
	 * @param int $year  Calendar year.
	 * @param int $month Month, 1 (January) to 12 (December).
	 * @return self
	 *
	 * @throws InvalidArgumentException When the month is outside 1..12, or the year is out of range.
	 */
	public static function from_year_month( int $year, int $month ): self {
		if ( $month < 1 || $month > self::MONTHS ) {
			throw new InvalidArgumentException(
				sprintf( 'A month must be between 1 and %d; got %d.', self::MONTHS, $month )
			);
		}

		return self::from_float( $year + ( ( $month - 1 ) / self::MONTHS ) );
	}

	/**
	 * The decimal year itself.
	 *
	 * @return float
	 */
	public function value(): float {
		return $this->value;
	}

	/**
	 * The calendar year.
	 *
	 * @return int
	 */
	public function year(): int {
		return (int) floor( $this->value );
	}

	/**
	 * The month the fractional part encodes, 1..12.
	 *
	 * The `round()` is not cosmetic: `2026 + 4/12` is stored as
	 * 2026.33333333333303, whose fraction times twelve is 3.999999999996362.
	 * Flooring that directly would report April for a value built as May.
	 *
	 * @return int
	 */
	public function month(): int {
		$months = round( $this->fraction() * self::MONTHS, 9 );
		$index  = (int) floor( $months );

		return min( self::MONTHS, max( 1, $index + 1 ) );
	}

	/**
	 * The fractional part of the year.
	 *
	 * @return float A value in [0, 1).
	 */
	public function fraction(): float {
		return $this->value - floor( $this->value );
	}

	/**
	 * Compare two points in time.
	 *
	 * @param self $other The year to compare against.
	 * @return int Negative if this is earlier, 0 if the same instant, positive if later.
	 */
	public function compare( self $other ): int {
		$difference = $this->value - $other->value;

		if ( abs( $difference ) < self::EPSILON ) {
			return 0;
		}

		return $difference < 0.0 ? -1 : 1;
	}

	/**
	 * Whether this is the same instant as another.
	 *
	 * @param self $other The year to compare against.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return 0 === $this->compare( $other );
	}

	/**
	 * Whether this falls before another.
	 *
	 * @param self $other The year to compare against.
	 * @return bool
	 */
	public function is_before( self $other ): bool {
		return $this->compare( $other ) < 0;
	}

	/**
	 * Whether this falls after another.
	 *
	 * @param self $other The year to compare against.
	 * @return bool
	 */
	public function is_after( self $other ): bool {
		return $this->compare( $other ) > 0;
	}
}
