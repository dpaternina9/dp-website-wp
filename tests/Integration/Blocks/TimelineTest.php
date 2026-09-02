<?php
/**
 * Integration tests for `dp/timeline`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Core\Blocks\Timeline;
use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Timeline\Bar;
use DP\Core\Content\Timeline\BarKind;
use DP\Core\Content\Timeline\Geometry;
use DP\Core\Content\Year;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * The chart, rendered, in every mode and under every filter.
 *
 * The three modes are container queries, so a rendered-markup test cannot see
 * which one a reader is in — and that is exactly the claim worth asserting.
 * There is **one** markup: the year track, the chevron and the swipe hint are
 * all in it, always, and the stylesheet decides which of them is drawn. A test
 * that only checked the markup would miss half of that, so the second half of
 * this file reads `components.css` and asserts the three container queries
 * carry the design's own numbers. The two together are the mode coverage.
 *
 * The filter is the other way round: it is a server render, three times over,
 * and `[hidden]` is what a filtered-out row carries. That is not a convenience.
 * `[hidden]` is honoured by every user agent's own stylesheet, so the filter
 * works with the scripts off; and because the whole record is still in the
 * document, the controller can switch filter without a page load. Rendering
 * only the visible rows would make the plain version correct and the upgraded
 * one impossible.
 *
 * `WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`, so the
 * content model is re-registered in `set_up()`. Without it every `get_post_meta()`
 * below returns nothing and the suite passes against an empty chart (ADR-0003).
 */
final class TimelineTest extends WP_UnitTestCase {

	/**
	 * The lane with an accent, a job title and two shipped things.
	 *
	 * @var int
	 */
	private int $accented = 0;

	/**
	 * A lane with nothing under it.
	 *
	 * @var int
	 */
	private int $bare = 0;

	/**
	 * The shipped thing that carries a write-up.
	 *
	 * @var int
	 */
	private int $ship = 0;

	/**
	 * The post the shipped thing links to.
	 *
	 * @var int
	 */
	private int $writeup = 0;

	/**
	 * Re-register the content model and build the fixture.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();

		$writeup = self::factory()->post->create(
			array(
				'post_title'  => 'How the queries got written',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $writeup );

		$this->writeup = $writeup;

		$this->bare     = $this->seed_role( 'Backbone Technology', 'Developer', 2014.0, 2016.0, '2014 — 2016', '' );
		$this->accented = $this->seed_role( 'Fanxie Lab', 'CTO & founder', 2016.0, 2026.6, '2016 — now', 'pink' );

		$this->ship = $this->seed_ship( 'Kiveo', $this->accented, 2023.0, 2026.6, '2023 — now', $this->writeup );

		$this->seed_ship( 'Agency platform & ops', $this->accented, 2024.0, 2026.6, '2024 — now', 0 );

		$this->go_to( home_url( '/' ) );
	}

	/**
	 * Forget any filter a test asked for.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_GET[ Timeline::FILTER_ARG ], $_GET[ Timeline::OPEN_ARG ] );

		parent::tear_down();
	}

	/**
	 * The block is registered, server-rendered, and reachable through `do_blocks()`.
	 *
	 * @return void
	 */
	public function test_the_block_is_registered_and_renders_from_content(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Timeline::BLOCK_NAME );

		$this->assertNotNull( $type, 'dp/timeline is not registered. Plugin::register() is where the line goes.' );
		$this->assertTrue( $type->is_dynamic(), 'The chart is read from the database on every request; a static block could not be.' );

		$html = do_blocks( '<!-- wp:dp/timeline /-->' );

