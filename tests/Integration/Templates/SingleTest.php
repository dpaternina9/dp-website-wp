<?php
/**
 * Integration tests for a post.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\LeadImage;
use DP\Theme\Blocks\SeriesPartsLink;
use WP_Block;
use WP_Post;

/**
 * The post: kicker, lead, series footer, and the two navigations under it.
 *
 * The kicker is the interesting one. The design derives it in one line —
 * `p.part ? 'SERIES · PART ' + p.part : p.cat` — with the tone following the
 * same condition, and nothing is stored: the part number is the post's position
 * among the published posts in its series (ADR-0016). Neither a template nor a
 * block binding can express any of that, so it comes from the theme's own
 * bindings source, and it is asserted here rather than trusted.
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
	 * The number is the position, so the third of three posts filed under the
	 * series — the oldest, because `seed_posts()` dates them newest first — is
	 * part 1 and the newest is part 3.
	 *
	 * @return void
	 */
	public function test_a_post_in_a_series_is_kickered_by_its_part(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		$this->file_under_series( $posts[0] );
		$this->file_under_series( $posts[1] );
		$this->file_under_series( $posts[2] );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<p class="dp-badge[^"]*is-tone-pink[^"]*"[^>]*>Series · Part 2</p>~', $html );
	}

	/**
	 * The standfirst is the post's first paragraph, and the read time is counted.
	 *
	 * Both used to be meta fields with no editor control, so both were blank on
	 * any post David wrote by hand (ADR-0016). What is asserted is unchanged: the
	 * words are above the body and the byline carries a duration.
	 *
	 * @return void
	 */
	public function test_the_standfirst_and_the_read_time_are_printed(): void {
		$this->seed_categories();

		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'A post that opens on its standfirst',
				'post_content'  => '<!-- wp:paragraph --><p>The standfirst above the body.</p><!-- /wp:paragraph -->'
					. '<!-- wp:paragraph --><p>' . implode( ' ', array_fill( 0, 900, 'word' ) ) . '</p><!-- /wp:paragraph -->',
				'post_category' => array( $this->categories['dev'] ),
			)
		);

		$this->assertIsInt( $post_id );

		$html = $this->render( $this->permalink( $post_id ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'The standfirst above the body.', $html );
		$this->assertStringContainsString( '5 min read', $html, '904 words at 200 a minute rounds up to five.' );
		$this->assertStringNotContainsString( 'dp-post-standfirst', $html, 'The standfirst is the first paragraph, not a block of its own.' );
	}

	/**
	 * A post with no body claims no read time rather than nought minutes.
	 *
	 * @return void
	 */
	public function test_an_empty_post_prints_no_read_time(): void {
		$this->seed_categories();

		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Nothing written yet',
				'post_content'  => '',
				'post_category' => array( $this->categories['dev'] ),
			)
		);

		$this->assertIsInt( $post_id );

		$html = $this->render( $this->permalink( $post_id ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-post-read', $html );
		$this->assertStringNotContainsString( 'min read', $html );
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
		$this->file_under_series( $posts[2] );
		$this->file_under_series( $posts[1] );
		$this->file_under_series( $posts[0] );

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

	/**
	 * The byline is the design's row: a mark, a name, a date, a read time.
	 *
	 * The monogram and the two middle dots are generated content, because both
	 * are `alt=""`/`aria-hidden` decoration in the design. What the markup owes
	 * them is the class the rule hangs off and one element per fact, in order.
	 *
	 * @return void
	 */
	public function test_the_byline_carries_a_name_a_date_and_a_read_time_in_order(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		/*
		 * `core/post-author-name` renders the empty string for a post whose
		 * author has no display name, and the post factory leaves `post_author`
		 * at zero. Giving the post an author is what makes the byline a byline.
		 */
		$author = self::factory()->user->create( array( 'display_name' => 'David Paternina' ) );

		$this->assertIsInt( $author );

		wp_update_post(
			array(
				'ID'          => $this->posts[0],
				'post_author' => $author,
			)
		);

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-post-byline', $html );

		$author = strpos( $html, 'dp-post-author' );
		$when   = strpos( $html, 'dp-post-when' );
		$read   = strpos( $html, 'dp-post-read' );

		$this->assertIsInt( $author );
		$this->assertIsInt( $when );
		$this->assertIsInt( $read );
		$this->assertGreaterThan( $author, $when );
		$this->assertGreaterThan( $when, $read );
		$this->assertStringContainsString( '1 min read', $html );
	}

	/**
	 * The lead image is captioned from the attachment, which is the only source.
	 *
	 * `dp_hero_caption` sat in front of this and had no editor control anywhere,
	 * so the media library's own caption box was in practice the only one anybody
	 * could fill in (ADR-0016). Now it is the only one that is read.
	 *
	 * @return void
	 */
	public function test_the_lead_image_takes_its_caption_from_the_attachment(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		set_post_thumbnail( $this->posts[0], $this->seed_attachment( 'A caption on the file itself.' ) );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( '<figcaption class="dp-post-lead-caption">A caption on the file itself.</figcaption>', $html );
	}

	/**
	 * A post with neither caption gets a figure and no figcaption.
	 *
	 * @return void
	 */
	public function test_an_uncaptioned_lead_image_gets_no_empty_figcaption(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		set_post_thumbnail( $this->posts[0], $this->seed_attachment( '' ) );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-post-lead-image', $html );
		$this->assertStringNotContainsString( 'dp-post-lead-caption', $html );
	}

	/**
	 * The lead image is asked for by name, and drawn whole.
	 *
	 * It used to be `core/post-featured-image` carrying the class
	 * `dp-post-lead-image`, with a `<figcaption>` spliced into core's rendered
	 * markup — `strrpos()` for the `</figure>` and `substr_replace()` to put the
	 * caption in front of it. Two things were wrong with that and only one of
	 * them was the fragility: the trigger was a bare CSS class (ADR-0018 rule 2),
	 * so the editor drew a figure with no caption while the page drew one with,
	 * and nothing in the template said why.
	 *
	 * The value it produced was right and is unchanged — the attachment's own
	 * caption, which ADR-0016 requires — so what this asserts is the mechanism.
	 *
	 * @return void
	 */
	public function test_the_lead_image_is_asked_for_by_name_rather_than_by_class(): void {
		$this->assertStringContainsString( 'wp:' . LeadImage::NAME, $this->theme_file( 'templates/single.html' ) );

		foreach ( $this->theme_markup_files() as $relative => $markup ) {
			$this->assertStringNotContainsString(
				'"className":"' . LeadImage::LEAD_CLASS . '"',
				$markup,
				$relative . ' asks for the caption with a class again.'
			);
		}
	}

	/**
	 * No theme source performs surgery on rendered HTML.
	 *
	 * The splice above was the last of it. `DP\Theme\Chrome\Navigation` still
	 * runs one `preg_replace`, over a URL rather than over markup, which is why
	 * this names the two functions that only ever appear when something is
	 * cutting a string of HTML open.
	 *
	 * @return void
	 */
	public function test_no_theme_source_splices_into_rendered_markup(): void {
		$offenders = array();

		foreach ( $this->theme_code() as $relative => $source ) {
			foreach ( array( 'substr_replace(', 'strrpos(' ) as $needle ) {
				if ( str_contains( $source, $needle ) ) {
					$offenders[] = $relative . ' — ' . $needle;
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Rendered markup is a block\'s to write, not a string to cut open. Render the element instead.'
		);
	}

	/**
	 * The caption is the one on the post the block was given, not the global one.
	 *
	 * The splice read `get_the_ID()`, so inside a loop over other posts — a
	 * related-posts grid, a query loop in a template part — it captioned
	 * whichever post happened to be set up rather than the one the block was
	 * rendering. The block reads its `postId` context first, the way
	 * `DP\Theme\Blocks\WorkCardTitle` does.
	 *
	 * @return void
	 */
	public function test_the_caption_follows_the_blocks_post_context(): void {
		$this->seed_categories();
		$posts = $this->seed_posts( 2 );

		set_post_thumbnail( $posts[0], $this->seed_attachment( 'The caption on the newer post.' ) );
		set_post_thumbnail( $posts[1], $this->seed_attachment( 'The caption on the older post.' ) );

		// Visiting the newer post is what sets the global post to it.
		$page = $this->render( $this->permalink( $posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'The caption on the newer post.', $page );

		$post = get_post();

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $posts[0], $post->ID );

		// And this is the same block, handed the older one instead.
		$block = new WP_Block(
			array(
				'blockName'    => LeadImage::NAME,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'postId'   => $posts[1],
				'postType' => 'post',
			)
		);

		$html = $block->render();

		$this->assertStringContainsString( 'The caption on the older post.', $html );
		$this->assertStringNotContainsString( 'The caption on the newer post.', $html );
	}

	/**
	 * The series footer is a kicker, a title, a way to the archive, and two cards.
	 *
	 * The build drew the term list *as* the title, in the kicker's pink, which
	 * collapsed three of the design's elements into one — and it had no link to
	 * the series archive beside them at all.
	 *
	 * @return void
	 */
	public function test_the_series_footer_separates_the_kicker_the_title_and_the_link(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		$this->file_under_series( $posts[2] );
		$this->file_under_series( $posts[1] );
		$this->file_under_series( $posts[0] );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$series_link = get_term_link( $this->series );

		$this->assertIsString( $series_link );

		$this->assertMatchesRegularExpression( '~<p class="dp-series-footer-kicker[^"]*">Series · Part 2</p>~', $html );
		$this->assertStringContainsString( 'dp-series-footer-title', $html );
		$this->assertStringContainsString( 'data-dp-destination="' . SeriesPartsLink::DESTINATION . '"', $html );
		$this->assertStringContainsString( '>All parts →</a>', $html );
		$this->assertSame(
			2,
			substr_count( $html, 'href="' . esc_url( $series_link ) . '"' ),
			'The title and the action both point at the archive.'
		);
	}

	/**
	 * Each part card is labelled with the number of the part it points at.
	 *
	 * The design writes "← PART 1" and "PART 3 →". Core's navigation block takes
	 * a fixed `label`, so the number arrives through `previous_post_link` /
	 * `next_post_link`, which hand over the adjacent post core already found.
	 *
	 * @return void
	 */
	public function test_the_part_cards_are_labelled_with_their_own_part_numbers(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 3 );

		$this->file_under_series( $posts[2] );
		$this->file_under_series( $posts[1] );
		$this->file_under_series( $posts[0] );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( '<span class="post-navigation-link__label">← Part 1</span>', $html );
		$this->assertStringContainsString( '<span class="post-navigation-link__label">Part 3 →</span>', $html );
		$this->assertStringNotContainsString( '%dp-part%', $html, 'The token never reaches the page.' );
	}

	/**
	 * A neighbour outside any series takes no number with it.
	 *
	 * @return void
	 */
	public function test_the_neighbouring_post_cards_are_labelled_newer_and_older(): void {
		$this->seed_categories();

		$posts = $this->seed_posts( 3 );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( '<span class="post-navigation-link__label">← Newer</span>', $html );
		$this->assertStringContainsString( '<span class="post-navigation-link__label">Older →</span>', $html );
	}

	/**
	 * "Keep reading" is three cards: same category first, never this post.
	 *
	 * The design states the rule in its own comment — "same category first, then
	 * whatever is newest, never the post you are on" — and `dpLoop: related`
	 * implements it as an explicit list, because it is a preference between two
	 * result sets rather than a sort of one.
	 *
	 * @return void
	 */
	public function test_keep_reading_puts_the_same_category_first_and_never_this_post(): void {
		$this->seed_categories();

		$food = $this->seed_posts( 1, 'food' );
		$dev  = $this->seed_posts( 4, 'dev' );

		$html = $this->render( $this->permalink( $dev[3] ), 'single', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-keep-reading', $html );
		$this->assertSame( 3, substr_count( $html, 'dp-related-card' ), 'Three cards, as the design draws.' );

		$this->assertSame(
			0,
			substr_count( $html, 'href="' . esc_url( $this->permalink( $dev[3] ) ) . '"' ),
			'Never the post you are on.'
		);

		foreach ( array_slice( $dev, 0, 3 ) as $sibling ) {
			$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $sibling ) ) . '"', $html );
		}

		$this->assertStringNotContainsString(
			'href="' . esc_url( $this->permalink( $food[0] ) ) . '"',
			$html,
			'Three in the same category fill the grid before anything else does.'
		);
	}

	/**
	 * With fewer siblings than cards, the rest of the newest posts fill in.
	 *
	 * @return void
	 */
	public function test_keep_reading_falls_back_to_the_newest_when_the_category_is_thin(): void {
		$this->seed_categories();

		$dev  = $this->seed_posts( 2, 'dev' );
		$food = $this->seed_posts( 3, 'food' );

		$html = $this->render( $this->permalink( $dev[0] ), 'single', self::HIERARCHY );

		$this->assertSame( 3, substr_count( $html, 'dp-related-card' ) );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $dev[1] ) ) . '"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $food[0] ) ) . '"', $html );
	}

	/**
	 * The only post on the site gets a section head and no cards.
	 *
	 * The guard that matters: an empty `post__in` is *ignored* by `WP_Query`, so
	 * a related loop that resolved to nothing would quietly draw the three
	 * newest posts on the site, one of which is the post being read.
	 *
	 * @return void
	 */
	public function test_keep_reading_draws_nothing_when_there_is_nothing_else(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		$html = $this->render( $this->permalink( $this->posts[0] ), 'single', self::HIERARCHY );

		$this->assertStringNotContainsString( 'dp-related-card', $html );
	}

	/**
	 * A card's kicker is the short form, without the word SERIES in front of it.
	 *
	 * @return void
	 */
	public function test_a_related_card_takes_the_short_kicker(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 2 );

		$this->file_under_series( $posts[0] );

		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<p class="dp-related-kicker[^"]*">Part 1</p>~', $html );
		$this->assertDoesNotMatchRegularExpression( '~<p class="dp-related-kicker[^"]*">Series · Part 1</p>~', $html );
	}

	/**
	 * An attachment carrying a caption, or not.
	 *
	 * @param string $caption The caption, or '' for none.
	 * @return int
	 */
	private function seed_attachment( string $caption ): int {
		/*
		 * A real upload, not `attachment->create()`. `core/post-featured-image`
		 * renders nothing at all when `wp_get_attachment_image()` cannot build a
		 * tag, and it cannot build one for an attachment with no file behind it —
		 * so an attachment without a file would test the caption by asserting on
		 * a figure that was never drawn.
		 */
		$attachment_id = self::factory()->attachment->create_upload_object(
			dirname( __DIR__, 3 ) . '/themes/dpaternina/assets/img/dp-mark-white-128.png'
		);

		$this->assertIsInt( $attachment_id );

		wp_update_post(
			array(
				'ID'           => $attachment_id,
				'post_excerpt' => $caption,
			)
		);

		return $attachment_id;
	}
}
