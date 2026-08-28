<?php
/**
 * Integration tests for the category and series archives.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Content\Taxonomies;
use WP_Post;

/**
 * The two archives, and the one thing the series archive must never do.
 *
 * Plan §3.1 accepts one cost for making planned parts drafts: their titles
 * become public. The whole of the rest of that decision rests on the cost being
 * exactly a title, a year range and a note — so the assertions here are as much
 * about what is absent as what is present.
 */
final class ArchiveTest extends TemplateTestCase {

	/**
	 * The hierarchy for a category archive.
	 *
	 * @var array<int, string>
	 */
	private const CATEGORY = array( 'category.php', 'archive.php', 'index.php' );

	/**
	 * The hierarchy for the series archive.
	 *
	 * @var array<int, string>
	 */
	private const SERIES = array( 'taxonomy-dp_series.php', 'taxonomy.php', 'archive.php', 'index.php' );

	/**
	 * A category archive lists only that category's posts.
	 *
	 * @return void
	 */
	public function test_a_category_archive_lists_only_its_own_posts(): void {
		$this->seed_categories();
		$this->seed_posts( 3, 'dev' );
		$this->seed_posts( 2, 'food' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertTrue( is_category() );
		$this->assertSame( 'dpaternina//category', $this->resolved_template() );
		$this->assertSame( 3, substr_count( $html, 'dp-row-body' ) );
	}

	/**
	 * The archive prints the term's own description, which the design uses as its deck.
	 *
	 * @return void
	 */
	public function test_the_archive_prints_the_terms_description(): void {
		$this->seed_categories();
		$this->seed_posts( 1, 'dev' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'Dev — what this archive collects.', $html );
	}

	/**
	 * The other-categories band lists the siblings and marks the current one for removal.
	 *
	 * The current category is taken out in CSS rather than in PHP — it is the
	 * page the reader is already on, and `wp_list_categories` already marks it.
	 * The assertion is therefore that the mark is there to be acted on.
	 *
	 * @return void
	 */
	public function test_the_other_categories_band_marks_the_current_one(): void {
		$this->seed_categories();
		$this->seed_posts( 1, 'dev' );
		$this->seed_posts( 1, 'food' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-category-band', $html );
		$this->assertStringContainsString( 'current-cat', $html );

		$food = get_category_link( $this->categories['food'] );

		$this->assertIsString( $food );
		$this->assertStringContainsString( 'href="' . esc_url( $food ) . '"', $html );
	}

	/**
	 * The series archive resolves to its own template and runs in part order.
	 *
	 * Part order is oldest first, which is the reverse of what an archive does by
	 * default and what makes this worth asserting: `seed_posts()` dates its posts
	 * newest first, so a series that came out in the seeded order would be wrong.
	 *
	 * @return void
	 */
	public function test_the_series_archive_runs_in_part_order(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		$this->file_under_series( $posts[0] );
		$this->file_under_series( $posts[1] );
		$this->file_under_series( $posts[2] );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertSame( 'dpaternina//taxonomy-dp_series', $this->resolved_template() );

		$positions = array();

		foreach ( $posts as $post_id ) {
			$position = strpos( $html, (string) get_the_title( $post_id ) );

			$this->assertIsInt( $position );

			$positions[] = $position;
		}

		$this->assertGreaterThan( $positions[2], $positions[1], 'Part 2 comes after part 1.' );
		$this->assertGreaterThan( $positions[1], $positions[0], 'Part 3 comes after part 2.' );
	}

	/**
	 * "Still to come" lists the drafts, and leaks neither body nor link.
	 *
	 * @return void
	 */
	public function test_still_to_come_lists_drafts_without_body_or_link(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		$draft_id = self::factory()->post->create(
			array(
				'post_title'   => 'Before any of it was a job',
				'post_status'  => 'draft',
				'post_name'    => 'before-any-of-it',
				'post_content' => 'UNPUBLISHED-BODY-THAT-MUST-NEVER-ESCAPE',
				'post_excerpt' => 'A borrowed computer and no idea this was work.',
			)
		);

		$this->assertIsInt( $draft_id );

		$this->file_under_series( $draft_id );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringContainsString( 'Before any of it was a job', $html );
		$this->assertStringContainsString( 'A borrowed computer and no idea this was work.', $html );
		$this->assertStringContainsString( '>Draft</p>', $html, 'A planned part is labelled, not numbered.' );

		$draft = get_post( $draft_id );

		$this->assertInstanceOf( WP_Post::class, $draft );
		$this->assertBodyAbsent( $html, $draft );
		$this->assertStringNotContainsString( 'before-any-of-it', $html, "A draft's permalink must not be reachable." );
	}

	/**
	 * With nothing drafted, "Still to come" renders nothing rather than an empty frame.
	 *
	 * @return void
	 */
	public function test_still_to_come_renders_nothing_when_there_is_nothing_planned(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringNotContainsString( 'dp-planned-row', $html );
	}

	/**
	 * The blog is the active nav item on both archives, derived from the queried object.
	 *
	 * @return void
	 */
	public function test_the_blog_reads_as_active_on_both_archives(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 1 );

		$page = $this->seed_posts_page();

		$this->file_under_series( $this->posts[0] );

		$expected = 'aria-current="page" class="wp-block-pages-list__item__link';

		$category = get_category_link( $this->categories['dev'] );
		$series   = get_term_link( $this->series );

		$this->assertIsString( $category );
		$this->assertIsString( $series );

		$this->assertStringContainsString(
			$expected,
			$this->render( $category, 'category', self::CATEGORY ),
			'digest §2.1: Blog reads as active for a category.'
		);

		$this->assertStringContainsString(
			$expected,
			$this->render( $series, 'taxonomy-dp_series', self::SERIES ),
			'digest §2.1: Blog reads as active for a series.'
		);

		$this->assertStringContainsString( (string) get_the_title( $page ), $this->render( $category, 'category', self::CATEGORY ) );
	}

	/**
	 * The breadcrumb is both halves the design draws, not just the link.
	 *
	 * "WRITING / CATEGORY" — an accent-text link, a slash nobody reads out, and
	 * the plain word for what kind of archive this is. The build dropped the
	 * second half and rendered the first as a lone button.
	 *
	 * @return void
	 */
	public function test_the_breadcrumb_names_where_it_is_as_well_as_where_it_came_from(): void {
		$this->seed_categories();
		$this->seed_posts( 1, 'dev' );
		$this->seed_posts_page();

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-crumbs', $html );
		$this->assertStringContainsString( 'dp-crumb-home', $html );
		$this->assertStringContainsString( '>Writing</a>', $html );
		$this->assertStringContainsString( 'dp-crumb-here', $html );
		$this->assertStringContainsString( '>Category</p>', $html );
	}

	/**
	 * The archive says how much of it there is, and in what order.
	 *
	 * @return void
	 */
	public function test_the_archive_counts_itself(): void {
		$this->seed_categories();
		$this->seed_posts( 3, 'dev' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( '>3 posts · Newest first</p>', $html );
	}

	/**
	 * A term with one post says "post", not "posts".
	 *
	 * @return void
	 */
	public function test_the_count_is_singular_when_there_is_one(): void {
		$this->seed_categories();
		$this->seed_posts( 1, 'food' );

		$link = get_category_link( $this->categories['food'] );

		$this->assertIsString( $link );

		$this->assertStringContainsString(
			'>1 post · Newest first</p>',
			$this->render( $link, 'category', self::CATEGORY )
		);
	}

	/**
	 * The archive closes with the line and the button the design puts there.
	 *
	 * @return void
	 */
	public function test_the_archive_closes_with_a_line_and_a_way_out(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts_page();

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-archive-outro', $html );
		$this->assertStringContainsString( 'That is everything filed under this one.', $html );
		$this->assertStringContainsString( '>All writing</a>', $html );
	}

	/**
	 * An empty term gets the quiet line, not the index's panel.
	 *
	 * The design draws two different empty states because they belong to two
	 * different views: `archiveEmpty` is one paragraph on a term archive, and
	 * `noResults` is a panel with a monogram and two buttons on the index.
	 *
	 * @return void
	 */
	public function test_an_empty_term_gets_one_line_rather_than_the_index_panel(): void {
		$this->seed_categories();

		$link = get_category_link( $this->categories['food'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-archive-empty', $html );
		$this->assertStringContainsString( 'Nothing filed here yet.', $html );
		$this->assertStringNotContainsString( 'dp-empty-count', $html );
	}

	/*
	 * What each pill in that band contains — the count in an element of its own,
	 * a name with parentheses in it printed whole — belongs to the block now, and
	 * is `DP\Tests\Integration\Templates\FilterPillsTest`.
	 */

	/**
	 * A term archive paginates, with the range naming the term.
	 *
	 * @return void
	 */
	public function test_a_long_term_archive_paginates_and_names_itself_in_the_range(): void {
		$this->seed_categories();
		$this->seed_posts( 12, 'dev' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( '>1–10 of 12 in Dev</p>', $html );
		$this->assertStringContainsString( 'dp-pagination', $html );
		$this->assertStringNotContainsString( 'dp-at-end', $html, 'The closing panel belongs to the index.' );
	}

	/**
	 * The series hero counts what is written and what is not.
	 *
	 * @return void
	 */
	public function test_the_series_hero_counts_published_and_drafted_parts(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 2 );

		$this->file_under_series( $published[0] );
		$this->file_under_series( $published[1] );

		$draft = self::factory()->post->create(
			array(
				'post_title'  => 'Not written yet',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $draft );

		$this->file_under_series( $draft );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringContainsString( '>2 parts up · 1 drafted</p>', $html );
	}

	/**
	 * The series deck is the term's description.
	 *
	 * The design calls it a deck, core calls the field `description`, and there is
	 * no second field. The value goes in through the textarea core already draws
	 * on the Edit Series screen, and comes out here.
	 *
	 * @return void
	 */
	public function test_the_series_deck_comes_from_the_term_description(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		wp_update_term(
			$this->series,
			Taxonomies::SERIES,
			array( 'description' => 'The deck the design puts under the title.' )
		);

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertMatchesRegularExpression(
			'~<p class="dp-series-hero-deck[^"]*">The deck the design puts under the title\.</p>~',
			$html
		);
	}

	/**
	 * A series with no description prints no deck.
	 *
	 * The bound paragraph is left with the content the template typed, which is
	 * nothing — so the hero draws `<p class="dp-series-hero-deck"></p>` and the
	 * stylesheet's `:empty` rule takes it out of the flow. The assertion is that
	 * the element is empty, not that it is gone: `ArchiveFacts::deck()` returns
	 * null, and a null binding leaves the block's own content alone rather than
	 * removing the block.
	 *
	 * @return void
	 */
	public function test_a_series_with_no_description_prints_no_deck(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		wp_update_term( $this->series, Taxonomies::SERIES, array( 'description' => '' ) );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertMatchesRegularExpression( '~<p class="dp-series-hero-deck[^"]*"></p>~', $html );
		$this->assertStringNotContainsString( 'The deck under the series title.', $html );
	}

	/**
	 * A published part is three columns: its number, its title, and a way in.
	 *
	 * The build reused the compact `PostRow`, which is a different component
	 * with a different grid — and it had no part number in it at all.
	 *
	 * @return void
	 */
	public function test_a_published_part_carries_its_number_its_meta_and_a_link(): void {
		$this->seed_categories();
		$this->seed_series();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringContainsString( 'dp-part-row', $html );
		$this->assertStringContainsString( '>Part 1</p>', $html );
		$this->assertStringContainsString( '1 min read', $html );
		$this->assertStringContainsString( 'dp-part-status', $html );
		$this->assertStringContainsString( 'Read it →', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $published[0] ) ) . '"', $html );
		$this->assertStringNotContainsString( 'dp-row-compact', $html, 'A part row is not a PostRow.' );
	}

	/**
	 * The series archive closes with the same way out the design gives it.
	 *
	 * @return void
	 */
	public function test_the_series_archive_closes_with_all_writing(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts_page();

		$published = $this->seed_posts( 1 );

		$this->file_under_series( $published[0] );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringContainsString( 'dp-series-outro', $html );
		$this->assertStringContainsString( '>All writing</a>', $html );
	}
}
