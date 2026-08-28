<?php
/**
 * Integration tests for `dpaternina/filter-pills`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\FilterPills;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_REST_Request;
use WP_Term;

/**
 * The pill row, which used to be two rewrites of `core/categories`' output.
 *
 * ADR-0018's three rules are what this file checks, and each of the three had a
 * failure to its name here:
 *
 * - **A template says what a template can say.** The block is named in the
 *   markup and its variation is an attribute, where a `className` used to be the
 *   whole trigger. `dp-filter-pills` on a `core/categories` block did not say
 *   that PHP would splice an extra `<li>` into the result.
 * - **Code that must compute something announces itself.** There is a block, a
 *   title and two variations in the inserter.
 * - **The editor and the page draw the same thing.** They did not: the canvas
 *   drew N categories and the page drew N + 1. That one is asserted directly,
 *   by rendering the block the way the editor renders it — through the
 *   block-renderer route — and comparing the two strings.
 *
 * The count deserves its own note. It used to be moved inside the anchor by a
 * regular expression over rendered HTML, `~(<a\b[^>]*>)([^<]*)</a>\s*\(([^()<]+)\)~`,
 * because core writes it as a bare text node. Every assertion about a category
 * whose *name* contains parentheses is therefore about a class of bug that a
 * pattern matched against markup can have and a block that writes its own
 * markup cannot: there is nothing to parse, so there is nothing to misparse.
 */
final class FilterPillsTest extends TemplateTestCase {

	/**
	 * The hierarchy for the posts index.
	 *
	 * @var array<int, string>
	 */
	private const HOME = array( 'home.php', 'index.php' );

	/**
	 * The hierarchy for a category archive.
	 *
	 * @var array<int, string>
	 */
	private const CATEGORY = array( 'category.php', 'archive.php', 'index.php' );

	/*
	 * --------------------------------------------------------- The block itself
	 */

	/**
	 * It is registered, dynamic, and offers both of the design's rows by name.
	 *
	 * The two variations are the answer to "one block with an attribute, or two
	 * blocks?" — one render, one query, one stylesheet contract, and two entries
	 * in the inserter so that neither row has to be built by typing an attribute.
	 *
	 * The variations are read from the shipped `block.json` rather than off the
	 * registered type, because that file is where they are declared and where a
	 * change to them would be made. Declaring them there rather than in
	 * JavaScript is the point: the editor bundle registers a preview and nothing
	 * else, so the inserter entries survive without a build.
	 *
	 * @return void
	 */
	public function test_the_block_offers_both_of_the_designs_rows(): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( FilterPills::NAME );

		$this->assertInstanceOf( WP_Block_Type::class, $block );
		$this->assertTrue( $block->is_dynamic() );
		$this->assertSame( 'theme', $block->category );

		$variations = array_keys( $this->declared_variations() );

		sort( $variations );

