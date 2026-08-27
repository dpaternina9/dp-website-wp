<?php
/**
 * Integration tests for the templates Phase 7 ships.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Contact\ContactForm;
use DP\Core\Resume\ResumePdf;
use DP\Theme\Blocks\DerivedLink;
use DP\Theme\Blocks\ResumeDownload;

/**
 * 404, the generic page, About, Contact and the résumé, as a request renders them.
 *
 * Four claims are made here that nothing else in the suite makes.
 *
 * **404 has no chrome.** The design renders it that way —
 * `const chrome = view !== 'notfound' && view !== 'offline'` — and `showCta` is
 * false there too, so the header, the footer and the closing band are all
 * deliberately absent. That is the kind of omission somebody restores in good
 * faith a phase later, so it is asserted rather than assumed.
 *
 * **Contact and the résumé have no closing CTA band either.** Same source line:
 * `showCta: view !== 'contact' && view !== 'notfound' && view !== 'offline' &&
 * view !== 'resume'`. Both templates carried one when Phase 7 opened.
 *
 * **The two blocks are placed.** They were registered by `f7dc576` and put
 * nowhere, which is a feature that exists and cannot be reached.
 *
 * **Nothing points at a page by name.** These templates ship the design's words
 * and no URLs; David sets the links in the site editor (ADR-0018). The one link
 * on them that nobody could type — the résumé's PDF — is a named block, and it
 * is the assertion about what resolves and what visibly does not.
 */
final class PagesTest extends TemplateTestCase {

	/**
	 * The hierarchy WordPress builds for a page with no template assigned.
	 *
	 * @var array<int, string>
	 */
	private const PAGE = array( 'page.php', 'singular.php', 'index.php' );

	/**
	 * The hierarchy for a missing URL.
	 *
	 * @var array<int, string>
	 */
	private const NOT_FOUND = array( '404.php' );

	/**
	 * The hierarchy for a page carrying one of this theme's custom templates.
	 *
	 * @param string $template The template file.
	 * @return array<int, string>
	 */
	private static function assigned( string $template ): array {
		return array( $template, 'page.php', 'singular.php', 'index.php' );
	}

	/*
	 * ---------------------------------------------------------------- 404
	 */

	/**
	 * A URL with nothing behind it gets this theme's 404.
	 *
	 * @return void
	 */
	public function test_a_missing_url_renders_the_404_template(): void {
		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		$this->assertSame( 'dpaternina//404', $this->resolved_template() );
		$this->assertStringContainsString( 'This one did not survive the merge.', $html );
		$this->assertStringContainsString( 'Error 404 · Page not found', $html );
	}

	/**
	 * It is drawn without the site chrome, exactly as the design draws it.
	 *
	 * @return void
	 */
	public function test_the_404_carries_no_header_footer_or_closing_band(): void {
		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		$this->assertStringNotContainsString( 'dp-header', $html );
		$this->assertStringNotContainsString( 'dp-footer', $html );
		$this->assertStringNotContainsString( 'dp-cta-band', $html );
	}

