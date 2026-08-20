<?php
/**
 * Integration tests for the design's components as patterns.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

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
		'dpaternina/post-row-compact'     => 'dp-row-compact',
		'dpaternina/work-card'            => 'dp-cards',
		'dpaternina/cta-band'             => 'dp-cta-band',
	);

	/**
	 * All ten are registered under the theme's own category.
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

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'dpaternina/work-card' );

		$this->assertIsArray( $pattern );

		$html = do_blocks( (string) ( $pattern['content'] ?? '' ) );

		$this->assertStringContainsString( 'Natural-language queries', $html );
		$this->assertStringContainsString( 'Kiveo', $html );
		$this->assertStringNotContainsString( 'Something unfinished', $html );

		$this->assertLessThan(
			strpos( $html, 'Kiveo' ),
			strpos( $html, 'Natural-language queries' ),
			'The most recently shipped comes first.'
		);
	}

	/**
	 * A pattern's copy is placeholder, and none of it invents a fact about David.
	 *
	 * The one thing worth asserting mechanically is that no pattern has quietly
	 * acquired an href. CLAUDE.md §5.1 forbids one, and a pattern is where the
	 * temptation is greatest because it looks like content rather than code.
	 *
	 * @return void
	 */
	public function test_no_pattern_carries_a_hardcoded_page_link(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( array_keys( self::PATTERNS ) as $slug ) {
			$pattern = $registry->get_registered( $slug );

			$this->assertIsArray( $pattern );

			$content = (string) ( $pattern['content'] ?? '' );

			$this->assertDoesNotMatchRegularExpression(
				'~href="(?!#|mailto:)[^"]~',
				$content,
				$slug . ' carries an href. Links say which destination they want and are resolved at render time.'
			);
		}
	}
}
