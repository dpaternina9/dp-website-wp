<?php
/**
 * Integration tests for the three links the theme computes.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Content\Taxonomies;
use DP\Core\Resume\ResumePdf;
use DP\Theme\Blocks\DerivedLink;
use DP\Theme\Blocks\EditorScript;
use DP\Theme\Blocks\FeedLink;
use DP\Theme\Blocks\ResumeDownload;
use DP\Theme\Blocks\SeriesPartsLink;
use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * `series-parts-link`, `resume-download`, `feed-link` — and why they are blocks.
 *
 * ADR-0018 deletes the class-triggered destination system and keeps exactly
 * three links, because nobody can type their URLs: the archive of the series the
 * post being read is part of, the résumé's PDF, and the feed. Each becomes a
 * named block, which is what the ADR's second rule asks of any computation —
 * a name in the inserter rather than an invisible class.
 *
 * Three things are asserted about each of them and each earns its place:
 *
 * - **It resolves**, which is the feature.
 * - **It degrades the ADR-0008 way when it cannot** — the element stays, the
 *   `href` goes, `aria-disabled` and the class say so — because these three are
 *   the only links left on the site where "missing" could mean either "not set
 *   up" or "broken", and the whole point of that treatment is to make the two
 *   distinguishable from the page.
 * - **The editor knows about it.** A block registered in PHP and not on the
 *   client draws as `core/missing` in the site editor, inside a template that
 *   renders perfectly on the front end (ADR-0009). The only place that shows up
 *   is the canvas, so the registration is asserted here instead.
 */
final class ComputedLinksTest extends TemplateTestCase {

	/**
	 * The three blocks, and the file each `block.json` lives in.
	 *
	 * @var array<string, string>
	 */
	private const BLOCKS = array(
		SeriesPartsLink::NAME => 'blocks/series-parts-link',
		ResumeDownload::NAME  => 'blocks/resume-download',
		FeedLink::NAME        => 'blocks/feed-link',
	);

	/**
	 * The hierarchy for a single post.
	 *
	 * @var array<int, string>
	 */
	private const SINGLE = array( 'single.php', 'index.php' );

	/*
	 * ------------------------------------------------------ All three at once
	 */

