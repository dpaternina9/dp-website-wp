<?php
/**
 * Integration tests for the posts index.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

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

		$titles = array();

		foreach ( $GLOBALS['wp_query']->posts as $post ) {
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
}
