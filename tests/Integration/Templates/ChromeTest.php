<?php
/**
 * Integration tests for the header, the footer and the links in them.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\DerivedLink;
use DP\Theme\Blocks\FeedLink;
use DP\Theme\Chrome\Destinations;
use DP\Theme\Chrome\PostPresentation;

/**
 * What the chrome ships, and what it does with what David puts in it.
 *
 * The header and the footer used to be where this theme resolved a dozen hrefs
 * at render time from a `dp-to-…` class. ADR-0018 deletes that. A link to a page
 * David made is a link he sets in the site editor, so what these tests assert
 * changed shape completely:
 *
 * - the shipped markup carries the design's **words** and no URL, which is
 *   CLAUDE.md §5.1 satisfied by shipping nothing rather than by computing
 *   something;
 * - a URL David *does* set survives rendering — the defect ADR-0018 was written
 *   for, and the two tests that reproduce it are the most important in this
 *   file;
 * - the one link in the footer nobody can type, the feed, is a named block.
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

	/*
	 * -------------------------------------------- The defect ADR-0018 is about
	 */

	/**
	 * A link David sets on a button in a template part is the link that renders.
	 *
	 * **This is the bug.** `Navigation::resolve_destination()` ended in an
	 * unconditional `set_attribute( 'href', $url )` on any button carrying a
	 * destination class, on the never-written-down assumption that the shipped
	 * templates carry no href and there is therefore never one to preserve. That
	 * stopped being true the moment the site editor was opened: an edit is saved
	 * as a `wp_template_part` post, that markup *does* carry an href, and the
	 * filter threw it away — while the editor, which renders saved markup and
	 * never runs the filter, went on showing it. Nothing reported it.
	 *
	 * The fixture is the theme's own header with a URL put on every button that
	 * ships without one, which is exactly what David does when he sets those
	 * links once. Under the old code every one of them came back stripped.
	 *
	 * @return void
	 */
	public function test_a_link_set_on_a_shipped_header_button_survives_rendering(): void {
		$target = home_url( '/somewhere-david-chose/' );

		$this->override( 'wp_template_part', 'header', $this->linked( 'parts/header.html', $target ) );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame(
			2,
			substr_count( $html, 'href="' . esc_url( $target ) . '"' ),
			"Both of the header's contact buttons must keep the link David gave them."
		);
		$this->assertStringContainsString( 'Get in touch', $html );
	}

	/**
	 * Nothing this theme registers touches a rendered button.
	 *
	 * The two tests above prove the symptom is gone; this one names the cause,
	 * and it is the assertion that fails loudest against the old code. Two
	 * filters used to sit on `render_block_core/button` — the destination
	 * resolver and the series link — so every button on every page went through
	 * a `WP_HTML_Tag_Processor` that was willing to call `set_attribute( 'href',
	 * … )` on it. The rule ADR-0018 puts in force is that code never overwrites a
	 * value an author set, and the cheapest way to keep it for `core/button` is
	 * for nothing to be listening at all. The two links that used to arrive this
	 * way are blocks of their own now.
	 *
	 * @return void
	 */
	public function test_nothing_filters_a_rendered_button(): void {
		$this->assertFalse(
			has_filter( 'render_block_core/button' ),
			'Something is filtering every button again. A link David sets is his; '
			. 'a computed link is a block of its own (ADR-0018).'
		);

		$target = home_url( '/wherever-he-pointed-it/' );
		$markup = sprintf(
			'<!-- wp:button {"url":"%1$s","className":"dp-button-quiet"} -->'
				. '<div class="wp-block-button dp-button-quiet">'
				. '<a class="wp-block-button__link wp-element-button" href="%1$s">Get in touch</a>'
				. '</div><!-- /wp:button -->',
			esc_url( $target )
		);

		$this->assertStringContainsString( 'href="' . esc_url( $target ) . '"', do_blocks( $markup ) );
	}

	/**
	 * The same is true of a whole template, not only of a part.
	 *
	 * A `wp_template` post is the other thing the site editor writes, and it is
	 * the one that carried the four home-page buttons David reported missing.
	 *
	 * @return void
	 */
	public function test_a_link_set_on_a_template_button_survives_rendering(): void {
		$target = home_url( '/the-page-i-picked/' );

		$this->override( 'wp_template', 'front-page', $this->linked( 'templates/front-page.html', $target ) );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame( 'dpaternina//front-page', $this->resolved_template() );
		$this->assertGreaterThan(
			0,
			substr_count( $html, 'href="' . esc_url( $target ) . '"' ),
			'A button linked in the site editor must render that link.'
		);
		$this->assertStringContainsString( 'See the work', $html );
		$this->assertStringContainsString( 'Full timeline', $html );
	}

	/*
	 * ------------------------------------------------ What the theme does ship
	 */

	/**
	 * The shipped chrome carries the design's words, and David supplies the URLs.
	 *
	 * That the words are all still there is the half worth asserting here:
	 * stripping a class off fourteen buttons is exactly the edit that loses one
	 * of them. That none of them ships a link to a page is asserted over every
	 * file the theme ships, in
	 * `DP\Tests\Integration\NoHardcodedRoutesTest`.
	 *
	 * @return void
	 */
	public function test_the_shipped_chrome_ships_the_designs_words(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		foreach ( array( 'Get in touch', 'Work', 'About', 'Contact', 'All posts', 'Uses', 'Résumé', 'Colophon', 'Privacy' ) as $label ) {
			$this->assertStringContainsString( $label, $html, sprintf( 'The chrome has lost "%s".', $label ) );
		}
	}

	/**
	 * An unset button is a button, not a dimmed one.
	 *
	 * ADR-0008's inert treatment is kept for the three links that are computed
	 * and can fail to resolve. A `core/button` David has not linked yet is not
	 * one of those: it is an ordinary button with no link, drawn identically in
	 * the editor and on the page, and dimming it would tell a visitor about a
	 * setup step rather than about anything on the site.
	 *
	 * @return void
	 */
	public function test_an_unlinked_button_is_not_marked_as_a_failed_destination(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'Get in touch', $html );
		$this->assertStringContainsString( 'Say hi', $html );
		$this->assertStringNotContainsString(
			DerivedLink::UNRESOLVED_CLASS,
			$html,
			'Nothing on the front page is a computed link, so nothing there can be an unresolved one.'
		);
	}

	/*
	 * ---------------------------------------------------------- The blog index
	 */

	/**
	 * The posts index follows Settings to Reading, under any name.
	 *
	 * @return void
	 */
	public function test_the_posts_index_follows_the_reading_setting(): void {
		$page = $this->seed_posts_page( 'Field notes' );

		$this->assertSame( $this->permalink( $page ), ( new Destinations() )->posts_index() );
	}

	/**
	 * Without one it falls back to the site root, which is where the posts are.
	 *
	 * @return void
	 */
	public function test_the_posts_index_falls_back_to_the_site_root(): void {
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
	 * `dp-core` can still ask the theme where the blog is, and only that.
	 *
	 * The `dp_destination_url` seam survives ADR-0018 with one name behind it.
	 * The plugin's contact panel offers "read something" after a message is sent
	 * and may not decide for itself which page that is; every other name the
	 * filter used to answer was a page David nominates by assigning a template,
	 * which is now a link he sets rather than a URL anyone derives.
	 *
	 * @return void
	 */
	public function test_the_plugin_seam_answers_the_posts_index_and_nothing_else(): void {
		$page = $this->seed_posts_page( 'Field notes' );

		$this->assertSame(
			$this->permalink( $page ),
			apply_filters( 'dp_destination_url', null, 'posts' )
		);

		foreach ( array( 'contact', 'work', 'about', 'resume', 'uses', 'colophon', 'privacy', 'series', 'home', 'nonsense' ) as $gone ) {
			$this->assertNull(
				apply_filters( 'dp_destination_url', null, $gone ),
				sprintf( '"%s" is not a destination any more (ADR-0018).', $gone )
			);
		}
	}

	/*
	 * ------------------------------------------------------------- The feed
	 */

	/**
	 * The footer links the feed through core rather than through a path.
	 *
	 * `SiteFooter.dc.html` calls RSS "the one real href in the whole design", and
	 * it is also the one href in the design that is wrong for WordPress: where
	 * the feed lives follows the permalink setting. So it is a named block.
	 *
	 * @return void
	 */
	public function test_the_footer_links_the_feed(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $html );
		$this->assertStringContainsString( 'data-dp-destination="' . FeedLink::DESTINATION . '"', $html );
		$this->assertStringContainsString( '>RSS</a>', $html );
	}

	/**
	 * Changing the permalink structure moves the feed, and the link with it.
	 *
	 * The reason this is a block and not a link somebody types.
	 *
	 * @return void
	 */
	public function test_the_feed_link_follows_the_permalink_structure(): void {
		$plain = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $plain );

		$this->set_permalink_structure( '/%postname%/' );

		$pretty = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'href="' . esc_url( get_feed_link() ) . '"', $pretty );
		$this->assertStringContainsString( '/feed/', $pretty );

		$this->set_permalink_structure( '' );
	}

	/*
	 * -------------------------------------------------------------- The mark
	 */

	/**
	 * The brand mark is an image David can swap, everywhere it is drawn.
	 *
	 * It used to be a `background: url()` painted over a visually-hidden
	 * `core/site-title`, which meant the only way to change it was to edit a
	 * stylesheet and ship a release. `core/site-logo` reads the `site_logo`
	 * option instead, so the header bar, the mobile panel's head, the footer and
	 * the top of the home page all draw whatever David chose.
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
	 * The mark links home, and core is what links it.
	 *
	 * This test used to assert the opposite: that the mark carried
	 * `data-dp-destination="home"`, because `Brand::link_home()` filtered the
	 * block and rewrote the href to `home_url( '/' )` — which is what core had
	 * already put there. Its own docblock named the motive, "the right URL and
	 * the wrong provenance", and the cost was that the theme silently overrode
	 * core's homepage-logo linking setting to get it. ADR-0018 deletes the
	 * filter. The URL is unchanged; nothing of ours produces it any more.
	 *
	 * @return void
	 */
	public function test_the_mark_links_home_through_core(): void {
		update_option( 'site_logo', $this->seed_logo() );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertMatchesRegularExpression(
			'~<div class="[^"]*wp-block-site-logo[^"]*"><a href="'
				. preg_quote( esc_url( home_url( '/' ) ), '~' )
				. '" class="custom-logo-link" rel="home"~',
			$html,
			"Core's own link, with core's own attributes on it."
		);
		$this->assertStringNotContainsString(
			'data-dp-destination="home"',
			$html,
			'The mark is core\'s link now. Nothing of ours stamps it.'
		);

		delete_option( 'site_logo' );
	}

	/**
	 * With no logo chosen the block draws nothing, in both contexts.
	 *
	 * This is `core/site-logo`'s own behaviour and is deliberately not papered
	 * over: a mark drawn from PHP when the option is empty would appear on the
	 * page and not in the editor's canvas. `dp-core`'s seeder is what stops a
	 * real site being blank.
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

	/*
	 * ------------------------------------------------------------ The footer
	 */

	/**
	 * The footer has the design's three groups, and they are not one menu.
	 *
	 * Digest §2: SITE / WRITING / MORE. The first group was once a
	 * `core/navigation` block with no `ref`, which resolved to the same menu as
	 * the header — so the footer could only ever mirror it, and MORE did not
	 * exist at all. Every link is now an ordinary one David sets.
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

		foreach ( array( 'Work', 'Watch', 'About', 'Contact', 'All posts', 'Uses', 'Résumé', 'Colophon', 'Privacy', 'RSS' ) as $link ) {
			$this->assertStringContainsString(
				'>' . $link . '</a>',
				$html,
				sprintf( 'The footer names no link reading "%s".', $link )
			);
		}
	}

	/**
	 * Watch is in the SITE column now that Phase 12 ships it.
	 *
	 * Phase 5 left it out rather than point at a 404; the digest's footer —
	 * SITE (Work, Watch, About, Contact) — is whole now. Like every chrome
	 * link it ships as a named button with no href (ADR-0018): the name in
	 * List View is "Watch link", the seeder fills it, David sets it once on a
	 * real site.
	 *
	 * @return void
	 */
	public function test_the_footer_carries_watch_now_that_phase_12_ships(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( '>Watch</a>', $html );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$part = file_get_contents( get_stylesheet_directory() . '/parts/footer.html' );

		$this->assertIsString( $part );
		$this->assertStringContainsString( '"name":"Watch link"', $part, 'The button announces itself in List View (ADR-0018 rule 2).' );
	}

	/**
	 * The footer prints the year, and the year is the site's.
	 *
	 * `SiteFooter.logic.js` computes `new Date().getFullYear()` and the design
	 * prints "© 2026 DAVID PATERNINA". `wp_date()` reads the site's timezone, so
	 * the line turns over when David's year does rather than when a visitor's
	 * does.
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

	/*
	 * --------------------------------------------------------------- The tone
	 */

	/**
	 * The tone filter is attached to the blocks that actually ask for it.
	 *
	 * It used to run on bare `render_block`, which meant parsing a class
	 * attribute for every block on every page to find two paragraphs. Narrowing
	 * it means the list of block types has to keep matching the markup, so the
	 * markup is what this reads.
	 *
	 * @return void
	 */
	public function test_every_block_asking_for_a_tone_is_one_the_filter_listens_to(): void {
		$asking = array();

		foreach ( $this->theme_markup_files() as $relative => $markup ) {
			if ( ! str_contains( $markup, PostPresentation::TONE_CLASS ) ) {
				continue;
			}

			preg_match_all( '~<!--\s+wp:([a-z0-9/-]+)\s+(\{.*?\})\s+(?:/)?-->~', $markup, $blocks, PREG_SET_ORDER );

			foreach ( $blocks as $block ) {
				if ( ! str_contains( $block[2], PostPresentation::TONE_CLASS ) ) {
					continue;
				}

				$name = str_contains( $block[1], '/' ) ? $block[1] : 'core/' . $block[1];

				$asking[ $name ] = $relative;
			}
		}

		$this->assertNotEmpty( $asking, 'Nothing asks for a tone, so this test proves nothing.' );

		foreach ( $asking as $name => $relative ) {
			$this->assertContains(
				$name,
				PostPresentation::TONE_BLOCKS,
				sprintf(
					'%s carries %s on a %s, which PostPresentation::TONE_BLOCKS does not list, so the tone is silently dropped.',
					$relative,
					PostPresentation::TONE_CLASS,
					$name
				)
			);
		}
	}

	/**
	 * The tone still lands on the badge above a post's title.
	 *
	 * @return void
	 */
	public function test_the_tone_class_still_reaches_the_badge(): void {
		$this->seed_categories();
		$this->seed_series();

		$posts = $this->seed_posts( 2 );

		$this->file_under_series( $posts[0] );

		$this->assertStringContainsString(
			'is-tone-pink',
			$this->render( $this->permalink( $posts[0] ), 'single', array( 'single.php', 'index.php' ) )
		);

		$this->assertStringContainsString(
			'is-tone-teal',
			$this->render( $this->permalink( $posts[1] ), 'single', array( 'single.php', 'index.php' ) )
		);
	}

	/*
	 * ------------------------------------------------------------------ Both
	 */

	/**
	 * The chrome is on every template this phase ships.
	 *
	 * @return void
	 */
	public function test_every_template_is_wrapped_in_the_chrome(): void {
		$this->seed_categories();
		$this->seed_series();
		$this->seed_posts( 2 );
		$this->file_under_series( $this->posts[0] );

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
}
