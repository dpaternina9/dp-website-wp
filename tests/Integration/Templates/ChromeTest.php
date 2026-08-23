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
	 * The brand mark is an image David can swap, everywhere it is drawn.
	 *
	 * It used to be a `background: url()` painted over a visually-hidden
	 * `core/site-title`, which meant the only way to change it was to edit a
	 * stylesheet and ship a release. `core/site-logo` reads the `site_logo`
	 * option instead, so the header bar, the mobile panel's head, the footer and
	 * the top of the home page all draw whatever David chose. This asserts the
	 * block is there four times and that the old mechanism is not.
	 *
	 * Four rather than three since the home hero gained its own: the design opens
	 * the front page with the monogram at 40px and the theme drew nothing there.
	 *
	 * @return void
	 */
	public function test_the_brand_mark_is_a_site_logo_everywhere_it_is_drawn(): void {
		update_option( 'site_logo', $this->seed_logo() );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame(
			4,
			substr_count( $html, 'wp-block-site-logo' ),
			'The header bar, the mobile panel, the footer and the home hero each render the mark.'
		);

		$this->assertStringContainsString( 'dp-brand dp-brand-hero', $html, "The home page's mark is the 40px one." );

		$this->assertStringNotContainsString(
			'dp-brand wp-block-site-title',
			$html,
			'The mark is no longer a site title with the text pushed off-screen.'
		);

		$this->assertStringContainsString( 'dp-brand dp-brand-sm', $html, "The footer's mark is the small one." );

		delete_option( 'site_logo' );
	}

	/**
	 * The footer prints the year, and the year is the site's.
	 *
	 * `SiteFooter.logic.js` computes `new Date().getFullYear()` and the design
	 * prints "© 2026 DAVID PATERNINA". ADR-0006 recorded dropping the year as a
	 * deviation, on the reasoning that a template part is static markup — which
	 * is true of the markup and not of a block binding, which is the mechanism
	 * for exactly this. `wp_date()` reads the site's timezone, so the line turns
	 * over when David's year does rather than when a visitor's does.
	 *
	 * @return void
	 */
	public function test_the_footer_copyright_carries_the_year(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );
		$year = (string) wp_date( 'Y' );

		$this->assertStringContainsString(
			'© ' . $year . ' David Paternina',
			$html,
			'The design\'s © line carries the year; the binding is dpaternina/site.'
		);
	}

	/**
	 * With no logo chosen the block draws nothing, in both contexts.
	 *
	 * This is `core/site-logo`'s own behaviour and is deliberately not papered
	 * over: a mark drawn from PHP when the option is empty would appear on the
	 * page and not in the editor's canvas, which is the divergence ADR-0008
	 * exists to stop. `dp-core`'s seeder is what stops a real site being blank.
	 *
	 * @return void
	 */
	public function test_no_logo_means_no_mark_rather_than_a_broken_one(): void {
		delete_option( 'site_logo' );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'dp-header', $html, 'The rest of the chrome still renders.' );
	}

	/**
	 * Swapping the logo swaps the mark, with no code and no deploy.
	 *
	 * @return void
	 */
	public function test_the_logo_follows_the_site_logo_option(): void {
		$attachment = $this->seed_logo();

		update_option( 'site_logo', $attachment );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );
		$src  = wp_get_attachment_image_url( $attachment, 'full' );

		$this->assertIsString( $src );
		$this->assertStringContainsString( esc_url( $src ), $html );

		delete_option( 'site_logo' );
	}

	/**
	 * The mark links home through the resolver, not through an href in a file.
	 *
	 * Core links its logo to `home_url()` directly, which is the right URL by
	 * the wrong route: every other link in this chrome says which destination it
	 * wants and is given one at render time, and `data-dp-destination` is what
	 * makes that visible. The mark answers the same way.
	 *
	 * @return void
	 */
	public function test_the_mark_links_home_through_a_destination(): void {
		update_option( 'site_logo', $this->seed_logo() );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertMatchesRegularExpression(
			'~<a[^>]*data-dp-destination="home"[^>]*href="' . preg_quote( esc_url( home_url( '/' ) ), '~' ) . '"~',
			$html
		);

		delete_option( 'site_logo' );
	}

	/**
	 * The mark still announces the site to a screen reader.
	 *
	 * The old markup got its accessible name from a site title nobody could
	 * see. An image gets it from `alt`, and core falls back to the site's name
	 * when an attachment has none — which is the behaviour being pinned here,
	 * because a nameless link in the header is the one accessibility failure
	 * this change could have introduced.
	 *
	 * @return void
	 */
	public function test_the_mark_carries_the_site_name_as_its_accessible_name(): void {
		update_option( 'site_logo', $this->seed_logo() );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"', $html );

		delete_option( 'site_logo' );
	}

	/**
	 * The footer has the design's three groups, and they are not one menu.
	 *
	 * Digest §2: SITE / WRITING / MORE. Until this phase the first group was a
	 * `core/navigation` block with no `ref`, which resolved to the same menu as
	 * the header — so the footer could only ever mirror it, and MORE did not
	 * exist at all. Every link now names a destination.
	 *
	 * @return void
	 */
	public function test_the_footer_has_the_designs_three_groups(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		foreach ( array( 'Site', 'Writing', 'More' ) as $label ) {
			$this->assertStringContainsString(
				'<p class="dp-label wp-block-paragraph">' . $label . '</p>',
				$html,
				sprintf( 'The footer is missing its "%s" group.', $label )
			);
		}

		foreach ( array( 'work', 'about', 'contact', 'posts', 'uses', 'resume', 'colophon', 'privacy', 'feed' ) as $destination ) {
			$this->assertStringContainsString(
				'data-dp-destination="' . $destination . '"',
				$html,
				sprintf( 'The footer names no link asking for "%s".', $destination )
			);
		}

		$this->assertStringContainsString( 'Uses', $html );
		$this->assertStringContainsString( 'Colophon', $html );
		$this->assertStringContainsString( 'Privacy', $html );
	}

	/**
	 * Watch is left out until it exists, rather than pointing at a 404.
	 *
	 * The same rule Phase 5 applied to the header. Phase 12 adds the template,
	 * the destination and the two links at once.
	 *
	 * @return void
	 */
	public function test_the_footer_leaves_watch_out_until_phase_12(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringNotContainsString( 'dp-to-watch', $html );
		$this->assertNotContains( 'watch', Navigation::DESTINATIONS );
	}

	/**
	 * Uses and Colophon resolve through the template David assigned them.
	 *
	 * @return void
	 */
	public function test_uses_and_colophon_resolve_through_their_templates(): void {
		$uses     = $this->seed_page( 'What I use', Navigation::TEMPLATES['uses'] );
		$colophon = $this->seed_page( 'How this is made', Navigation::TEMPLATES['colophon'] );

		$navigation = new Navigation( new Destinations() );

		$this->assertSame( $this->permalink( $uses ), $navigation->url_for( 'uses' ) );
		$this->assertSame( $this->permalink( $colophon ), $navigation->url_for( 'colophon' ) );
	}

	/**
	 * Privacy follows Settings to Privacy, which is core's own nomination.
	 *
	 * @return void
	 */
	public function test_privacy_follows_the_privacy_setting(): void {
		$navigation = new Navigation( new Destinations() );

		update_option( 'wp_page_for_privacy_policy', 0 );

		$this->assertNull( $navigation->url_for( 'privacy' ), 'No page chosen means no link, not a link to the root.' );

		$page = $this->seed_page( 'What I keep' );

		update_option( 'wp_page_for_privacy_policy', $page );

		$this->assertSame( $this->permalink( $page ), $navigation->url_for( 'privacy' ) );
	}

	/**
	 * An attachment standing in for a logo David uploaded.
	 *
	 * The metadata is written by hand rather than generated, because there is no
	 * file behind it and none is needed: `image_downsize()` answers from
	 * `width` and `height` alone, which is all `core/site-logo` asks for.
	 *
	 * @return int
	 */
	private function seed_logo(): int {
		$attachment = wp_insert_attachment(
			array(
				'post_title'     => 'A mark David uploaded',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			),
			'davids-own-mark.png',
			0,
			true
		);

		$this->assertIsInt( $attachment );

		wp_update_attachment_metadata(
			$attachment,
			array(
				'file'   => 'davids-own-mark.png',
				'width'  => 128,
				'height' => 128,
				'sizes'  => array(),
			)
		);

		return $attachment;
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