	/**
	 * The monogram is the way back, and it is a real link.
	 *
	 * With no header there is nothing else on the page that goes home, so this
	 * is not decoration.
	 *
	 * @return void
	 */
	public function test_the_404_keeps_the_monogram_as_a_way_home(): void {
		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		$this->assertStringContainsString( 'dp-brand-white', $html );

		/*
		 * The mark is `core/site-title`, and core links it home itself. That is
		 * the whole of the way out of this page now: the four buttons under it
		 * are links David sets, and until he does they are words rather than
		 * links (ADR-0018). This assertion used to pass because the "Back to
		 * home" button was given `home_url( '/' )` by a filter.
		 */
		$this->assertStringContainsString( 'rel="home"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( home_url() ) . '"', $html );
	}

	/**
	 * One `h1`, and it is the headline.
	 *
	 * @return void
	 */
	public function test_the_404_has_exactly_one_h1(): void {
		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		$this->assertSame( 1, preg_match_all( '~<h1[\s>]~', $html ) );
	}

	/**
	 * It offers the four ways out the design draws, and invents no URL for them.
	 *
	 * @return void
	 */
	public function test_the_404_offers_the_designs_four_ways_out(): void {
		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		foreach ( array( 'Back to home', 'Report the link', 'The work', 'The writing' ) as $label ) {
			$this->assertStringContainsString( $label, $html, $label );
		}

		$this->assertStringNotContainsString(
			DerivedLink::UNRESOLVED_CLASS,
			$html,
			'None of these is a computed link, so none of them can be an unresolved one.'
		);
	}

	/**
	 * A link David sets on one of them is the link that renders.
	 *
	 * The 404's four buttons are `core/button`s with no URL. What ADR-0018 asks
	 * of the theme is that setting one is enough — no class, no filter, and
	 * nothing that reads the href back out and replaces it.
	 *
	 * @return void
	 */
	public function test_a_link_set_on_a_404_button_survives_rendering(): void {
		$target = home_url( '/where-i-sent-them/' );

		$this->override( 'wp_template', '404', $this->linked( 'templates/404.html', $target ) );

		$html = $this->render( home_url( '/nothing-here/' ), '404', self::NOT_FOUND );

		$this->assertSame( 'dpaternina//404', $this->resolved_template() );
		$this->assertGreaterThan(
			0,
			substr_count( $html, 'href="' . esc_url( $target ) . '"' )
		);
	}

	/*
	 * -------------------------------------------------- The generic page
	 */

	/**
	 * A plain page renders through `page`, not through `index`.
	 *
	 * @return void
	 */
	public function test_a_plain_page_renders_through_the_page_template(): void {
		$page = $this->seed_page( 'Uses' );

		$this->render( $this->permalink( $page ), 'page', self::PAGE );

		$this->assertSame( 'dpaternina//page', $this->resolved_template() );
	}

	/**
	 * The eyebrow and the deck are the page's own meta, bound rather than typed.
	 *
	 * The seeder writes both — `dp_updated` and `dp_lead` — so the three design
	 * pages arrive with them and nothing has to be pasted into the body.
	 *
	 * @return void
	 */
	public function test_a_page_prints_its_updated_stamp_and_its_deck(): void {
		$page = $this->seed_page( 'Uses' );

		update_post_meta( $page, 'dp_updated', 'UPDATED AUG 2026' );
		update_post_meta( $page, 'dp_lead', 'The hardware, software, and small objects I actually reach for.' );

		$html = $this->render( $this->permalink( $page ), 'page', self::PAGE );

		$this->assertStringContainsString( 'UPDATED AUG 2026', $html );
		$this->assertStringContainsString( 'The hardware, software, and small objects I actually reach for.', $html );
		$this->assertStringContainsString( 'Uses', $html );
	}

	/**
	 * With no meta the two lines are empty rather than wrong.
	 *
	 * They are hidden by `:empty` in the stylesheet, which is a presentation
	 * decision; what matters here is that nothing invents a value.
	 *
	 * @return void
	 */
	public function test_a_page_without_the_meta_prints_nothing_in_their_place(): void {
		$page = $this->seed_page( 'A page David wrote himself' );

		$html = $this->render( $this->permalink( $page ), 'page', self::PAGE );

		$this->assertMatchesRegularExpression( '~<p class="dp-hero-meta[^"]*"></p>~', $html );
		$this->assertMatchesRegularExpression( '~<p class="dp-hero-deck[^"]*"></p>~', $html );
	}

	/**
	 * The body is the page's content, in the house style.
	 *
	 * @return void
	 */
	public function test_a_page_renders_its_own_body(): void {
		$page = $this->seed_page( 'Colophon' );

		$html = $this->render( $this->permalink( $page ), 'page', self::PAGE );

		$this->assertStringContainsString( 'Placeholder body.', $html );
	}

	/*
	 * --------------------------------------------------------- About
	 */

	/**
	 * The about template answers for the page David assigned it to.
	 *
	 * @return void
	 */
	public function test_the_about_template_answers_for_its_page(): void {
		$page = $this->seed_page( 'Who is behind this', 'dp-about.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-about.html' ) );

		$this->assertSame( 'dpaternina//dp-about', $this->resolved_template() );
		$this->assertStringContainsString( 'Who is behind this', $html );
		$this->assertStringContainsString( 'Placeholder body.', $html );
		$this->assertStringContainsString( 'dp-about-intro', $html );
	}

	/**
	 * About keeps the chrome and the closing band; the design gives it both.
	 *
	 * @return void
	 */
	public function test_the_about_page_keeps_its_chrome_and_closing_band(): void {
		$page = $this->seed_page( 'Who is behind this', 'dp-about.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-about.html' ) );

