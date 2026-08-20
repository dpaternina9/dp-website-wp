<?php
/**
 * Unit tests for manifest parsing and version comparison.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Update;

use DP\Core\Update\Manifest;
use DP\Core\Update\ManifestError;
use DP\Core\Update\PackageType;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Exercises Manifest without WordPress and without a signature.
 *
 * Manifest is the last line of defence between a payload that verified and the
 * WordPress upgrader. Everything it refuses here is something a signed-but-wrong
 * manifest could otherwise talk core into doing.
 */
final class ManifestTest extends TestCase {

	/**
	 * A payload with every field populated and plausible.
	 *
	 * @param array<string, mixed> $overrides Fields to replace or add.
	 * @return array<string, mixed>
	 */
	private function payload( array $overrides = array() ): array {
		return array_merge(
			array(
				'type'         => 'theme',
				'slug'         => 'dpaternina',
				'version'      => '1.2.3',
				'package'      => 'https://github.com/dp/site/releases/download/theme-v1.2.3/dpaternina-1.2.3.zip',
				'url'          => 'https://github.com/dp/site/releases/tag/theme-v1.2.3',
				'requires'     => '6.6',
				'requires_php' => '8.4',
				'tested'       => '7.1',
			),
			$overrides
		);
	}

	/**
	 * A well-formed payload parses into the value object.
	 *
	 * @return void
	 */
	public function test_a_complete_payload_parses(): void {
		$manifest = Manifest::from_array( $this->payload() );

		$this->assertSame( PackageType::Theme, $manifest->type );
		$this->assertSame( 'dpaternina', $manifest->slug );
		$this->assertSame( '1.2.3', $manifest->version );
		$this->assertSame( '6.6', $manifest->requires );
		$this->assertSame( '8.4', $manifest->requires_php );
		$this->assertSame( '7.1', $manifest->tested );
	}

	/**
	 * Optional descriptive fields may be absent.
	 *
	 * @return void
	 */
	public function test_optional_fields_default_to_empty_strings(): void {
		$manifest = Manifest::from_array(
			array(
				'type'    => 'plugin',
				'slug'    => 'dp-core',
				'version' => '0.1.0',
				'package' => 'https://github.com/dp/site/releases/download/core-v0.1.0/dp-core-0.1.0.zip',
			)
		);

		$this->assertSame( '', $manifest->url );
		$this->assertSame( '', $manifest->requires );
		$this->assertSame( '', $manifest->tested );
	}

