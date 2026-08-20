<?php
/**
 * Integration tests for the category and series archives.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

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
	 * @return void
	 */
	public function test_the_series_archive_runs_in_part_order(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		// Filed out of date order on purpose: newest post, earliest part.
		$this->file_under_series( $posts[0], 3 );
		$this->file_under_series( $posts[1], 1 );
		$this->file_under_series( $posts[2], 2 );

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

		$this->assertGreaterThan( $positions[1], $positions[2], 'Part 2 comes after part 1.' );
		$this->assertGreaterThan( $positions[2], $positions[0], 'Part 3 comes after part 2.' );
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

		$this->file_under_series( $published[0], 1 );

		$draft_id = self::factory()->post->create(
			array(
				'post_title'   => 'Before any of it was a job',
				'post_status'  => 'draft',
				'post_name'    => 'before-any-of-it',
				'post_content' => 'UNPUBLISHED-BODY-THAT-MUST-NEVER-ESCAPE',
			)
		);

		$this->assertIsInt( $draft_id );

		$this->file_under_series( $draft_id, 2, '1995 — 2007', 'A borrowed computer and no idea this was work.' );

		$link = get_term_link( $this->series );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'taxonomy-dp_series', self::SERIES );

		$this->assertStringContainsString( 'Before any of it was a job', $html );
		$this->assertStringContainsString( '1995 — 2007', $html );
		$this->assertStringContainsString( 'A borrowed computer and no idea this was work.', $html );

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

		$this->file_under_series( $published[0], 1 );

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

		$this->file_under_series( $this->posts[0], 1 );

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
}
