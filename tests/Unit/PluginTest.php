<?php
/**
 * Unit tests for the dP Core container.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use DP\Core\Plugin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Exercises Plugin without loading WordPress.
 *
 * Every assertion here depends on Brain Monkey actually intercepting the
 * WordPress functions the class calls. If the harness is not wired up, these
 * tests fatal on an undefined function rather than passing vacuously.
 */
final class PluginTest extends TestCase {

	/**
	 * Start Brain Monkey and clear any previously booted instance.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
		Plugin::reset();
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Plugin::reset();
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Booting attaches the registration callback to `init` exactly once.
	 *
	 * @return void
	 */
	public function test_boot_registers_on_init(): void {
		Actions\expectAdded( 'init' )->once();

		$plugin = Plugin::boot( '/srv/plugins/dp-core/dp-core.php', '0.1.0' );

		$this->assertSame( '/srv/plugins/dp-core/dp-core.php', $plugin->file() );
		$this->assertSame( '0.1.0', $plugin->version() );
	}

	/**
	 * Booting twice yields the same instance and hooks `init` only once.
	 *
	 * @return void
	 */
	public function test_boot_is_idempotent(): void {
		Actions\expectAdded( 'init' )->once();

		$first  = Plugin::boot( '/srv/plugins/dp-core/dp-core.php', '0.1.0' );
		$second = Plugin::boot( '/srv/plugins/dp-core/dp-core.php', '9.9.9' );

		$this->assertSame( $first, $second );
		$this->assertSame( '0.1.0', $second->version(), 'The first boot wins.' );
	}

	/**
	 * Paths and URLs are derived from WordPress, never hardcoded.
	 *
	 * @return void
	 */
	public function test_path_and_url_delegate_to_wordpress(): void {
		Actions\expectAdded( 'init' )->once();
		Functions\when( 'plugin_dir_path' )->justReturn( '/srv/plugins/dp-core/' );
		Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.test/wp-content/plugins/dp-core/' );

		$plugin = Plugin::boot( '/srv/plugins/dp-core/dp-core.php', '0.1.0' );

		$this->assertSame( '/srv/plugins/dp-core/build/index.asset.php', $plugin->path( 'build/index.asset.php' ) );
		$this->assertSame( '/srv/plugins/dp-core/build/index.asset.php', $plugin->path( '/build/index.asset.php' ) );
		$this->assertSame(
			'https://example.test/wp-content/plugins/dp-core/build/style.css',
			$plugin->url( 'build/style.css' )
		);
	}
}
