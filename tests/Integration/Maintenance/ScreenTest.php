<?php
/**
 * Integration tests for the document the public is shown.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Maintenance;

use DP\Core\Maintenance\Screen;
use DP\Core\Maintenance\Settings;

/**
 * The screen carries David's copy, none that is not his, and nothing a strict
 * content policy would drop.
 *
 * Three claims, in that order, and the second is the one CLAUDE.md rule 2 makes:
 * a page the public sees whose sentences are literals in PHP is a page this
 * repository has written on David's behalf. So the test does not only look for
 * what he set — it looks for the shipped placeholder *not* being there once he
 * has replaced it, which is the failure a "contains the right string" assertion
 * would miss.
 *
 * The third claim is the standing obligation from docs/plan.md Phase 10: headers
 * on the live site come from David's security plugin, and nothing this
 * repository emits may force him to loosen them. The e2e sweep in
 * `tests/e2e/front-end.ts` holds every *theme* template to that, but it drives a
 * running site through a browser and this document only exists when the site is
 * switched off, so it is out of that sweep's reach by construction. This is the
 * same audit, made where the document is built.
 */
final class ScreenTest extends MaintenanceTestCase {

	/**
	 * The copy David set, and no copy he did not.
	 *
	 * @return void
	 */
	public function test_it_carries_the_copy_david_set(): void {
		update_option( 'blogname', 'A Site Being Built' );
		update_option( 'blogdescription', 'A tagline nobody wrote in PHP' );
		update_option( Settings::HEADING, 'Pardon the dust' );
		update_option( Settings::MESSAGE, "Almost there.\n\nCheck back tomorrow." );
		update_option( Settings::CONTACT, 'hello@example.com' );

		$document = $this->screen()->render();

		$this->assertStringContainsString( 'A Site Being Built', $document );
		$this->assertStringContainsString( 'A tagline nobody wrote in PHP', $document );
		$this->assertStringContainsString( 'Pardon the dust', $document );
		$this->assertStringContainsString( 'Almost there.', $document );
		$this->assertStringContainsString( 'Check back tomorrow.', $document );
		$this->assertStringContainsString( 'mailto:hello@example.com', $document );

		$this->assertStringNotContainsString(
			Settings::default_heading(),
			$document,
			'The shipped placeholder outlived the heading David set.'
		);
		$this->assertStringNotContainsString(
			Settings::default_message(),
			$document,
			'The shipped placeholder outlived the message David set.'
		);
	}

	/**
	 * A message of two paragraphs comes out as two paragraphs.
	 *
	 * @return void
	 */
	public function test_a_blank_line_starts_a_paragraph(): void {
		update_option( Settings::MESSAGE, "First.\n\nSecond." );

		$document = $this->screen()->render();

		$this->assertStringContainsString( '<p>First.</p>', $document );
		$this->assertStringContainsString( '<p>Second.</p>', $document );
	}

	/**
	 * With no address set there is no link on the page at all.
	 *
	 * "No hardcoded links anywhere on the screen" is only true if a blank field
	 * renders nothing, rather than rendering an empty `mailto:` or falling back
	 * to the administration address, which is a different decision from
	 * publishing one.
	 *
	 * @return void
	 */
	public function test_a_blank_address_renders_no_link(): void {
		update_option( Settings::CONTACT, '' );
		update_option( 'admin_email', 'admin@example.com' );

		$document = $this->screen()->render();

		$this->assertStringNotContainsString( '<a ', $document );
		$this->assertStringNotContainsString( 'mailto:', $document );
		$this->assertStringNotContainsString( 'admin@example.com', $document );
	}

	/**
	 * The document is a document: a language, a title and exactly one heading.
	 *
	 * @return void
	 */
	public function test_it_is_a_complete_accessible_document(): void {
		update_option( 'blogname', 'A Site Being Built' );

		$document = $this->screen()->render();

		$this->assertStringStartsWith( '<!DOCTYPE html>', $document );
		$this->assertStringContainsString( sprintf( '<html lang="%s">', esc_attr( get_bloginfo( 'language' ) ) ), $document );
		$this->assertStringContainsString( '<meta name="viewport"', $document );
		$this->assertStringContainsString( '<title>', $document );
		$this->assertStringContainsString( 'A Site Being Built', $document, 'The title should name the site.' );
		$this->assertSame( 1, substr_count( $document, '<h1' ) );
		$this->assertStringEndsWith( "</html>\n", $document );
	}

