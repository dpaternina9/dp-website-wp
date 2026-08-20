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
 * does not leaves a visible, inert control rather than a link to a 404 or a
 * hole in the page (ADR-0008).
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
	 * With no page behind it, the button stays put and stops being a link.
	 *
	 * ADR-0008. The previous behaviour dropped the whole block, and this test
	 * used to assert that. It was wrong in the way that matters: the site
	 * editor draws the same button from the same saved markup and cannot know
	 * the front end threw it away, so "it is there when I edit it and gone when
	 * I look at the site" was the only symptom of a resolver returning nothing
	 * — whether because David had not made the page or because the resolver was
	 * broken. Both now look the same, and both look like something.
	 *
	 * @return void
	 */
	public function test_a_destination_with_no_page_stays_visible_and_inert(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'Get in touch', $html );
		$this->assertStringContainsString( 'Say hi', $html );
		$this->assertStringContainsString( Navigation::UNRESOLVED_CLASS, $html );
		$this->assertStringContainsString( 'data-dp-destination="contact"', $html );
		$this->assertStringContainsString( 'aria-disabled="true"', $html );
	}

	/**
	 * The "Full timeline" link is on the page whether or not it can point anywhere.
	 *
	 * This is the bug David reported, from both ends. The button is in
	 * `front-page.html`, it renders in the site editor because the editor draws
	 * the saved markup, and on the front end it was being removed entirely by
	 * `Navigation::resolve_destination()`. Two separate causes could produce
	 * that — no page carrying `dp-work`, or a resolver that could not find the
	 * page that does — and neither left a trace. The first case is asserted
	 * here; `test_a_cached_map_from_an_older_release_still_resolves` covers the
	 * second.
	 *
	 * @return void
	 */
	public function test_the_full_timeline_link_survives_an_unresolved_work_page(): void {
		$without = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'Full timeline', $without );
		$this->assertStringContainsString( 'data-dp-destination="work"', $without );
		$this->assertMatchesRegularExpression(
			'~<a[^>]*data-dp-destination="work"[^>]*>~',
			$without
		);
		$this->assertDoesNotMatchRegularExpression(
			'~<a[^>]*data-dp-destination="work"[^>]*href=~',
			$without,
			'An unresolved destination must not invent an href.'
		);

		$work = $this->seed_page( 'What I have built', Navigation::TEMPLATES['work'] );

		$with = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( $this->permalink( $work ) ) . '"', $with );
		$this->assertDoesNotMatchRegularExpression(
			'~<a[^>]*data-dp-destination="work"[^>]*' . preg_quote( Navigation::UNRESOLVED_CLASS, '~' ) . '~',
			$with,
			'A destination that resolves must not also be marked unset.'
		);
	}

	/**
	 * A map cached by an older release resolves instead of reading as "no page".
	 *
	 * The transient used to be keyed by whatever `_wp_page_template` held, and
	 * `dp-work.html` and `dp-work` are the same template under two spellings.
	 * When the write side started normalising them, every install already
	 * holding the old map answered "no such page" for four destinations at once
	 * until something happened to save a page — which is exactly what David saw
	 * on :8888, and exactly the kind of failure a cache should not be able to
	 * cause.
	 *
	 * @return void
	 */
	public function test_a_cached_map_from_an_older_release_still_resolves(): void {
		$work = $this->seed_page( 'What I have built', Navigation::TEMPLATES['work'] );

		set_transient(
			Destinations::CACHE_KEY,
			array( Navigation::TEMPLATES['work'] . '.html' => $work ),
			DAY_IN_SECONDS
		);

		$navigation = new Navigation( new Destinations() );

		$this->assertSame( $this->permalink( $work ), $navigation->url_for( 'work' ) );
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
	 * Every template the chrome names is one WordPress will actually offer.
	 *
	 * This is the assertion that catches a spelling difference nobody would
	 * notice by reading: WordPress offers a block theme's custom templates to
	 * the admin under their slugs, so a page assigned Contact from the dropdown
	 * stores `dp-contact` and a resolver looking for `dp-contact.html` finds
	 * nothing, forever, in silence.
	 *
	 * @return void
	 */
	public function test_every_named_template_is_one_the_admin_offers(): void {
		$offered = array_keys( wp_get_theme()->get_post_templates()['page'] ?? array() );

		$this->assertNotEmpty( $offered );

		foreach ( Navigation::TEMPLATES as $destination => $template ) {
			$this->assertContains(
				$template,
				$offered,
				sprintf( 'The "%s" destination names a template the admin does not offer.', $destination )
			);
		}
	}

	/**
	 * A page assigned its template the way the admin assigns it still resolves.
	 *
	 * @return void
	 */
	public function test_a_page_assigned_through_rest_still_resolves(): void {
		$contact = $this->seed_page( 'Say hello' );

		$updated = wp_update_post(
			array(
				'ID'            => $contact,
				'page_template' => Navigation::TEMPLATES['contact'],
			),
			true
		);

		$this->assertIsInt( $updated );
		$this->assertSame( Navigation::TEMPLATES['contact'], get_page_template_slug( $contact ) );

		delete_transient( Destinations::CACHE_KEY );

		$navigation = new Navigation( new Destinations() );

		$this->assertSame( $this->permalink( $contact ), $navigation->url_for( 'contact' ) );
	}

	/**
	 * Every destination the theme offers resolves or is deliberately absent.
	 *
	 * @return void
	 */
	public function test_every_named_destination_resolves_or_is_absent(): void {
		$navigation = new Navigation( new Destinations() );

		$this->seed_page( 'Say hello', Navigation::TEMPLATES['contact'] );

		delete_transient( Destinations::CACHE_KEY );

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
