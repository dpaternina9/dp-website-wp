<?php
/**
 * Integration tests for a post.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

/**
 * The post: kicker, lead, series footer, and the two navigations under it.
 *
 * The kicker is the interesting one. `dp_kicker`'s registered description says
 * an empty value means derive it, and the design derives it in one line —
 * `p.part ? 'SERIES · PART ' + p.part : p.cat` — with the tone following the
 * same condition. Neither a template nor a block binding can express a choice
 * between two fields, so both come from the theme's own bindings source, and
 * both are asserted here rather than trusted.
 */
final class SingleTest extends TemplateTestCase {

	/**
	 * The hierarchy core hands `locate_block_template()` for a post.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'single-post.php', 'single.php', 'singular.php', 'index.php' );

	/**
	 * A post resolves to `single` and carries one h1.
	 *
	 * @return void
	 */
	public function test_a_post_resolves_to_single(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertTrue( is_singular( 'post' ) );
		$this->assertSame( 'dpaternina//single', $this->resolved_template() );
		$this->assertSame( 1, substr_count( $html, '<h1' ) );
		$this->assertStringContainsString( 'Body copy.', $html );
	}

	/**
	 * A post outside a series takes its category as its kicker, in teal.
	 *
	 * @return void
	 */
	public function test_a_post_outside_a_series_is_kickered_by_its_category(): void {
		$this->seed_categories();
		$this->seed_posts( 1, 'food' );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<p class="dp-badge[^"]*is-tone-teal[^"]*"[^>]*>Food</p>~', $html );
	}

	/**
	 * A post in a series takes its part number, in pink.
	 *
	 * @return void
	 */
	public function test_a_post_in_a_series_is_kickered_by_its_part(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 1 );
		$this->file_under_series( $this->posts[0], 2 );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<p class="dp-badge[^"]*is-tone-pink[^"]*"[^>]*>Series · Part 2</p>~', $html );
	}

	/**
	 * `dp_kicker` overrides the derivation, which is what it is for.
	 *
	 * @return void
	 */
	public function test_a_stored_kicker_wins_over_the_derivation(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		update_post_meta( $this->posts[0], 'dp_kicker', 'Field note' );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( '>Field note</p>', $html );
		$this->assertStringNotContainsString( '>Dev</p>', $html );
	}

	/**
	 * The standfirst and the read time come from meta.
	 *
	 * @return void
	 */
	public function test_the_lead_and_the_read_time_are_printed(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		update_post_meta( $this->posts[0], 'dp_lead', 'The standfirst above the body.' );
		update_post_meta( $this->posts[0], 'dp_read_time', '9 MIN READ' );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'The standfirst above the body.', $html );
		$this->assertStringContainsString( '9 MIN READ', $html );
	}

	/**
	 * The series footer links the series and the parts either side of this one.
	 *
	 * @return void
	 */
	public function test_the_series_footer_links_the_series_and_its_neighbours(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		// seed_posts() dates them newest first, so posts[2] is the oldest.
		$this->file_under_series( $posts[2], 1 );
		$this->file_under_series( $posts[1], 2 );
		$this->file_under_series( $posts[0], 3 );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$series_link = get_term_link( $this->series );

		$this->assertIsString( $series_link );
		$this->assertStringContainsString( 'dp-series-footer', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $series_link ) . '"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $posts[2] ) ) . '"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $posts[0] ) ) . '"', $html );
	}

	/**
	 * A post in no series renders the frame with nothing in it, for CSS to hide.
	 *
	 * The gradient frame is removed with `:has()`, not with PHP, so what this
	 * asserts is the precondition that rule depends on: there is no term link
	 * inside it to keep it visible.
	 *
	 * @return void
	 */
	public function test_a_post_in_no_series_has_no_term_link_in_the_footer(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringNotContainsString( 'taxonomy-dp_series', $html );
	}

	/**
	 * Newer and older reach the posts either side by date.
	 *
	 * @return void
	 */
	public function test_newer_and_older_reach_the_neighbouring_posts(): void {
		$this->seed_categories();

		$posts = $this->seed_posts( 3 );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-post-nav', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $posts[0] ) ) . '"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $posts[2] ) ) . '"', $html );
	}

	/**
	 * The blog reads as active on a post, which core does not do on its own.
	 *
	 * @return void
	 */
	public function test_the_blog_reads_as_active_on_a_post(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );
		$this->seed_posts_page();

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString(
			'aria-current="page" class="wp-block-pages-list__item__link',
			$html,
			'digest §2.1: the queried object is a post, so the blog is where the reader is.'
		);
	}

	/**
	 * With no posts page chosen, nothing in the nav claims to be the blog.
	 *
	 * Without this guard the item pointing at the site root would light up on
	 * every post, which is Home.
	 *
	 * @return void
	 */
	public function test_nothing_is_marked_when_no_posts_page_was_chosen(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringNotContainsString( 'aria-current="page"', $html );
	}
}
