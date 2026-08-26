<?php
/**
 * Integration tests: our config, driven through WordPress core's own update path.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Update;

use DP\Core\Update\UpdateRegistration;
use FanxieLab\WpUpdates\UpdateClient;
use stdClass;
use WP_UnitTestCase;

/**
 * Everything here goes through `wp_update_themes()` and `wp_update_plugins()`.
 *
 * The library's own repository tests the mechanism — verification, caching,
 * refusal paths, multi-tenant dispatch. What only this repo can break is its
 * own wiring: the `Update URI` headers of the mounted theme and plugin, the
 * host the filters are derived from, and the namespace the manifests live
 * under. So each test drives the real core function against the real installed
 * packages and asserts on `get_site_transient()` — which is the thing the
 * upgrader later reads.
 */
final class UpdateFilterTest extends WP_UnitTestCase {

	use SignedManifest;

	/**
	 * Stand up a keypair, an HTTP harness and a clean slate.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->start_update_harness();
	}

	/**
	 * Put every filter and transient back.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->stop_update_harness();
		parent::tear_down();
	}

	/**
	 * The theme update transient, after core has rebuilt it.
	 *
	 * @return stdClass
	 */
	private function refresh_theme_updates(): stdClass {
		delete_site_transient( 'update_themes' );
		wp_update_themes();

		$transient = get_site_transient( 'update_themes' );

		$this->assertInstanceOf( stdClass::class, $transient, 'Core always writes the transient, even when it offers nothing.' );

		return $transient;
	}

	/**
	 * The plugin update transient, after core has rebuilt it.
	 *
	 * @return stdClass
	 */
	private function refresh_plugin_updates(): stdClass {
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$transient = get_site_transient( 'update_plugins' );

		$this->assertInstanceOf( stdClass::class, $transient, 'Core always writes the transient, even when it offers nothing.' );

		return $transient;
	}

	/**
	 * Registration attaches exactly the four filters core will look for.
	 *
	 * The hook names are spelled out because they are the wiring under test:
	 * core derives them from the host in our `Update URI` headers, so a header
	 * that drifted from `UpdateRegistration::HOST` would leave the client
	 * listening on a filter core never calls — an update that silently never
	 * arrives.
	 *
	 * @return void
	 */
	public function test_registration_attaches_the_filters_core_will_call(): void {
		$this->assertFalse(
			has_filter( 'update_themes_wp-updates.fanxie.cloud' ),
			'The harness detached whatever the plugin registered at boot.'
		);

		UpdateRegistration::register();

		$expected = array(
			'update_themes_wp-updates.fanxie.cloud'  => 'on_theme_update',
			'update_plugins_wp-updates.fanxie.cloud' => 'on_plugin_update',
			'auto_update_theme'                      => 'on_auto_update',
			'auto_update_plugin'                     => 'on_auto_update',
		);

		foreach ( $expected as $hook => $method ) {
			$this->assertSame(
				UpdateClient::PRIORITY,
				has_filter( $hook, array( UpdateClient::class, $method ) ),
				$hook . ' is answered by a callback that can be named, and therefore removed.'
			);
		}

		UpdateClient::reset();

		foreach ( $expected as $hook => $method ) {
			$this->assertFalse( has_filter( $hook, array( UpdateClient::class, $method ) ), $hook . ' is detached.' );
		}
	}

