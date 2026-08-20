<?php
/**
 * Integration tests for the header, the footer and the destinations they link to.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Chrome\Destinations;
use DP\Theme\Chrome\Navigation;

/**
 * CLAUDE.md §5.1, in the one place it is easiest to break.
 *
 * The header and the footer are where a theme normally hardcodes an href, and
 * this one may not. Every link in them says which destination it wants and is
 * given a URL at render time, from a Reading setting, from core's feed link, or
 * from the page carrying a template David assigned. The tests below check both
 * halves of that: that a destination which exists resolves, and that one which
 * does not renders as nothing at all rather than as a link to a 404.
 */
final class ChromeTest extends TemplateTestCase {

	/**
	 * The hierarchy for a front page, which is where the chrome is easiest to read.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'front-page.php', 'home.php', 'index.php' );

	/**
	 * Both template parts are declared and both render.
	 *
	 * @return void
	 */
	public function test_the_header_and_the_footer_are_declared_parts(): void {
		$parts = wp_get_theme()->get_block_template_folders();

		$this->assertSame( 'parts', $parts['wp_template_part'] );

		$declared = array();

		foreach ( get_block_templates( array(), 'wp_template_part' ) as $part ) {
			$declared[ $part->slug ] = $part->area;
		}

		$this->assertSame( 'header', $declared['header'] ?? '' );
		$this->assertSame( 'footer', $declared['footer'] ?? '' );
	}

	/**
	 * The panel is a real `<dialog>` with the id the hamburger points at.
	 *
	 * The whole no-JavaScript path depends on those two facts: the stylesheet
	 * opens `.dp-panel:target`, and the only thing that can make it a target is
	 * a link to its id. If the element stops being a dialog, or the id moves,
	 * the menu stops opening for anyone with scripting off — silently.
	 *
	 * @return void
	 */
	public function test_the_mobile_panel_is_a_dialog_a_link_can_open(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertMatchesRegularExpression( '~<dialog class="[^"]*dp-panel[^"]*" id="dp-nav-panel">~', $html );
		$this->assertStringContainsString( 'href="#dp-nav-panel"', $html );
		$this->assertStringContainsString( 'dp-menu-open', $html );
		$this->assertStringContainsString( 'dp-menu-close', $html );
	}

