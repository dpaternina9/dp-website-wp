<?php
/**
 * Unit tests: our update registration and the file headers cannot drift apart.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Update;

use DP\Core\Update\UpdateRegistration;
use FanxieLab\WpUpdates\Package;
use FanxieLab\WpUpdates\PackageType;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The library resolves ownership by the full Update URI path, and WordPress
 * core derives its filter names from the header's host — so the headers in
 * `dp-core.php` and `style.css` must equal `UpdateConfig::update_uri()` for
 * their package, byte for byte. The mechanism itself is tested in the
 * `fanxielab/wp-update-client` repository; what only this repo can get wrong
 * is its own coordinates, which is what these tests pin down.
 */
final class UpdateRegistrationTest extends TestCase {

	/**
	 * The `Update URI` header of a file, or '' when absent.
	 *
	 * @param string $relative Path relative to the repository root.
	 * @return string
	 */
	private function update_uri_header( string $relative ): string {
		$path = dirname( __DIR__, 3 ) . '/' . $relative;

		$this->assertFileExists( $path );

		// A local repo file read by a unit test that runs without WordPress.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );

		$this->assertIsString( $contents );

		if ( 1 !== preg_match( '/^.*Update URI:\s*(\S+)\s*$/m', $contents, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * The configured package of one type. There must be exactly one of each.
	 *
	 * @param PackageType $type Theme or plugin.
	 * @return Package
	 */
	private function package_of_type( PackageType $type ): Package {
		$found = array();

		foreach ( UpdateRegistration::config()->packages as $package ) {
			if ( $package->type === $type ) {
				$found[] = $package;
			}
		}

		$this->assertCount( 1, $found, 'This repo publishes exactly one ' . $type->value . '.' );

		return $found[0];
	}

	/**
	 * The plugin header names exactly the Update URI the config derives.
	 *
	 * @return void
	 */
	public function test_the_plugin_header_matches_the_config(): void {
		$config  = UpdateRegistration::config();
		$package = $this->package_of_type( PackageType::Plugin );

		$this->assertSame( 'dp-core', $package->slug );
		$this->assertSame( 'dp-core/dp-core.php', $package->identity );
		$this->assertSame(
			$config->update_uri( $package ),
			$this->update_uri_header( 'plugins/dp-core/dp-core.php' ),
			'dp-core.php must carry the exact Update URI the registration derives.'
		);
	}

	/**
	 * The theme header names exactly the Update URI the config derives.
	 *
	 * @return void
	 */
	public function test_the_theme_header_matches_the_config(): void {
		$config  = UpdateRegistration::config();
		$package = $this->package_of_type( PackageType::Theme );

		$this->assertSame( 'dpaternina', $package->slug );
		$this->assertSame( 'dpaternina', $package->identity );
		$this->assertSame(
			$config->update_uri( $package ),
			$this->update_uri_header( 'themes/dpaternina/style.css' ),
			'style.css must carry the exact Update URI the registration derives.'
		);
	}

	/**
	 * The decided coordinates, spelled out so a change here is a decision.
	 *
	 * @return void
	 */
	public function test_the_decided_uris_are_the_ones_registered(): void {
		$config = UpdateRegistration::config();

		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/plugin-dp-core',
			$config->update_uri( $this->package_of_type( PackageType::Plugin ) )
		);
		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/theme-dpaternina',
			$config->update_uri( $this->package_of_type( PackageType::Theme ) )
		);
	}

	/**
	 * The names the hook prefix derives are the ones the docs promise.
	 *
	 * `UpdateKey`'s docblock, the ADR and the troubleshooting notes all name
	 * these; if the prefix changes they must change with it.
	 *
	 * @return void
	 */
	public function test_the_hook_prefix_derives_the_documented_names(): void {
		$config = UpdateRegistration::config();

		$this->assertSame( 'DPATERNINA_UPDATE_PUBLIC_KEY', $config->override_constant() );
		$this->assertSame( 'dpaternina_update_refused', $config->refused_action() );
	}

	/**
	 * The shipped build compiles in whatever UpdateKey holds — and nothing else.
	 *
	 * @return void
	 */
	public function test_the_config_uses_the_compiled_key_by_default(): void {
		$this->assertSame( \DP\Core\Update\UpdateKey::COMPILED, UpdateRegistration::config()->public_key );
		$this->assertSame( 'test-key', UpdateRegistration::config( 'test-key' )->public_key );
	}
}
