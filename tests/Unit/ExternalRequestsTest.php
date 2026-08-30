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
	 * The analytics host is dropped by default, and allowed when David says so.
	 *
	 * `docs/plan.md` Phase 8: dropping the Rybbit plugin's `preconnect` "should
	 * be a decision, not a discovery". This is the decision, both halves of it —
	 * refused unless `dp_resource_hint_hosts` names the host, and honoured the
	 * moment it does, with no edit to the theme.
	 *
	 * @return void
	 */
	public function test_a_named_host_is_allowed_through(): void {
		$hint = array(
			'href'        => 'https://analytics.example',
			'crossorigin' => true,
		);

		$this->assertSame(
			array(),
			$this->requests->filter_resource_hints( array( $hint ) ),
			'Nothing but the site itself is advertised until somebody asks for it.'
		);

		Monkey\Filters\expectApplied( 'dp_resource_hint_hosts' )
			->andReturnUsing(
				static fn ( array $hosts ): array => array( ...$hosts, 'analytics.example' )
			);

		$this->assertSame(
			array( $hint ),
			$this->requests->filter_resource_hints( array( $hint ) ),
			'The filter is the escape hatch; naming a host is the whole of it.'
		);
	}

	/**
	 * A filter that answers with the wrong shape narrows rather than widens.
	 *
	 * @return void
	 */
	public function test_a_malformed_allowlist_never_widens_it(): void {
		Monkey\Filters\expectApplied( 'dp_resource_hint_hosts' )
			->andReturn( array( 'analytics.example', '', 42, null ) );

		$this->assertSame(
			array( 'analytics.example' ),
			$this->requests->allowed_hosts(),
			'Everything that is not a non-empty string is discarded, the site host included.'
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
