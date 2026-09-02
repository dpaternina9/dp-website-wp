<?php
/**
 * Integration tests for the design's components as patterns.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\PageState;
use DP\Theme\Patterns;
use WP_Block_Patterns_Registry;

/**
 * Every repeated part of the design is its own pattern, and every one renders.
 *
 * Digest §1: "every repeated part is its own file". The list below is that
 * mapping, written down once, so a pattern that is renamed or dropped fails
 * here rather than leaving a template rendering an empty `core/pattern` block —
 * which is what an unregistered slug does, silently.
 *
 * `SectionHead`'s own note carries a rule the markup has to keep: `meta` only
 * renders when there is no `action`. There is a test for it, because "never
 * both" is exactly the kind of constraint that survives review and dies in the
 * next edit.
 */
final class PatternsTest extends TemplateTestCase {

	/**
	 * Every pattern this theme ships, and the class each one is recognised by.
	 *
	 * @var array<string, string>
	 */
	private const PATTERNS = array(
		'dpaternina/page-hero'            => 'dp-hero',
		'dpaternina/section-head'         => 'dp-section-head-kicker',
		'dpaternina/section-head-heading' => 'dp-section-head-heading',
		'dpaternina/cta-banner'           => 'dp-cta-banner',
		'dpaternina/cta-banner-filled'    => 'dp-cta-banner-filled',
		'dpaternina/contact-method'       => 'dp-contact-method',
		'dpaternina/post-row-list'        => 'dp-rows',
		'dpaternina/post-row-archive'     => 'dp-rows-ruled',
		'dpaternina/post-row-compact'     => 'dp-row-compact',
		'dpaternina/work-card'            => 'dp-cards',
		'dpaternina/cta-band'             => 'dp-cta-band',
	);

