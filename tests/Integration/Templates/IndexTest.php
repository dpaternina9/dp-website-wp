<?php
/**
 * Integration tests for the hierarchy's last resort and the search view.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

/**
 * The four URLs nothing in this theme used to answer.
 *
 * `index.html` is what WordPress falls back to when no more specific template
 * matches, and it answered `/?s=…`, `/tag/…`, `/author/…` and every date archive
 * with `core/post-title` and `core/post-content` — that is, with the **first
 * result's** heading and its entire body, as though the request had been for a
 * single post. Four listing URLs each rendered one post and hid the rest.
 *
 * Every assertion below is about that: a list where there was a body, a count
 * that describes the whole result set, and an empty state that says a search
 * matched nothing rather than showing the reader a blank page.
 *
 * The body is asserted absent by its rendered form rather than by
 * `assertBodyAbsent()`. The fixture's `post_content` is block markup, which
 * never survives to the page in any case; `<p>Body copy.</p>` is what
 * `core/post-content` would actually have printed.
 */
final class IndexTest extends TemplateTestCase {

	/**
	 * The rendered form of the body every fixture post carries.
	 */
	private const BODY = '<p>Body copy.</p>';

	/**
	 * The hierarchy core hands `locate_block_template()` for a search.
	 *
	 * @var array<int, string>
	 */
	private const SEARCH = array( 'search.php', 'index.php' );

	/**
	 * The hierarchy for a tag archive, shortened to the parts a theme can ship.
	 *
	 * @var array<int, string>
	 */
	private const TAG = array( 'tag.php', 'archive.php', 'index.php' );

	/**
	 * The hierarchy for an author archive.
	 *
	 * @var array<int, string>
	 */
	private const AUTHOR = array( 'author.php', 'archive.php', 'index.php' );

	/**
	 * The hierarchy for a date archive.
	 *
	 * @var array<int, string>
	 */
	private const DATE = array( 'date.php', 'archive.php', 'index.php' );

	/*
	 * -------------------------------------------------------------------- Search
	 */

	/**
	 * A search renders every match as a row, and no post's body.
	 *
	 * @return void
	 */
	public function test_a_search_renders_a_results_list_rather_than_one_post_body(): void {
		$this->seed_categories();
		$this->seed_posts( 4 );

		$html = $this->render( $this->search_url( 'number' ), 'search', self::SEARCH );

		$this->assertTrue( is_search() );
		$this->assertSame( 'dpaternina//search', $this->resolved_template() );
		$this->assertSame( 4, substr_count( $html, 'dp-row-body' ), 'Every match is a row.' );
		$this->assertStringNotContainsString(
			self::BODY,
			$html,
			'A search result is a row, not the first match rendered in full.'
		);
	}

	/**
	 * The search view says what was searched for, once, as its heading.
	 *
	 * @return void
	 */
	public function test_the_search_view_names_the_query_in_its_only_heading(): void {
		$this->seed_categories();
		$this->seed_posts( 2 );

		$html = $this->render( $this->search_url( 'number' ), 'search', self::SEARCH );

		$this->assertSame( 1, substr_count( $html, '<h1' ), 'One h1 per document.' );
		$this->assertMatchesRegularExpression(
			'~<h1[^>]*class="[^"]*dp-archive-title[^"]*"[^>]*>[^<]*number[^<]*</h1>~',
			$html,
			'The heading carries the search term.'
		);
	}

	/**
	 * It counts the whole result set, not the page of it being shown.
	 *
	 * @return void
	 */
	public function test_the_search_view_counts_every_match(): void {
		$this->seed_categories();
		$this->seed_posts( 12 );

		$html = $this->render( $this->search_url( 'number' ), 'search', self::SEARCH );

		$this->assertStringContainsString( '12 posts · Newest first', $html );
		$this->assertStringContainsString( '1–10 of 12 posts', $html, 'The pager works on a search too.' );
	}

	/**
	 * A search that matches nothing gets the design's empty panel.
	 *
	 * @return void
	 */
	public function test_a_search_that_matches_nothing_says_so(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );

		$html = $this->render( $this->search_url( 'aardvark' ), 'search', self::SEARCH );

		$this->assertSame( 0, substr_count( $html, 'dp-row-body' ) );
		$this->assertStringContainsString( 'dp-empty', $html );
		$this->assertStringContainsString( '0 posts', $html );
	}

	/*
	 * ---------------------------------------------------------- The last resort
	 */

	/**
	 * A tag archive lists its posts instead of rendering one of them.
	 *
	 * @return void
	 */
	public function test_a_tag_archive_lists_its_posts(): void {
		$this->seed_categories();
		$posts = $this->seed_posts( 3 );

		foreach ( $posts as $post_id ) {
			wp_set_post_terms( $post_id, array( 'lenses' ), 'post_tag' );
		}

		$link = get_term_link( 'lenses', 'post_tag' );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'tag', self::TAG );

		$this->assertTrue( is_tag() );
		$this->assertSame( 'dpaternina//index', $this->resolved_template() );
		$this->assertSame( 3, substr_count( $html, 'dp-row-body' ) );
		$this->assertStringNotContainsString( self::BODY, $html );
	}

	/**
	 * So does an author archive.
	 *
	 * @return void
	 */
	public function test_an_author_archive_lists_its_posts(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertIsInt( $author );

		foreach ( $this->posts as $post_id ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_author' => $author,
				)
			);
		}

		$html = $this->render( get_author_posts_url( $author ), 'author', self::AUTHOR );

		$this->assertTrue( is_author() );
		$this->assertSame( 'dpaternina//index', $this->resolved_template() );
		$this->assertSame( 3, substr_count( $html, 'dp-row-body' ) );
		$this->assertStringNotContainsString( self::BODY, $html );
	}

	/**
	 * And a date archive.
	 *
	 * @return void
	 */
	public function test_a_date_archive_lists_its_posts(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );

		$stamp = get_post_time( 'Y-n-j', false, $this->posts[0] );

		$this->assertIsString( $stamp );

		$parts = array_map( 'intval', explode( '-', $stamp ) );

		$html = $this->render( get_day_link( $parts[0], $parts[1], $parts[2] ), 'date', self::DATE );

		$this->assertTrue( is_date() );
		$this->assertSame( 'dpaternina//index', $this->resolved_template() );
		$this->assertStringContainsString( 'dp-row-body', $html );
		$this->assertStringNotContainsString( self::BODY, $html );
	}

	/**
	 * The last resort holds no `core/post-content` anywhere in it.
	 *
	 * The list above catches the four URLs that exist today. This catches the
	 * shape of the bug rather than its instances: a template that can answer any
	 * request in the hierarchy must not render one post as though it had been
	 * asked for.
	 *
	 * @return void
	 */
	public function test_the_last_resort_renders_no_single_post(): void {
		$markup = $this->theme_file( 'templates/index.html' );

		$this->assertStringNotContainsString( 'wp:post-content', $markup );
		$this->assertStringNotContainsString( 'wp:post-title', $markup );
		$this->assertStringContainsString( 'wp:query', $markup, 'The last resort is a list.' );
	}

	/*
	 * ------------------------------------------------------------------ Fixtures
	 */

	/**
	 * The URL of a search.
	 *
	 * @param string $term What was typed.
	 * @return string
	 */
	private function search_url( string $term ): string {
		return home_url( '/?s=' . rawurlencode( $term ) );
	}
}
