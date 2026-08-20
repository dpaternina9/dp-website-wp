<?php
/**
 * Integration tests for the homepage.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

/**
 * The editorial homepage: hero, RIGHT NOW, the record, shipped, latest writing.
 *
 * The two assertions worth having here are about the loops rather than the
 * copy. The record strip and the work cards query post types that are not
 * publicly viewable, which `core/query` silently refuses to do — it drops the
 * `postType` attribute and queries posts instead, returning something that
 * looks plausible and is wrong. Counting what came back is what catches that.
 */
final class FrontPageTest extends TemplateTestCase {

	/**
	 * The hierarchy core hands `locate_block_template()` for a front page.
	 *
	 * @var array<int, string>
	 */
	private const HIERARCHY = array( 'front-page.php', 'home.php', 'index.php' );

	/**
	 * The front page resolves to `front-page`, whichever page David chose.
	 *
	 * @return void
	 */
	public function test_the_front_page_resolves_to_front_page(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );
		$this->seed_posts_page();

		$this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertTrue( is_front_page() );
		$this->assertSame( 'dpaternina//front-page', $this->resolved_template() );
	}

	/**
	 * The hero carries the accent word, once, in an h1.
	 *
	 * @return void
	 */
	public function test_the_hero_carries_the_accent_word(): void {
		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'dp-home-hero-title', $html );
		$this->assertStringContainsString( '<span class="dp-accent">useful</span>', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ), 'One h1 per page (CLAUDE.md §1.7).' );
	}

	/**
	 * The record strip shows three roles, most recent first.
	 *
	 * @return void
	 */
	public function test_the_record_strip_shows_three_roles_most_recent_first(): void {
		$this->seed_role( 'Aplyca', 'Developer', 2020.0 );
		$this->seed_role( 'Globant', 'Developer', 2022.0 );
		$this->seed_role( 'MonsterInsights', 'Developer team lead', 2026.0 );
		$this->seed_role( 'Fanxie Lab', 'CTO and founder', 2026.9 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame( 3, substr_count( $html, 'dp-record-org' ), 'The design shows one quiet strip of three.' );

		$order = array();

		foreach ( array( 'Fanxie Lab', 'MonsterInsights', 'Globant', 'Aplyca' ) as $org ) {
			$position = strpos( $html, '>' . $org . '</h3>' );

			if ( false !== $position ) {
				$order[ $org ] = $position;
			}
		}

		$this->assertSame( array( 'Fanxie Lab', 'MonsterInsights', 'Globant' ), array_keys( $order ) );
		$this->assertArrayNotHasKey( 'Aplyca', $order, 'The fourth role is off the end of the strip.' );
	}

	/**
	 * The record strip prints the fields core's own meta binding refuses.
	 *
	 * `core/post-meta` returns null for anything on a post type that is not
	 * publicly viewable, and none of `dp-core`'s three is. The theme's own
	 * source names these fields one at a time; this is the assertion that the
	 * allowlist actually reaches the page.
	 *
	 * @return void
	 */
	public function test_the_record_strip_prints_the_role_title_and_range(): void {
		$this->seed_role( 'Fanxie Lab', 'CTO and founder', 2026.9 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'CTO and founder', $html );
		$this->assertStringContainsString( '2024 — 2026', $html );
	}

	/**
	 * Latest writing shows three posts, newest first, in compact rows.
	 *
	 * @return void
	 */
	public function test_latest_writing_shows_three_compact_rows(): void {
		$this->seed_categories();
		$this->seed_posts( 5 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertSame( 3, substr_count( $html, 'dp-row dp-row-compact' ) );
		$this->assertStringContainsString( 'Post number 1', $html );
		$this->assertStringNotContainsString( 'Post number 4', $html );
	}

	/**
	 * A static front page's own content still renders.
	 *
	 * @return void
	 */
	public function test_the_chosen_front_pages_content_is_rendered(): void {
		$this->seed_posts_page();

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertStringContainsString( 'Placeholder body.', $html );
	}

	/**
	 * With Reading left alone there is no queried page, and nothing breaks.
	 *
	 * @return void
	 */
	public function test_the_front_page_renders_with_reading_left_unset(): void {
		$this->seed_categories();
		$this->seed_posts( 2 );

		$html = $this->render( home_url( '/' ), 'front-page', self::HIERARCHY );

		$this->assertTrue( is_home(), 'Nothing was chosen, so the front page is the posts index.' );
		$this->assertStringContainsString( 'dp-home-hero-title', $html );
		$this->assertStringNotContainsString( 'Placeholder body.', $html, 'There is no page whose content could render.' );
	}
}
