<?php
/**
 * Integration tests for `dpaternina/series-index` and the `dp-series` template.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Content\Taxonomies;
use DP\Theme\Blocks\SeriesIndex;
use DP\Theme\Query\ArchiveFacts;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_REST_Request;
use WP_Term;

/**
 * The view `/series/` did not have.
 *
 * `register_taxonomy()` makes `/series/{term}/` and nothing else — there is no
 * term-index route for a flat taxonomy and no template in the hierarchy for one
 * — so the plural every part of the site talks about led nowhere. What is under
 * test is the shape CLAUDE.md §5.1 prescribes for exactly that: a `dp-`
 * prefixed custom template David assigns to a page he slugs himself, a block
 * that does the listing, and **no rewrite of any kind**.
 *
 * Four things are asserted that nothing else in the suite covers.
 *
 * **A series with only drafts is not listed.** Its archive's published list is
 * empty; the row would send a reader somewhere there is nothing to read.
 *
 * **Order is a decision, not an accident.** Most published parts first, lowest
 * term ID on a tie — the rule the deleted `Destinations::featured_series()`
 * used. The tie case is seeded deliberately, because a stable sort over
 * whatever `get_terms()` happened to return would pass without it.
 *
 * **The parts line has one author.** The sentence in a row is compared against
 * the sentence the series' own archive prints beside its badge, taken from the
 * rendered archive rather than from a constant — so a second copy of the string
 * fails here rather than drifting quietly.
 *
 * **The canvas and the page draw the same list**, which is ADR-0018's third
 * concern, asserted the way `FilterPillsTest` asserts it: the same block
 * rendered once by the template and once through the block-renderer route the
 * site editor previews with.
 */
final class SeriesIndexTest extends TemplateTestCase {

	/**
	 * The hierarchy for a page carrying the series-index template.
	 *
	 * @var array<int, string>
	 */
	private const ASSIGNED = array( 'dp-series.html', 'page.php', 'singular.php', 'index.php' );

	/**
	 * The hierarchy for a `dp_series` term archive.
	 *
	 * @var array<int, string>
	 */
	private const SERIES_ARCHIVE = array( 'taxonomy-dp_series.php', 'taxonomy.php', 'archive.php', 'index.php' );

	/*
	 * ------------------------------------------------------------- The block
	 */

	/**
	 * It is registered, dynamic, and in the inserter under its own name.
	 *
	 * ADR-0018 rule 2: a computation announces itself. A list nobody can type is
	 * a named block, not a class on a group.
	 *
	 * @return void
	 */
	public function test_the_block_is_registered_dynamic_and_named(): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( SeriesIndex::NAME );

