<?php
/**
 * Unit tests for the timeline's company grouping.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Core\Content\Timeline\Bar;
use DP\Core\Content\Timeline\BarKind;
use DP\Core\Content\Timeline\Lane;
use DP\Core\Content\Timeline\LaneGroup;
use DP\Core\Content\Tone;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Two roles at one company are one company, and only when they are adjacent.
 *
 * The defect: a company David worked at twice is two `dp_role` posts with the
 * same `post_title`, and the chart drew them as two independent lanes that each
 * printed the company name — which reads as two employers rather than as one
 * employer and a promotion.
 *
 * The rule that fixes it is small and the constraint on it is the interesting
 * part, which is why it is a class of its own with a test of its own, on the
 * host, with no WordPress between the assertion and the rule. **Only
 * consecutive lanes group.** Lane order is `menu_order` then post date, which is
 * the sequence David sets under Page Attributes, so the trigger is two posts he
 * has placed next to each other — visible in the admin, changeable there.
 * Gathering lanes that are *not* adjacent would silently reorder his record with
 * nothing on the page to say so, which is the thing ADR-0018 exists to forbid.
 *
 * The other half is that a company with one role is untouched: a run of one is
 * still a group here, `is_shared()` is false, and the renderer draws it exactly
 * as a lane has always been drawn.
 */
final class TimelineLaneGroupTest extends TestCase {

	/**
	 * How close two percentages must be to count as the same.
	 *
	 * @var float
	 */
	private const DELTA = 0.0001;