	/**
	 * An emptied heading still leaves the document its one `<h1>`.
	 *
	 * @return void
	 */
	public function test_an_empty_heading_still_produces_one_h1(): void {
		update_option( Settings::HEADING, '' );

		$document = $this->screen()->render();

		$this->assertSame( 1, substr_count( $document, '<h1' ) );
		$this->assertStringContainsString( Settings::default_heading(), $document );
	}

	/**
	 * Copy is escaped, whatever route it arrived by.
	 *
	 * The sanitizers strip markup on the way in, but an option can arrive from
	 * WP-CLI or a migration without passing one, and this document is served to
	 * the public.
	 *
	 * @return void
	 */
	public function test_copy_is_escaped_on_the_way_out(): void {
		remove_all_filters( 'sanitize_option_' . Settings::HEADING );
		remove_all_filters( 'sanitize_option_' . Settings::MESSAGE );

		update_option( Settings::HEADING, '<script>alert("heading")</script>' );
		update_option( Settings::MESSAGE, '<img src=x onerror=alert(1)>' );

		$document = $this->screen()->render();

		$this->assertStringNotContainsString( '<script>', $document );
		$this->assertStringNotContainsString( '<img', $document );
		$this->assertStringContainsString( '&lt;script&gt;', $document );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $document );

		// The handler survives as text, which is the point: it is never an attribute.
		$this->assertDoesNotMatchRegularExpression( '/<[^>]+\son[a-z]+\s*=/i', $document );
	}

	/**
	 * Nothing here would make David loosen his content policy.
	 *
	 * No inline `<style>`, no `<script>` of any kind, no `style=` attribute, no
	 * `on*` handler — and the one asset it does load is a file in this plugin, on
	 * this origin, so a `style-src 'self'` policy needs no exception for it.
	 *
	 * @return void
	 */
	public function test_it_emits_nothing_a_strict_policy_would_drop(): void {
		update_option( Settings::CONTACT, 'hello@example.com' );

		$document = $this->screen()->render();

		$this->assertStringNotContainsString( '<style', $document );
		$this->assertStringNotContainsString( '<script', $document );
		$this->assertStringNotContainsString( ' style=', $document );
		$this->assertStringNotContainsString( 'javascript:', $document );
		$this->assertDoesNotMatchRegularExpression( '/<[^>]+\son[a-z]+\s*=/i', $document );
	}

	/**
	 * Every URL in the document is this origin or a mailto:.
	 *
	 * The Phase 10 obligation includes "no off-origin request", and a webfont or
	 * a CDN reset would be exactly that. The design's own faces are the theme's
	 * files and are imported from Google Fonts in `design-source`, which is why
	 * the screen's type falls back to a system stack instead.
	 *
	 * @return void
	 */
	public function test_every_url_is_this_origin_or_a_mailto(): void {
		update_option( Settings::CONTACT, 'hello@example.com' );

		$document = $this->screen()->render();

		preg_match_all( '/(?:href|src)="([^"]+)"/i', $document, $matches );

		$urls = $matches[1];

		$this->assertNotEmpty( $urls );

		foreach ( $urls as $url ) {
			$decoded = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );

			$this->assertTrue(
				str_starts_with( $decoded, 'mailto:' ) || str_starts_with( $decoded, site_url() ),
				$decoded . ' is neither this origin nor a mailto:.'
			);
		}
	}

	/**
	 * The stylesheet is a real file in this plugin, cache-busted by its version.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_exists_and_is_versioned(): void {
		$screen = $this->screen();
		$url    = $screen->stylesheet_url();

		$this->assertStringContainsString( Screen::STYLESHEET, $url );
		$this->assertStringContainsString( 'ver=', $url );

		$this->assertFileExists( dirname( __DIR__, 3 ) . '/plugins/dp-core/' . Screen::STYLESHEET );
	}
}