	/**
	 * A verified manifest with a newer version produces a theme update offer.
	 *
	 * @return void
	 */
	public function test_a_good_signature_offers_a_theme_update(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve( $this->manifest_url( 'theme' ), $this->envelope( $this->theme_payload( $next ) ) );
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayHasKey( 'dpaternina', $transient->response, 'The theme is offered an update.' );

		$offer = $transient->response['dpaternina'];

		// Themes are stored as arrays; plugins as objects. Core's asymmetry, not ours.
		$this->assertIsArray( $offer );
		$this->assertSame( $next, $offer['new_version'] );
		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/packages/theme-dpaternina-' . $next . '.zip',
			$offer['package'],
			'The package URL is pinned inside our namespace on the update host.'
		);
		$this->assertSame(
			'dpaternina',
			$offer['theme'],
			'WP_Automatic_Updater reads $item->theme; core never fills it in for us.'
		);
		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/theme-dpaternina',
			$offer['id'],
			'Core sets id from the Update URI header, so this pins the header itself.'
		);
		$this->assertSame( array(), $this->refusals );
	}

	/**
	 * A verified manifest with a newer version produces a plugin update offer.
	 *
	 * @return void
	 */
	public function test_a_good_signature_offers_a_plugin_update(): void {
		$next = $this->newer_than( $this->installed_plugin_version() );

		$this->serve( $this->manifest_url( 'plugin' ), $this->envelope( $this->plugin_payload( $next ) ) );
		$this->register_client();

		$transient = $this->refresh_plugin_updates();

		$this->assertArrayHasKey( 'dp-core/dp-core.php', $transient->response );

		$offer = $transient->response['dp-core/dp-core.php'];

		// Plugins are stored as objects; themes as arrays. Core's asymmetry, not ours.
		$this->assertInstanceOf( stdClass::class, $offer );
		$this->assertSame( $next, $offer->new_version );
		$this->assertSame( 'dp-core/dp-core.php', $offer->plugin, 'Core forces $update->plugin for plugins.' );
		$this->assertSame( 'dp-core', $offer->slug );
		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/packages/plugin-dp-core-' . $next . '.zip',
			$offer->package
		);
		$this->assertSame(
			'https://wp-updates.fanxie.cloud/dpaternina/plugin-dp-core',
			$offer->id,
			'Core sets id from the Update URI header, so this pins the header itself.'
		);
		$this->assertSame( array(), $this->refusals );
	}

	/**
	 * A manifest signed with the wrong key offers nothing, and says why.
	 *
	 * The refusal arrives on `dpaternina_update_refused` — the action name the
	 * library derives from our hook prefix, and the one the troubleshooting
	 * docs tell David to listen on.
	 *
	 * @return void
	 */
	public function test_a_bad_signature_offers_nothing_and_logs(): void {
		$next     = $this->newer_than( $this->installed_theme_version() );
		$impostor = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );

		$this->serve(
			$this->manifest_url( 'theme' ),
			$this->envelope( $this->theme_payload( $next ), $impostor )
		);
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response, 'Nothing is offered.' );
		$this->assertArrayNotHasKey( 'dpaternina', (array) $transient->no_update, 'Not even as a no-op entry.' );
		$this->assertContains( 'Update manifest signature does not verify.', $this->refusals );
	}

	/**
	 * A manifest older than what is installed produces no update offer.
	 *
	 * Core does this comparison itself and files the offer under `no_update`,
	 * which is what makes the "Auto-updates enabled" column render. So the
	 * assertion is not "we returned false" — it is "nothing is offered", which
	 * is the behaviour anybody actually cares about.
	 *
	 * @return void
	 */
	public function test_a_lower_version_is_never_offered(): void {
		$this->serve( $this->manifest_url( 'theme' ), $this->envelope( $this->theme_payload( '0.0.1' ) ) );
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response, 'No update is offered.' );
		$this->assertArrayHasKey(
			'dpaternina',
			(array) $transient->no_update,
			'Core files a non-newer offer under no_update, which is what powers the auto-update UI.'
		);
		$this->assertContains( 'Manifest is not newer than the installed version; no update will be offered.', $this->refusals );
	}

	/**
	 * With no key compiled in — the shipped default — nothing is offered, and it is logged.
	 *
	 * This registers the production config with an empty key and no injected
	 * source, so the library builds its real `ManifestSource` around an empty
	 * `Verifier` — exactly what a site running the repository build (where
	 * `UpdateKey::COMPILED` is still `''`) would do.
	 *
	 * @return void
	 */
	public function test_an_empty_compiled_key_refuses_and_logs(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve( $this->manifest_url( 'theme' ), $this->envelope( $this->theme_payload( $next ) ) );

		UpdateClient::register( UpdateRegistration::config( '' ) );

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertContains( 'No usable update signing key is compiled into this build.', $this->refusals );
	}
}
