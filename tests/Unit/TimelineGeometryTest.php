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
	 * Every label begins exactly where its own year begins.
	 *
	 * This is the invariant the stylesheet has to hold, said in the one place
	 * that can state it as arithmetic. `.dp-tl-years` is a grid of `span()`
	 * equal columns, so the `n`th label's left edge is `n / span` of the track;
	 * `position()` of the `n`th labelled year is `((first + n) - first) / span`,
	 * which is the same number. The design's own axis is not: it spreads the
	 * labels with `justify-content: space-between`, which divides by
	 * `span() - 1` and puts the last label at 100% where its year is at 92.3%.
	 *
	 * Asserted over four tracks rather than one, because a formula that is only
	 * checked at thirteen years is a formula that is checked once.
	 *
	 * @return void
	 */
	public function test_each_year_label_starts_where_its_own_year_does(): void {
		foreach ( array( array( 2014, 2026 ), array( 2014, 2027 ), array( 2020, 2024 ), array( 1999, 2000 ) ) as $track ) {
			$geometry = new Geometry( $track[0], $track[1] );
			$labels   = $geometry->year_labels();
			$span     = $geometry->span();

			$this->assertCount( $span, $labels, 'A label per whole year, which is what the columns count.' );

			foreach ( $labels as $index => $year ) {
				$this->assertEqualsWithDelta(
					( $index / $span ) * 100.0,
					$geometry->position( Year::from_float( (float) $year ) ),
					self::DELTA,
					sprintf(
						'Label %d ("%d") on the %d-%d track: the grid puts its left edge at %.4f%%.',
						$index,
						$year,
						$track[0],
						$track[1],
						( $index / $span ) * 100.0
					)
				);
			}

			$this->assertEqualsWithDelta(
				0.0,
				$geometry->position( Year::from_float( (float) $labels[0] ) ),
				self::DELTA,
				'The first label is flush with the left edge of the track.'
			);

			$this->assertEqualsWithDelta(
				( ( $span - 1 ) / $span ) * 100.0,
				$geometry->position( Year::from_float( (float) $labels[ $span - 1 ] ) ),
				self::DELTA,
				'The last label occupies the final slot; it does not terminate the axis.'
			);
		}
	}

	/**
	 * The design's axis and the design's bars disagree, and by how much.
	 *
	 * The defect ADR-0014 fixes, pinned as a number so that the ADR's arithmetic
	 * has something standing behind it. `space-between` divides by twelve
	 * intervals; `pos()` divides by thirteen years.
	 *
	 * @return void
	 */
	public function test_the_designs_own_axis_is_on_a_different_scale_from_its_bars(): void {
		$geometry = Geometry::for_the_design();
		$last     = $geometry->span() - 1;

		$spread = ( $last / (float) ( $geometry->span() - 1 ) ) * 100.0;
		$actual = $geometry->position( Year::from_float( 2026.0 ) );

		$this->assertEqualsWithDelta( 100.0, $spread, self::DELTA, 'space-between puts the last label at the far edge.' );
		$this->assertEqualsWithDelta( 92.3076923, $actual, 0.0000001, 'pos(2026) is twelve thirteenths.' );
		$this->assertEqualsWithDelta( 7.6923077, $spread - $actual, 0.0000001, 'The two scales are 7.7% apart at the right-hand edge.' );

		// And what David saw: a role running to May 2026 stops short of a label
		// that the design has already moved to 100%.
		$this->assertEqualsWithDelta(
			95.3846154,
			$geometry->position( Year::from_float( 2026.4 ) ),
			0.0000001,
			'A role running to 2026.4 ends at 95.4%, which is why it read as ending in 2025.'
		);
	}

	/**
	 * An unpinned track ends where the calendar is, not where the design stopped.
	 *
	 * @return void
	 */
	public function test_an_unpinned_track_ends_at_the_current_year(): void {
		$this->assertSame(
			range( Geometry::DESIGN_FIRST_YEAR, 2026 ),
			Geometry::through( null, null, 2026 )->year_labels(),
			'In 2026 the default track is the design\'s, which is why this change is invisible today.'
		);

		$this->assertSame(
			range( Geometry::DESIGN_FIRST_YEAR, 2031 ),
			Geometry::through( null, null, 2031 )->year_labels(),
			'firstYear stays the design\'s 2014; only the far end follows the clock.'
		);
	}

	/**
	 * A pinned track is honoured, in both directions.
	 *
	 * `lastYear` is how David holds the axis still, and also how he runs it past
	 * the present for something already scheduled. Both are the same attribute.
	 *
	 * @return void
	 */
	public function test_a_pinned_track_is_left_alone(): void {
		$this->assertSame( range( 2014, 2026 ), Geometry::through( null, 2026, 2031 )->year_labels() );
		$this->assertSame( range( 2014, 2035 ), Geometry::through( null, 2035, 2031 )->year_labels() );
		$this->assertSame( range( 2018, 2022 ), Geometry::through( 2018, 2022, 2031 )->year_labels() );
	}

	/**
	 * A track nobody could draw is resolved, never thrown.
	 *
	 * This runs on a public page. An inverted pair — David typing 2010 into a
	 * block that starts in 2014 — used to fall back to the design's whole track,
	 * which threw away the half of his intent that was answerable. Now the pin
	 * that cannot be honoured is discarded and the one that can is kept: the
	 * track still begins where he said, and ends where the default would.
	 *
	 * @return void
	 */
	public function test_a_degenerate_track_is_resolved_rather_than_thrown(): void {
		$this->assertSame( range( 2014, 2031 ), Geometry::through( null, 2010, 2031 )->year_labels() );
		$this->assertSame( range( 2020, 2031 ), Geometry::through( 2020, 2010, 2031 )->year_labels() );
		$this->assertSame( range( 2026, 2026 + 1 ), Geometry::through( 2026, 2026, 2026 )->year_labels() );

		// A first year past the present still gets a track, because a chart with
		// no axis is worse than a chart of two years.
		$this->assertSame( range( 2040, 2041 ), Geometry::through( 2040, null, 2026 )->year_labels() );
	}

	/**
	 * Nothing a block or a clock can hold makes this throw.
	 *
	 * @return void
	 */
	public function test_nothing_a_block_can_carry_can_fatal_a_page(): void {
		$wild = array( null, PHP_INT_MIN, PHP_INT_MAX, -1, 0, 1899, 1900, 2026, 2200, 2201 );

		foreach ( $wild as $first ) {
			foreach ( $wild as $last ) {
				foreach ( array( PHP_INT_MIN, 1899, 2026, 2201, PHP_INT_MAX ) as $now ) {
					$geometry = Geometry::through( $first, $last, $now );

					$this->assertGreaterThanOrEqual(
						2,
						$geometry->span(),
						sprintf( 'through(%s, %s, %d) produced a track of one year.', var_export( $first, true ), var_export( $last, true ), $now )
					);
				}
			}
		}
	}

	/**
	 * The span, and every bar with it, moves on 1 January.
	 *
	 * The reason `lastYear` had to stop defaulting to 2026: on the last day of
	 * the year a role marked "now" reaches 96.9% of a thirteen-year track, and
	 * on the next morning the track is fourteen years long, the same role reaches
	 * 90%, and the new label sits at 92.9% — ahead of it, which is what "the year
	 * has not happened yet" should look like.
	 *
	 * @return void
	 */
	public function test_the_span_moves_at_a_year_boundary(): void {
		$before = Geometry::through( null, null, 2026 );
		$after  = Geometry::through( null, null, 2027 );

		$this->assertSame( 13, $before->span() );
		$this->assertSame( 14, $after->span() );

		$now = Year::from_float( 2026.6 );

		$this->assertEqualsWithDelta( 96.9230769, $before->position( $now ), 0.0000001 );
		$this->assertEqualsWithDelta( 90.0, $after->position( $now ), 0.0000001 );

		// Its own year's tick, on both sides of midnight. The bar reaches it.
		$this->assertGreaterThan( $before->position( Year::from_float( 2026.0 ) ), $before->position( $now ) );
		$this->assertGreaterThan( $after->position( Year::from_float( 2026.0 ) ), $after->position( $now ) );

		// And the tick for the year that has only just started is ahead of it.
		$this->assertLessThan( $after->position( Year::from_float( 2027.0 ) ), $after->position( $now ) );
	}

	/**
	 * A role dated into the future clamps; it does not stretch the track.
	 *
	 * The decision, and it is a decision rather than an omission: the track is
	 * something David states, and `lastYear` is where he states it. Reading the
	 * furthest end date out of the content instead would mean one post silently
	 * rescaling every bar on the chart, with nothing on the page to say which
	 * post did it — and it would cost a second query, because the geometry is
	 * built before the lanes are read. `position()` already clamps, so a role
	 * running past the end draws to the final edge, which is the same thing the
	 * design does to a role that starts before the track.
	 *
	 * @return void
	 */
	public function test_a_future_dated_role_clamps_to_the_end_of_the_track(): void {
		$geometry = Geometry::through( null, null, 2026 );

		$this->assertEqualsWithDelta( 100.0, $geometry->position( Year::from_float( 2030.0 ) ), self::DELTA );

		$bar = $geometry->bar( Year::from_float( 2024.0 ), Year::from_float( 2030.0 ), BarKind::Role );

		$this->assertStringContainsString( 'left:76.9231%', $bar->style() );
		$this->assertStringContainsString( 'width:23.0769%', $bar->style() );
		$this->assertStringContainsString( 'max-width:23.0769%', $bar->style(), 'It stops at the edge rather than past it.' );
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
