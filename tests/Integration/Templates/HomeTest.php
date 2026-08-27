<?php
/**
 * Integration tests for the posts index.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\DerivedLink;
use WP_Post;
use WP_Query;

/**
 * The blog, wherever David decided it lives.
 *
 * Digest §2 puts the blog at "David's choice", resolved through `home` and
 * Settings → Reading. Every test here goes through that setting rather than
 * through a URL the theme chose, and the fixture calls the page **Field notes**
 * on purpose: a fixture slugged `blog` would never catch a slug creeping into
 * the theme.
 */
final class HomeTest extends TemplateTestCase {

	/**
	 * The hierarchy core hands `locate_block_template()` for a posts index.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'home.php', 'index.php' );

	/**
	 * The posts index resolves to `home`, whatever the page is called.
	 *
	 * @return void
	 */
	public function test_the_posts_index_resolves_to_home_under_any_slug(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );
		$page = $this->seed_posts_page( 'Field notes' );

		$this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertTrue( is_home(), 'The URL from Settings → Reading is the posts index.' );
		$this->assertSame( 'dpaternina//home', $this->resolved_template() );
	}

	/**
	 * It still resolves when David never chose a page at all.
	 *
	 * With Reading left alone the posts index is the site root, and the front
	 * page template answers for it. The assertion that matters is that the query
	 * is still the posts query and the render still happens — the theme must not
	 * depend on `page_for_posts` being set for anything.
	 *
	 * @return void
	 */
	public function test_the_posts_index_still_works_with_reading_left_unset(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );

		$html = $this->render( home_url( '/' ), 'home', self::HIERARCHY );

		$this->assertTrue( is_home() );
		$this->assertTrue( is_front_page() );
		$this->assertNotSame( '', trim( $html ) );
	}

	/**
	 * The main query is inherited, so the list is the archive's own.
	 *
	 * @return void
	 */
	public function test_the_list_shows_every_published_post(): void {
		$this->seed_categories();
		$this->seed_posts( 4 );
		$page = $this->seed_posts_page();

		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		/*
		 * Counted on the row's own class, not on `wp-block-post`: the featured
		 * panel is a query loop too, so its single entry carries the same
		 * wrapper and would be counted twice over.
		 */
		$this->assertSame( 3, substr_count( $html, 'dp-row-body' ), 'Three in the list, one in the panel above it.' );

		foreach ( $this->posts as $post_id ) {
			$this->assertStringContainsString( (string) get_the_title( $post_id ), $html );
		}
	}

	/**
	 * The newest post is featured once, and is not repeated in the list.
	 *
	 * @return void
	 */
	public function test_the_newest_post_is_featured_and_held_back_from_the_list(): void {
		$this->seed_categories();
		$this->seed_posts( 4 );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$newest = (string) get_the_title( $this->posts[0] );

		$this->assertStringContainsString( 'dp-featured-panel', $html );
		$this->assertSame( 1, substr_count( $html, '>' . $newest . '</a>' ), 'The featured post appears once, in the panel.' );
		$this->assertSame( 3, substr_count( $html, 'dp-row-body' ), 'The list holds back the post above it.' );

		foreach ( array_slice( $this->posts, 1 ) as $post_id ) {
			$this->assertStringContainsString( (string) get_the_title( $post_id ), $html );
		}
	}

	/**
	 * The exclusion never reaches the feed.
	 *
	 * A reader subscribing to the blog must not lose its newest entry because a
	 * panel on a web page happens to be showing it.
	 *
	 * @return void
	 */
	public function test_the_feed_still_carries_the_featured_post(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );
		$this->seed_posts_page();

		$this->go_to( get_feed_link() );

		$this->assertTrue( is_feed() );

		$query = $GLOBALS['wp_query'];

		$this->assertInstanceOf( WP_Query::class, $query );

		$titles = array();

		foreach ( $query->posts as $post ) {
			$this->assertInstanceOf( WP_Post::class, $post );

			$titles[] = $post->post_title;
		}

		$this->assertContains( (string) get_the_title( $this->posts[0] ), $titles );
	}

	/**
	 * The filter pills are links to real archives, with an All pill in front.
	 *
	 * FilterPills.dc.html: "these are real links to filtered archive URLs, not
	 * JS tabs." The assertion is on the anchors, because that is the whole
	 * promise — the row works with scripting switched off.
	 *
	 * @return void
	 */
	public function test_the_filter_pills_are_links_and_lead_with_all(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts( 1, 'food' );
		$page = $this->seed_posts_page();

		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-filter-pills', $html );
		$this->assertStringContainsString( 'dp-pill-all', $html );

		foreach ( array( 'dev', 'food' ) as $slug ) {
			$link = get_category_link( $this->categories[ $slug ] );

			$this->assertIsString( $link );
			$this->assertStringContainsString( 'href="' . esc_url( $link ) . '"', $html );
		}

		$this->assertStringContainsString(
			'href="' . esc_url( $this->permalink( $page ) ) . '"',
			$html,
			'The All pill points at the posts index resolved from Settings → Reading.'
		);
	}

	/**
	 * On the index, All is the pill that is current.
	 *
	 * @return void
	 */
	public function test_the_all_pill_is_current_on_the_index_and_not_on_an_archive(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$page = $this->seed_posts_page();

		$index = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<li class="[^"]*dp-pill-all[^"]*current-cat"~', $index );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$archive = $this->render( $link, 'category', array( 'category.php', 'archive.php', 'index.php' ) );

		$this->assertStringNotContainsString( 'dp-pill-all', $archive, 'The pill row belongs to the index, not to an archive.' );
	}

	/**
	 * Nothing on the index renders a notice, and the chrome is there.
	 *
	 * @return void
	 */
	public function test_the_chrome_wraps_the_index(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );
		$page = $this->seed_posts_page();

		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-header', $html );
		$this->assertStringContainsString( 'dp-footer', $html );
		$this->assertStringContainsString( '<main', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ), 'One h1 per page (CLAUDE.md §1.7).' );
	}

	/**
	 * The featured panel's meta is one line, not two blocks in a row.
	 *
	 * `featured.meta` is `p.cat + ' · ' + p.read` — a single string in a single
	 * element, with no link inside it, because the whole panel is the click
	 * target. The build drew a `core/post-terms` beside a bound paragraph, which
	 * is two elements, two link targets and a flex gap where the design has a
	 * space.
	 *
	 * @return void
	 */
	public function test_the_featured_meta_is_one_line_of_category_and_read_time(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'food' );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertStringContainsString( '>Food · 1 min read</p>', $html );
		$this->assertSame( 1, substr_count( $html, 'dp-featured-meta' ), 'One element, once.' );
	}

	/**
	 * The two mono affordances under the pills, with the design's words on them.
	 *
	 * "READ MY LIFE STORY IN ORDER →" used to be a derived link: the theme picked
	 * the series with the most published parts and wrote its archive URL in.
	 * ADR-0018 supersedes that half of ADR-0016. There is no way for the theme to
	 * know which series David means without guessing, the design's own fixture
	 * has exactly one so it never had to say, and a link that works to the wrong
	 * place is the failure mode ADR-0008 called the hardest of the three to
	 * notice. So the theme ships the words and David points them at the series he
	 * means, once, in the site editor.
	 *
	 * @return void
	 */
	public function test_the_quicklinks_ship_the_designs_words_and_no_url(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 1 );
		$this->file_under_series( $this->posts[0] );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-quicklinks', $html );
		$this->assertStringContainsString( 'dp-quicklink-series', $html );
		$this->assertStringContainsString( 'Read my life story in order', $html );
		$this->assertStringNotContainsString(
			DerivedLink::UNRESOLVED_CLASS,
			$html,
			'An unlinked button is a button, not a failed derivation.'
		);
	}

	/**
	 * The series he points it at is the one it points at.
	 *
	 * @return void
	 */
	public function test_a_link_set_on_the_quicklink_survives_rendering(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 1 );
		$this->file_under_series( $this->posts[0] );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$this->override( 'wp_template', 'home', $this->linked( 'templates/home.html', $link ) );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertSame( 'dpaternina//home', $this->resolved_template() );
		$this->assertStringContainsString( 'href="' . esc_url( $link ) . '"', $html );
	}

	/**
	 * One page of posts draws no pager at all — not an empty bordered bar.
	 *
	 * `pager.show` is `matching.length > PER_PAGE`, and the bar carries a rule
	 * and 64px of margin, so rendering it empty is a visible defect rather than
	 * a harmless one.
	 *
	 * @return void
	 */
	public function test_a_single_page_index_draws_no_pager(): void {
		$this->seed_categories();
		$this->seed_posts( 4 );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		$this->assertStringNotContainsString( 'dp-pagination', $html );
		$this->assertStringNotContainsString( 'dp-at-end', $html );
	}

	/**
	 * A paginated index draws the range, the numbers, and a dead PREV.
	 *
	 * The disabled step is the design's, and it is the reason this is not left to
	 * core: core renders nothing for a step with nowhere to go, so the row moves
	 * sideways between page one and page two.
	 *
	 * @return void
	 */
	public function test_the_pager_carries_the_range_and_a_disabled_step(): void {
		$this->seed_categories();
		$this->seed_posts( 14 );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		// Fourteen published, one held back for the panel, ten to a page.
		$this->assertStringContainsString( '>1–10 of 13 posts</p>', $html );
		$this->assertStringContainsString( 'dp-pagination-range', $html );
		$this->assertMatchesRegularExpression(
			'~<span class="wp-block-query-pagination-previous dp-page-step-disabled" aria-disabled="true">~',
			$html
		);
		$this->assertStringContainsString( 'class="page-numbers current"', $html );
		$this->assertStringNotContainsString( 'dp-at-end', $html, 'Page one is not the end of the archive.' );
	}

	/**
	 * The last page carries the closing panel, and NEXT is the dead step there.
	 *
	 * @return void
	 */
	public function test_the_last_page_carries_the_end_of_archive_panel(): void {
		$this->seed_categories();
		$this->seed_posts( 14 );

		$page = $this->seed_posts_page();

		/*
		 * `get_pagenum_link()` builds the URL the way the pager does, which is
		 * the only way to write this that survives a permalink structure — the
		 * tests site runs plain permalinks and `page/2/` means nothing there.
		 * It reads the current request, so the request has to exist first.
		 */
		$this->go_to( $this->permalink( $page ) );

		// `get_pagenum_link()` runs its result through `esc_url()`, so the
		// separator comes back as `&#038;` and `go_to()` parses it as one
		// argument called `#038;paged`.
		$url = html_entity_decode( (string) get_pagenum_link( 2 ), ENT_QUOTES );

		$html = $this->render( $url, 'home', self::HIERARCHY );

		$paged = get_query_var( 'paged' );

		$this->assertSame( 2, is_numeric( $paged ) ? (int) $paged : 0, 'The second page is the one being rendered.' );
		$this->assertStringContainsString( '>11–13 of 13 posts</p>', $html );
		$this->assertStringContainsString( 'dp-at-end', $html );
		$this->assertStringContainsString( 'That is the end of the archive', $html );
		$this->assertMatchesRegularExpression(
			'~<span class="wp-block-query-pagination-next dp-page-step-disabled" aria-disabled="true">~',
			$html
		);
	}

	/**
	 * The pager's steps are the chip the token is named for, in a `border-box`.
	 *
	 * Asserted in the browser, not here — a target size is a property of the
	 * rendered box. What this holds is the half a rendered box cannot: that the
	 * markup carries the classes the stylesheet sizes.
	 *
	 * @return void
	 */
	public function test_every_pager_control_carries_the_class_that_sizes_it(): void {
		$this->seed_categories();
		$this->seed_posts( 14 );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HIERARCHY );

		foreach ( array( 'dp-pagination-steps', 'wp-block-query-pagination-previous', 'page-numbers' ) as $class ) {
			$this->assertStringContainsString( $class, $html );
		}
	}
}