		$this->assertInstanceOf( WP_Block_Type::class, $block );
		$this->assertTrue( $block->is_dynamic() );
		$this->assertSame( 'theme', $block->category );
		$this->assertNotSame( '', (string) $block->title );
	}

	/**
	 * Every series with something published in it, linking to its own archive.
	 *
	 * @return void
	 */
	public function test_it_lists_every_series_that_has_something_published(): void {
		$life        = $this->seed_series_term( 'life-story', 'My life story', 'The long version, one part at a time.' );
		$placeholder = $this->seed_series_term( 'placeholder-series', 'Placeholder series', 'A second series, so there are two.' );

		$this->publish_parts( $life, 2 );
		$this->publish_parts( $placeholder, 1 );

		$list = $this->list_from( $this->render_the_page() );

		$this->assertStringContainsString( 'My life story', $list );
		$this->assertStringContainsString( 'Placeholder series', $list );
		$this->assertStringContainsString( 'The long version, one part at a time.', $list );
		$this->assertStringContainsString( 'A second series, so there are two.', $list );
		$this->assertStringContainsString( 'href="' . esc_url( $this->term_link( $life ) ) . '"', $list );
		$this->assertStringContainsString( 'href="' . esc_url( $this->term_link( $placeholder ) ) . '"', $list );
	}

	/**
	 * A series that exists only as drafts is not offered to anybody.
	 *
	 * @return void
	 */
	public function test_a_series_with_only_drafts_is_not_listed(): void {
		$readable  = $this->seed_series_term( 'life-story', 'My life story' );
		$unwritten = $this->seed_series_term( 'not-started', 'Not started yet' );

		$this->publish_parts( $readable, 1 );
		$this->draft_parts( $unwritten, 3 );

		$list = $this->list_from( $this->render_the_page() );

		$this->assertStringContainsString( 'My life story', $list );
		$this->assertStringNotContainsString( 'Not started yet', $list );
	}

	/**
	 * Nothing readable renders nothing at all — not an empty state.
	 *
	 * The page keeps its heading and its deck, which is the whole of what there
	 * is to say when there is nothing to list.
	 *
	 * @return void
	 */
	public function test_a_site_with_no_readable_series_renders_no_list(): void {
		$this->draft_parts( $this->seed_series_term( 'not-started', 'Not started yet' ), 2 );

		$html = $this->render_the_page();

		$this->assertStringNotContainsString( 'dp-part-row', $html );
		$this->assertStringNotContainsString( 'Not started yet', $html );
		$this->assertStringContainsString( 'Every series', $html, 'The page itself still renders; only the list is absent.' );
	}

	/**
	 * The longest series first, and the lower term ID wins a tie.
	 *
	 * @return void
	 */
	public function test_the_longest_series_comes_first_and_a_tie_goes_to_the_lower_term_id(): void {
		$first  = $this->seed_series_term( 'tied-first', 'Tied, made first' );
		$second = $this->seed_series_term( 'tied-second', 'Tied, made second' );
		$long   = $this->seed_series_term( 'the-long-one', 'The long one' );

		$this->publish_parts( $first, 2 );
		$this->publish_parts( $second, 2 );
		$this->publish_parts( $long, 3 );

		$this->assertLessThan( $second, $first, 'The tie case needs the two terms in a known ID order.' );

		$list = $this->list_from( $this->render_the_page() );

		$this->assertSame(
			array( 'The long one', 'Tied, made first', 'Tied, made second' ),
			$this->names_in( $list )
		);
	}

	/**
	 * A row prints the sentence the series' own archive prints.
	 *
	 * Taken off the rendered archive rather than out of a constant: what this
	 * catches is a second copy of the string, and a second copy compared against
	 * itself would pass.
	 *
	 * @return void
	 */
	public function test_a_row_prints_the_same_parts_line_the_archive_does(): void {
		$series = $this->seed_series_term( 'life-story', 'My life story' );

		$this->publish_parts( $series, 3 );
		$this->draft_parts( $series, 2 );

		$archive = $this->render( $this->term_link( $series ), 'taxonomy-dp_series', self::SERIES_ARCHIVE );
		$written = $this->written_line( $archive );

		$this->assertNotSame( '', $written, 'The series archive prints no parts line, so there is nothing to hold the index to.' );

		$this->assertStringContainsString(
			'<p class="dp-series-written">' . esc_html( $written ) . '</p>',
			$this->list_from( $this->render_the_page() ),
			'The index and the archive describe the same series in different words.'
		);
	}

	/**
	 * The one place that sentence is written answers for a named term too.
	 *
	 * @return void
	 */
	public function test_the_parts_line_is_null_without_a_term(): void {
		$this->assertNull( ( new ArchiveFacts() )->parts_line( 0 ) );
	}

	/*
	 * ---------------------------------------------------- The editor and the page
	 */

	/**
	 * What the site editor previews is what the page draws.
	 *
	 * @return void
	 */
	public function test_the_canvas_and_the_page_render_the_same_list(): void {
		$life = $this->seed_series_term( 'life-story', 'My life story', 'The long version.' );

		$this->publish_parts( $life, 2 );
		$this->draft_parts( $life, 1 );
		$this->publish_parts( $this->seed_series_term( 'placeholder-series', 'Placeholder series' ), 1 );

		$this->assertSame(
			$this->list_from( $this->render_the_page() ),
			$this->list_from( $this->as_the_editor_renders_it() ),
			'The site editor previews this block through the block-renderer route; what it draws there is what the page has to draw.'
		);
	}

	/*
	 * ------------------------------------------------------------ The template
	 */

	/**
	 * The theme offers `dp-series` for a page, and the hierarchy never applies it.
	 *
	 * The `dp-` prefix is the whole point: `page-series.html` would be applied by
	 * the hierarchy to any page slugged `series`, which is the coupling
	 * CLAUDE.md §5.1 exists to prevent.
	 *
	 * @return void
	 */
	public function test_the_template_is_offered_as_a_page_template_and_is_prefixed(): void {
		$offered = wp_get_theme()->get_page_templates( null, 'page' );

		$this->assertArrayHasKey( 'dp-series', $offered );
		$this->assertFileIsReadable( get_theme_file_path( 'templates/dp-series.html' ) );
		$this->assertFileDoesNotExist( get_theme_file_path( 'templates/page-series.html' ) );
	}

	/**
	 * A page assigned it renders the hero, the list, and one `h1`.
	 *
	 * The page's own title and deck are what the hero prints, so the words on the
	 * page are David's and none of them is in the theme.
	 *
	 * @return void
	 */
	public function test_a_page_assigned_the_template_renders_the_index(): void {
		$series = $this->seed_series_term( 'life-story', 'My life story' );

		$this->publish_parts( $series, 2 );

		$html = $this->render_the_page();

		$this->assertSame( 'dpaternina//dp-series', $this->resolved_template() );
		$this->assertStringContainsString( 'The reading orders', $html );
		$this->assertStringContainsString( 'Whatever David called this page.', $html );
		$this->assertStringContainsString( 'dp-part-row', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ), 'A page has exactly one h1.' );
	}

	/**
	 * It carries no link to a page on this site, in either direction.
	 *
	 * Every href the template produces is a term archive, written by the block
	 * because nobody can type it. The chrome's links are David's, set in the
	 * editor (ADR-0018).
	 *
	 * @return void
	 */
	public function test_the_template_ships_no_href(): void {
		$this->assertStringNotContainsString( 'href', $this->theme_file( 'templates/dp-series.html' ) );
	}

	/*
	 * ---------------------------------------------------------------- Harness
	 */

	/**
	 * Render a page assigned the template, with the words a seeded page carries.
	 *
	 * @return string
	 */
	private function render_the_page(): string {
		$page = $this->seed_page( 'The reading orders', 'dp-series.html' );

		update_post_meta( $page, 'dp_lead', 'Whatever David called this page.' );

		return $this->render( $this->permalink( $page ), 'page', self::ASSIGNED );
	}

	/**
	 * Render the block the way `ServerSideRender` does.
	 *
	 * @return string The rendered markup.
	 */
	private function as_the_editor_renders_it(): string {
		$editor = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertIsInt( $editor );

		wp_set_current_user( $editor );

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/' . SeriesIndex::NAME );

		$request->set_param( 'context', 'edit' );

		$response = rest_do_request( $request );

		$this->assertFalse( $response->is_error(), 'The block-renderer route refused the block the editor previews with.' );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertIsString( $data['rendered'] ?? null );

		return $data['rendered'];
	}

	/**
	 * A `dp_series` term.
	 *
	 * @param string $slug        The term slug.
	 * @param string $name        The term name.
	 * @param string $description The deck, which is where ADR-0017 put it.
	 * @return int The term ID.
	 */
	private function seed_series_term( string $slug, string $name, string $description = '' ): int {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy'    => Taxonomies::SERIES,
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
			)
		);

		$this->assertInstanceOf( WP_Term::class, $term );

		return $term->term_id;
	}

	/**
	 * Published parts of a series.
	 *
	 * @param int $term_id The series.
	 * @param int $count   How many.
	 * @return void
	 */
	private function publish_parts( int $term_id, int $count ): void {
		$this->parts( $term_id, $count, 'publish' );
	}

	/**
	 * Drafted parts of a series.
	 *
	 * @param int $term_id The series.
	 * @param int $count   How many.
	 * @return void
	 */
	private function draft_parts( int $term_id, int $count ): void {
		$this->parts( $term_id, $count, 'draft' );
	}

	/**
	 * Posts filed under a series.
	 *
	 * @param int    $term_id The series.
	 * @param int    $count   How many.
	 * @param string $status  The post status.
	 * @return void
	 */
	private function parts( int $term_id, int $count, string $status ): void {
		for ( $index = 0; $index < $count; $index++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_title'   => sprintf( 'Part %d of term %d', $index + 1, $term_id ),
					'post_excerpt' => 'The standfirst.',
					'post_status'  => $status,
				)
			);

			$this->assertIsInt( $post_id );

			wp_set_post_terms( $post_id, array( $term_id ), Taxonomies::SERIES, false );
		}
	}

	/**
	 * A term's archive URL, asserted to exist.
	 *
	 * @param int $term_id The term.
	 * @return string
	 */
	private function term_link( int $term_id ): string {
		$link = get_term_link( $term_id, Taxonomies::SERIES );

		$this->assertIsString( $link );

		return $link;
	}

	/**
	 * The block's `<ul>`, out of a whole rendered page.
	 *
	 * @param string $html The rendered markup.
	 * @return string Empty when the list is absent.
	 */
	private function list_from( string $html ): string {
		return 1 === preg_match(
			'~<ul[^>]*class="[^"]*' . preg_quote( SeriesIndex::LIST_CLASS, '~' ) . '[^"]*"[^>]*>.*?</ul>~s',
			$html,
			$found
		) ? $found[0] : '';
	}

	/**
	 * The series names in the list, in the order they are drawn.
	 *
	 * @param string $markup The block's `<ul>`.
	 * @return list<string>
	 */
	private function names_in( string $markup ): array {
		preg_match_all( '~<h3 class="dp-part-title"><a href="[^"]*">([^<]*)</a></h3>~', $markup, $found );

		return $found[1];
	}

	/**
	 * The parts line the series archive printed beside its badge.
	 *
	 * @param string $html The rendered archive.
	 * @return string Empty when the archive printed none.
	 */
	private function written_line( string $html ): string {
		return 1 === preg_match( '~class="[^"]*dp-series-written[^"]*"[^>]*>([^<]+)</p>~', $html, $found )
			? html_entity_decode( $found[1], ENT_QUOTES, 'UTF-8' )
			: '';
	}
}
