<?php
/**
 * Integration tests for the work page.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Blocks\Timeline;
use DP\Theme\Blocks\Timeline as TimelinePresentation;

/**
 * Featured work above, the whole record below, and a way between them.
 *
 * Phase 5 shipped the `WorkCard` pattern and deliberately did not place it,
 * because the thing a card opens did not exist yet. This is where the two meet,
 * and the assertion that matters is the one that would otherwise be checked by
 * eye: **the card's link resolves to an entry that is actually on the chart
 * below it.** A card linking to `#dp-ship-something-renamed` looks perfect and
 * does nothing.
 *
 * The link is a query arg as well as a fragment. That is what makes it work
 * with the scripts off: the server reads `dp-open` and renders that entry
 * already open, so following a card is one navigation rather than a fragment
 * pointing at a closed `<details>` that no user agent is required to expand.
 */
final class WorkTest extends TemplateTestCase {

	/**
	 * The hierarchy WordPress builds for a page carrying a custom template.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'dp-work.html', 'page.php', 'singular.php', 'index.php' );

	/**
	 * The page assigned the work template.
	 *
	 * @var int
	 */
	private int $page = 0;

	/**
	 * Build the page, three shipped things and the roles under them.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->page = $this->seed_page( 'What I have worked on', 'dp-work.html' );

		$role = $this->seed_role( 'Fanxie Lab', 'CTO & founder', 2026.6 );

		foreach ( array( 'Kiveo', 'Agency platform & ops' ) as $index => $name ) {
			$ship = $this->seed_ship( $name, true, 2026.6 - $index );

			update_post_meta( $ship, 'dp_role_id', $role );
			update_post_meta( $ship, 'dp_start', 2023.0 );
		}
	}

	/**
	 * The work page renders the work template, which the hierarchy never picks.
	 *
	 * @return void
	 */
	public function test_the_page_resolves_to_the_work_template(): void {
		$this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );

		$this->assertSame( 'dpaternina//dp-work', $this->resolved_template() );
	}

	/**
	 * The cards are above the chart, and the chart is below them.
	 *
	 * @return void
	 */
	public function test_the_cards_sit_above_the_whole_record(): void {
		$html = $this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );

		$cards = strpos( $html, 'dp-cards' );
		$chart = strpos( $html, 'id="' . Timeline::ROOT_ID . '"' );

		$this->assertIsInt( $cards, 'The WorkCard grid is not on the page Phase 6 was supposed to place it on.' );
		$this->assertIsInt( $chart, 'The timeline is not on the work page.' );
		$this->assertLessThan( $chart, $cards, 'The design puts the three cards above the record, not below it.' );

		$this->assertStringContainsString( 'Featured work', $html );
		$this->assertStringContainsString( 'The whole record', $html );
	}

	/**
	 * The query loop asks for shipped work and gets shipped work.
	 *
	 * `core/query` silently drops a post type that is not publicly viewable, so
	 * a card grid asking for `dp_ship` would quietly list posts instead. The
	 * theme restates the type through `query_loop_block_query_vars`; this is the
	 * assertion that it is still doing so on this page.
	 *
	 * @return void
	 */
	public function test_the_cards_are_shipped_work_and_not_posts(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );

		$html = $this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );

		$this->assertStringContainsString( 'Kiveo', $html );
		$this->assertStringNotContainsString( 'Post number 1', $html, 'The card grid fell back to posts.' );
	}

	/**
	 * Every card title is a link, and every link opens an entry that exists.
	 *
	 * @return void
	 */
	public function test_every_card_links_to_an_entry_on_the_chart_below(): void {
		$html = $this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );
		$hits = array();

		preg_match_all( '~<a class="dp-card-open" data-dp-entry="([^"]+)" href="([^"]+)"~', $html, $hits, PREG_SET_ORDER );

		$this->assertCount( 2, $hits, 'Both featured things should carry a link into the chart.' );

		foreach ( $hits as $hit ) {
			$key = $hit[1];
			$url = html_entity_decode( $hit[2] );

			$this->assertStringContainsString(
				'id="' . $key . '"',
				$html,
				sprintf( 'The card links to "%s", which is not an entry on the chart below it.', $key )
			);

			$this->assertStringContainsString( Timeline::OPEN_ARG . '=' . $key, $url, 'Without the query arg the link needs JavaScript to do anything.' );
			$this->assertStringEndsWith( '#' . $key, $url, 'And without the fragment it lands at the top of the page.' );
		}
	}

	/**
	 * Following a card's link renders that entry open, with no script involved.
	 *
	 * @return void
	 */
	public function test_following_a_card_opens_its_entry_on_the_server(): void {
		$html = $this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );
		$hits = array();

		preg_match( '~data-dp-entry="([^"]+)"~', $html, $hits );

		$this->assertArrayHasKey( 1, $hits );

		/*
		 * The arg goes in the URL rather than into `$_GET`, because `go_to()`
		 * empties `$_GET` and rebuilds it from the query string — which is
		 * exactly what a real request does, and the reason this test is worth
		 * having: it follows the link the way a browser with no scripts follows it.
		 */
		$opened = $this->render(
			add_query_arg( Timeline::OPEN_ARG, $hits[1], $this->permalink( $this->page ) ),
			'page',
			self::HIERARCHY
		);

		$this->assertStringContainsString( 'id="' . $hits[1] . '" open>', $opened );
	}

	/**
	 * The controller is loaded on this page, and only because the chart is here.
	 *
	 * @return void
	 */
	public function test_the_controller_is_loaded_only_where_the_chart_is(): void {
		/*
		 * `wp_scripts()` is a global that outlives a test, and the point of this
		 * one is what the enqueue looked like before anything rendered. So the
		 * handle is forgotten first, rather than trusting the order the suite
		 * happens to run in.
		 */
		$this->forget_the_controller();

		$this->assertFalse(
			wp_script_is( TimelinePresentation::SCRIPT_HANDLE, 'enqueued' ),
			'The timeline controller is enqueued before anything has rendered.'
		);

		$this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );

		$this->assertTrue(
			wp_script_is( TimelinePresentation::SCRIPT_HANDLE, 'enqueued' ),
			'The chart rendered and its controller did not load.'
		);

		$script = wp_scripts()->registered[ TimelinePresentation::SCRIPT_HANDLE ] ?? null;

		$this->assertNotNull( $script );
		$this->assertSame( 'defer', $script->extra['strategy'] ?? '', 'CLAUDE.md §1.7: no render-blocking JS.' );
		$this->assertIsString( $script->src );
		$this->assertStringStartsWith( get_stylesheet_directory_uri(), $script->src, 'Nothing enqueues from off-origin.' );
	}

	/**
	 * Forget that the timeline's controller was ever enqueued.
	 *
	 * @return void
	 */
	private function forget_the_controller(): void {
		wp_dequeue_script( TimelinePresentation::SCRIPT_HANDLE );
		wp_deregister_script( TimelinePresentation::SCRIPT_HANDLE );
	}

	/**
	 * A page not assigned this template gets no cards and no chart.
	 *
	 * @return void
	 */
	public function test_an_unassigned_page_is_left_alone(): void {
		$this->forget_the_controller();

		$plain = $this->seed_page( 'Something else entirely' );

		$html = $this->render( $this->permalink( $plain ), 'page', array( 'page.php', 'singular.php', 'index.php' ) );

		$this->assertStringNotContainsString( 'id="' . Timeline::ROOT_ID . '"', $html );
		$this->assertStringNotContainsString( 'dp-card-open', $html );

		$this->assertFalse(
			wp_script_is( TimelinePresentation::SCRIPT_HANDLE, 'enqueued' ),
			'A page with no chart on it should not be paying for the chart\'s controller.'
		);
	}

	/**
	 * The work template renders no post content, and leaves no wrapper behind.
	 *
	 * Same removal as the homepage's, for the same reason: the page is
	 * composed, and the empty group the block sat in was a `.dp-section` with
	 * nothing in it and 8px of padding under it.
	 *
	 * @return void
	 */
	public function test_the_work_template_renders_no_post_content(): void {
		$html = $this->render( $this->permalink( $this->page ), 'page', self::HIERARCHY );

		$this->assertStringNotContainsString( 'wp-block-post-content', $html );
		$this->assertStringNotContainsString( 'dp-work-intro', $html );
		$this->assertStringContainsString( 'dp-work-featured', $html, 'The rest of the template is still there.' );
	}
}
