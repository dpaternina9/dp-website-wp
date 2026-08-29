<?php
/**
 * Integration tests for the Watch template.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Patterns;
use WP_Block_Patterns_Registry;
use WP_Error;

/**
 * `dp-watch`, as a request renders it.
 *
 * The template holds to §5.1's shape: it is declared in `theme.json`
 * `customTemplates` under the load-bearing `dp-` prefix, offered in the admin,
 * and applied only by assignment — a page slugged `watch` with nothing
 * assigned renders the ordinary page template. Both directions are asserted.
 *
 * The gear list is the other claim: it is page content David owns, not
 * template markup, so it renders exactly when the page's own body carries it
 * — which is what the seeder starts it with, through `dp_seed_watch_body`.
 */
final class WatchTest extends TemplateTestCase {

	/**
	 * The hierarchy for a page carrying the Watch template.
	 *
	 * @var array<int, string>
	 */
	private const ASSIGNED = array( 'dp-watch.html', 'page.php', 'singular.php', 'index.php' );

	/**
	 * The hierarchy for a page with no template assigned.
	 *
	 * @var array<int, string>
	 */
	private const PAGE = array( 'page.php', 'singular.php', 'index.php' );

	/**
	 * Keep every render off the network; the thumbnail cache would otherwise
	 * try to warm itself from a template test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter(
			'pre_http_request',
			static fn (): WP_Error => new WP_Error( 'http_request_blocked', 'External HTTP is blocked in the test suite.' )
		);
	}

	/**
	 * The template is declared in theme.json, prefixed, and offered for pages.
	 *
	 * @return void
	 */
	public function test_the_template_is_declared_and_offered(): void {
		$offered = wp_get_theme()->get_page_templates( null, 'page' );

		$this->assertArrayHasKey( 'dp-watch', $offered, 'theme.json customTemplates is where the declaration goes (ADR-0020).' );
		$this->assertSame( 'Watch', $offered['dp-watch'] );
	}

	/**
	 * The hierarchy must never auto-apply it: a page slugged `watch` with no
	 * assignment renders the ordinary page template. The `dp-` prefix is the
	 * whole defence (CLAUDE.md §5.1).
	 *
	 * @return void
	 */
	public function test_a_page_slugged_watch_is_not_captured_by_the_template(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Watch.',
				'post_name'   => 'watch',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $page );

		$this->render( $this->permalink( $page ), 'page', self::PAGE );

		$this->assertSame( 'dpaternina//page', $this->resolved_template() );
	}

	/**
	 * Assigned, the template draws the hero, both Watch blocks' sections and
	 * the page's own body, wrapped in the site chrome.
	 *
	 * @return void
	 */
	public function test_the_assigned_template_renders_the_watch_page(): void {
		$page = $this->seed_page( 'Watch.', 'dp-watch.html' );

		update_post_meta( $page, 'dp_lead', 'Not live at the moment.' );

		$html = $this->render( $this->permalink( $page ), 'page', self::ASSIGNED );

		$this->assertSame( 'dpaternina//dp-watch', $this->resolved_template() );
		$this->assertStringContainsString( 'Watch.', $html );
		$this->assertStringContainsString( 'Not live at the moment.', $html );
		$this->assertStringContainsString( 'The archive', $html );
		$this->assertStringContainsString( 'Twitch VODs · YouTube uploads', $html );
		$this->assertStringContainsString( 'Placeholder body.', $html, 'The gear section renders the page\'s own content.' );

		$this->assertStringContainsString( 'dp-header', $html );
		$this->assertStringContainsString( 'dp-footer', $html );
		$this->assertStringContainsString( 'dp-cta-band', $html );
	}

	/**
	 * With videos published, the featured panel and the grid are on the page,
	 * and no player is.
	 *
	 * @return void
	 */
	public function test_the_template_renders_the_grid_and_never_a_player(): void {
		foreach ( array( 'One', 'Two', 'Three' ) as $order => $title ) {
			$video = self::factory()->post->create(
				array(
					'post_type'   => 'dp_video',
					'post_title'  => 'Fixture stream — ' . $title,
					'post_status' => 'publish',
					'menu_order'  => $order + 1,
				)
			);

			$this->assertIsInt( $video );

			update_post_meta( $video, 'dp_video_source', 'twitch' );
			update_post_meta( $video, 'dp_video_ref', '228091884' . $order );
		}

		$page = $this->seed_page( 'Watch.', 'dp-watch.html' );
		$html = $this->render( $this->permalink( $page ), 'page', self::ASSIGNED );

		$this->assertStringContainsString( 'dp-watch-featured', $html );
		$this->assertStringContainsString( 'Fixture stream — One', $html );
		$this->assertSame( 2, substr_count( $html, 'dp-vg-card' ), 'The featured video stays out of the grid.' );
		$this->assertStringNotContainsString( '<iframe', $html );
	}

	/**
	 * The gear pattern is registered, filed with the theme's own patterns, and
	 * is what the seeding seam answers with.
	 *
	 * @return void
	 */
	public function test_the_gear_pattern_answers_the_seeding_seam(): void {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( Patterns::WATCH_GEAR );

		$this->assertIsArray( $pattern );
		$this->assertContains( Patterns::CATEGORY, (array) ( $pattern['categories'] ?? array() ) );

		$body = apply_filters( 'dp_seed_watch_body', '<!-- wp:paragraph --><p>fallback</p><!-- /wp:paragraph -->' );

		$this->assertIsString( $body );
		$this->assertStringContainsString( 'What the stream runs on', $body );
		$this->assertStringContainsString( 'dp-gear-group', $body );
		$this->assertStringNotContainsString( 'fallback', $body );
		$this->assertStringNotContainsString( 'href=', $body, 'A shipped pattern carries no destinations (ADR-0018).' );

		foreach ( array( 'Desk', 'Camera &amp; light', 'Audio', 'Software' ) as $group ) {
			$this->assertStringContainsString( $group, $body );
		}
	}
}