	/**
	 * All eleven are registered under the theme's own category.
	 *
	 * @return void
	 */
	public function test_every_pattern_is_registered(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( array_keys( self::PATTERNS ) as $slug ) {
			$this->assertTrue( $registry->is_registered( $slug ), $slug . ' is not registered.' );

			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );
			$this->assertContains( Patterns::CATEGORY, $pattern['categories'] ?? array(), $slug . ' is filed under the wrong category.' );
		}
	}

	/**
	 * Each one renders its own markup, without a notice.
	 *
	 * The query-loop patterns are given something to loop over first: an empty
	 * `core/post-template` renders nothing at all, and a test that accepted that
	 * would pass against a pattern whose query was broken.
	 *
	 * @return void
	 */
	public function test_every_pattern_renders_its_own_markup(): void {
		$this->seed_categories();
		$this->seed_posts( 3 );
		$this->seed_ship( 'Kiveo', true, 2025.0 );
		$this->seed_page( 'Say hello', 'dp-contact.html' );

		$this->go_to( home_url( '/' ) );

		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( self::PATTERNS as $slug => $marker ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );

			$html = do_blocks( (string) ( $pattern['content'] ?? '' ) );

			$this->assertStringContainsString( $marker, $html, $slug . ' did not render its own markup.' );
		}
	}

	/**
	 * A section head carries a kicker or a heading, and a meta note or an action.
	 *
	 * @return void
	 */
	public function test_a_section_head_never_carries_both_halves(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( array( 'dpaternina/section-head', 'dpaternina/section-head-heading' ) as $slug ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );

			$content = (string) ( $pattern['content'] ?? '' );

			$this->assertFalse(
				str_contains( $content, 'dp-section-head-kicker' ) && str_contains( $content, 'dp-section-head-heading' ),
				$slug . ' carries both a kicker and a heading. SectionHead takes one or the other.'
			);

			$this->assertFalse(
				str_contains( $content, 'dp-section-head-meta' ) && str_contains( $content, 'dp-section-head-action' ),
				$slug . ' carries both a meta note and an action. SectionHead: "meta only renders when there is no action".'
			);
		}
	}

	/**
	 * The work cards query the ships David marked featured, and only those.
	 *
	 * @return void
	 */
	public function test_the_work_cards_show_only_featured_ships(): void {
		$this->seed_ship( 'Kiveo', true, 2025.0 );
		$this->seed_ship( 'Natural-language queries', true, 2026.0 );
		$this->seed_ship( 'Something unfinished', false, 2026.5 );

		$this->go_to( home_url( '/' ) );

		$html = $this->work_cards_html();

		$this->assertStringContainsString( 'Natural-language queries', $html );
		$this->assertStringContainsString( 'Kiveo', $html );
		$this->assertStringNotContainsString( 'Something unfinished', $html );
	}

	/**
	 * The cards run in David's order, not in the order things shipped.
	 *
	 * Until 2026-09-02 this loop sorted on `dp_end` descending, so the newest
	 * thing led the work page whether or not it was the strongest. Which three
	 * pieces of work open the page is an editorial decision — the same one
	 * ADR-0019 settled for a series — so it is `menu_order`, set in Page
	 * Attributes.
	 *
	 * The fixture is built so the two rules disagree: the card David ordered
	 * first is the one that shipped *earliest*. A regression to the old sort
	 * therefore fails here rather than passing by coincidence.
	 *
	 * @return void
	 */
	public function test_the_work_cards_run_in_the_order_david_set(): void {
		$this->seed_ship( 'Ordered first, shipped earliest', true, 2020.0, '', 1 );
		$this->seed_ship( 'Ordered second, shipped latest', true, 2026.0, '', 2 );

		$this->go_to( home_url( '/' ) );

		$html = $this->work_cards_html();

		$this->assertLessThan(
			strpos( $html, 'Ordered second, shipped latest' ),
			strpos( $html, 'Ordered first, shipped earliest' ),
			'menu_order decides the sequence; the shipped date does not.'
		);
	}

	/**
	 * A featured ship with no end date is still featured.
	 *
	 * The old ordering needed a second `meta_query` clause requiring `dp_end` to
	 * EXIST, purely to have a named clause to sort on. `register_post_meta()`'s
	 * default is not a row, so an ongoing project whose end was never saved
	 * matched neither the clause nor the sort and dropped out of a three-card
	 * grid entirely — the most current work, least likely to be shown.
	 *
	 * @return void
	 */
	public function test_a_featured_ship_with_no_end_date_still_appears(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'dp_ship',
				'post_title' => 'Still going, no end date',
				'menu_order' => 1,
			)
		);

		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, 'dp_featured', true );
		delete_post_meta( $post_id, 'dp_end' );

		$this->go_to( home_url( '/' ) );

		$this->assertStringContainsString( 'Still going, no end date', $this->work_cards_html() );
	}

	/**
	 * The work-card pattern, rendered.
	 *
	 * @return string
	 */
	private function work_cards_html(): string {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'dpaternina/work-card' );

		$this->assertIsArray( $pattern );

		return do_blocks( (string) ( $pattern['content'] ?? '' ) );
	}

	/**
	 * A pattern's copy is placeholder, and none of it invents a fact about David.
	 *
	 * The one thing worth asserting mechanically is that no pattern has quietly
	 * acquired a link to a page on this site. A pattern is where the temptation
	 * is greatest, because it looks like content rather than code — and it is
	 * code: a pattern's markup ships in the release, so a path in one is the
	 * theme deciding David's slugs, which is what CLAUDE.md §5.1 forbids.
	 *
	 * **This test used to say something stronger and wrong.** It asserted that no
	 * pattern contained an href *at all*, which was ADR-0006 §2's own rule rather
	 * than §5.1's, and it is the reason the destination filter could be written to
	 * overwrite an href unconditionally: an author-set link had been defined out
	 * of existence, so there was nothing to preserve. ADR-0018 removes the rule.
	 * A fragment, a `mailto:`, and a link to somewhere that is not this site are
	 * all fine; a path here is not.
	 *
	 * @return void
	 */
	public function test_no_pattern_carries_a_link_to_a_page_on_this_site(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( array_keys( self::PATTERNS ) as $slug ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );

			preg_match_all( '~href="([^"]*)"~', (string) ( $pattern['content'] ?? '' ), $hrefs );

			foreach ( $hrefs[1] as $href ) {
				if ( '' === $href || str_starts_with( $href, '#' ) ) {
					continue;
				}

				$scheme = wp_parse_url( $href, PHP_URL_SCHEME );

				// `mailto:`, `tel:` and the like address something that is not a page.
				if ( is_string( $scheme ) && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
					continue;
				}

				$this->assertNotContains(
					wp_parse_url( $href, PHP_URL_HOST ),
					array( null, false, $host ),
					sprintf(
						'%s links "%s", which is a path on this site. David creates every page and picks '
						. 'its slug (CLAUDE.md §5.1); a pattern may not decide one for him.',
						$slug,
						$href
					)
				);
			}
		}
	}

	/**
	 * The two list patterns draw the same row, because there is one of it.
	 *
	 * `PostRow`'s list variant appears twice — the blog index and the term
	 * archive — inside different surroundings: the index takes the design's
	 * empty *panel* and its end-of-archive note, the archive takes a one-line
	 * empty state and a closing rule. A `core/query` cannot be split across two
	 * patterns, so there are two patterns; `DP\Theme\Patterns::post_row()` is
	 * what stops there being two rows.
	 *
	 * @return void
	 */
	public function test_the_two_list_patterns_share_one_row(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$row      = Patterns::post_row();

		$this->assertStringContainsString( 'dp-row-title', $row );

		foreach ( array( 'dpaternina/post-row-list', 'dpaternina/post-row-archive' ) as $slug ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );
			$this->assertStringContainsString(
				$row,
				(string) ( $pattern['content'] ?? '' ),
				$slug . ' has its own copy of the row rather than the shared one.'
			);
		}
	}

	/**
	 * Both of them carry the pager, and only the index carries the end panel.
	 *
	 * @return void
	 */
	public function test_both_lists_carry_the_pager_and_only_the_index_closes_the_archive(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$pager    = Patterns::pager();

		foreach ( array( 'dpaternina/post-row-list', 'dpaternina/post-row-archive' ) as $slug ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );
			$this->assertStringContainsString( $pager, (string) ( $pattern['content'] ?? '' ), $slug );
		}

		$index   = $registry->get_registered( 'dpaternina/post-row-list' );
		$archive = $registry->get_registered( 'dpaternina/post-row-archive' );

		$this->assertIsArray( $index );
		$this->assertIsArray( $archive );
		$this->assertStringContainsString( PageState::LAST_PAGE_VARIATION, (string) ( $index['content'] ?? '' ) );
		$this->assertStringNotContainsString( PageState::LAST_PAGE_VARIATION, (string) ( $archive['content'] ?? '' ) );
	}
}
