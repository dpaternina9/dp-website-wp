<?php
/**
 * Unit tests for the decimal-year value object.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Core\Content\Year;
use InvalidArgumentException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Pins the encoding the whole timeline rests on.
 *
 * `Year` touches no WordPress function, so these run on the host with no
 * bootstrap and no mocking. That is the point of the class being closed: the
 * arithmetic Phase 6 depends on can be proved without a database.
 */
final class YearTest extends TestCase {

	/**
	 * How close two floats must be for these tests to call them equal.
	 *
	 * @var float
	 */
	private const DELTA = 0.000000001;

	/**
	 * A decimal year keeps the value it was given.
	 *
	 * @return void
	 */
	public function test_it_keeps_its_value(): void {
		$this->assertEqualsWithDelta( 2026.4, Year::from_float( 2026.4 )->value(), self::DELTA );
		$this->assertSame( 2026, Year::from_float( 2026.4 )->year() );
		$this->assertEqualsWithDelta( 0.4, Year::from_float( 2026.4 )->fraction(), self::DELTA );
	}

	/**
	 * The fraction is twelfths of a year, so `2026.4` reads as May.
	 *
	 * The three values in the fixture are the ones that matter: digest section 3.3
	 * states 2026.4 is May, and the design was authored in August 2026, which is
	 * what "now" — 2026.6 — has to resolve to.
	 *
	 * @dataProvider provide_fixture_years
	 *
	 * @param float $value    The decimal year.
	 * @param int   $expected The month it encodes.
	 * @return void
	 */
	public function test_the_fraction_encodes_a_month( float $value, int $expected ): void {
		$this->assertSame( $expected, Year::from_float( $value )->month() );
	}

	/**
	 * Every decimal year the fixture actually contains, and its month.
	 *
	 * @return array<string, array{float, int}>
	 */
	public static function provide_fixture_years(): array {
		return array(
			'a whole year is January'          => array( 2014.0, 1 ),
			'2026.4 is May, per the digest'    => array( 2026.4, 5 ),
			'2026.6 is August, which is "now"' => array( 2026.6, 8 ),
			'2025.8 is October'                => array( 2025.8, 10 ),
			'2024.8 is October'                => array( 2024.8, 10 ),
		);
	}

	/**
	 * A month boundary falls exactly on a twelfth, and the value below it is the month before.
	 *
	 * This is the assertion that would fail if the encoding were ever "corrected"
	 * to tenths, and the one that fails if the floating-point rounding in
	 * `month()` is removed: `2026 + 4/12` is stored as 2026.33333333333303, whose
	 * fraction times twelve is 3.999999999996362.
	 *
	 * @return void
	 */
	public function test_month_boundaries_are_exact(): void {
		for ( $month = 1; $month <= 12; $month++ ) {
			$exact = Year::from_year_month( 2026, $month );

			$this->assertSame(
				$month,
				$exact->month(),
				sprintf( 'Month %d did not survive a round trip through the decimal encoding.', $month )
			);

			$just_after = Year::from_float( $exact->value() + 0.04 );

			$this->assertSame(
				$month,
				$just_after->month(),
				sprintf( 'A value half a month past the start of month %d changed month.', $month )
			);

			if ( $month > 1 ) {
				$just_before = Year::from_float( $exact->value() - 0.000001 );

				$this->assertSame(
					$month - 1,
					$just_before->month(),
					sprintf( 'A value a hair before month %d is not month %d.', $month, $month - 1 )
				);
			}
		}
	}

	/**
	 * The last instant of a year is December, not the January after it.
	 *
	 * @return void
	 */
	public function test_the_end_of_a_year_is_december(): void {
		$year = Year::from_float( 2026.999999 );

		$this->assertSame( 2026, $year->year() );
		$this->assertSame( 12, $year->month() );
	}

	/**
	 * A whole year is the first of January.
	 *
	 * @return void
	 */
	public function test_a_whole_year_is_january(): void {
		$this->assertSame( 1, Year::from_float( 2014.0 )->month() );
		$this->assertEqualsWithDelta( 0.0, Year::from_float( 2014.0 )->fraction(), self::DELTA );
	}