	/**
	 * Anything that is not a JSON object is refused outright.
	 *
	 * @return void
	 */
	public function test_a_non_object_payload_is_refused(): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( 'not-an-object' );
	}

	/**
	 * A package type we do not publish is refused.
	 *
	 * @return void
	 */
	public function test_an_unknown_type_is_refused(): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( $this->payload( array( 'type' => 'mu-plugin' ) ) );
	}

	/**
	 * Versions that `version_compare()` would silently reinterpret are refused.
	 *
	 * @dataProvider provide_unusable_versions
	 *
	 * @param mixed $version The version string under test.
	 * @return void
	 */
	public function test_unusable_versions_are_refused( mixed $version ): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( $this->payload( array( 'version' => $version ) ) );
	}

	/**
	 * Version strings a manifest may not carry.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provide_unusable_versions(): array {
		return array(
			'empty'         => array( '' ),
			'two segments'  => array( '1.2' ),
			'four segments' => array( '1.2.3.4' ),
			'leading v'     => array( 'v1.2.3' ),
			'words'         => array( 'latest' ),
			'not a string'  => array( 123 ),
			'trailing junk' => array( '1.2.3 (build 7)' ),
		);
	}

	/**
	 * Pre-release and build metadata are legitimate semver and are accepted.
	 *
	 * @return void
	 */
	public function test_a_prerelease_version_is_accepted(): void {
		$manifest = Manifest::from_array( $this->payload( array( 'version' => '1.2.3-rc.1' ) ) );

		$this->assertSame( '1.2.3-rc.1', $manifest->version );
	}

	/**
	 * A slug must look like a directory name, because that is what it becomes.
	 *
	 * @dataProvider provide_unusable_slugs
	 *
	 * @param mixed $slug The slug under test.
	 * @return void
	 */
	public function test_unusable_slugs_are_refused( mixed $slug ): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( $this->payload( array( 'slug' => $slug ) ) );
	}

	/**
	 * Slugs a manifest may not carry.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provide_unusable_slugs(): array {
		return array(
			'empty'        => array( '' ),
			'traversal'    => array( '../evil' ),
			'slash'        => array( 'dp/core' ),
			'uppercase'    => array( 'DpCore' ),
			'single char'  => array( 'd' ),
			'not a string' => array( array( 'dp-core' ) ),
		);
	}

	/**
	 * The upgrader may only be pointed at HTTPS.
	 *
	 * @return void
	 */
	public function test_a_plain_http_package_is_refused(): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( $this->payload( array( 'package' => 'http://github.com/dp/site/x.zip' ) ) );
	}

	/**
	 * A package on an origin we do not publish from is refused.
	 *
	 * @return void
	 */
	public function test_a_package_on_an_unexpected_host_is_refused(): void {
		$this->expectException( ManifestError::class );

		Manifest::from_array( $this->payload( array( 'package' => 'https://evil.example/dpaternina.zip' ) ) );
	}

	/**
	 * The caller decides which hosts are acceptable.
	 *
	 * @return void
	 */
	public function test_the_allowed_package_hosts_are_configurable(): void {
		$manifest = Manifest::from_array(
			$this->payload( array( 'package' => 'https://releases.example/dpaternina.zip' ) ),
			array( 'releases.example' )
		);

		$this->assertSame( 'https://releases.example/dpaternina.zip', $manifest->package );
	}

	/**
	 * Version comparison is `version_compare()`'s, and strictly greater-than.
	 *
	 * @dataProvider provide_version_comparisons
	 *
	 * @param string $offered   Version in the manifest.
	 * @param string $installed Version on the site.
	 * @param bool   $expected  Whether the manifest is newer.
	 * @return void
	 */
	public function test_version_comparison( string $offered, string $installed, bool $expected ): void {
		$manifest = Manifest::from_array( $this->payload( array( 'version' => $offered ) ) );

		$this->assertSame( $expected, $manifest->is_newer_than( $installed ) );
	}

	/**
	 * Comparisons that must hold, including the ones a naive string compare breaks on.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public static function provide_version_comparisons(): array {
		return array(
			'patch bump'               => array( '1.2.4', '1.2.3', true ),
			'minor bump'               => array( '1.3.0', '1.2.9', true ),
			'major bump'               => array( '2.0.0', '1.99.99', true ),
			'identical'                => array( '1.2.3', '1.2.3', false ),
			'older'                    => array( '1.2.2', '1.2.3', false ),
			'ten beats nine'           => array( '1.10.0', '1.9.0', true ),
			'release beats its rc'     => array( '1.2.3', '1.2.3-rc.1', true ),
			'rc does not beat release' => array( '1.2.3-rc.1', '1.2.3', false ),
			'zero-version site'        => array( '0.1.0', '0.0.1', true ),
		);
	}

	/**
	 * A theme offer carries `theme`; core never fills that in for us.
	 *
	 * @return void
	 */
	public function test_a_theme_offer_carries_the_stylesheet(): void {
		$offer = Manifest::from_array( $this->payload() )
			->to_offer( 'dpaternina', 'https://updates.dpaternina.com/theme' );

		$this->assertSame( 'dpaternina', $offer['theme'] );
		$this->assertArrayNotHasKey( 'plugin', $offer );
		$this->assertSame( '1.2.3', $offer['version'] );
		$this->assertSame( '1.2.3', $offer['new_version'] );
		$this->assertSame( 'https://updates.dpaternina.com/theme', $offer['id'] );
	}

	/**
	 * A plugin offer carries `plugin`, keyed by the plugin file.
	 *
	 * @return void
	 */
	public function test_a_plugin_offer_carries_the_plugin_file(): void {
		$offer = Manifest::from_array(
			$this->payload(
				array(
					'type'    => 'plugin',
					'slug'    => 'dp-core',
					'package' => 'https://github.com/dp/site/releases/download/core-v1.2.3/dp-core-1.2.3.zip',
				)
			)
		)->to_offer( 'dp-core/dp-core.php', 'https://updates.dpaternina.com/core' );

		$this->assertSame( 'dp-core/dp-core.php', $offer['plugin'] );
		$this->assertArrayNotHasKey( 'theme', $offer );
		$this->assertSame( 'dp-core', $offer['slug'] );
	}

	/**
	 * Absent optional fields are omitted rather than sent as empty strings.
	 *
	 * An empty `requires_php` would make `WP_Automatic_Updater` compare
	 * PHP_VERSION against '' and refuse the update.
	 *
	 * @return void
	 */
	public function test_empty_optional_fields_are_omitted_from_the_offer(): void {
		$offer = Manifest::from_array(
			array(
				'type'    => 'theme',
				'slug'    => 'dpaternina',
				'version' => '1.2.3',
				'package' => 'https://github.com/dp/site/x.zip',
			)
		)->to_offer( 'dpaternina', 'https://updates.dpaternina.com/theme' );

		$this->assertArrayNotHasKey( 'requires_php', $offer );
		$this->assertArrayNotHasKey( 'requires', $offer );
		$this->assertArrayNotHasKey( 'tested', $offer );
	}
}
