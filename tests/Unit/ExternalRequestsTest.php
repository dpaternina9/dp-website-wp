<?php
/**
 * Unit tests for the off-origin filters.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Theme\ExternalRequests;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Exercises the two filter callbacks without loading WordPress.
 *
 * Both take whatever a plugin hands them, so both are tested against the shapes
 * a plugin can legally produce: a bare URL, a protocol-relative URL, and the
 * attribute array `wp_resource_hints` documents.
 */
final class ExternalRequestsTest extends TestCase {

	/**
	 * The object under test.
	 *
	 * @var ExternalRequests
	 */
	private ExternalRequests $requests;

	/**
	 * Start Brain Monkey.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Functions\when( 'home_url' )->justReturn( 'https://dpaternina.test' );
		Functions\when( 'wp_parse_url' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- this IS the stand-in for wp_parse_url().
			static fn ( string $url, int $component = -1 ): mixed => parse_url( $url, $component )
		);

		$this->requests = new ExternalRequests();
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Same-origin hints survive; everything else is dropped.
	 *
	 * @return void
	 */
	public function test_off_origin_resource_hints_are_dropped(): void {
		$kept = $this->requests->filter_resource_hints(
			array(
				'//fonts.gstatic.com',
				'https://fonts.googleapis.com',
				'https://dpaternina.test/wp-content/themes/dpaternina/assets/css/tokens.css',
				array( 'href' => 'https://analytics.example/script.js' ),
				array(
					'href' => 'https://dpaternina.test/wp-includes/js/a.js',
					'as'   => 'script',
				),
				'/wp-content/relative.css',
			)
		);

		$this->assertSame(
			array(
				'https://dpaternina.test/wp-content/themes/dpaternina/assets/css/tokens.css',
				array(
					'href' => 'https://dpaternina.test/wp-includes/js/a.js',
					'as'   => 'script',
				),
				'/wp-content/relative.css',
			),
			$kept,
			'A relative hint has no host and cannot leave the origin, so it is kept.'
		);
	}

	/**
	 * A filter handed something that is not a list returns an empty list.
	 *
	 * @return void
	 */
	public function test_a_malformed_resource_hint_list_is_refused(): void {
		$this->assertSame( array(), $this->requests->filter_resource_hints( 'not-a-list' ) );
		$this->assertSame( array(), $this->requests->filter_resource_hints( null ) );
	}

	/**
	 * The emoji TinyMCE plugin is removed and the rest are left alone.
	 *
	 * @return void
	 */
	public function test_the_emoji_tinymce_plugin_is_removed(): void {
		$this->assertSame(
			array( 'charmap', 'lists' ),
			$this->requests->remove_emoji_tinymce_plugin( array( 'charmap', 'wpemoji', 'lists' ) )
		);

		$this->assertSame( array(), $this->requests->remove_emoji_tinymce_plugin( 'nonsense' ) );
	}
}
