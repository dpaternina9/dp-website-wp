<?php
/**
 * The one place that knows this site's update-service coordinates.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

use FanxieLab\WpUpdates\Package;
use FanxieLab\WpUpdates\PackageType;
use FanxieLab\WpUpdates\UpdateClient;
use FanxieLab\WpUpdates\UpdateConfig;

/**
 * Builds the `fanxielab/wp-update-client` configuration for our two packages.
 *
 * The update mechanism itself — core's `update_themes_{$host}` /
 * `update_plugins_{$host}` filters, manifest fetch and caching, Ed25519
 * verification, and the `auto_update_theme` / `auto_update_plugin` opt-in for
 * owned packages — lives in the library (`FanxieLab\WpUpdates`), which was
 * extracted from this repo's Phase 2 module. What stays here is only what is
 * ours: the host, the tenant namespace, the package list, and the compiled-in
 * public key. See docs/adr/0015-adopt-the-wp-update-client-library.md.
 *
 * The values below must agree with the `Update URI` headers in
 * `plugins/dp-core/dp-core.php` and `themes/dpaternina/style.css` — core
 * derives its filter names from the header's host, and the library resolves
 * ownership by the full `{host}/{namespace}/{type}-{slug}` path. A unit test
 * (`tests/Unit/Update/UpdateRegistrationTest.php`) holds the two files to
 * exactly `UpdateConfig::update_uri()` so they cannot drift.
 */
final class UpdateRegistration {

	/**
	 * The update host releases are published behind.
	 */
	public const HOST = 'wp-updates.fanxie.cloud';

	/**
	 * Our tenant namespace on that host.
	 */
	public const TENANT = 'dpaternina';

	/**
	 * Prefix for the refusal action (`dpaternina_update_refused`), the
	 * manifest transients (`dpaternina_upd_{type}_{slug}`) and the
	 * `wp-config.php` key-override constant (`DPATERNINA_UPDATE_PUBLIC_KEY`).
	 */
	public const HOOK_PREFIX = 'dpaternina';

	/**
	 * This class only carries constants and static factories.
	 */
	private function __construct() {}

	/**
	 * Register the update client for our packages. Call on `init`.
	 *
	 * @return void
	 */
	public static function register(): void {
		UpdateClient::register( self::config() );
	}

	/**
	 * The library configuration for this build.
	 *
	 * @param string|null $public_key Override for tests only. Production always
	 *                                compiles the key in via UpdateKey.
	 * @return UpdateConfig
	 */
	public static function config( ?string $public_key = null ): UpdateConfig {
		return new UpdateConfig(
			host: self::HOST,
			namespace: self::TENANT,
			public_key: $public_key ?? UpdateKey::COMPILED,
			hook_prefix: self::HOOK_PREFIX,
			packages: array(
				new Package( PackageType::Plugin, 'dp-core', 'dp-core/dp-core.php' ),
				new Package( PackageType::Theme, 'dpaternina', 'dpaternina' ),
			),
		);
	}
}
