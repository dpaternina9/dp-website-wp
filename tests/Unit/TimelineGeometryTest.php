<?php
/**
 * Unit tests for the timeline's bar geometry.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Core\Content\Timeline\BarKind;
use DP\Core\Content\Timeline\Geometry;
use DP\Core\Content\Year;
use InvalidArgumentException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The numbers in `design-source/components/TimelineChart.dc.html`, asserted.
 *
 * Phase 6 renders what `Geometry` returns and derives nothing of its own, so
 * these are the tests that stand behind the chart. They are written against the
 * design's own track — 2014 to 2026 — and against the fixture's own lanes, so a
 * failure names a bar somebody can go and look at.
 */
final class TimelineGeometryTest extends TestCase {

	/**
	 * Percentages are compared to nine places; the design rounds to four.
	 *
	 * @var float
	 */
	private const DELTA = 0.000000001;

	/**
	 * The track covers thirteen whole years, not twelve intervals.
	 *
	 * The `+ 1` in `(yearEnd - yearStart + 1)` is the single most deletable-looking
	 * character in the specification and the one that silently moves every bar in
	 * the chart if it goes.
	 *
	 * @return void
	 */
	public function test_the_span_counts_years_not_intervals(): void {
		$geometry = Geometry::for_the_design();

		$this->assertSame( 13, $geometry->span() );
		$this->assertSame( range( 2014, 2026 ), $geometry->year_labels() );
		$this->assertCount( 13, $geometry->year_labels() );
	}

	/**
	 * The last labelled year starts at twelve thirteenths, not at the far edge.
	 *
	 * @return void
	 */
	public function test_the_last_year_does_not_end_the_axis(): void {
		$geometry = Geometry::for_the_design();

		$this->assertEqualsWithDelta(
			( 12.0 / 13.0 ) * 100.0,
			$geometry->position( Year::from_float( 2026.0 ) ),
			self::DELTA,
			'2026 occupies the final thirteenth; it does not terminate the track.'
		);
	}

	/**
	 * Every position the fixture actually asks for.
	 *
	 * @dataProvider provide_positions
	 *
	 * @param float $year     The decimal year.
	 * @param float $expected Its position, in percent.
	 * @return void
	 */
	public function test_it_positions_a_year( float $year, float $expected ): void {
		$this->assertEqualsWithDelta(
			$expected,
			Geometry::for_the_design()->position( Year::from_float( $year ) ),
			self::DELTA
		);
	}

	/**
	 * `pos(y) = ((y - 2014) / 13) * 100`, worked through for the fixture's years.
	 *
	 * @return array<string, array{float, float}>
	 */
	public static function provide_positions(): array {
		return array(
			'the first year is the origin'             => array( 2014.0, 0.0 ),
			'Imaginamos and Fanxie Lab start together' => array( 2016.0, ( 2.0 / 13.0 ) * 100.0 ),
			'MonsterInsights starts'                   => array( 2022.0, ( 8.0 / 13.0 ) * 100.0 ),
			'Kiveo starts'                             => array( 2023.0, ( 9.0 / 13.0 ) * 100.0 ),
			'MonsterInsights ends, May 2026'           => array( 2026.4, ( 12.4 / 13.0 ) * 100.0 ),
			'"now", August 2026'                       => array( 2026.6, ( 12.6 / 13.0 ) * 100.0 ),
		);
	}

	/**
	 * A year before the track starts is pulled onto it rather than off the left edge.
	 *
	 * The specification does not say to clamp, because the fixture never leaves
	 * the track. A job that started in 2009 is ordinary data David could enter
	 * tomorrow, and unclamped it renders at minus thirty-eight percent.
	 *
	 * @return void
	 */
	public function test_a_year_before_the_track_clamps_to_the_start(): void {
		$this->assertSame( 0.0, Geometry::for_the_design()->position( Year::from_float( 2009.0 ) ) );
	}

	/**
	 * A year past the track clamps to the end.
	 *
	 * @return void
	 */
	public function test_a_year_past_the_track_clamps_to_the_end(): void {
		$this->assertSame( 100.0, Geometry::for_the_design()->position( Year::from_float( 2030.0 ) ) );
	}