	/**
	 * Two adjacent lanes at one company become one group.
	 *
	 * @return void
	 */
	public function test_two_adjacent_lanes_at_one_company_become_one_group(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			)
		);

		$this->assertCount( 1, $groups );
		$this->assertTrue( $groups[0]->is_shared() );
		$this->assertCount( 2, $groups[0]->lanes );
		$this->assertSame( 'Aplyca', $groups[0]->org );
	}

	/**
	 * Three adjacent lanes become one group, not one group and a stray.
	 *
	 * @return void
	 */
	public function test_three_adjacent_lanes_become_one_group(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-3' ),
			)
		);

		$this->assertCount( 1, $groups );
		$this->assertCount( 3, $groups[0]->lanes );
	}

	/**
	 * Two lanes at one company with another job between them do not group.
	 *
	 * This is the assertion the whole class is shaped around. Gathering these
	 * would move a lane past one David deliberately put between them, and
	 * nothing in the editor or on the page would say that anything had been
	 * moved. ADR-0018: computation is visible or it does not happen.
	 *
	 * @return void
	 */
	public function test_lanes_at_one_company_with_another_between_them_do_not_group(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'Globant', 'dp-role-globant' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			)
		);

		$this->assertCount( 3, $groups );

		foreach ( $groups as $group ) {
			$this->assertFalse( $group->is_shared(), 'Nothing here is adjacent to anything with its own name.' );
		}

		$this->assertSame(
			array( 'Aplyca', 'Globant', 'Aplyca' ),
			array_map( static fn ( LaneGroup $group ): string => $group->org, $groups ),
			'And the order is untouched: this partitions, it does not sort.'
		);
	}

	/**
	 * A company with one role is a run of one, and says so.
	 *
	 * @return void
	 */
	public function test_a_company_with_one_role_is_a_run_of_one(): void {
		$lane   = $this->lane( 'Backbone Technology', 'dp-role-backbone-technology' );
		$groups = LaneGroup::consecutive( array( $lane ) );

		$this->assertCount( 1, $groups );
		$this->assertFalse( $groups[0]->is_shared() );
		$this->assertSame( $lane, $groups[0]->first );
	}

	/**
	 * Every lane comes back exactly once, in the order it arrived.
	 *
	 * @return void
	 */
	public function test_every_lane_comes_back_once_and_in_order(): void {
		$lanes = array(
			$this->lane( 'Backbone Technology', 'dp-role-backbone-technology' ),
			$this->lane( 'Aplyca', 'dp-role-aplyca' ),
			$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			$this->lane( 'Fanxie Lab', 'dp-role-fanxie-lab' ),
		);

		$flattened = array();

		foreach ( LaneGroup::consecutive( $lanes ) as $group ) {
			foreach ( $group->lanes as $lane ) {
				$flattened[] = $lane;
			}
		}

		$this->assertSame( $lanes, $flattened );
	}

	/**
	 * Grouping nothing is nothing, not a group with no lanes in it.
	 *
	 * @return void
	 */
	public function test_grouping_nothing_is_nothing(): void {
		$this->assertSame( array(), LaneGroup::consecutive( array() ) );
	}

	/**
	 * A blank company name never joins anything, however adjacent it is.
	 *
	 * Two half-finished posts are two posts somebody has not titled yet, and
	 * gathering them under an empty header would hide both behind nothing.
	 *
	 * @return void
	 */
	public function test_a_blank_company_name_never_groups(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( '', 'dp-role-1' ),
				$this->lane( '', 'dp-role-2' ),
			)
		);

		$this->assertCount( 2, $groups );
	}

	/**
	 * A trailing space is not a different company.
	 *
	 * It is invisible in the admin list, so it may not be the difference between
	 * one header and two. The comparison is still case-sensitive: "Aplyca" and
	 * "APLYCA" are two pieces of copy, and grouping them would mean this class
	 * choosing which of them to print.
	 *
	 * @return void
	 */
	public function test_a_stray_space_is_not_a_different_company(): void {
		$spaced = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca ', 'dp-role-aplyca' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			)
		);

		$cased = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'APLYCA', 'dp-role-aplyca-2' ),
			)
		);

		$this->assertCount( 1, $spaced );
		$this->assertCount( 2, $cased );
	}

	/**
	 * The group names itself after its first lane, so the id is stable.
	 *
	 * The header's name is what `aria-labelledby` on the group points at. It is
	 * built from an entry key, which WordPress already keeps unique per post,
	 * and prefixed so it cannot collide with one: entry keys are `dp-role-…` and
	 * `dp-ship-…`, and nothing WordPress generates begins `dp-tl-`.
	 *
	 * @return void
	 */
	public function test_the_group_names_itself_after_its_first_lane(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			)
		);

		$this->assertSame( 'dp-tl-group-dp-role-aplyca', $groups[0]->key );
	}

	/**
	 * A group spans from the earliest start to the latest end.
	 *
	 * Read off the lanes' own bars rather than off their dates, so the header
	 * cannot end up on a different scale from the rows underneath it — which is
	 * the whole of ADR-0014 said about one more element.
	 *
	 * The lanes here are deliberately out of chronological order: `menu_order` is
	 * David's and he may run the chart either way round, so "earliest" has to
	 * mean earliest and not first.
	 *
	 * @return void
	 */
	public function test_a_group_spans_from_the_earliest_start_to_the_latest_end(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca', $this->bar( 40.0, 20.0 ) ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2', $this->bar( 10.0, 15.0 ) ),
			)
		);

		$span = $groups[0]->bar;

		$this->assertNotNull( $span );
		$this->assertEqualsWithDelta( 10.0, $span->left(), self::DELTA );
		$this->assertEqualsWithDelta( 50.0, $span->width(), self::DELTA, '10% to 60% is fifty points of track.' );
		$this->assertEqualsWithDelta( 90.0, $span->max_width(), self::DELTA, 'Geometry states max-width as 100 - pos(start).' );
		$this->assertSame( BarKind::Role, $span->kind() );
	}

	/**
	 * A lane with no usable dates is skipped rather than counted as year zero.
	 *
	 * @return void
	 */
	public function test_a_lane_with_no_bar_does_not_drag_the_span_to_the_left(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca', null ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2', $this->bar( 30.0, 10.0 ) ),
			)
		);

		$span = $groups[0]->bar;

		$this->assertNotNull( $span );
		$this->assertEqualsWithDelta( 30.0, $span->left(), self::DELTA );
		$this->assertEqualsWithDelta( 10.0, $span->width(), self::DELTA );
	}

	/**
	 * A group in which nothing is dated has no bar, and no track to draw one in.
	 *
	 * @return void
	 */
	public function test_a_group_with_nothing_dated_has_no_bar(): void {
		$groups = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca', null ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2', null ),
			)
		);

		$this->assertNull( $groups[0]->bar );
	}

	/**
	 * A group takes the accent its lanes agree on, and nothing when they do not.
	 *
	 * A header drawn in a colour none of its own rows uses would be a fourth
	 * thing for the legend to explain.
	 *
	 * @return void
	 */
	public function test_a_group_takes_the_accent_its_lanes_agree_on(): void {
		$agreed = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca', null, Tone::Purple ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2', null, Tone::Purple ),
			)
		);

		$mixed = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca', null, Tone::Purple ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2', null, Tone::Gold ),
			)
		);

		$plain = LaneGroup::consecutive(
			array(
				$this->lane( 'Aplyca', 'dp-role-aplyca' ),
				$this->lane( 'Aplyca', 'dp-role-aplyca-2' ),
			)
		);

		$this->assertSame( Tone::Purple, $agreed[0]->accent );
		$this->assertNull( $mixed[0]->accent );
		$this->assertNull( $plain[0]->accent, 'No accent is an agreement too: they all take the default teal.' );
	}

	/**
	 * One lane, with only the fields grouping reads.
	 *
	 * @param string    $org    The organisation, which is the post title.
	 * @param string    $key    The entry key, which WordPress keeps unique.
	 * @param Bar|null  $bar    The computed bar, or null when the dates are unusable.
	 * @param Tone|null $accent An accent this lane owns.
	 * @return Lane
	 */
	private function lane( string $org, string $key, ?Bar $bar = null, ?Tone $accent = null ): Lane {
		return new Lane( 0, $key, $org, '', '', '', '', $accent, $bar, array() );
	}

	/**
	 * One role bar at a given position.
	 *
	 * @param float $left  Distance from the left of the track, in percent.
	 * @param float $width Bar width, in percent.
	 * @return Bar
	 */
	private function bar( float $left, float $width ): Bar {
		return new Bar( $left, $width, 100.0 - $left, BarKind::Role );
	}
}
