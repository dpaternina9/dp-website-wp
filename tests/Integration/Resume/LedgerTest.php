<?php
/**
 * Integration tests for the `dp/resume-ledger` block.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Resume;

use DP\Core\Content\PostTypes;
use DP\Core\Content\Timeline\BarKind;
use DP\Core\Content\Timeline\Geometry;
use DP\Core\Content\Year;
use DP\Core\Resume\Ledger;
use WP_Block_Type_Registry;

/**
 * Experience, newest first, out of the same record the timeline draws.
 *
 * The design's own note is that the résumé and the timeline are two views of one
 * thing — "the interactive version of this … is the timeline" — so the ledger
 * reads through `Chart` rather than querying for itself. The two assertions that
 * matter are therefore about agreement and about order: the same roles the chart
 * shows, and the one thing the ledger deliberately does differently, which is
 * that a chronology runs oldest first and a résumé runs newest first
 * (`LANES.slice().sort((a, b) => b.start - a.start)`).
 */
final class LedgerTest extends ResumeTestCase {

	/**
	 * The block is registered under the name the template uses.
	 *
	 * @return void
	 */
	public function test_the_block_is_registered(): void {
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( Ledger::BLOCK_NAME ),
			'dp/resume-ledger is not registered; Plugin::register() is where it is attached.'
		);
	}

	/**
	 * With no record there is nothing to draw, and no empty shell either.
	 *
	 * @return void
	 */
	public function test_an_empty_record_renders_nothing(): void {
		$this->assertSame( '', $this->ledger()->render() );
	}

	/**
	 * Roles come out newest first, against the chart's own order.
	 *
	 * @return void
	 */
	public function test_roles_are_listed_newest_first(): void {
		$this->seed_role( 'Backbone Technology', 2014.0, '2014-01-01 00:00:00' );
		$this->seed_role( 'MonsterInsights', 2020.0, '2020-01-01 00:00:00' );
		$this->seed_role( 'Fanxie Lab', 2024.4, '2024-05-01 00:00:00' );

		$html = $this->ledger()->render();

		$this->assertSame(
			array( 'Fanxie Lab', 'MonsterInsights', 'Backbone Technology' ),
			$this->organisations( $html )
		);
	}

	/**
	 * The order is the start year, not the order they were typed in.
	 *
	 * @return void
	 */
	public function test_the_order_is_the_start_year_not_the_row_order(): void {
		$this->seed_role( 'Newest', 2024.0, '2014-01-01 00:00:00' );
		$this->seed_role( 'Oldest', 2014.0, '2024-01-01 00:00:00' );

		$this->assertSame( array( 'Newest', 'Oldest' ), $this->organisations( $this->ledger()->render() ) );
	}

	/**
	 * A role prints its title, its range, its detail and its stack.
	 *
	 * @return void
	 */
	public function test_a_role_prints_the_fields_the_design_shows(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );

		$html = $this->ledger()->render();

		$this->assertStringContainsString( 'Placeholder title', $html );
		$this->assertStringContainsString( 'MonsterInsights', $html );
		$this->assertStringContainsString( '2020 — 2022', $html );
		$this->assertStringContainsString( 'Placeholder role description.', $html );
		$this->assertStringContainsString( 'PHP · JS', $html );
	}

	/**
	 * The things that shipped out of a role are listed under it.
	 *
	 * @return void
	 */
	public function test_ships_are_listed_under_the_role_they_came_from(): void {
		$role = $this->seed_role( 'MonsterInsights', 2020.0 );

		$this->seed_ship( 'Natural-language queries', $role, '2021 — 2022' );

		$html = $this->ledger()->render();

		$this->assertStringContainsString( 'dp-ledger-ships', $html );
		$this->assertStringContainsString( 'Natural-language queries', $html );
		$this->assertStringContainsString( '2021 — 2022', $html );
	}

	/**
	 * A role with nothing under it does not get an empty list.
	 *
	 * @return void
	 */
	public function test_a_role_with_no_ships_has_no_list(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );

		$this->assertStringNotContainsString( 'dp-ledger-ships', $this->ledger()->render() );
	}

	/**
	 * A ship hanging off nothing is not shown loose.
	 *
	 * "Every project hangs off the job it came from" is the design's sentence,
	 * and a project with no job has nowhere on this page to be.
	 *
	 * @return void
	 */
	public function test_a_ship_with_no_role_is_not_listed(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );
		$this->seed_ship( 'An orphan', 0 );

		$this->assertStringNotContainsString( 'An orphan', $this->ledger()->render() );
	}

	/**
	 * The section head comes from the block's attributes.
	 *
	 * @return void
	 */
	public function test_the_heading_and_meta_come_from_the_attributes(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );

		$html = $this->ledger()->render(
			array(
				'heading' => 'Experience',
				'meta'    => '2014 — now',
			)
		);

		$this->assertStringContainsString( '<h2 class="dp-section-head-heading">Experience</h2>', $html );
		$this->assertStringContainsString( '2014 — now', $html );
	}

	/**
	 * With no meta there is no empty line under the heading.
	 *
	 * @return void
	 */
	public function test_an_empty_meta_line_is_omitted(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );

		$this->assertStringNotContainsString(
			'dp-section-head-meta',
			$this->ledger()->render( array( 'heading' => 'Experience' ) )
		);
	}

	/**
	 * Everything printed is escaped at the point of output.
	 *
	 * The meta is already sanitised on the way in — `register_post_meta()` gives
	 * each of these fields a sanitiser, so markup never reaches the database in
	 * the first place. Escaping here is the second half of that, and the way to
	 * see it working is a character sanitisation leaves alone: an ampersand
	 * comes back as an entity, which only `esc_html()` does.
	 *
	 * @return void
	 */
	public function test_content_is_escaped_on_the_way_out(): void {
		$role = $this->seed_role( 'Fanxie Lab', 2020.0 );

		update_post_meta( $role, 'dp_detail', 'Agency platform & ops' );
		update_post_meta( $role, 'dp_stack', 'PHP & JS' );

		$html = $this->ledger()->render();

		$this->assertStringContainsString( 'Agency platform &amp; ops', $html );
		$this->assertStringContainsString( 'PHP &amp; JS', $html );
	}

	/**
	 * Markup does not survive the write, let alone reach the page.
	 *
	 * @return void
	 */
	public function test_markup_in_the_record_never_reaches_the_page(): void {
		$role = $this->seed_role( 'Fanxie Lab', 2020.0 );

		update_post_meta( $role, 'dp_detail', 'Detail with <em>markup</em> and a <script>alert(1)</script> in it.' );

		$html = $this->ledger()->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<em>', $html );
	}

	/**
	 * A draft role is not on the page.
	 *
	 * @return void
	 */
	public function test_an_unpublished_role_is_not_listed(): void {
		$this->seed_role( 'MonsterInsights', 2020.0 );

		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'dp_role',
				'post_title'  => 'Somewhere I have not written up yet',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $draft );
		$this->assertStringNotContainsString( 'Somewhere I have not written up yet', $this->ledger()->render() );
	}

	/**
	 * A line break in a detail survives to the page here too.
	 *
	 * `nl2br( esc_html( … ) )`, in that order: the breaks David typed are breaks,
	 * and the only markup that can reach the page is the `<br />` `nl2br()` added
	 * after everything else had already been escaped. `test_content_is_escaped_on_the_way_out()`
	 * above is the other half of the pair.
	 *
	 * @return void
	 */
	public function test_a_line_break_in_a_detail_survives_to_the_page(): void {
		$role = $this->seed_role( 'Fanxie Lab', 2020.0 );

		remove_all_filters( 'sanitize_post_meta_dp_detail' );
		remove_all_filters( 'sanitize_post_meta_dp_detail_for_' . PostTypes::ROLE );

		update_post_meta( $role, 'dp_detail', "First line.\nSecond & last <b>line</b>." );

		$html = $this->ledger()->render();

		$this->assertStringContainsString( 'First line.<br />' . "\n" . 'Second &amp; last', $html );
		$this->assertStringContainsString( '&lt;b&gt;line&lt;/b&gt;', $html );
	}

	/**
	 * The résumé and the chart agree about a role that has not ended.
	 *
	 * Both read the record through one `Chart`, and both now hand it the same
	 * answer to "what is today". If they answered it from two clocks — or if one
	 * of them did not answer it at all — the résumé and the timeline could
	 * disagree about whether the current job has finished, which is exactly the
	 * disagreement reading through `Chart` exists to prevent.
	 *
	 * @return void
	 */
	public function test_the_ledger_and_the_chart_agree_about_an_ongoing_role(): void {
		$role = $this->seed_role( 'Fanxie Lab', 2020.0 );

		update_post_meta( $role, 'dp_end', 0.0 );

		$today = Year::from_year_month( 2026, 9 );
		$lanes = $this->ledger( $today )->lanes();

		$this->assertCount( 1, $lanes );

		$bar = $lanes[0]->bar;

		$this->assertNotNull( $bar, 'A role David is still in is a role with a bar.' );

		$expected = ( new Geometry( Geometry::DESIGN_FIRST_YEAR, Geometry::DESIGN_LAST_YEAR ) )
			->bar( Year::from_float( 2020.0 ), $today, BarKind::Role );

		$this->assertSame( $expected->style(), $bar->style() );
	}

	/**
	 * The block under test.
	 *
	 * `$today` is where an unfinished role's bar ends, handed to the same `Chart`
	 * the timeline hands it to. Injected rather than read from a clock, for the
	 * reason `Geometry::through()`'s year is.
	 *
	 * @param Year|null $today Where an unfinished role runs to, or null to read the clock.
	 * @return Ledger
	 */
	private function ledger( ?Year $today = null ): Ledger {
		return new Ledger( dirname( __DIR__, 3 ) . '/plugins/dp-core', $today );
	}

	/**
	 * The organisations printed, in the order they appear.
	 *
	 * @param string $html The rendered ledger.
	 * @return list<string>
	 */
	private function organisations( string $html ): array {
		preg_match_all( '~<p class="dp-ledger-org">(.*?)</p>~s', $html, $matches );

		return array_map( 'html_entity_decode', $matches[1] );
	}
}
