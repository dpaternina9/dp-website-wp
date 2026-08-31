<?php
/**
 * Unit tests for what the curtain lets through, and what it answers with.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Maintenance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Core\Maintenance\Curtain;
use DP\Core\Maintenance\Gate;
use DP\Core\Maintenance\Screen;
use DP\Core\Maintenance\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The REST pass-through and the header set, away from a request.
 *
 * `rest_authentication_errors` is a chain — null, true or a `WP_Error`, each
 * hook receiving the last one's answer — and there are two ways to get it
 * wrong. One is to refuse a request that should have been let by; the other is
 * to relabel a refusal core has already made. **The first half is here** because
 * it is pure decision and needs nothing of WordPress. The second half asserts
 * against `WP_Error`, which is a class the unit harness does not have and a
 * class worth nothing as a fake, so it lives in
 * `DP\Tests\Integration\Maintenance\CurtainTest` against the real one.
 *
 * The headers are here for the opposite reason: `header()` is a PHP internal, so
 * neither Brain Monkey nor a CLI-run integration suite can watch one being sent.
 * `Curtain::headers()` exists so the set is a value that can be asserted rather
 * than a side effect that has to be taken on trust.
 */
final class CurtainTest extends TestCase {

	/**
	 * Option name to stored value.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Capabilities the pretend current user holds.
	 *
	 * @var list<string>
	 */
	private array $capabilities = array();

	/**
	 * Start Brain Monkey and stand the gate's world up.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->options      = array();
		$this->capabilities = array();

		Functions\when( 'get_option' )->alias( $this->option( ... ) );
		Functions\when( 'current_user_can' )->alias( $this->can( ... ) );
		Functions\when( 'wp_doing_cron' )->justReturn( false );

		/*
		 * Nothing in this file reaches the refusal, so nothing here is ever
		 * handed an error to recognise.
		 */
		Functions\when( 'is_wp_error' )->justReturn( false );
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
	 * Stand in for `get_option()`.
	 *
	 * @param string $name The option name.
	 * @return mixed The stored value, or false for an option nobody has set.
	 */
	public function option( string $name ): mixed {
		return $this->options[ $name ] ?? false;
	}

	/**
	 * Stand in for `current_user_can()`.
	 *
	 * @param string $capability The capability being asked about.
	 * @return bool
	 */
	public function can( string $capability ): bool {
		return in_array( $capability, $this->capabilities, true );
	}

	/**
	 * With the switch off the filter is a pass-through.
	 *
	 * @return void
	 */
	public function test_rest_is_untouched_while_the_switch_is_off(): void {
		$curtain = $this->curtain();

		$this->assertNull( $curtain->refuse_rest( null ) );
		$this->assertTrue( $curtain->refuse_rest( true ) );
	}

	/**
	 * On, and somebody who may see the site: the API answers as usual.
	 *
	 * This is the case that keeps the block editor working, which is the reason
	 * the REST API is open on this site at all.
	 *
	 * @return void
	 */
	public function test_an_authenticated_editor_keeps_the_api(): void {
		$this->options[ Settings::ENABLED ] = '1';
		$this->capabilities                 = array( 'edit_posts' );

		$this->assertNull( $this->curtain()->refuse_rest( null ) );
		$this->assertTrue( $this->curtain()->refuse_rest( true ) );
	}

	/**
	 * Cron is never refused on the REST path either.
	 *
	 * @return void
	 */
	public function test_cron_keeps_the_api(): void {
		$this->options[ Settings::ENABLED ] = '1';

		Functions\when( 'wp_doing_cron' )->justReturn( true );

		$this->assertNull( $this->curtain()->refuse_rest( null ) );
	}

	/**
	 * The headers say how long and not to index.
	 *
	 * `Retry-After` because a 503 without one is an outage of unknown length, and
	 * `X-Robots-Tag` because a header covers the feeds and files served under the
	 * same curtain, which the document's own `<meta>` cannot. That the status
	 * itself is 503 rather than 200 is asserted where it is actually sent, in
	 * `DP\Tests\Integration\Maintenance\CurtainTest`.
	 *
	 * @return void
	 */
	public function test_the_header_set(): void {
		$this->assertSame(
			array(
				'Retry-After'  => '3600',
				'X-Robots-Tag' => 'noindex',
			),
			$this->curtain()->headers()
		);
	}

	/**
	 * A curtain with a screen this file never renders.
	 *
	 * @return Curtain
	 */
	private function curtain(): Curtain {
		return new Curtain( new Gate(), new Screen( '/plugins/dp-core/dp-core.php', '0.1.0' ) );
	}
}