	/**
	 * The contact button points at whatever page carries the contact template.
	 *
	 * @return void
	 */
	public function test_the_contact_button_resolves_through_the_assigned_template(): void {
		$contact = $this->seed_page( 'Say hello', Navigation::TEMPLATES['contact'] );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $contact ) ) . '"', $html );
		$this->assertStringContainsString( 'Get in touch', $html );
	}

	/**
	 * Renaming that page changes the link, because the link was never the name.
	 *
	 * Pretty permalinks are switched on for the duration: with the plain
	 * structure the test suite installs, every page URL is `?page_id=N` and a
	 * rename would be invisible, so the test would pass without proving
	 * anything. Nothing clears the theme's cache by hand either — the point is
	 * that saving the page does.
	 *
	 * @return void
	 */
	public function test_moving_the_contact_page_moves_the_link(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$contact = $this->seed_page( 'Say hello', Navigation::TEMPLATES['contact'] );
		$before  = $this->permalink( $contact );

		$this->assertStringContainsString(
			'href="' . esc_url( $before ) . '"',
			$this->render( home_url( '/' ), 'front-page', self::HIERARCHY )
		);

		wp_update_post(
			array(
				'ID'        => $contact,
				'post_name' => 'talk-to-me',
			)
		);

		$after = $this->permalink( $contact );

		$this->assertNotSame( $before, $after, 'The rename has to change the URL, or the test proves nothing.' );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( $after ) . '"', $html );
		$this->assertStringNotContainsString( 'href="' . esc_url( $before ) . '"', $html );

		$this->set_permalink_structure( '' );
	}

	/**
	 * With no contact page, the button is absent rather than broken.
	 *
	 * @return void
	 */
	public function test_a_destination_with_no_page_renders_nothing(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringNotContainsString( 'Get in touch', $html );
		$this->assertStringNotContainsString( 'Say hi', $html );
		$this->assertStringNotContainsString( 'dp-to-contact', $html );
	}

	/**
	 * The posts destination follows Settings to Reading, under any name.
	 *
	 * @return void
	 */
	public function test_the_posts_destination_follows_the_reading_setting(): void {
		$page = $this->seed_posts_page( 'Field notes' );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $page ) ) . '"', $html );
		$this->assertStringContainsString( 'All posts', $html );
	}

	/**
	 * Without one it falls back to the site root, which is where the posts are.
	 *
	 * @return void
	 */
	public function test_the_posts_destination_falls_back_to_the_site_root(): void {
		$destinations = new Destinations();

		$this->assertSame( home_url( '/' ), $destinations->posts_index() );
		$this->assertNull( $destinations->posts_page() );
	}

	/**
	 * A posts page that has been unpublished stops being the posts page.
	 *
	 * @return void
	 */
	public function test_an_unpublished_posts_page_is_not_used(): void {
		$page = $this->seed_posts_page();

		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);

		$destinations = new Destinations();

		$this->assertNull( $destinations->posts_page() );
		$this->assertSame( home_url( '/' ), $destinations->posts_index() );
	}

	/**
	 * The footer links the feed through core rather than through a path.
	 *
	 * @return void
	 */
	public function test_the_footer_links_the_feed(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $html );
		$this->assertStringContainsString( 'RSS', $html );
	}

	/**
	 * Every destination the theme offers resolves or is deliberately absent.
	 *
	 * @return void
	 */
	public function test_every_named_destination_resolves_or_is_absent(): void {
		$navigation = new Navigation( new Destinations() );

		$this->seed_page( 'Say hello', Navigation::TEMPLATES['contact'] );

		delete_transient( 'dpaternina_template_pages' );

		foreach ( Navigation::DESTINATIONS as $destination ) {
			$url = $navigation->url_for( $destination );

			$this->assertTrue(
				null === $url || '' !== $url,
				sprintf( '"%s" must resolve to a real URL or to nothing at all.', $destination )
			);
		}

		$this->assertNotNull( $navigation->url_for( 'contact' ) );
		$this->assertNull( $navigation->url_for( 'work' ), 'No page carries the work template yet.' );
		$this->assertNull( $navigation->url_for( 'nonsense' ) );
	}

	/**
	 * The chrome is on every template this phase ships.
	 *
	 * @return void
	 */
	public function test_every_template_is_wrapped_in_the_chrome(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 2 );
		$this->file_under_series( $this->posts[0], 1 );

		$page = $this->seed_posts_page();

		$category = get_category_link( $this->categories['dev'] );
		$series   = get_term_link( $this->series );

		$this->assertIsString( $category );
		$this->assertIsString( $series );

		$views = array(
			'front-page'         => array( home_url( '/' ), self::HIERARCHY ),
			'home'               => array( $this->permalink( $page ), array( 'home.php', 'index.php' ) ),
			'single'             => array( $this->permalink( $this->posts[0] ), array( 'single.php', 'index.php' ) ),
			'category'           => array( $category, array( 'category.php', 'index.php' ) ),
			'taxonomy-dp_series' => array( $series, array( 'taxonomy-dp_series.php', 'index.php' ) ),
		);

		foreach ( $views as $type => $view ) {
			$html = $this->render( $view[0], $type, $view[1] );

			$this->assertStringContainsString( 'dp-header', $html, $type . ' has a header.' );
			$this->assertStringContainsString( 'dp-footer', $html, $type . ' has a footer.' );
			$this->assertStringContainsString( 'dp-cta-band', $html, $type . ' closes on the CTA band.' );
			$this->assertStringContainsString( '<main', $html, $type . ' has a main landmark.' );
		}
	}
}