		$this->assertStringContainsString( 'class="dp-timeline', $html );
		$this->assertStringContainsString( 'id="' . Timeline::ROOT_ID . '"', $html );
	}

	/**
	 * One markup carries everything all three modes need.
	 *
	 * A server render cannot know a container's width. So the track, the
	 * chevron the stack mode draws instead of it, the year axis and the scroll
	 * mode's swipe hint are all present in every render, and the stylesheet
	 * decides which are visible. Anything else would need JavaScript to decide
	 * the layout, which is what the design's own closing note rules out.
	 *
	 * @return void
	 */
	public function test_one_markup_carries_all_three_modes(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'dp-tl-track', $html, 'Bars mode needs the track.' );
		$this->assertStringContainsString( 'dp-tl-chevron', $html, 'Stack mode needs the chevron.' );
		$this->assertStringContainsString( 'dp-tl-swipe', $html, 'Scroll mode needs the swipe hint.' );
		$this->assertStringContainsString( 'dp-tl-years', $html, 'Bars and scroll modes need the axis.' );

		$this->assertSame(
			13,
			substr_count( $html, '<span>20' ),
			'The axis is thirteen whole years, 2014 through 2026 — Geometry::span(), not twelve intervals.'
		);

		$this->assertStringContainsString(
			'<div class="dp-tl-years" aria-hidden="true"><span>2014</span>',
			$html,
			'The axis begins at the first labelled year, flush with the left edge of the track.'
		);

		$this->assertStringContainsString(
			'<span>2026</span></div>',
			$html,
			'And ends at the last one, which occupies the final slot rather than terminating the axis.'
		);
	}

	/**
	 * Stack is the default below 700px, and scroll is the variant.
	 *
	 * The Ledger chose stack. Scroll is implemented and reachable through the
	 * block's own attribute, and it is never what a reader gets by accident.
	 *
	 * @return void
	 */
	public function test_stack_is_the_default_and_scroll_is_asked_for(): void {
		$this->assertStringContainsString( 'is-mobile-stack', $this->render() );
		$this->assertStringNotContainsString( 'is-mobile-scroll', $this->render() );

		$scroll = $this->render( array( 'mobileMode' => 'scroll' ) );

		$this->assertStringContainsString( 'is-mobile-scroll', $scroll );
		$this->assertStringNotContainsString( 'is-mobile-stack', $scroll );

		$this->assertStringContainsString(
			'is-mobile-stack',
			$this->render( array( 'mobileMode' => 'sideways' ) ),
			'An unrecognised mode is the default, not a broken chart.'
		);
	}

	/**
	 * Every bar is Phase 3's geometry, written out as CSS and nothing else.
	 *
	 * The numbers are recomputed here from `Geometry` rather than typed in, so
	 * this asserts that the renderer transcribes what it is given. A percentage
	 * calculated anywhere in Phase 6 would disagree with one of these.
	 *
	 * @return void
	 */
	public function test_every_bar_is_the_geometry_it_was_handed(): void {
		$html     = $this->render();
		$geometry = Geometry::for_the_design();

		$backbone = $geometry->bar(
			\DP\Core\Content\Year::from_float( 2014.0 ),
			\DP\Core\Content\Year::from_float( 2016.0 ),
			\DP\Core\Content\Timeline\BarKind::Role
		);

		$kiveo = $geometry->bar(
			\DP\Core\Content\Year::from_float( 2023.0 ),
			\DP\Core\Content\Year::from_float( 2026.6 ),
			\DP\Core\Content\Timeline\BarKind::Ship
		);

		$this->assertStringContainsString( 'style="' . esc_attr( $backbone->style() ) . '"', $html );
		$this->assertStringContainsString( 'style="' . esc_attr( $kiveo->style() ) . '"', $html );

		$this->assertStringContainsString( 'min-width:10px', $html, 'A role bar may not render below 10px.' );
		$this->assertStringContainsString( 'min-width:8px', $html, 'A shipped bar may not render below 8px.' );
	}

	/**
	 * The axis ends where the calendar is, not where the design stopped.
	 *
	 * The bug this closes: `lastYear` defaulted to 2026 in `block.json`, a
	 * declared default is sent on every render, and nothing moved it. On
	 * 1 January 2027 the chart would still have ended at 2026 while a role marked
	 * "now" ran past the final tick — and it would have gone on doing that until
	 * somebody edited a block attribute.
	 *
	 * This is the one test in the file that reads the real clock, and it reads it
	 * through `wp_date()`, which is the site's timezone rather than the
	 * container's.
	 *
	 * @return void
	 */
	public function test_the_axis_ends_at_the_current_year(): void {
		$html = $this->render( array(), null );
		$year = (int) wp_date( 'Y' );

		$this->assertStringContainsString(
			'<span>' . $year . '</span></div>',
			$html,
			'The last label on the axis is this year.'
		);

		$this->assertSame(
			( $year - Geometry::DESIGN_FIRST_YEAR ) + 1,
			substr_count( $html, '<span>20' ),
			'One label per whole year from 2014 to now.'
		);
	}

	/**
	 * The track follows the clock, and `lastYear` is how David stops it.
	 *
	 * Both directions of the same attribute: pinning it holds the axis still,
	 * and pinning it past the present runs the axis out to something already
	 * scheduled. `firstYear` is untouched by either.
	 *
	 * @return void
	 */
	public function test_the_track_follows_the_clock_unless_a_pin_holds_it(): void {
		$moved = $this->render( array(), 2031 );

		$this->assertStringContainsString( '<span>2031</span></div>', $moved );
		$this->assertSame( 18, substr_count( $moved, '<span>20' ), '2014 through 2031 is eighteen whole years.' );

		$pinned = $this->render( array( 'lastYear' => 2026 ), 2031 );

		$this->assertStringContainsString( '<span>2026</span></div>', $pinned );
		$this->assertSame( 13, substr_count( $pinned, '<span>20' ), 'A pinned track ignores the clock.' );

		$ahead = $this->render( array( 'lastYear' => 2035 ), 2031 );

		$this->assertStringContainsString( '<span>2035</span></div>', $ahead, 'A pin may also run past the present.' );

		$later = $this->render( array( 'firstYear' => 2020 ), 2031 );

		$this->assertStringContainsString(
			'aria-hidden="true"><span>2020</span>',
			$later,
			'firstYear still says where the track begins.'
		);
	}

	/**
	 * An impossible pair draws a chart instead of a fatal.
	 *
	 * `Geometry` throws on an inverted track and this is a public page, so the
	 * block resolves rather than raises: the pin that cannot be honoured is
	 * dropped and the one that can — where the track begins — is kept.
	 *
	 * @return void
	 */
	public function test_an_impossible_track_still_draws(): void {
		$backwards = $this->render(
			array(
				'firstYear' => 2020,
				'lastYear'  => 2010,
			),
			2031
		);

		$this->assertStringContainsString( 'aria-hidden="true"><span>2020</span>', $backwards );
		$this->assertStringContainsString( '<span>2031</span></div>', $backwards );
		$this->assertStringContainsString( 'dp-tl-bar', $backwards, 'And it still has bars on it.' );

		$nonsense = $this->render(
			array(
				'firstYear' => 'the nineties',
				'lastYear'  => array( 2026 ),
			),
			2031
		);

		$this->assertStringContainsString( 'aria-hidden="true"><span>2014</span>', $nonsense );
		$this->assertStringContainsString( '<span>2031</span></div>', $nonsense );
	}

	/**
	 * A role that runs to now reaches the tick for the year it ends in.
	 *
	 * This is the whole of what David reported — "the lines don't go all the way
	 * to the end. In this case they read like they ended sometime in 2025 instead
	 * of going all the way to 2026." The bar was never wrong; the axis was on a
	 * different scale, so the label the bar was supposed to reach had been moved
	 * 7.7% to the right of the year it names.
	 *
	 * The axis is now `span()` equal columns, so the label for year `n` begins at
	 * `Geometry::position()` of that year — which makes this assertable in PHP,
	 * against the bar's own inline style, without measuring anything. The label
	 * positions themselves are pinned in the browser by
	 * `tests/e2e/timeline.spec.ts`.
	 *
	 * Asserted on both sides of a year boundary, because "its own tick" has to go
	 * on meaning the same thing when the track grows a column underneath it.
	 *
	 * @return void
	 */
	public function test_a_role_running_to_now_reaches_its_own_tick(): void {
		foreach ( array( 2026, 2027 ) as $now ) {
			$geometry = Geometry::through( null, null, $now );

			$bar = $geometry->bar(
				\DP\Core\Content\Year::from_float( 2016.0 ),
				\DP\Core\Content\Year::from_float( 2026.6 ),
				\DP\Core\Content\Timeline\BarKind::Role
			);

			$tick = $geometry->position( \DP\Core\Content\Year::from_float( 2026.0 ) );

			$this->assertGreaterThan(
				$tick,
				$bar->left() + $bar->width(),
				sprintf(
					'With the track ending in %d, a role running to mid-2026 stops at %.4f%% and the '
						. '"2026" label begins at %.4f%%. The bar has to reach past its own label.',
					$now,
					$bar->left() + $bar->width(),
					$tick
				)
			);

			$this->assertStringContainsString(
				'style="' . esc_attr( $bar->style() ) . '"',
				$this->render( array(), $now ),
				'And that is the bar the chart actually draws.'
			);
		}
	}

	/**
	 * Everything shows every lane and every ship.
	 *
	 * @return void
	 */
	public function test_the_everything_filter_hides_nothing(): void {
		$html = $this->render();

		$this->assertSame( 2, substr_count( $html, 'class="dp-tl-lane"' ), 'Two lanes, neither hidden.' );
		$this->assertSame( 0, substr_count( $html, 'class="dp-tl-lane" data-dp-lane' ) - 2 );
		$this->assertStringNotContainsString( 'hidden>', $html );
		$this->assertStringContainsString( 'data-dp-filter="everything"', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
	}

	/**
	 * Roles keeps both lanes and hides the rail under the one that shipped.
	 *
	 * @return void
	 */
	public function test_the_roles_filter_hides_the_ships_and_keeps_the_lanes(): void {
		$_GET[ Timeline::FILTER_ARG ] = 'roles';

		$html = $this->render();

		$this->assertSame( 2, substr_count( $html, '<div class="dp-tl-lane"' ), 'Both lanes are still lanes.' );
		$this->assertStringNotContainsString( '<div class="dp-tl-lane" data-dp-lane="dp-role-fanxie-lab" data-dp-ships="yes" hidden>', $html );
		$this->assertStringContainsString( '<div class="dp-tl-ships" hidden>', $html, 'The rail is hidden, not removed.' );
		$this->assertStringContainsString( 'Kiveo', $html, 'A hidden row is still in the document, which is what lets the script show it again without a page load.' );
		$this->assertStringContainsString( 'data-dp-filter="roles"', $html );
	}

	/**
	 * Shipped drops the lane nothing came out of.
	 *
	 * @return void
	 */
	public function test_the_shipped_filter_drops_the_lanes_with_nothing_under_them(): void {
		$_GET[ Timeline::FILTER_ARG ] = 'shipped';

		$html = $this->render();

		$this->assertStringContainsString(
			'data-dp-lane="dp-role-backbone-technology" data-dp-ships="no" hidden',
			$html,
			'A lane that shipped nothing has nothing to show under this filter.'
		);

		$this->assertStringContainsString( 'data-dp-lane="dp-role-fanxie-lab" data-dp-ships="yes">', $html );
		$this->assertStringNotContainsString( '<div class="dp-tl-ships" hidden>', $html, 'Hiding the ships here would leave the filter showing nothing.' );
	}

	/**
	 * An unrecognised filter shows the whole record rather than failing.
	 *
	 * @return void
	 */
	public function test_an_unknown_filter_shows_everything(): void {
		$_GET[ Timeline::FILTER_ARG ] = 'shipped-things-only-please';

		$this->assertStringContainsString( 'data-dp-filter="everything"', $this->render() );
	}

	/**
	 * The query arg opens the entries it names, so a deep link needs no script.
	 *
	 * @return void
	 */
	public function test_the_open_query_arg_renders_the_entries_open(): void {
		$_GET[ Timeline::OPEN_ARG ] = 'dp-ship-kiveo';

		$html = $this->render();

		$this->assertStringContainsString( 'id="dp-ship-kiveo" open>', $html );
		$this->assertSame( 1, substr_count( $html, ' open>' ), 'Only the entry the URL named.' );

		$_GET[ Timeline::OPEN_ARG ] = Timeline::OPEN_ALL;

		$all = $this->render();

		$this->assertSame( 4, substr_count( $all, ' open>' ), 'Two lanes and two shipped things.' );
		$this->assertStringContainsString( 'Collapse all', $all, 'With everything open the control offers the opposite.' );
		$this->assertStringNotContainsString( 'Expand all<', $all );
	}

	/**
	 * Several entries open at once, which is the whole point of `<details>`.
	 *
	 * @return void
	 */
	public function test_many_entries_open_at_once(): void {
		$_GET[ Timeline::OPEN_ARG ] = 'dp-ship-kiveo,dp-role-fanxie-lab';

		$html = $this->render();

		$this->assertStringContainsString( 'id="dp-ship-kiveo" open>', $html );
		$this->assertStringContainsString( 'id="dp-role-fanxie-lab" open>', $html );
		$this->assertStringNotContainsString( 'id="dp-role-backbone-technology" open>', $html );
	}

	/**
	 * Every row is a `<details>` with a `<summary>`, so the disclosure is native.
	 *
	 * @return void
	 */
	public function test_every_row_is_a_native_disclosure(): void {
		$html = $this->render();

		$this->assertSame( 4, substr_count( $html, '<details class="dp-tl-row' ) );
		$this->assertSame( 4, substr_count( $html, '<summary class="dp-tl-summary">' ) );
		$this->assertStringNotContainsString( 'role="button"', $html, 'A `summary` is already a disclosure button; saying so again is worse than not saying it.' );
		$this->assertStringNotContainsString( 'aria-expanded', $html, 'The open state of a `details` is reported by the element itself.' );
	}

	/**
	 * The filter is three links, not three buttons.
	 *
	 * `FilterPills.dc.html` settles it: "these are real links to filtered archive
	 * URLs, not JS tabs". So does CLAUDE.md §1.7. The extra control is a link for
	 * the same reason — with the scripts off it still has to expand every row.
	 *
	 * @return void
	 */
	public function test_the_filter_is_links_carrying_query_args(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'class="dp-filter-pills dp-tl-pills"', $html, 'The timeline reuses the pill row the archives already have.' );
		$this->assertStringContainsString( Timeline::FILTER_ARG . '=roles', $html );
		$this->assertStringContainsString( Timeline::FILTER_ARG . '=shipped', $html );
		$this->assertStringContainsString( Timeline::OPEN_ARG . '=' . Timeline::OPEN_ALL, $html );
		$this->assertStringNotContainsString( '<button', $html );

		foreach ( array( 'everything', 'roles', 'shipped' ) as $value ) {
			$this->assertStringContainsString( 'data-dp-filter="' . $value . '"', $html );
		}

		$this->assertStringContainsString( '#' . Timeline::ROOT_ID, $html, 'Following a filter link returns you to the chart, not to the top of the page.' );
	}

	/**
	 * A lane with its own accent earns a legend swatch, so its bar is explained.
	 *
	 * @return void
	 */
	public function test_an_accented_lane_earns_a_legend_swatch(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'dp-tl-swatch dp-tl-swatch-role is-accent-pink', $html );
		$this->assertStringContainsString( 'is-accent-pink" aria-hidden="true"></span>Fanxie Lab', $html );
		$this->assertStringContainsString( 'dp-tl-row-role is-accent-pink', $html, 'The bar takes its colour from a class, never from an inline hex.' );

		$this->assertSame(
			1,
			substr_count( $html, 'dp-tl-swatch dp-tl-swatch-role is-accent-' ),
			'One swatch per accent, not one per lane carrying it.'
		);
	}

	/**
	 * Nothing but geometry reaches the page as an inline style.
	 *
	 * @return void
	 */
	public function test_geometry_is_the_only_inline_style(): void {
		$html   = $this->render();
		$styles = array();

		preg_match_all( '~ style="([^"]*)"~', $html, $styles );

		$this->assertNotEmpty( $styles[1] );

		foreach ( $styles[1] as $style ) {
			$this->assertMatchesRegularExpression(
				'~^left:[\d.]+%;width:[\d.]+%;max-width:[\d.]+%;min-width:\d+px$~',
				$style,
				'CLAUDE.md §5: values live in the stylesheet. The four numbers of a bar are the one exception.'
			);
		}
	}

	/**
	 * A shipped thing links to its write-up only when there is one to link to.
	 *
	 * @return void
	 */
	public function test_the_writeup_link_appears_only_for_a_published_post(): void {
		$this->assertStringContainsString( (string) get_permalink( $this->writeup ), $this->render() );
		$this->assertSame( 1, substr_count( $this->render(), 'dp-tl-writeup' ), 'Only the shipped thing that has one.' );

		wp_update_post(
			array(
				'ID'          => $this->writeup,
				'post_status' => 'draft',
			)
		);

		$this->assertStringNotContainsString(
			'dp-tl-writeup',
			$this->render(),
			'An unpublished write-up is no link at all, not a link that 404s.'
		);
	}

	/**
	 * A shipped thing with no role is not on the chart.
	 *
	 * @return void
	 */
	public function test_a_ship_with_no_role_has_nowhere_to_hang(): void {
		$this->seed_ship( 'Orphaned project', 0, 2020.0, 2021.0, '2020 — 2021', 0 );

		$this->assertStringNotContainsString(
			'Orphaned project',
			$this->render(),
			'"Every project hangs off the job it came from" — a project with no job has nowhere on this chart to be.'
		);
	}

	/**
	 * Content is escaped on the way out, not only on the way in.
	 *
	 * The meta fields have `sanitize_callback`s, so markup typed into the admin
	 * never reaches the database in the first place. That is one gate, and it is
	 * not the one this asserts: a WXR import, a direct `$wpdb` write or a value
	 * stored before the field was registered all skip it, which is why
	 * CLAUDE.md §1.4 puts the escaping at the point of output. So the sanitiser
	 * is unhooked for one field here — reproducing exactly that history — and
	 * the renderer is asked to deal with what it finds.
	 *
	 * @return void
	 */
	public function test_content_is_escaped(): void {
		$hostile = $this->seed_role( 'Tools & Co. "the agency"', 'Tester <lead>', 2019.0, 2020.0, '2019 — 2020', '' );

		/*
		 * `register_post_meta()` hooks its sanitiser on the subtype-specific name
		 * when a post type is named, which is every field in this content model.
		 * Both are removed so the value lands in the database exactly as an
		 * import would leave it.
		 */
		remove_all_filters( 'sanitize_post_meta_dp_detail' );
		remove_all_filters( 'sanitize_post_meta_dp_detail_for_' . PostTypes::ROLE );

		update_post_meta( $hostile, 'dp_detail', 'Imported <b>markup</b>, and a <script>alert(1)</script>.' );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( 'Imported &lt;b&gt;markup&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Tools &amp; Co. &quot;the agency&quot;', $html );

		$this->assertStringContainsString(
			'&gt; which posts',
			$this->render_with_artifact( '> which posts grew last month?' ),
			'The artifact is a preformatted block, and a shell prompt is not markup.'
		);
	}

	/**
	 * Render the chart with one shipped thing carrying a given artifact.
	 *
	 * @param string $artifact The preformatted sample.
	 * @return string
	 */
	private function render_with_artifact( string $artifact ): string {
		update_post_meta( $this->ship, 'dp_artifact', $artifact );

		return $this->render();
	}

	/**
	 * With no roles there is no chart, and no empty card either.
	 *
	 * @return void
	 */
	public function test_a_chart_with_no_lanes_renders_nothing(): void {
		foreach ( array( $this->bare, $this->accented ) as $role ) {
			wp_delete_post( $role, true );
		}

		$this->assertSame( '', $this->render() );
	}

	/**
	 * The three modes are declared in the stylesheet, with the design's numbers.
	 *
	 * The markup cannot show which mode is drawn, because the mode is a
	 * container query. This is the other half of that: the thresholds and the
	 * measurements quoted from the LAYOUT NOTES block of
	 * `design-source/components/TimelineChart.dc.html`, asserted where they
	 * actually live.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_declares_three_modes_with_the_design_numbers(): void {
		$css = (string) file_get_contents( get_stylesheet_directory() . '/assets/css/components.css' );

		$this->assertStringContainsString(
			'container: dp-timeline / inline-size',
			$css,
			'digest §5.2: the threshold is the component\'s own width, never the viewport.'
		);

		$bars   = $this->at_rule( $css, '@container dp-timeline (width >= 700px)' );
		$narrow = $this->at_rule( $css, '@container dp-timeline (width < 700px)' );

		// Bars: label column 200px, rail 20px, bleed -16px, ships tinted more lightly.
		$this->assertStringContainsString( '--dp-tl-label-base: 200px', $bars );
		$this->assertStringContainsString( '--dp-tl-rail: 20px', $bars );
		$this->assertStringContainsString( '--dp-tl-bleed: 16px', $bars );
		$this->assertStringContainsString( '--dp-tl-tint: 2.5%', $bars );
		$this->assertStringContainsString( 'padding: clamp(20px, 3vw, 32px)', $bars );

		// Stack: the default, so its numbers are on the component itself.
		$this->assertStringContainsString( '--dp-tl-rail: 16px', $css );
		$this->assertStringContainsString( '--dp-tl-bleed: 12px', $css );
		$this->assertStringContainsString( '--dp-tl-tint: 4%', $css );
		$this->assertStringContainsString( 'padding: 4px 16px 24px', $css );

		// Scroll: 128px label column, 720px inner, and everything readable pinned.
		$this->assertStringContainsString( '--dp-tl-label-base: 128px', $narrow );
		$this->assertStringContainsString( 'min-width: 720px', $narrow );
		$this->assertStringContainsString( 'width: max(240px, calc(100cqw - 60px))', $narrow );
		$this->assertStringContainsString( 'overflow-x: auto', $narrow );

		// The ship label column gives the rail back, so both bars share one axis.
		$this->assertStringContainsString( '--dp-tl-label: calc(var(--dp-tl-label-base) - var(--dp-tl-rail))', $css );

		// The design's colour rules, as formulas rather than as values.
		$this->assertStringContainsString( 'color-mix(in srgb, var(--dp-tl-color) 38%, var(--bg-surface))', $css );
		$this->assertStringContainsString( 'box-shadow: 0 0 0 4px color-mix(in srgb, var(--dp-tl-color) 16%, transparent)', $css );
		$this->assertStringContainsString( 'color-mix(in srgb, var(--dp-white) var(--dp-tl-tint), transparent)', $css );

		// CLAUDE.md §1.7. The global backstop in base.css is not the only word.
		$this->assertMatchesRegularExpression(
			'~@media \(prefers-reduced-motion: reduce\) \{[^@]*\.dp-tl-panel \{\s*animation: none;~',
			$css
		);
	}

	/**
	 * The chart's own stylesheet reaches both the editor and the front end.
	 *
	 * @return void
	 */
	public function test_the_chart_is_styled_by_the_theme_not_by_the_plugin(): void {
		$plugin = dirname( __DIR__, 3 ) . '/plugins/dp-core';

		$sheets = glob( $plugin . '/blocks/timeline/*.css' );

		$this->assertSame(
			array(),
			is_array( $sheets ) ? $sheets : array(),
			'CLAUDE.md §2.1: the render callback is the plugin\'s, the appearance is the theme\'s.'
		);

		$this->assertArrayHasKey( 'dpaternina-components', \DP\Theme\Assets::stylesheets() );
	}

	/**
	 * Two adjacent roles at one company share one header.
	 *
	 * The defect, reported against the live chart: Aplyca appeared twice, once
	 * above the other, each row printing the company name again. That reads as
	 * two employers rather than as one employer and a promotion.
	 *
	 * The company is now printed once, on a header, and each role row is named
	 * by its own job title. `menu_order` is what makes the two adjacent, which is
	 * what makes them group — see `DP\Core\Content\Timeline\LaneGroup`.
	 *
	 * @return void
	 */
	public function test_two_adjacent_roles_at_one_company_share_one_header(): void {
		$this->seed_aplyca();

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<div class="dp-tl-group"' ), 'One company, one group.' );
		$this->assertSame( 1, substr_count( $html, 'class="dp-tl-group-head' ), 'And one header on it.' );

		$this->assertSame(
			1,
			substr_count( $html, '>Aplyca</span>' ),
			'The company is printed once, not once per role. This is the whole defect.'
		);

		$this->assertStringContainsString(
			'<span class="dp-tl-org" id="dp-tl-group-dp-role-aplyca">Aplyca</span>',
			$html,
			'The header names the group, and the group points at the header.'
		);

		$this->assertStringContainsString( '<span class="dp-tl-org">Full-Stack Developer</span>', $html );
		$this->assertStringContainsString( '<span class="dp-tl-org">Solutions Architect</span>', $html );

		$this->assertStringNotContainsString(
			'<span class="dp-tl-role">Solutions Architect</span>',
			$html,
			'Under a header the job title is the row\'s own name, not a second line beneath a repeated company.'
		);

		$this->assertSame(
			2,
			substr_count( $this->group_markup( $html ), '<div class="dp-tl-lane"' ),
			'Both roles are still lanes, and both are inside the group.'
		);
	}

	/**
	 * Three adjacent roles at one company share one header, not two.
	 *
	 * @return void
	 */
	public function test_three_adjacent_roles_share_one_header(): void {
		$this->seed_aplyca();
		$this->seed_role( 'Aplyca', 'Principal Architect', 2021.0, 2022.0, '2021 — 2022', '', 12 );

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<div class="dp-tl-group"' ) );
		$this->assertSame( 1, substr_count( $html, '>Aplyca</span>' ) );
		$this->assertSame( 3, substr_count( $this->group_markup( $html ), '<div class="dp-tl-lane"' ) );
	}

	/**
	 * A company with one role is drawn exactly as it was before any of this.
	 *
	 * Byte-identical, and asserted as bytes: the same lane is extracted from a
	 * chart with no group on it and from a chart with one, and the two strings
	 * have to be the same string. Every company on the live chart but Aplyca is
	 * this case, and none of them may move.
	 *
	 * @return void
	 */
	public function test_a_company_with_one_role_is_untouched(): void {
		$before = $this->lane_markup( $this->render(), 'dp-role-backbone-technology' );

		$this->seed_aplyca();

		$after = $this->lane_markup( $this->render(), 'dp-role-backbone-technology' );

		$this->assertSame( $before, $after, 'A lane that is not in a group is not a group and is not wrapped in one.' );

		$this->assertStringStartsWith(
			'div class="dp-tl-lane" data-dp-lane="dp-role-backbone-technology" data-dp-ships="no">'
				. '<details class="dp-tl-row dp-tl-row-role" id="dp-role-backbone-technology">'
				. '<summary class="dp-tl-summary"><span class="dp-tl-grid"><span class="dp-tl-label">'
				. '<span class="dp-tl-org">Backbone Technology</span>'
				. '<span class="dp-tl-role">Developer</span>',
			$before,
			'And the organisation is still the row\'s name, with the job title under it.'
		);
	}

	/**
	 * Two roles at one company with another job between them do not group.
	 *
	 * `menu_order` is David's. A derivation that reached past the job he put
	 * between them would be reordering his record with nothing in the editor or
	 * on the page to say that it had happened — ADR-0018.
	 *
	 * @return void
	 */
	public function test_non_adjacent_roles_at_one_company_do_not_group(): void {
		$this->seed_role( 'Aplyca', 'Full-Stack Developer', 2018.5, 2019.0, 'July — Dec 2018', '', 10 );
		$this->seed_role( 'Globant', 'Developer', 2019.0, 2020.0, '2019 — 2020', '', 11 );
		$this->seed_role( 'Aplyca', 'Solutions Architect', 2020.0, 2021.0, '2020 — 2021', '', 12 );

		$html = $this->render();

		$this->assertSame( 0, substr_count( $html, '<div class="dp-tl-group"' ) );
		$this->assertSame( 2, substr_count( $html, '<span class="dp-tl-org">Aplyca</span>' ), 'Two entries, because he said two entries.' );
		$this->assertSame( 5, substr_count( $html, '<div class="dp-tl-lane"' ) );
	}

	/**
	 * The header is a header row, and never a second disclosure.
	 *
	 * Nesting `<details>` would let a role row be open inside a closed parent and
	 * would break every `dp-open` link that points straight at one. So the number
	 * of disclosures on the chart is the number of roles and ships, grouped or
	 * not, and the header is none of them.
	 *
	 * @return void
	 */
	public function test_a_group_header_is_not_a_disclosure(): void {
		$this->seed_aplyca();

		$html = $this->render();

		$this->assertStringNotContainsString( '<details class="dp-tl-group', $html );
		$this->assertSame( 6, substr_count( $html, '<details class="dp-tl-row' ), 'Four from the fixture, two more roles, and no header.' );
		$this->assertSame( 6, substr_count( $html, '<summary class="dp-tl-summary">' ) );

		$_GET[ Timeline::OPEN_ARG ] = 'dp-role-aplyca-2';

		$this->assertStringContainsString(
			'id="dp-role-aplyca-2" open>',
			$this->render(),
			'A grouped role is still deep-linkable by its own key, unchanged.'
		);
	}

	/**
	 * A header whose every role row is hidden is hidden with them.
	 *
	 * @return void
	 */
	public function test_a_group_is_hidden_when_the_filter_hides_every_role_in_it(): void {
		$this->seed_aplyca();

		$this->assertStringContainsString(
			'<div class="dp-tl-group" role="group" aria-labelledby="dp-tl-group-dp-role-aplyca">',
			$this->render(),
			'Everything hides nothing.'
		);

		$_GET[ Timeline::FILTER_ARG ] = 'shipped';

		$this->assertStringContainsString(
			'<div class="dp-tl-group" role="group" aria-labelledby="dp-tl-group-dp-role-aplyca" hidden>',
			$this->render(),
			'Neither Aplyca role shipped anything, so the company heads nothing under this filter.'
		);
	}

	/**
	 * A header with one visible role in it stays, and the other row goes.
	 *
	 * @return void
	 */
	public function test_a_group_stays_when_one_of_its_roles_survives_the_filter(): void {
		$roles = $this->seed_aplyca();

		$this->seed_ship( 'Something they shipped', $roles[1], 2020.0, 2021.0, '2020 — 2021', 0 );

		$_GET[ Timeline::FILTER_ARG ] = 'shipped';

		$html = $this->render();

		$this->assertStringContainsString(
			'<div class="dp-tl-group" role="group" aria-labelledby="dp-tl-group-dp-role-aplyca">',
			$html
		);
		$this->assertStringContainsString( 'data-dp-lane="dp-role-aplyca" data-dp-ships="no" hidden', $html );
		$this->assertStringContainsString( 'data-dp-lane="dp-role-aplyca-2" data-dp-ships="yes">', $html );
	}

	/**
	 * The header spans its roles, and no role bar moves to make room.
	 *
	 * ADR-0014: the axis and the bars are one scale. The header's bar is the
	 * union of the bars below it — earliest start to latest end — computed from
	 * those bars rather than from the dates, so it cannot end up on a scale of
	 * its own. The role bars are recomputed here from `Geometry` and asserted
	 * unchanged, which is the half of this that matters.
	 *
	 * @return void
	 */
	public function test_the_header_spans_its_roles_without_moving_them(): void {
		$this->seed_aplyca();

		$html     = $this->render();
		$geometry = Geometry::for_the_design();

		$one = $geometry->bar( Year::from_float( 2019.5 ), Year::from_float( 2019.75 ), BarKind::Role );
		$two = $geometry->bar( Year::from_float( 2019.75 ), Year::from_float( 2021.0 ), BarKind::Role );

		$this->assertStringContainsString( 'style="' . esc_attr( $one->style() ) . '"', $html, 'The first role is where Geometry put it.' );
		$this->assertStringContainsString( 'style="' . esc_attr( $two->style() ) . '"', $html, 'And so is the second.' );

		$span = new Bar( $one->left(), ( $two->left() + $two->width() ) - $one->left(), 100.0 - $one->left(), BarKind::Role );

		$this->assertStringContainsString(
			'<span class="dp-tl-bar dp-tl-bar-group" style="' . esc_attr( $span->style() ) . '"></span>',
			$html,
			'The header runs from the earliest start to the latest end and nowhere else.'
		);

		$this->assertMatchesRegularExpression(
			'~^left:[\d.]+%;width:[\d.]+%;max-width:[\d.]+%;min-width:\d+px$~',
			$span->style(),
			'A header bar is geometry like any other, so it obeys the one inline-style rule too.'
		);
	}

	/**
	 * A grouped company earns one legend swatch, not one per role.
	 *
	 * @return void
	 */
	public function test_a_grouped_company_earns_one_swatch(): void {
		$this->seed_role( 'Aplyca', 'Full-Stack Developer', 2019.5, 2019.75, 'July — Dec 2019', 'purple', 10 );
		$this->seed_role( 'Aplyca', 'Solutions Architect', 2019.75, 2021.0, '2019 — 2021', 'purple', 11 );

		$html = $this->render();

		$this->assertSame(
			1,
			substr_count( $html, 'is-accent-purple" aria-hidden="true"></span>Aplyca' ),
			'The legend dedupes by accent, so two rows carrying one colour explain it once.'
		);

		$this->assertSame(
			2,
			substr_count( $html, 'dp-tl-swatch dp-tl-swatch-role is-accent-' ),
			'Pink for Fanxie Lab and purple for Aplyca. No third.'
		);

		$this->assertStringContainsString(
			'<div class="dp-tl-group-head is-accent-purple">',
			$html,
			'And the header is drawn in the colour its own rows agree on.'
		);
	}

	/**
	 * A run whose roles disagree about their accent gets the default.
	 *
	 * @return void
	 */
	public function test_a_group_whose_roles_disagree_about_colour_takes_the_default(): void {
		$this->seed_role( 'Aplyca', 'Full-Stack Developer', 2019.5, 2019.75, 'July — Dec 2019', 'purple', 10 );
		$this->seed_role( 'Aplyca', 'Solutions Architect', 2019.75, 2021.0, '2019 — 2021', 'gold', 11 );

		$this->assertStringContainsString(
			'<div class="dp-tl-group-head">',
			$this->render(),
			'A header drawn in a colour neither of its rows uses would be a fourth thing for the legend to explain.'
		);
	}

	/**
	 * A grouped row gives the rail back in the label column, twice over.
	 *
	 * This is the highest-risk part of the grouping change and it is CSS, so it
	 * is asserted where it lives. A group indents its lanes on a hairline rail
	 * exactly as a role indents its shipped things; `padding-left` moves the
	 * whole row right, so the label column beside it has to shrink by the same
	 * amount or the track — and every bar on it — slides off the year axis. A
	 * ship inside a grouped role is two rails in and gives back two.
	 *
	 * The design states the rule for ships in its own LAYOUT NOTES: "the ship
	 * label column subtracts that same padding back out so ship bars stay true to
	 * the year axis". Both modes that draw a track have to say it.
	 *
	 * @return void
	 */
	public function test_a_grouped_row_gives_the_rail_back_in_both_track_modes(): void {
		$css = (string) file_get_contents( get_stylesheet_directory() . '/assets/css/components.css' );

		$bars   = $this->at_rule( $css, '@container dp-timeline (width >= 700px)' );
		$narrow = $this->at_rule( $css, '@container dp-timeline (width < 700px)' );

		$modes = array(
			'bars'   => $bars,
			'scroll' => $narrow,
		);

		foreach ( $modes as $mode => $block ) {
			$this->assertStringContainsString(
				'.dp-tl-group-lanes .dp-tl-row',
				$block,
				sprintf( 'A grouped role gives back one rail in %s mode.', $mode )
			);

			$this->assertStringContainsString(
				'.dp-tl-group-lanes .dp-tl-ships .dp-tl-row',
				$block,
				sprintf( 'And a ship inside one gives back two in %s mode.', $mode )
			);

			$this->assertStringContainsString(
				'--dp-tl-label: calc(var(--dp-tl-label-base) - var(--dp-tl-rail) * 2)',
				$block,
				sprintf( 'Two rails, said as arithmetic on the rail rather than as a number, in %s mode.', $mode )
			);
		}

		$this->assertStringContainsString(
			'padding-left: var(--dp-tl-rail)',
			$css,
			'The rail a group hangs its lanes off is the rail a role already hangs its ships off.'
		);
	}

	/**
	 * A blank end date means the entry is still going, and its bar reaches today.
	 *
	 * `dp_end`'s registered description has promised this since the content model
	 * was written — "leave it blank for a role you are still in" — and nothing
	 * implemented it, so David left it blank as instructed and his current role
	 * had no bar at all.
	 *
	 * "Today" carries the month. `Year` encodes months as twelfths, so a role
	 * ending at January of the current year would be drawn up to eleven months
	 * short, which is the same class of error ADR-0014 was written about. Both
	 * ends of a year are asserted here, and the year boundary after them.
	 *
	 * @return void
	 */
	public function test_a_blank_end_date_runs_the_bar_to_today(): void {
		$this->seed_role( 'Still going', 'Principal', 2024.0, 0.0, '2024 — now', '', 20 );

		$styles = array();

		$moments = array(
			array(
				'year'  => 2026,
				'month' => 1,
			),
			array(
				'year'  => 2026,
				'month' => 12,
			),
			array(
				'year'  => 2027,
				'month' => 1,
			),
		);

		foreach ( $moments as $moment ) {
			$year  = $moment['year'];
			$month = $moment['month'];

			$today = Year::from_year_month( $year, $month );
			$html  = $this->render( array(), $year, $today );
			$bar   = Geometry::through( null, null, $year )->bar( Year::from_float( 2024.0 ), $today, BarKind::Role );

			$this->assertStringContainsString(
				'dp-tl-bar',
				$this->lane_markup( $html, 'dp-role-still-going' ),
				'A role David is still in has a bar.'
			);

			$this->assertStringContainsString(
				'style="' . esc_attr( $bar->style() ) . '"',
				$html,
				sprintf( 'And it runs to %d-%02d, which is what the field said it would.', $year, $month )
			);

			$styles[] = $bar->style();
		}

		$this->assertNotSame(
			$styles[0],
			$styles[1],
			'January and December of one year are eleven months apart, and the bar has to know it.'
		);
	}

	/**
	 * A shipped thing with a blank end date runs to today as well.
	 *
	 * @return void
	 */
	public function test_a_blank_ship_end_date_runs_to_today_too(): void {
		$this->seed_ship( 'Still shipping', $this->accented, 2025.0, 0.0, '2025 — now', 0 );

		$today = Year::from_year_month( 2026, 9 );
		$bar   = Geometry::for_the_design()->bar( Year::from_float( 2025.0 ), $today, BarKind::Ship );

		$this->assertStringContainsString(
			'style="' . esc_attr( $bar->style() ) . '"',
			$this->render( array(), Geometry::DESIGN_LAST_YEAR, $today ),
			'"When it shipped, or leave it blank for something still going" — said in the editor, done here.'
		);
	}

	/**
	 * A blank start date is still no bar, and that asymmetry is deliberate.
	 *
	 * Only the end is optional. A role with no beginning has nowhere on the track
	 * to begin, and inventing one would be inventing a date rather than filling a
	 * blank the field said could be left blank.
	 *
	 * @return void
	 */
	public function test_a_blank_start_date_is_still_no_bar(): void {
		$this->seed_role( 'No beginning', 'Adviser', 0.0, 2021.0, '— 2021', '', 21 );

		$lane = $this->lane_markup(
			$this->render( array(), Geometry::DESIGN_LAST_YEAR, Year::from_year_month( 2026, 9 ) ),
			'dp-role-no-beginning'
		);

		$this->assertStringContainsString( 'No beginning', $lane, 'The row still lists.' );
		$this->assertStringNotContainsString( 'dp-tl-track', $lane, 'It just has nothing to draw.' );
	}

	/**
	 * A date that is wrong rather than absent is still no bar.
	 *
	 * Zero is the content model's own sentinel for "no date yet" — `Meta`
	 * declares it as the default and sanitises a blank to it. A value that is
	 * present and outside what a `Year` will hold is a date somebody typed wrong,
	 * which only an import or a direct write can produce, and drawing it as
	 * "today" would put a bar on the chart the record does not support.
	 *
	 * @return void
	 */
	public function test_an_unusable_end_date_is_not_read_as_today(): void {
		$broken = $this->seed_role( 'Imported wrong', 'Developer', 2020.0, 2021.0, '2020 — 2021', '', 22 );

		remove_all_filters( 'sanitize_post_meta_dp_end' );
		remove_all_filters( 'sanitize_post_meta_dp_end_for_' . PostTypes::ROLE );

		update_post_meta( $broken, 'dp_end', 1500.0 );

		$lane = $this->lane_markup(
			$this->render( array(), Geometry::DESIGN_LAST_YEAR, Year::from_year_month( 2026, 9 ) ),
			'dp-role-imported-wrong'
		);

		$this->assertStringNotContainsString( 'dp-tl-track', $lane );
	}

	/**
	 * A line break David types into a detail is a line break on the page.
	 *
	 * The value is already stored with its newlines — `dp_detail` is declared
	 * `multiline: true`, so it is sanitised with `sanitize_textarea_field()` — and
	 * HTML collapsed them on the way out.
	 *
	 * The order is the safety, and it is what the second half of this asserts:
	 * `nl2br( esc_html( … ) )` can only ever add the `<br />`s it added itself,
	 * where the other order would escape those breaks back into text and let
	 * everything else through.
	 *
	 * @return void
	 */
	public function test_a_line_break_in_a_detail_survives_to_the_page(): void {
		remove_all_filters( 'sanitize_post_meta_dp_detail' );
		remove_all_filters( 'sanitize_post_meta_dp_detail_for_' . PostTypes::ROLE );
		remove_all_filters( 'sanitize_post_meta_dp_detail_for_' . PostTypes::SHIP );

		update_post_meta( $this->bare, 'dp_detail', "First line.\nSecond line." );
		update_post_meta( $this->ship, 'dp_detail', "Ship line one.\nShip line two." );

		$html = $this->render();

		$this->assertStringContainsString( 'First line.<br />' . "\n" . 'Second line.', $html );
		$this->assertStringContainsString( 'Ship line one.<br />' . "\n" . 'Ship line two.', $html );
	}

	/**
	 * Adding the breaks after the escaping is what keeps the escaping airtight.
	 *
	 * @return void
	 */
	public function test_a_detail_with_markup_in_it_is_still_escaped(): void {
		remove_all_filters( 'sanitize_post_meta_dp_detail' );
		remove_all_filters( 'sanitize_post_meta_dp_detail_for_' . PostTypes::ROLE );

		update_post_meta( $this->bare, 'dp_detail', "A & B\n<script>alert(1)</script>" );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( 'A &amp; B<br />', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	/**
	 * Two adjacent roles at Aplyca, in the order David put them.
	 *
	 * @return array<int, int> The two post IDs, in lane order.
	 */
	private function seed_aplyca(): array {
		return array(
			$this->seed_role( 'Aplyca', 'Full-Stack Developer', 2019.5, 2019.75, 'July — Dec 2019', '', 10 ),
			$this->seed_role( 'Aplyca', 'Solutions Architect', 2019.75, 2021.0, '2019 — 2021', '', 11 ),
		);
	}

	/**
	 * The markup of the one group on the chart.
	 *
	 * @param string $html The rendered chart.
	 * @return string
	 */
	private function group_markup( string $html ): string {
		$start = strpos( $html, '<div class="dp-tl-group"' );

		$this->assertIsInt( $start, 'There is no group on this chart.' );

		return substr( $html, $start );
	}

	/**
	 * Render the block the way `do_blocks()` would.
	 *
	 * The year is injected, and it defaults to the design's, so that everything
	 * below goes on meaning the same thing after midnight on 31 December. The
	 * axis follows the clock now (ADR-0014), so a helper that let the real one
	 * through would make every bar percentage in this file a function of the day
	 * the suite happened to run — the test would pass all year and fail on New
	 * Year's Day, which is the worst possible time to find out.
	 *
	 * `test_the_axis_ends_at_the_current_year()` is where the real clock is
	 * asserted, and it is the only place that needs it.
	 *
	 * `$today` is the same argument one resolution finer, and it answers the
	 * other clock question this block asks: an entry whose `dp_end` is blank is
	 * still going, and its bar runs to today. It has to carry the month, because
	 * `Year` encodes months as twelfths — so a test that wants a month boundary
	 * passes the boundary rather than waiting for one.
	 *
	 * @param array<string, mixed> $attributes   Block attributes.
	 * @param int|null             $current_year The year to treat as now; null reads the site's clock.
	 * @param Year|null            $today        Where an unfinished entry ends; null reads the site's clock.
	 * @return string
	 */
	private function render(
		array $attributes = array(),
		?int $current_year = Geometry::DESIGN_LAST_YEAR,
		?Year $today = null
	): string {
		return ( new Timeline( dirname( __DIR__, 3 ) . '/plugins/dp-core', $current_year, $today ) )->render( $attributes );
	}

	/**
	 * The markup of one lane, from its own opening tag to the next lane or group.
	 *
	 * Used to assert that a company with one role is drawn exactly as it was
	 * before consecutive lanes learned to share a header — which is every
	 * company on the chart but two, and none of them should move a byte.
	 *
	 * @param string $html The rendered chart.
	 * @param string $key  The lane's entry key.
	 * @return string
	 */
	private function lane_markup( string $html, string $key ): string {
		$start = strpos( $html, '<div class="dp-tl-lane" data-dp-lane="' . $key . '"' );

		$this->assertIsInt( $start, sprintf( 'There is no lane for %s on this chart.', $key ) );

		$rest = substr( $html, $start + 1 );
		$ends = array();

		foreach ( array( '<div class="dp-tl-lane"', '<div class="dp-tl-group"' ) as $marker ) {
			$at = strpos( $rest, $marker );

			if ( is_int( $at ) ) {
				$ends[] = $at;
			}
		}

		return array() === $ends ? $rest : substr( $rest, 0, min( $ends ) );
	}

	/**
	 * The body of one at-rule, so an assertion cannot pass on the wrong block.
	 *
	 * @param string $css     The stylesheet.
	 * @param string $prelude The at-rule's prelude, exactly as written.
	 * @return string
	 */
	private function at_rule( string $css, string $prelude ): string {
		$start = strpos( $css, $prelude . ' {' );

		$this->assertIsInt( $start, sprintf( '"%s" is not in the stylesheet.', $prelude ) );

		$cursor = strpos( $css, '{', $start );

		$this->assertIsInt( $cursor );

		$depth  = 0;
		$length = strlen( $css );

		for ( $index = $cursor; $index < $length; $index++ ) {
			if ( '{' === $css[ $index ] ) {
				++$depth;
			}

			if ( '}' === $css[ $index ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $css, $cursor, $index - $cursor );
				}
			}
		}

		$this->fail( sprintf( '"%s" is never closed.', $prelude ) );
	}

	/**
	 * A `dp_role` carrying everything the chart prints.
	 *
	 * @param string $org    The organisation, which is the post title.
	 * @param string $title  The job title.
	 * @param float  $start  Decimal year it began.
	 * @param float  $end    Decimal year it ended.
	 * @param string $range      The range as it is printed.
	 * @param string $accent     A tone this lane owns, or ''.
	 * @param int    $menu_order Where David put it under Page Attributes, which is what decides adjacency.
	 * @return int
	 */
	private function seed_role(
		string $org,
		string $title,
		float $start,
		float $end,
		string $range,
		string $accent,
		int $menu_order = 0
	): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ROLE,
				'post_title'  => $org,
				'post_status' => 'publish',
				'menu_order'  => $menu_order,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_role_title', $title );
		update_post_meta( $post_id, 'dp_start', $start );
		update_post_meta( $post_id, 'dp_end', $end );
		update_post_meta( $post_id, 'dp_range', $range );
		update_post_meta( $post_id, 'dp_detail', $org . ' — what the job was.' );
		update_post_meta( $post_id, 'dp_stack', 'PHP · VUE.JS' );
		update_post_meta( $post_id, 'dp_accent', $accent );

		return $post_id;
	}

	/**
	 * A `dp_ship` hanging off a role.
	 *
	 * @param string $name       The thing's name, which is the post title.
	 * @param int    $role_id    The role it hangs off, or 0.
	 * @param float  $start      Decimal year work began.
	 * @param float  $end        Decimal year it shipped.
	 * @param string $range      The range as it is printed.
	 * @param int    $writeup_id The post that writes it up, or 0.
	 * @return int
	 */
	private function seed_ship( string $name, int $role_id, float $start, float $end, string $range, int $writeup_id ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::SHIP,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_role_id', $role_id );
		update_post_meta( $post_id, 'dp_start', $start );
		update_post_meta( $post_id, 'dp_end', $end );
		update_post_meta( $post_id, 'dp_range', $range );
		update_post_meta( $post_id, 'dp_headline', $name . ' — the one line.' );
		update_post_meta( $post_id, 'dp_detail', 'What ' . $name . ' is and who it is for.' );
		update_post_meta( $post_id, 'dp_bullets', array( 'One constraint.', 'Another constraint.' ) );
		update_post_meta( $post_id, 'dp_ship_role', 'Everything' );
		update_post_meta( $post_id, 'dp_stack', 'SWIFT · SWIFTUI' );
		update_post_meta( $post_id, 'dp_artifact_label', 'SWIFTUI' );
		update_post_meta( $post_id, 'dp_artifact', "struct EntryList: View {\n}" );
		update_post_meta( $post_id, 'dp_stat1', '0' );
		update_post_meta( $post_id, 'dp_stat1_label', 'TRACKERS' );
		update_post_meta( $post_id, 'dp_stat2', '—' );
		update_post_meta( $post_id, 'dp_stat2_label', 'APPS SHIPPED' );
		update_post_meta( $post_id, 'dp_writeup_id', $writeup_id );

		return $post_id;
	}
}
