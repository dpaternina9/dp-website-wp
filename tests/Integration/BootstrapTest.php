<?php
/**
 * Integration tests for the Phase 0 floor.
 *
 * These run against a real WordPress install with a real database inside the
 * wp-env `tests` environment. They assert that the harness itself is live — a
 * broken bootstrap fails here instead of silently reporting an empty pass.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Plugin;
use WP_UnitTestCase;

/**
 * Proves WordPress is really loaded and both packages are really present.
 */
final class BootstrapTest extends WP_UnitTestCase {

	/**
	 * A real WordPress is loaded, not a set of mocks.
	 *
	 * @return void
	 */
	public function test_wordpress_is_actually_loaded(): void {
		$this->assertTrue( defined( 'ABSPATH' ), 'ABSPATH is only defined by a real WordPress bootstrap.' );
		$this->assertTrue( function_exists( 'wp_insert_post' ), 'Core is loaded.' );
		$this->assertGreaterThan( 0, did_action( 'init' ), 'The init action has fired.' );
		$this->assertTrue( version_compare( get_bloginfo( 'version' ), '6.6', '>=' ) );
	}

	/**
	 * The database is real and writable, so integration tests can use it.
	 *
	 * @return void
	 */
	public function test_the_database_round_trips_a_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Phase 0 floor',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );

		$this->assertNotNull( $post );
		$this->assertSame( 'Phase 0 floor', $post->post_title );
	}

	/**
	 * The dp-core plugin is loaded and booted through its own Composer autoloader.
	 *
	 * @return void
	 */
	public function test_dp_core_is_booted(): void {
		$this->assertTrue( class_exists( Plugin::class, false ), 'PSR-4 autoloading resolved DP\\Core\\Plugin.' );
		$this->assertTrue( defined( 'DP\\Core\\VERSION' ) );

		$plugin = Plugin::boot( 'ignored-because-already-booted', 'ignored' );

		$this->assertStringEndsWith( 'plugins/dp-core/dp-core.php', $plugin->file() );
		$this->assertSame( \DP\Core\VERSION, $plugin->version() );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$header = get_plugin_data( $plugin->file(), false, false );

		$this->assertSame( \DP\Core\VERSION, $header['Version'], 'The header and the constant must not drift.' );
		$this->assertSame( 'https://updates.dpaternina.com/core', $header['UpdateURI'] );
	}

	/**
	 * The theme is a recognised, activatable block theme.
	 *
	 * @return void
	 */
	public function test_the_theme_is_an_activatable_block_theme(): void {
		$theme = wp_get_theme( 'dpaternina' );

		$this->assertTrue( $theme->exists(), 'The theme directory is mounted and readable.' );
		$this->assertSame( array(), $theme->errors() ? $theme->errors()->get_error_messages() : array() );
		$this->assertSame( 'dpaternina', $theme->get_stylesheet() );
		$this->assertSame(
			'https://updates.dpaternina.com/theme',
			$theme->get( 'UpdateURI' ),
			'The Update URI header drives the Phase 2 release pipeline.'
		);
		$this->assertTrue(
			file_exists( $theme->get_stylesheet_directory() . '/templates/index.html' ),
			'templates/index.html is what makes this a block theme.'
		);
	}

	/**
	 * Nothing here registers a rewrite rule. CLAUDE.md §5.1, enforced.
	 *
	 * Pretty permalinks are switched on for the duration so the rule set is
	 * actually generated — asserting against an empty rule set would prove
	 * nothing.
	 *
	 * @return void
	 */
	public function test_no_custom_rewrite_rules_are_registered(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$rules = get_option( 'rewrite_rules' );

		$this->assertIsArray( $rules );
		$this->assertNotEmpty( $rules, 'Core generated its own rules, so the check below is meaningful.' );

		$ours = array_values(
			array_filter(
				array_keys( $rules ),
				static fn ( string $pattern ): bool => str_contains( $pattern, 'dp_' )
					|| str_contains( $pattern, 'dp-' )
			)
		);

		$this->assertSame( array(), $ours, 'Pages belong to David. The only rewrites we may ever add arrive in Phase 3.' );

		$this->set_permalink_structure( '' );
	}
}