		$this->assertSame(
			array( FilterPills::VARIANT_BAND, FilterPills::VARIANT_FILTER ),
			$variations,
			'The design draws two pill rows; both should be insertable without typing an attribute.'
		);
	}

	/**
	 * The variations the block's own `block.json` declares, as name => title.
	 *
	 * @return array<string, string>
	 */
	private function declared_variations(): array {
		$path = get_theme_file_path( 'blocks/filter-pills/block.json' );

		$this->assertFileIsReadable( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the theme under test.
		$json = file_get_contents( $path );

		$this->assertIsString( $json );

		$definition = json_decode( $json, true );

		$this->assertIsArray( $definition );

		$declared = $definition['variations'] ?? null;

		$this->assertIsArray( $declared, 'The block declares no variations, so neither row is offered by name.' );

		$variations = array();

		foreach ( $declared as $variation ) {
			$this->assertIsArray( $variation );

			$name  = $variation['name'] ?? null;
			$title = $variation['title'] ?? null;

			$this->assertIsString( $name );
			$this->assertIsString( $title, 'A variation with no title is not an entry in the inserter.' );

			$variations[ $name ] = $title;
		}

		return $variations;
	}

	/*
	 * ------------------------------------------------------------ The filter row
	 */

	/**
	 * The index leads with All, and every pill is a link to a real archive.
	 *
	 * `FilterPills.dc.html`: "these are real links to filtered archive URLs, not
	 * JS tabs." That is the whole promise — the row works with scripting off —
	 * so the assertion is on the anchors.
	 *
	 * @return void
	 */
	public function test_the_filter_row_leads_with_all_and_links_to_real_archives(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts( 1, 'food' );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HOME );

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

		$this->assertStringNotContainsString(
			FilterPills::COUNT_CLASS,
			$this->pill_row( $html, 'dp-filter-pills' ),
			'The design prints no counts on the index filter; those belong to the archive band.'
		);
	}

	/**
	 * All is the current pill on the index and the queried category is on its archive.
	 *
	 * @return void
	 */
	public function test_the_current_pill_follows_the_query(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts( 1, 'food' );

		$page  = $this->seed_posts_page();
		$index = $this->render( $this->permalink( $page ), 'home', self::HOME );

		$this->assertStringContainsString(
			'<li class="cat-item dp-pill-all current-cat"><a href=',
			$index,
			'Nothing is filtering the index, so All is the pill that is current.'
		);
		$this->assertStringContainsString( 'aria-current="page"', $index );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$archive = $this->render( $link, 'category', self::CATEGORY );
		$term    = get_term( $this->categories['dev'] );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertStringContainsString(
			'<li class="cat-item cat-item-' . $term->term_id . ' current-cat">',
			$archive,
			'The archive being read is the pill that is marked, which is what hides it from the band.'
		);
		$this->assertStringNotContainsString(
			'dp-pill-all',
			$archive,
			'The filter row belongs to the index; the archive draws the band instead.'
		);
	}

	/**
	 * A site with no categories draws no row at all.
	 *
	 * A filter offering one choice is not a filter, and the design draws no
	 * empty rail above the list.
	 *
	 * @return void
	 */
	public function test_a_site_with_no_categories_draws_no_row(): void {
		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HOME );

		$this->assertStringNotContainsString( 'dp-filter-pills', $html );
		$this->assertStringNotContainsString( 'dp-pill-all', $html );
	}

	/*
	 * ----------------------------------------------------------------- The band
	 */

	/**
	 * The band prints each count in an element of its own.
	 *
	 * The count is `--text-muted` where the name is `--text-primary`, which needs
	 * a wrapper. Core writes it as a text node outside the pill, which is why
	 * this used to be a rewrite.
	 *
	 * @return void
	 */
	public function test_the_band_prints_each_count_in_its_own_element(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts( 3, 'food' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-category-pills', $html );
		$this->assertStringContainsString( 'Food<span class="dp-cat-count">3</span></a>', $html );
		$this->assertStringNotContainsString( '</a> (3)', $html, 'The count is inside the pill, not beside it.' );
	}

	/**
	 * A category whose name contains parentheses keeps its name and its count.
	 *
	 * This is the shape of bug the old regular expression could have — it looked
	 * for `</a>` followed by `(n)` and had to guess where the name ended — and it
	 * is the reason the count is written as an element rather than moved into
	 * one. Nothing here parses HTML, so a name is a string that gets escaped and
	 * printed, whatever is in it.
	 *
	 * @return void
	 */
	public function test_a_category_named_with_parentheses_is_printed_whole(): void {
		$this->seed_categories();

		$odd = self::factory()->category->create_and_get(
			array(
				'slug' => 'tools-beta',
				'name' => 'Tools (beta)',
			)
		);

		$this->assertInstanceOf( WP_Term::class, $odd );

		$this->categories['tools-beta'] = $odd->term_id;

		$this->seed_posts( 2, 'tools-beta' );
		$this->seed_posts( 1, 'dev' );

		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		$html = $this->render( $link, 'category', self::CATEGORY );

		$this->assertStringContainsString(
			'>Tools (beta)<span class="dp-cat-count">2</span></a>',
			$html,
			'The name is printed whole and the count is its own element beside it.'
		);
		$this->assertStringNotContainsString( 'Tools <span class="dp-cat-count">beta</span>', $html );
	}

	/*
	 * ------------------------------------------------------ The editor and the page
	 */

	/**
	 * The editor draws exactly what the page draws.
	 *
	 * This is the assertion the old mechanism could never have passed. The
	 * editor renders saved markup and never ran `render_block_core/categories`,
	 * so the canvas listed the site's categories and the front end listed them
	 * plus an All pill nobody could see in the editor. Here the same block is
	 * rendered twice — once by the template, once through the block-renderer
	 * route the canvas previews with — and the two strings have to match.
	 *
	 * @return void
	 */
	public function test_the_canvas_and_the_page_render_the_same_row(): void {
		$this->seed_categories();
		$this->seed_posts( 2, 'dev' );
		$this->seed_posts( 3, 'food' );

		$page = $this->seed_posts_page();
		$html = $this->render( $this->permalink( $page ), 'home', self::HOME );

		$this->assertSame(
			$this->pill_row( $html, 'dp-filter-pills' ),
			$this->pill_row( $this->as_the_editor_renders_it( FilterPills::VARIANT_FILTER ), 'dp-filter-pills' ),
			'The site editor previews this block through the block-renderer route; what it draws there is what the page has to draw.'
		);
	}

	/**
	 * Render the block the way `ServerSideRender` does.
	 *
	 * @param string $variant Which of the design's two rows to ask for.
	 * @return string The rendered markup.
	 */
	private function as_the_editor_renders_it( string $variant ): string {
		$editor = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertIsInt( $editor );

		wp_set_current_user( $editor );

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/' . FilterPills::NAME );

		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', array( 'variant' => $variant ) );

		$response = rest_do_request( $request );

		$this->assertFalse( $response->is_error(), 'The block-renderer route refused the block the editor previews with.' );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertIsString( $data['rendered'] ?? null );

		return $data['rendered'];
	}

	/**
	 * The one `<ul>` carrying a design class, out of a whole rendered page.
	 *
	 * @param string $html         The rendered markup.
	 * @param string $design_class The design class the row carries.
	 * @return string
	 */
	private function pill_row( string $html, string $design_class ): string {
		$offset = 0;

		while ( true ) {
			$start = strpos( $html, '<ul ', $offset );

			if ( ! is_int( $start ) ) {
				break;
			}

			$tag = strpos( $html, '>', $start );
			$end = strpos( $html, '</ul>', $start );

			if ( ! is_int( $tag ) || ! is_int( $end ) ) {
				break;
			}

			if ( str_contains( substr( $html, $start, $tag - $start ), $design_class ) ) {
				return substr( $html, $start, $end - $start + 5 );
			}

			$offset = $tag;
		}

		$this->fail( sprintf( 'No <ul> carrying %s in this markup.', $design_class ) );
	}
}