		$this->assertStringContainsString( 'dp-header', $html );
		$this->assertStringContainsString( 'dp-footer', $html );
		$this->assertStringContainsString( 'dp-cta-band', $html );
	}

	/*
	 * ------------------------------------------------------- Contact
	 */

	/**
	 * The form is on the contact page, which is the whole point of Phase 7.
	 *
	 * @return void
	 */
	public function test_the_contact_template_places_the_form(): void {
		$page = $this->seed_page( 'Say hello', 'dp-contact.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-contact.html' ) );

		$this->assertSame( 'dpaternina//dp-contact', $this->resolved_template() );
		$this->assertStringContainsString( 'dp-contact-panel', $html );
		$this->assertStringContainsString( 'id="' . ContactForm::ROOT_ID . '"', $html );
		$this->assertStringContainsString( 'data-dp-contact-state="form"', $html );
	}

	/**
	 * The design gives contact no closing band, because the page is the CTA.
	 *
	 * @return void
	 */
	public function test_the_contact_page_has_no_closing_band(): void {
		$page = $this->seed_page( 'Say hello', 'dp-contact.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-contact.html' ) );

		$this->assertStringNotContainsString( 'dp-cta-band', $html );
		$this->assertStringContainsString( 'dp-footer', $html );
	}

	/**
	 * A closed form leaves the page, and the ways to reach David beside it.
	 *
	 * @return void
	 */
	public function test_a_closed_form_leaves_the_rest_of_the_page(): void {
		add_filter( 'dp_contact_form_enabled', '__return_false' );

		$page = $this->seed_page( 'Say hello', 'dp-contact.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-contact.html' ) );

		$this->assertStringNotContainsString( 'data-dp-contact-state', $html );
		$this->assertStringContainsString( 'Say hello', $html );
		$this->assertStringContainsString( 'Placeholder body.', $html );
	}

	/*
	 * -------------------------------------------------------- Résumé
	 */

	/**
	 * The ledger is on the résumé page.
	 *
	 * @return void
	 */
	public function test_the_resume_template_places_the_ledger(): void {
		$this->seed_role( 'MonsterInsights', 'Lead developer', 2024.0 );

		$page = $this->seed_page( 'The record, on one page', 'dp-resume.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-resume.html' ) );

		$this->assertSame( 'dpaternina//dp-resume', $this->resolved_template() );
		$this->assertStringContainsString( 'dp-ledger', $html );
		$this->assertStringContainsString( 'MonsterInsights', $html );
		$this->assertStringContainsString( 'Experience', $html );
	}

	/**
	 * The download block points at the query variable, on this very page.
	 *
	 * Never at a path: the theme asks `dp-core` what a résumé download looks
	 * like and `dp-core` builds it from the page being drawn, so renaming or
	 * moving the page moves the download with it. It is a named block rather
	 * than a class on a button (ADR-0018) because that URL is one nobody can
	 * type: the query variable's name is the plugin's.
	 *
	 * @return void
	 */
	public function test_the_download_block_resolves_to_the_query_variable(): void {
		$page = $this->seed_page( 'The record, on one page', 'dp-resume.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-resume.html' ) );

		$this->assertStringContainsString( 'data-dp-destination="' . ResumeDownload::DESTINATION . '"', $html );
		$this->assertStringContainsString(
			esc_url( ResumePdf::download_url( $page ) ),
			$html
		);
		$this->assertStringNotContainsString( 'href="/resume', $html );
		$this->assertStringNotContainsString( DerivedLink::UNRESOLVED_CLASS, $html );
	}

	/**
	 * Moving the page moves the download, because the URL was never the name.
	 *
	 * @return void
	 */
	public function test_moving_the_resume_page_moves_the_download(): void {
		$this->set_permalink_structure( '/%postname%/' );

		/*
		 * The slug spelling, not the file name: `wp_update_post()` re-validates
		 * whatever `page_template` the post already carries, and WordPress offers
		 * a block theme's custom templates under their slugs. A page holding
		 * `dp-resume.html` can be read but not saved, which is a fact about core
		 * rather than about this theme — the block accepts either spelling and
		 * `test_the_resume_download_accepts_either_spelling_of_the_template`
		 * covers the other one.
		 */
		$page = $this->seed_page( 'The record, on one page', 'dp-resume' );

		$updated = wp_update_post(
			array(
				'ID'        => $page,
				'post_name' => 'the-cv',
			),
			true
		);

		$this->assertIsInt( $updated, 'The rename has to happen, or the test proves nothing.' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-resume.html' ) );

		$this->assertStringContainsString( 'the-cv', $this->permalink( $page ) );
		$this->assertStringContainsString( esc_url( ResumePdf::download_url( $page ) ), $html );

		$this->set_permalink_structure( '' );
	}

	/**
	 * The design gives the résumé no closing band either.
	 *
	 * @return void
	 */
	public function test_the_resume_page_has_no_closing_band(): void {
		$page = $this->seed_page( 'The record, on one page', 'dp-resume.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-resume.html' ) );

		$this->assertStringNotContainsString( 'dp-cta-band', $html );
		$this->assertStringContainsString( 'dp-footer', $html );
	}

	/**
	 * With no record the page still renders, without an empty Experience shell.
	 *
	 * @return void
	 */
	public function test_the_resume_page_survives_an_empty_record(): void {
		$page = $this->seed_page( 'The record, on one page', 'dp-resume.html' );

		$html = $this->render( $this->permalink( $page ), 'page', self::assigned( 'dp-resume.html' ) );

		$this->assertStringContainsString( 'The record, on one page', $html );
		$this->assertStringNotContainsString( 'dp-ledger-rows', $html );
	}
}
