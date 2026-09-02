<?php
/**
 * Integration tests for the homepage.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

/**
 * The editorial homepage: hero, RIGHT NOW, the record, shipped, latest writing.
 *
 * The two assertions worth having here are about the loops rather than the
 * copy. The record strip and the work cards query post types that are not
 * publicly viewable, which `core/query` silently refuses to do — it drops the
 * `postType` attribute and queries posts instead, returning something that
 * looks plausible and is wrong. Counting what came back is what catches that.
 */
final class FrontPageTest extends TemplateTestCase {

	/**
	 * The hierarchy core hands `locate_block_template()` for a front page.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'front-page.php', 'home.php', 'index.php' );

	/**
	 * The front page resolves to `front-page`, whichever page David chose.
	 *
	 * @return void
	 */
	public function test_the_front_page_resolves_to_front_page(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );
		$this->seed_posts_page();

		$this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertTrue( is_front_page() );
		$this->assertSame( 'dpaternina//front-page', $this->resolved_template() );
	}

	/**
	 * The hero carries the accent word, once, in an h1.
	 *
	 * @return void
	 */
	public function test_the_hero_carries_the_accent_word(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-home-hero-title', $html );
		$this->assertStringContainsString( '<span class="dp-accent">useful</span>', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ), 'One h1 per page (CLAUDE.md §1.7).' );
	}

	/**
	 * The record strip shows three roles, most recent first.
	 *
	 * @return void
	 */
	public function test_the_record_strip_shows_three_roles_most_recent_first(): void {
		$this->seed_role( 'Aplyca', 'Developer', 2020.0 );
		$this->seed_role( 'Globant', 'Developer', 2022.0 );
		$this->seed_role( 'MonsterInsights', 'Developer team lead', 2026.0 );
		$this->seed_role( 'Fanxie Lab', 'CTO and founder', 2026.9 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame( 3, substr_count( $html, 'dp-record-org' ), 'The design shows one quiet strip of three.' );

		$order = array();

		foreach ( array( 'Fanxie Lab', 'MonsterInsights', 'Globant', 'Aplyca' ) as $org ) {
			$position = strpos( $html, '>' . $org . '</h3>' );

			if ( false !== $position ) {
				$order[ $org ] = $position;
			}
		}

		$this->assertSame( array( 'Fanxie Lab', 'MonsterInsights', 'Globant' ), array_keys( $order ) );
		$this->assertArrayNotHasKey( 'Aplyca', $order, 'The fourth role is off the end of the strip.' );
	}

	/**
	 * The strip sorts on when a role began, not on when it ended.
	 *
	 * The fixture above cannot tell the two apart: `seed_role()` derives a start
	 * two years before the end it is given, so start order and end order are the
	 * same list. These three are the design's own, with their real years, and
	 * they disagree — a founder role that began first and has not finished ends
	 * last by start and first by end.
	 *
	 * `dp_start` descending is what the class docblock on
	 * `DP\Theme\Query\QueryLoops` states, what `dpaternina.dc.html` sorts roles
	 * by (`LANES.slice().sort((a, b) => b.start - a.start)`), and what
	 * `DP\Core\Resume\Ledger` already does — so a strip ordered by `dp_end`
	 * put the homepage and the résumé in two different orders.
	 *
	 * @return void
	 */
	public function test_the_record_strip_sorts_on_when_a_role_began(): void {
		$this->seed_role_between( 'Fanxie Lab', 'CTO and founder', 2016.0, 2026.9 );
		$this->seed_role_between( 'MonsterInsights', 'Developer team lead', 2022.0, 2026.0 );
		$this->seed_role_between( 'Globant', 'Developer', 2020.0, 2022.0 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$order = array();

		/*
		 * Matched on the strip's own class. "Fanxie Lab" is also an `h3` in the
		 * RIGHT NOW bento higher up the page, so a bare title match reads the
		 * wrong element and puts it first whatever the strip did.
		 */
		foreach ( array( 'MonsterInsights', 'Globant', 'Fanxie Lab' ) as $org ) {
			$this->assertSame(
				1,
				preg_match( '~dp-record-org[^"]*">' . preg_quote( $org, '~' ) . '</h3>~', $html, $found, PREG_OFFSET_CAPTURE ),
				$org . ' is not on the strip at all.'
			);

			$position = $found[0][1];

			$order[ $org ] = $position;
		}

		asort( $order );

		$this->assertSame(
			array( 'MonsterInsights', 'Globant', 'Fanxie Lab' ),
			array_keys( $order ),
			'Newest role first means the one that started most recently.'
		);
	}

	/**
	 * A role with a start that is not two years before its end.
	 *
	 * @param string $org   The organisation, which is the post title.
	 * @param string $title The job title.
	 * @param float  $start The decimal year it began.
	 * @param float  $end   The decimal year it ended.
	 * @return int
	 */
	private function seed_role_between( string $org, string $title, float $start, float $end ): int {
		$post_id = $this->seed_role( $org, $title, $end );

		update_post_meta( $post_id, 'dp_start', $start );

		return $post_id;
	}

	/**
	 * A role David has not left leads the strip, whenever it began.
	 *
	 * The defect this closes, reported 2026-09-02: the strip sorted on
	 * `dp_start` alone, so a founder role begun in 2016 and still running sat
	 * below three jobs taken since and fell off a strip that holds three. The
	 * front page's three cards answer "what does he do", and a job he still has
	 * outranks one that ended.
	 *
	 * The fixture makes the two rules disagree on purpose: the ongoing role is
	 * the *oldest* thing here, so a regression to `dp_start` descending fails
	 * rather than passing by coincidence.
	 *
	 * @return void
	 */
	public function test_an_ongoing_role_leads_the_record_strip(): void {
		$this->seed_role_between( 'MonsterInsights', 'Developer team lead', 2022.0, 2026.0 );
		$this->seed_role_between( 'Globant', 'Developer', 2020.0, 2022.0 );
		$this->seed_ongoing_role( 'Fanxie Lab', 'CTO and founder', 2016.0 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame(
			array( 'Fanxie Lab', 'MonsterInsights', 'Globant' ),
			$this->strip_order( $html, array( 'Fanxie Lab', 'MonsterInsights', 'Globant' ) ),
			'A role with no end date leads, then the one that started last.'
		);
	}

	/**
	 * Two ongoing roles order among themselves by when they began.
	 *
	 * "Still going" is the first key, not the only one.
	 *
	 * @return void
	 */
	public function test_ongoing_roles_sort_by_when_they_began(): void {
		$this->seed_ongoing_role( 'Fanxie Lab', 'CTO and founder', 2016.0 );
		$this->seed_ongoing_role( 'Awesome Motive', 'Developer team lead', 2024.0 );
		$this->seed_role_between( 'Globant', 'Developer', 2020.0, 2022.0 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame(
			array( 'Awesome Motive', 'Fanxie Lab', 'Globant' ),
			$this->strip_order( $html, array( 'Awesome Motive', 'Fanxie Lab', 'Globant' ) ),
			'Both ongoing roles lead, the later start first; the finished one follows.'
		);
	}

	/**
	 * A role whose end was never saved at all, not merely blanked.
	 *
	 * `register_post_meta()`'s default is not a row, so "missing" and "0" are
	 * two different states in the database and only one of them is what the
	 * editor produces. Both have to mean "still going".
	 *
	 * @return void
	 */
	public function test_a_role_with_no_end_meta_row_counts_as_ongoing(): void {
		$post_id = $this->seed_ongoing_role( 'Fanxie Lab', 'CTO and founder', 2016.0 );
		delete_post_meta( $post_id, 'dp_end' );

		$this->seed_role_between( 'MonsterInsights', 'Developer team lead', 2022.0, 2026.0 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame(
			array( 'Fanxie Lab', 'MonsterInsights' ),
			$this->strip_order( $html, array( 'Fanxie Lab', 'MonsterInsights' ) ),
			'A missing dp_end row is a role still running, exactly as a blank one is.'
		);
	}

	/**
	 * A role with a start, no end, and the range David typed.
	 *
	 * @param string $org   The organisation, which is the post title.
	 * @param string $title The job title.
	 * @param float  $start The decimal year it began.
	 * @return int
	 */
	private function seed_ongoing_role( string $org, string $title, float $start ): int {
		$post_id = $this->seed_role( $org, $title, 0.0 );

		update_post_meta( $post_id, 'dp_start', $start );
		update_post_meta( $post_id, 'dp_end', 0.0 );
		update_post_meta( $post_id, 'dp_range', sprintf( '%d — now', (int) $start ) );

		return $post_id;
	}

	/**
	 * The organisations the strip printed, in the order it printed them.
	 *
	 * Matched on the strip's own class: several of these titles are also `h3`s
	 * in the RIGHT NOW bento higher up the page, so a bare title match reads the
	 * wrong element.
	 *
	 * @param string        $html      The rendered page.
	 * @param array<string> $expected  The organisations to look for.
	 * @return array<string> Those that appeared, in document order.
	 */
	private function strip_order( string $html, array $expected ): array {
		$found = array();

		foreach ( $expected as $org ) {
			$hit = array();

			if ( 1 === preg_match( '~dp-record-org[^"]*">' . preg_quote( $org, '~' ) . '</h3>~', $html, $hit, PREG_OFFSET_CAPTURE ) ) {
				$found[ $org ] = $hit[0][1];
			}
		}

		asort( $found );

		return array_keys( $found );
	}

	/**
	 * The record strip prints the fields core's own meta binding refuses.
	 *
	 * `core/post-meta` returns null for anything on a post type that is not
	 * publicly viewable, and none of `dp-core`'s three is. The theme's own
	 * source names these fields one at a time; this is the assertion that the
	 * allowlist actually reaches the page.
	 *
	 * @return void
	 */
	public function test_the_record_strip_prints_the_role_title_and_range(): void {
		$this->seed_role( 'Fanxie Lab', 'CTO and founder', 2026.9 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'CTO and founder', $html );
		$this->assertStringContainsString( '2024 — 2026', $html );
	}

	/**
	 * Latest writing shows three posts, newest first, in compact rows.
	 *
	 * @return void
	 */
	public function test_latest_writing_shows_three_compact_rows(): void {
		$this->seed_categories();
		$this->seed_posts( 5 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame( 3, substr_count( $html, 'dp-row dp-row-compact' ) );
		$this->assertStringContainsString( 'Post number 1', $html );
		$this->assertStringNotContainsString( 'Post number 4', $html );
	}

	/**
	 * The homepage renders no post content, and leaves no wrapper behind.
	 *
	 * David asked for the content block to go: the home page is composed, and a
	 * `core/post-content` on it drew whichever page happened to be queried —
	 * the posts index, on a site with no static front page. The group that held
	 * it went with it, because an empty `.dp-section` is 24px of block gap and
	 * a stray column, which is the opposite of the spacing this phase fixed.
	 *
	 * @return void
	 */
	public function test_the_homepage_renders_no_post_content(): void {
		$this->seed_posts_page();

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringNotContainsString( 'wp-block-post-content', $html );
		$this->assertStringNotContainsString( 'dp-front-content', $html );
		$this->assertStringNotContainsString( 'Placeholder body.', $html );
	}

	/**
	 * With Reading left alone there is no queried page, and nothing breaks.
	 *
	 * @return void
	 */
	public function test_the_front_page_renders_with_reading_left_unset(): void {
		$this->seed_categories();
		$this->seed_posts( 2 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertTrue( is_home(), 'Nothing was chosen, so the front page is the posts index.' );
		$this->assertStringContainsString( 'dp-home-hero-title', $html );
		$this->assertStringNotContainsString( 'Placeholder body.', $html, 'There is no page whose content could render.' );
	}

	/**
	 * The hero's first section is not preceded by a block-gap margin.
	 *
	 * The spacing rules this phase added live in a stylesheet, so the only
	 * thing an integration test can honestly assert is that the stylesheet says
	 * what it is supposed to say — the cascade itself is checked in a browser,
	 * in `tests/e2e/spacing.spec.ts`, and in both contexts. This is the cheap
	 * half: the rules that neutralise core's block gap have to out-specify
	 * `:root :where(.is-layout-flow) > *`, which means naming a second class.
	 *
	 * @return void
	 */
	public function test_the_block_gap_overrides_name_a_second_class(): void {
		$lines = file( get_theme_file_path( 'assets/css/components.css' ), FILE_IGNORE_NEW_LINES );

		$this->assertIsArray( $lines );

		$css = implode( "\n", $lines );

		foreach ( array(
			'.dp-bento.wp-block-group > *',
			'.dp-shipped.wp-block-group > .dp-shipped-item',
			'.dp-section-head.wp-block-group > *',
			'.dp-latest.wp-block-group > *',
			'.dp-card.wp-block-group > *',
		) as $selector ) {
			$this->assertStringContainsString(
				$selector,
				$css,
				sprintf(
					'"%s" is how the design\'s own spacing beats core\'s block gap in the editor '
					. 'as well as on the front end. A one-class rule wins in one context only.',
					$selector
				)
			);
		}
	}
}