	/**
	 * The MonsterInsights lane, end to end.
	 *
	 * @return void
	 */
	public function test_it_measures_a_role_bar(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2022.0 ),
			Year::from_float( 2026.4 ),
			BarKind::Role
		);

		$this->assertEqualsWithDelta( ( 8.0 / 13.0 ) * 100.0, $bar->left(), self::DELTA );
		$this->assertEqualsWithDelta( ( 4.4 / 13.0 ) * 100.0, $bar->width(), self::DELTA );
		$this->assertEqualsWithDelta( 100.0 - ( ( 8.0 / 13.0 ) * 100.0 ), $bar->max_width(), self::DELTA );
		$this->assertSame( 64, $bar->min_width() );
		$this->assertSame( BarKind::Role, $bar->kind() );
	}

	/**
	 * The Kiveo bar, which is a ship and therefore has the smaller floor.
	 *
	 * @return void
	 */
	public function test_it_measures_a_ship_bar(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2023.0 ),
			Year::from_float( 2026.6 ),
			BarKind::Ship
		);

		$this->assertEqualsWithDelta( ( 9.0 / 13.0 ) * 100.0, $bar->left(), self::DELTA );
		$this->assertEqualsWithDelta( ( 3.6 / 13.0 ) * 100.0, $bar->width(), self::DELTA );
		$this->assertSame( 40, $bar->min_width() );
	}

	/**
	 * A bar may not run past the right-hand edge of the track.
	 *
	 * @return void
	 */
	public function test_a_bar_is_clamped_to_the_track(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2026.9 ),
			Year::from_float( 2030.0 ),
			BarKind::Role
		);

		$expected_max = 100.0 - ( ( 12.9 / 13.0 ) * 100.0 );

		$this->assertEqualsWithDelta( $expected_max, $bar->max_width(), self::DELTA );
		$this->assertEqualsWithDelta( $expected_max, $bar->width(), self::DELTA );
		$this->assertLessThanOrEqual( 100.0, $bar->left() + $bar->width() );
	}

	/**
	 * A bar that ends before it starts has no width, rather than a negative one.
	 *
	 * One typo in the admin away, and a negative width renders a bar inverted
	 * instead of rendering it wrong.
	 *
	 * @return void
	 */
	public function test_an_inverted_span_has_no_width(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2020.0 ),
			Year::from_float( 2016.0 ),
			BarKind::Role
		);

		$this->assertSame( 0.0, $bar->width() );
		$this->assertEqualsWithDelta( ( 6.0 / 13.0 ) * 100.0, $bar->left(), self::DELTA );
	}

	/**
	 * A bar with no duration still has a floor to render at.
	 *
	 * @return void
	 */
	public function test_a_zero_length_bar_keeps_its_minimum(): void {
		$role = Geometry::for_the_design()->bar( Year::from_float( 2020.0 ), Year::from_float( 2020.0 ), BarKind::Role );
		$ship = Geometry::for_the_design()->bar( Year::from_float( 2020.0 ), Year::from_float( 2020.0 ), BarKind::Ship );

		$this->assertSame( 0.0, $role->width() );
		$this->assertSame( 64, $role->min_width() );
		$this->assertSame( 40, $ship->min_width() );
	}

	/**
	 * The CSS a bar produces, so Phase 6 does not have to invent a format.
	 *
	 * @return void
	 */
	public function test_it_renders_css(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2022.0 ),
			Year::from_float( 2026.4 ),
			BarKind::Role
		);

		$this->assertSame(
			'left:61.5385%;width:33.8462%;max-width:38.4615%;min-width:64px',
			$bar->style()
		);
	}

	/**
	 * A whole-number percentage does not lose its digits to trailing-zero trimming.
	 *
	 * @return void
	 */
	public function test_it_renders_whole_percentages_intact(): void {
		$bar = Geometry::for_the_design()->bar(
			Year::from_float( 2014.0 ),
			Year::from_float( 2030.0 ),
			BarKind::Ship
		);

		$this->assertSame( 'left:0%;width:100%;max-width:100%;min-width:40px', $bar->style() );
	}

	/**
	 * A track needs at least two years.
	 *
	 * @return void
	 */
	public function test_it_refuses_an_empty_track(): void {
		$this->expectException( InvalidArgumentException::class );

		new Geometry( 2026, 2026 );
	}

	/**
	 * A track may not run backwards.
	 *
	 * @return void
	 */
	public function test_it_refuses_an_inverted_track(): void {
		$this->expectException( InvalidArgumentException::class );

		new Geometry( 2026, 2014 );
	}

	/**
	 * A track may not sit outside what a Year can hold.
	 *
	 * @return void
	 */
	public function test_it_refuses_a_track_outside_the_calendar(): void {
		$this->expectException( InvalidArgumentException::class );

		new Geometry( 1800, 2026 );
	}

	/**
	 * A different track is measured against itself, not against the design's.
	 *
	 * @return void
	 */
	public function test_it_works_on_a_track_of_another_length(): void {
		$geometry = new Geometry( 2020, 2024 );

		$this->assertSame( 5, $geometry->span() );
		$this->assertEqualsWithDelta( 20.0, $geometry->position( Year::from_float( 2021.0 ) ), self::DELTA );
	}
}