	/**
	 * A year built from a month is that month, and is not the tenths spelling.
	 *
	 * @return void
	 */
	public function test_from_year_month_uses_twelfths(): void {
		$may = Year::from_year_month( 2026, 5 );

		$this->assertSame( 5, $may->month() );
		$this->assertEqualsWithDelta( 2026.0 + ( 4.0 / 12.0 ), $may->value(), self::DELTA );
		$this->assertNotEquals( 2026.4, $may->value(), 'Twelfths, not tenths.' );
	}

	/**
	 * Anything that is not a point in time is refused.
	 *
	 * @dataProvider provide_impossible_values
	 *
	 * @param float $value The value.
	 * @return void
	 */
	public function test_it_refuses_impossible_values( float $value ): void {
		$this->expectException( InvalidArgumentException::class );

		Year::from_float( $value );
	}

	/**
	 * Values that are not decimal years.
	 *
	 * @return array<string, array{float}>
	 */
	public static function provide_impossible_values(): array {
		return array(
			'not a number'          => array( NAN ),
			'infinity'              => array( INF ),
			'negative infinity'     => array( -INF ),
			'zero'                  => array( 0.0 ),
			'negative'              => array( -2026.4 ),
			'a month number'        => array( 5.0 ),
			'a unix timestamp'      => array( 1787000000.0 ),
			'just below the floor'  => array( 1899.999 ),
			'just past the ceiling' => array( 2201.0 ),
		);
	}

	/**
	 * The bounds themselves are accepted.
	 *
	 * @return void
	 */
	public function test_the_bounds_are_inclusive_at_the_bottom_and_open_at_the_top(): void {
		$this->assertSame( Year::MIN_YEAR, Year::from_float( (float) Year::MIN_YEAR )->year() );
		$this->assertSame( Year::MAX_YEAR, Year::from_float( 2200.999 )->year() );
	}

	/**
	 * A month outside the calendar is refused.
	 *
	 * @return void
	 */
	public function test_it_refuses_a_month_outside_the_calendar(): void {
		$this->expectException( InvalidArgumentException::class );

		Year::from_year_month( 2026, 13 );
	}

	/**
	 * A month of zero is refused, not treated as December of the year before.
	 *
	 * @return void
	 */
	public function test_it_refuses_a_zero_month(): void {
		$this->expectException( InvalidArgumentException::class );

		Year::from_year_month( 2026, 0 );
	}

	/**
	 * The forgiving constructor returns null rather than throwing.
	 *
	 * @return void
	 */
	public function test_try_from_float_returns_null_for_rubbish(): void {
		$this->assertNull( Year::try_from_float( NAN ) );
		$this->assertNull( Year::try_from_float( 0.0 ) );
		$this->assertNull( Year::try_from_float( 9999.0 ) );
		$this->assertInstanceOf( Year::class, Year::try_from_float( 2026.4 ) );
	}

	/**
	 * Comparison orders two points in time.
	 *
	 * @return void
	 */
	public function test_it_compares(): void {
		$earlier = Year::from_float( 2022.0 );
		$later   = Year::from_float( 2026.4 );

		$this->assertTrue( $earlier->is_before( $later ) );
		$this->assertTrue( $later->is_after( $earlier ) );
		$this->assertFalse( $earlier->is_after( $later ) );
		$this->assertFalse( $earlier->equals( $later ) );
		$this->assertLessThan( 0, $earlier->compare( $later ) );
		$this->assertGreaterThan( 0, $later->compare( $earlier ) );
	}

	/**
	 * Two values a floating-point hair apart are the same instant.
	 *
	 * Without this, `2026 + 4/12` and a stored 2026.3333333333333 would compare
	 * as different points in time, which is a bug that only ever shows up as an
	 * off-by-one bar somewhere in a chart.
	 *
	 * @return void
	 */
	public function test_floating_point_noise_is_not_a_difference(): void {
		$a = Year::from_year_month( 2026, 5 );
		$b = Year::from_float( $a->value() + 0.0000000001 );

		$this->assertTrue( $a->equals( $b ) );
		$this->assertSame( 0, $a->compare( $b ) );
		$this->assertFalse( $a->is_before( $b ) );
		$this->assertFalse( $a->is_after( $b ) );
	}
}