	/**
	 * Each is registered, dynamic, and offered by name in the inserter.
	 *
	 * `supports.inserter` defaults to true and is deliberately not set to false
	 * on any of these — unlike `dpaternina/series-planned`, which is chrome for
	 * one template. These three are links David may want in a template he
	 * builds, and ADR-0018's second rule is that a computation announces itself:
	 * a block nobody can insert announces nothing.
	 *
	 * @return void
	 */
	public function test_each_block_is_registered_dynamic_and_in_the_inserter(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array_keys( self::BLOCKS ) as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertInstanceOf( WP_Block_Type::class, $block, $name . ' is not registered.' );
			$this->assertTrue( $block->is_dynamic(), $name . ' has no render callback.' );
			$this->assertNotSame( '', (string) $block->title, $name . ' has no title to show in the inserter.' );
			$this->assertNotSame( '', (string) $block->description, $name . ' has no description.' );
			$this->assertSame( 'theme', $block->category, $name . ' is filed somewhere unexpected.' );
			$this->assertNotFalse(
				$block->supports['inserter'] ?? true,
				$name . ' is hidden from the inserter, so nothing in the editor names it.'
			);
		}
	}

	/**
	 * Each names the theme's editor script, and that handle is registered.
	 *
	 * This is the assertion that catches the failure ADR-0009 was written for,
	 * and it is worth spelling out why it is not obvious: a `block.json` naming
	 * a handle nothing registered enqueues nothing, silently, and the block then
	 * draws as `core/missing` in the canvas while every markup assertion in this
	 * suite goes on passing.
	 *
	 * @return void
	 */
	public function test_each_block_names_an_editor_script_that_exists(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue(
			wp_script_is( EditorScript::HANDLE, 'registered' ),
			'The theme registers no editor script, so every block naming it loads nothing.'
		);

		$this->assertFileIsReadable( get_theme_file_path( EditorScript::PATH ) );

		foreach ( array_keys( self::BLOCKS ) as $name ) {
			$block = $registry->get_registered( $name );

			$this->assertInstanceOf( WP_Block_Type::class, $block );
			$this->assertContains(
				EditorScript::HANDLE,
				(array) $block->editor_script_handles,
				$name . ' has no editor registration, so the site editor draws it as core/missing.'
			);
		}
	}

	/**
	 * The editor file registers exactly the blocks that need it.
	 *
	 * Read out of the source, the way `ServerRenderedParityTest` reads
	 * `dp-core`'s: a fourth server-rendered theme block added without a line in
	 * that array fails here rather than in David's canvas.
	 *
	 * @return void
	 */
	public function test_the_editor_script_previews_exactly_these_blocks(): void {
		$javascript = $this->theme_file( EditorScript::PATH );

		$this->assertSame( 1, preg_match( '~const SERVER_RENDERED = \[(.*?)\];~s', $javascript, $array ) );

		preg_match_all( "~'([^']+)'~", $array[1], $names );

		$previewed = $names[1];
		$expected  = array_keys( self::BLOCKS );

		sort( $previewed );
		sort( $expected );

		$this->assertSame( $expected, $previewed );
	}

	/*
	 * ------------------------------------------------------- The series' parts
	 */

	/**
	 * On a post in a series, it points at that series' archive.
	 *
	 * @return void
	 */
	public function test_the_series_link_points_at_the_series_of_the_post_being_read(): void {
		$this->seed_categories();
		$this->seed_series();

		$second = $this->seed_second_series();
		$posts  = $this->seed_posts( 2 );

		$this->file_under_series( $posts[0] );

		wp_set_post_terms( $posts[1], array( $second ), Taxonomies::SERIES, false );

		$first = get_term_link( $this->series );
		$other = get_term_link( $second );

		$this->assertIsString( $first );
		$this->assertIsString( $other );

		$html = $this->render( $this->permalink( $posts[0] ), 'single', self::SINGLE );

		$this->assertStringContainsString( 'href="' . esc_url( $first ) . '"', $html );
		$this->assertStringNotContainsString( 'href="' . esc_url( $other ) . '"', $html );

		// The same block, the same template, a different answer. That is the
		// reason it cannot be a link anybody types.
		$html = $this->render( $this->permalink( $posts[1] ), 'single', self::SINGLE );

		$this->assertStringContainsString( 'href="' . esc_url( $other ) . '"', $html );
		$this->assertStringNotContainsString( 'href="' . esc_url( $first ) . '"', $html );
	}

	/**
	 * On a post in no series, it keeps its element and loses its link.
	 *
	 * @return void
	 */
	public function test_the_series_link_degrades_visibly_on_a_post_in_no_series(): void {
		$this->seed_categories();

		$posts = $this->seed_posts( 1 );

		$html = $this->render( $this->permalink( $posts[0] ), 'single', self::SINGLE );

		$this->assertStringContainsString( '>All parts →</a>', $html );
		$this->assertStringContainsString( 'data-dp-destination="' . SeriesPartsLink::DESTINATION . '"', $html );
		$this->assertStringContainsString( DerivedLink::UNRESOLVED_CLASS, $html );
		$this->assertStringContainsString( 'aria-disabled="true"', $html );
		$this->assertDoesNotMatchRegularExpression(
			'~<a[^>]*data-dp-destination="' . SeriesPartsLink::DESTINATION . '"[^>]*href=~',
			$html,
			'An unresolved link must not invent an href.'
		);
	}

	/**
	 * Rendered with no post in scope at all, it is inert rather than fatal.
	 *
	 * This is the site editor's canvas: the template is being edited rather than
	 * applied to a post, so `ServerSideRender` asks the block renderer for markup
	 * with nothing in the loop. Whatever it answers is what David sees, so it has
	 * to be the same shape the front end draws — not an empty string, and not a
	 * warning.
	 *
	 * @return void
	 */
	public function test_the_series_link_is_inert_with_no_post_in_scope(): void {
		$html = do_blocks( '<!-- wp:dpaternina/series-parts-link /-->' );

		$this->assertStringContainsString( '>All parts →</a>', $html );
		$this->assertStringContainsString( DerivedLink::UNRESOLVED_CLASS, $html );
		$this->assertStringNotContainsString( 'href=', $html );
	}

	/*
	 * ------------------------------------------------------------ The résumé
	 */

	/**
	 * On the page carrying the résumé template, it is that page's PDF.
	 *
	 * @return void
	 */
	public function test_the_resume_download_addresses_the_page_it_is_drawn_on(): void {
		$page = $this->seed_page( 'The record, on one page', ResumeDownload::TEMPLATE );

		$html = $this->render(
			$this->permalink( $page ),
			'page',
			array( ResumeDownload::TEMPLATE . '.html', 'page.php', 'singular.php', 'index.php' )
		);

		$this->assertStringContainsString( esc_url( ResumePdf::download_url( $page ) ), $html );
		$this->assertStringContainsString( 'data-dp-destination="' . ResumeDownload::DESTINATION . '"', $html );
	}

	/**
	 * Anywhere else, it is inert — because `?format=pdf` would do nothing there.
	 *
	 * `dp-core` acts on the query variable only on a page carrying the résumé
	 * template, so a link to it from any other page is a link that silently
	 * reloads the page. Better to be visibly unwired.
	 *
	 * @return void
	 */
	public function test_the_resume_download_is_inert_off_the_resume_page(): void {
		$page = $this->seed_page( 'An ordinary page' );

		$this->go_to( $this->permalink( $page ) );

		$html = do_blocks( '<!-- wp:dpaternina/resume-download /-->' );

		$this->assertStringContainsString( '>Download PDF</a>', $html );
		$this->assertStringContainsString( DerivedLink::UNRESOLVED_CLASS, $html );
		$this->assertStringContainsString( 'aria-disabled="true"', $html );
		$this->assertStringNotContainsString( 'href=', $html );
	}

	/**
	 * The `.html` spelling of the template resolves too.
	 *
	 * WordPress offers a block theme's custom templates under their slugs, so a
	 * page assigned from the dropdown stores `dp-resume`. A page imported from
	 * elsewhere carries the file name. Both mean the same template, and a
	 * resolver that knows only one of them fails silently — which is exactly the
	 * bug the old destination cache shipped.
	 *
	 * @return void
	 */
	public function test_the_resume_download_accepts_either_spelling_of_the_template(): void {
		$page = $this->seed_page( 'The record', ResumeDownload::TEMPLATE . '.html' );

		$this->go_to( $this->permalink( $page ) );

		$html = do_blocks( '<!-- wp:dpaternina/resume-download /-->' );

		$this->assertStringContainsString( esc_url( ResumePdf::download_url( $page ) ), $html );
		$this->assertStringNotContainsString( DerivedLink::UNRESOLVED_CLASS, $html );
	}

	/*
	 * -------------------------------------------------------------- The feed
	 */

	/**
	 * It is `get_feed_link()`, and it moves when the permalink setting moves.
	 *
	 * @return void
	 */
	public function test_the_feed_link_is_cores_feed_link(): void {
		$plain = do_blocks( '<!-- wp:dpaternina/feed-link /-->' );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $plain );
		$this->assertStringContainsString( '>RSS</a>', $plain );
		$this->assertStringContainsString( 'data-dp-destination="' . FeedLink::DESTINATION . '"', $plain );

		$this->set_permalink_structure( '/%postname%/' );

		$pretty = do_blocks( '<!-- wp:dpaternina/feed-link /-->' );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $pretty );
		$this->assertNotSame( $plain, $pretty, 'The link has to follow the setting, or it need not be a block.' );

		$this->set_permalink_structure( '' );
	}

	/*
	 * ------------------------------------------------------------- The markup
	 */

	/**
	 * All three draw the markup `core/buttons` draws, plus their own class.
	 *
	 * They sit where a `core/button` sat, inside design rules written against
	 * core's class names — `.dp-series-footer-action .wp-block-button__link` and
	 * the rest — so a wrapper of a different shape is a silent restyling.
	 *
	 * @return void
	 */
	public function test_each_block_renders_the_shape_a_core_button_renders(): void {
		foreach ( array_keys( self::BLOCKS ) as $name ) {
			$html = do_blocks( sprintf( '<!-- wp:%s {"className":"dp-somewhere"} /-->', $name ) );

			$this->assertMatchesRegularExpression(
				'~<div class="[^"]*wp-block-buttons[^"]*"><div class="wp-block-button [^"]*"><a class="wp-block-button__link wp-element-button~',
				$html,
				$name . ' does not draw the shape the stylesheet is written against.'
			);
			$this->assertStringContainsString( 'dp-somewhere', $html, $name . ' drops the class the template gave it.' );
			$this->assertStringContainsString(
				'wp-block-' . str_replace( '/', '-', $name ),
				$html,
				$name . ' does not name itself in its own markup.'
			);
		}
	}

	/**
	 * Every one of them is placed in a template, not merely registered.
	 *
	 * A block that exists and is drawn nowhere is a feature nobody can reach —
	 * the failure `f7dc576` shipped for the contact form and the ledger.
	 *
	 * @return void
	 */
	public function test_each_block_is_placed_in_the_markup_the_theme_ships(): void {
		$markup = implode( "\n", $this->theme_markup_files() );

		foreach ( array_keys( self::BLOCKS ) as $name ) {
			$this->assertStringContainsString(
				'wp:' . $name,
				$markup,
				$name . ' is registered and placed nowhere.'
			);
		}
	}

	/**
	 * Each `block.json` is where the PHP says it is.
	 *
	 * @return void
	 */
	public function test_each_block_definition_is_on_disk(): void {
		foreach ( self::BLOCKS as $name => $directory ) {
			$path = get_theme_file_path( $directory . '/block.json' );

			$this->assertFileIsReadable( $path, $name . ' has no block.json.' );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the theme under test.
			$definition = json_decode( (string) file_get_contents( $path ), true );

			$this->assertIsArray( $definition );
			$this->assertSame( $name, $definition['name'] ?? '' );
		}
	}
}
