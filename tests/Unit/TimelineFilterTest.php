<?php
/**
 * Unit tests for the timeline's filter.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Core\Content\Timeline\Filter;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The three sentences at the bottom of `TimelineChart.dc.html`, asserted.
 *
 * ```
 * Everything -> all lanes, all ships
 * Roles      -> all lanes, ships hidden
 * Shipped    -> only lanes with ships
 * ```
 *
 * Three lines of specification that four things have to agree on: the server
 * render, the query-arg links, the front-end controller and the stylesheet.
 * They agree by asking this enum, which is why it is worth a test of its own —
 * and why the test is here, on the host, with no WordPress and no browser
 * between the assertion and the rule.
 *
 * The asymmetry is the part worth pinning. `Roles` hides the ships and keeps
 * every lane; `Shipped` drops the lanes rather than hiding their ships. Making
 * the two symmetrical is the obvious tidy-up and it is wrong: under `Roles` a
 * job with nothing shipped is still a job you held, and under `Shipped` a job
 * with nothing shipped has nothing to show.
 */
final class TimelineFilterTest extends TestCase {

	/**
	 * Everything shows every lane and every ship.
	 *
	 * @return void
	 */
	public function test_everything_hides_nothing(): void {
		$filter = Filter::Everything;

		$this->assertTrue( $filter->shows_lane( true ) );
		$this->assertTrue( $filter->shows_lane( false ) );
		$this->assertTrue( $filter->shows_ships() );
		$this->assertTrue( $filter->is_default() );
	}

	/**
	 * Roles keeps every lane and hides what came out of them.
	 *
	 * @return void
	 */
	public function test_roles_keeps_every_lane_and_hides_the_ships(): void {
		$filter = Filter::Roles;

		$this->assertTrue( $filter->shows_lane( true ), 'A lane that shipped something is still a role.' );
		$this->assertTrue( $filter->shows_lane( false ), 'A lane that shipped nothing is still a role.' );
		$this->assertFalse( $filter->shows_ships() );
		$this->assertFalse( $filter->is_default() );
	}

	/**
	 * Shipped drops the lanes nothing came out of, and keeps their ships.
	 *
	 * @return void
	 */
	public function test_shipped_drops_the_lanes_with_nothing_under_them(): void {
		$filter = Filter::Shipped;

		$this->assertTrue( $filter->shows_lane( true ) );
		$this->assertFalse( $filter->shows_lane( false ) );
		$this->assertTrue( $filter->shows_ships(), 'Hiding the ships under Shipped would leave the filter showing nothing.' );
		$this->assertFalse( $filter->is_default() );
	}

	/**
	 * A query arg names a filter, in any case and with any surrounding space.
	 *
	 * @return void
	 */
	public function test_a_request_value_resolves_to_the_filter_it_names(): void {
		$this->assertSame( Filter::Everything, Filter::from_request( 'everything' ) );
		$this->assertSame( Filter::Roles, Filter::from_request( 'roles' ) );
		$this->assertSame( Filter::Shipped, Filter::from_request( 'shipped' ) );
		$this->assertSame( Filter::Shipped, Filter::from_request( ' SHIPPED ' ) );
	}

	/**
	 * Anything else is the default, not an error.
	 *
	 * The value arrives in a query string, where a stale bookmark, a truncated
	 * link or somebody's curiosity is ordinary. Showing the whole record is the
	 * right answer to all three; a 400 would be theatre.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_value_falls_back_to_everything(): void {
		foreach ( array( '', 'ships', 'role', 'EVERYTHING!', '0', 'null', '../../etc/passwd' ) as $value ) {
			$this->assertSame(
				Filter::Everything,
				Filter::from_request( $value ),
				sprintf( '"%s" should have fallen back to the default.', $value )
			);
		}
	}

	/**
	 * The pill row is the three filters, in the design's order, once each.
	 *
	 * @return void
	 */
	public function test_the_pill_row_is_the_whole_enum_in_order(): void {
		$this->assertSame(
			array( Filter::Everything, Filter::Roles, Filter::Shipped ),
			Filter::pills()
		);

		$this->assertCount( count( Filter::cases() ), Filter::pills(), 'A filter that is not a pill is a filter nobody can reach.' );
		$this->assertSame( Filter::Everything, Filter::default_filter() );
	}

	/**
	 * Exactly one filter is the default, and it is the one a bare URL shows.
	 *
	 * @return void
	 */
	public function test_only_one_filter_is_the_default(): void {
		$defaults = array_filter( Filter::cases(), static fn ( Filter $filter ): bool => $filter->is_default() );

		$this->assertSame( array( Filter::Everything ), array_values( $defaults ) );
	}
}
