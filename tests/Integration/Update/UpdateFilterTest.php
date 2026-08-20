<?php
/**
 * Integration tests: a fake manifest, driven through WordPress core's own update path.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Update;

use DP\Core\Update\Log;
use DP\Core\Update\ManifestSource;
use DP\Core\Update\UpdateClient;
use DP\Core\Update\Verifier;
use stdClass;
use WP_UnitTestCase;

/**
 * Everything here goes through `wp_update_themes()` and `wp_update_plugins()`.
 *
 * Calling `apply_filters( 'update_themes_updates.dpaternina.com', … )` directly
 * would be easier and would prove less: it would not catch a hook attached
 * under the wrong name, would not exercise core's own version comparison, and
 * would not show what core actually stores in the transient — which is the
 * thing the upgrader later reads. So each test drives the real core function
 * and then asserts on `get_site_transient()`.
 */
final class UpdateFilterTest extends WP_UnitTestCase {

	use SignedManifest;

	/**
	 * Where our theme's manifest lives.
	 */
	private const THEME_MANIFEST = 'https://updates.dpaternina.com/theme.json';

	/**
	 * Where our plugin's manifest lives.
	 */
	private const PLUGIN_MANIFEST = 'https://updates.dpaternina.com/core.json';

	/**
	 * Stand up a keypair, an HTTP harness and a registered client.
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
	 * This is the assertion a one-line wiring mistake fails on. Everything else
	 * in this file would still pass if `register()` hooked nothing, because core
	 * would simply never call us and no update would ever be offered — which
	 * looks identical to "there is no update".
	 *
	 * The callbacks are asserted *by name*. That is the guarantee, not an
	 * incidental detail: a closure's hook id is `spl_object_id()`, so closures
	 * cannot be removed by anyone but the object that added them, cannot be
	 * de-duplicated across two registrations, and cannot be unhooked by a site
	 * owner. Reverting these to `$this->method( ... )` must fail here.
	 *
	 * @return void
	 */
	public function test_registration_attaches_the_filters_core_will_call(): void {
		$this->assertFalse(
			has_filter( 'update_themes_updates.dpaternina.com' ),
			'The harness detached whatever the plugin registered at boot.'
		);

		$first = $this->register_client();

		$expected = array(
			'update_themes_updates.dpaternina.com'  => 'on_theme_update',
			'update_plugins_updates.dpaternina.com' => 'on_plugin_update',
			'auto_update_theme'                     => 'on_auto_update',
			'auto_update_plugin'                    => 'on_auto_update',
		);

		foreach ( $expected as $hook => $method ) {
			$this->assertSame(
				UpdateClient::PRIORITY,
				has_filter( $hook, array( UpdateClient::class, $method ) ),
				$hook . ' is answered by a callback that can be named, and therefore removed.'
			);
		}

		$this->assertSame( $first, UpdateClient::register(), 'Registering twice does not hook twice.' );

		UpdateClient::reset();

		foreach ( $expected as $hook => $method ) {
			$this->assertFalse( has_filter( $hook, array( UpdateClient::class, $method ) ), $hook . ' is detached.' );
		}
	}

	/**
	 * Registering with a source replaces the client instead of stacking beside it.
	 *
	 * This is the regression test for the bug that shipped: `register()` used to
	 * return early when an instance already existed, silently discarding the
	 * source it was handed, and it attached bound closures that a later `reset()`
	 * could not detach. Between them, a second registration left *two* clients on
	 * one filter. The first to run would fetch the manifest, fail to verify it
	 * against the wrong key, and negative-cache the failure; the second would then
	 * read that negative cache and stay silent. Both an update offer and the
	 * refusal that should have explained its absence disappeared.
	 *
	 * So: register a client that can verify nothing, then register the real one,
	 * and require that the first is gone rather than merely outvoted.
	 *
	 * @return void
	 */
	public function test_registering_again_replaces_the_client_rather_than_stacking(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve( self::THEME_MANIFEST, $this->envelope( $this->theme_payload( $next ) ) );

		// A client with no trust anchor — the shipped default, which refuses everything.
		UpdateClient::register( new ManifestSource( new Verifier( '' ), new Log() ) );

		// Now the one that holds the key this manifest was signed with.
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayHasKey( 'dpaternina', $transient->response, 'The injected source is the one in effect.' );
		$this->assertSame( $next, $transient->response['dpaternina']['new_version'] );
		$this->assertSame(
			array(),
			$this->refusals,
			'The replaced client must not still be running: any refusal here means two clients saw this manifest.'
		);
	}

	/**
	 * A verified manifest with a newer version produces a theme update offer.
	 *
	 * @return void
	 */
	public function test_a_good_signature_offers_a_theme_update(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve( self::THEME_MANIFEST, $this->envelope( $this->theme_payload( $next ) ) );
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayHasKey( 'dpaternina', $transient->response, 'The theme is offered an update.' );

		$offer = $transient->response['dpaternina'];

		// Themes are stored as arrays; plugins as objects. Core's asymmetry, not ours.
		$this->assertIsArray( $offer );
		$this->assertSame( $next, $offer['new_version'] );
		$this->assertSame(
			'https://github.com/dpaternina/dp-site/releases/download/theme-v' . $next . '/dpaternina-' . $next . '.zip',
			$offer['package']
		);
		$this->assertSame(
			'dpaternina',
			$offer['theme'],
			'WP_Automatic_Updater reads $item->theme; core never fills it in for us.'
		);
		$this->assertSame( 'https://updates.dpaternina.com/theme', $offer['id'], 'Core sets id from the Update URI header.' );
		$this->assertSame( array(), $this->refusals );
	}

	/**
	 * A verified manifest with a newer version produces a plugin update offer.
	 *
	 * @return void
	 */
	public function test_a_good_signature_offers_a_plugin_update(): void {
		$next = $this->newer_than( $this->installed_plugin_version() );

		$this->serve( self::PLUGIN_MANIFEST, $this->envelope( $this->plugin_payload( $next ) ) );
		$this->register_client();

		$transient = $this->refresh_plugin_updates();

		$this->assertArrayHasKey( UpdateClient::PLUGIN_FILE, $transient->response );

		$offer = $transient->response[ UpdateClient::PLUGIN_FILE ];

		// Plugins are stored as objects; themes as arrays. Core's asymmetry, not ours.
		$this->assertInstanceOf( stdClass::class, $offer );
		$this->assertSame( $next, $offer->new_version );
		$this->assertSame( UpdateClient::PLUGIN_FILE, $offer->plugin, 'Core forces $update->plugin for plugins.' );
		$this->assertSame( 'dp-core', $offer->slug );
		$this->assertStringEndsWith( '/dp-core-' . $next . '.zip', $offer->package );
		$this->assertSame( array(), $this->refusals );
	}

	/**
	 * A manifest signed with the wrong key offers nothing, and says why.
	 *
	 * @return void
	 */
	public function test_a_bad_signature_offers_nothing_and_logs(): void {
		$next     = $this->newer_than( $this->installed_theme_version() );
		$impostor = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );

		$this->serve(
			self::THEME_MANIFEST,
			$this->envelope( $this->theme_payload( $next ), $impostor )
		);
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response, 'Nothing is offered.' );
		$this->assertArrayNotHasKey( 'dpaternina', (array) $transient->no_update, 'Not even as a no-op entry.' );
		$this->assertContains( 'Update manifest signature does not verify.', $this->refusals );
	}

	/**
	 * A refusal reaches the PHP error log, not just the action.
	 *
	 * The action is what tests observe; `error_log()` is what an operator reads
	 * at three in the morning. Both have to work, so both are asserted.
	 *
	 * @return void
	 */
	public function test_a_refusal_reaches_the_php_error_log(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$this->markTestSkipped( 'Logging is deliberately silent unless WP_DEBUG is on.' );
		}

		$destination = tempnam( sys_get_temp_dir(), 'dp-update-log-' );

		$this->assertIsString( $destination );

		$previous = ini_get( 'error_log' );

		// phpcs:ignore WordPress.PHP.IniSet.Risky
		if ( false === ini_set( 'error_log', $destination ) ) {
			$this->markTestSkipped( 'error_log cannot be redirected in this SAPI.' );
		}

		try {
			$impostor = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );

			$this->serve(
				self::THEME_MANIFEST,
				$this->envelope( $this->theme_payload( '99.0.0' ), $impostor )
			);
			$this->register_client();
			$this->refresh_theme_updates();

			// The file is a temporary PHP error log this test created; there is
			// no remote URL and no WP_Filesystem context here.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$written = file_get_contents( $destination );
		} finally {
			// phpcs:ignore WordPress.PHP.IniSet.Risky
			ini_set( 'error_log', false === $previous ? '' : $previous );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $destination );
		}

		$this->assertIsString( $written );
		$this->assertStringContainsString( '[dp-core/update]', $written );
		$this->assertStringContainsString( 'signature does not verify', $written );
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
		$this->serve( self::THEME_MANIFEST, $this->envelope( $this->theme_payload( '0.0.1' ) ) );
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
	 * An unreachable manifest host degrades quietly: no offer, no fatal, no noise.
	 *
	 * @return void
	 */
	public function test_an_unreachable_host_fails_soft(): void {
		$this->serve_failure( self::THEME_MANIFEST );
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertIsInt( $transient->last_checked, 'Core still completed its check.' );
		$this->assertContains( 'Update manifest could not be fetched.', $this->refusals );

		// And the failure is remembered, so a down host is not re-dialled on every admin page load.
		$cached = get_site_transient( ManifestSource::TRANSIENT_PREFIX . 'theme' );

		$this->assertIsArray( $cached );
		$this->assertNull( $cached['body'] );
	}

	/**
	 * A non-200 from the manifest host is a failure, not an empty manifest.
	 *
	 * @return void
	 */
	public function test_a_502_from_the_manifest_host_offers_nothing(): void {
		$this->serve( self::THEME_MANIFEST, '<!doctype html><title>502 Bad Gateway</title>', 502 );
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertContains( 'Update manifest request returned an unexpected status.', $this->refusals );
	}

	/**
	 * A correctly signed manifest for something else is not applied to us.
	 *
	 * @return void
	 */
	public function test_a_manifest_for_another_package_is_refused(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve(
			self::THEME_MANIFEST,
			$this->envelope( $this->theme_payload( $next, array( 'slug' => 'twentytwentyfive' ) ) )
		);
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertArrayNotHasKey( 'twentytwentyfive', $transient->response, 'And certainly not applied to someone else.' );
		$this->assertContains(
			'Verified manifest describes a different package than the one being checked.',
			$this->refusals
		);
	}

	/**
	 * A signed manifest cannot point the upgrader at an arbitrary origin.
	 *
	 * @return void
	 */
	public function test_a_package_url_on_a_foreign_host_is_refused(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve(
			self::THEME_MANIFEST,
			$this->envelope(
				$this->theme_payload( $next, array( 'package' => 'https://evil.example/dpaternina.zip' ) )
			)
		);
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertContains( 'Manifest package URL points at an unexpected host.', $this->refusals );
	}

	/**
	 * The cached envelope is re-verified on read, so a writable cache is not a way in.
	 *
	 * @return void
	 */
	public function test_a_poisoned_cache_is_caught_on_read(): void {
		$next     = $this->newer_than( $this->installed_theme_version() );
		$impostor = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );

		// Nothing is served: if the client trusted its cache it would never notice.
		set_site_transient(
			ManifestSource::TRANSIENT_PREFIX . 'theme',
			array( 'body' => $this->envelope( $this->theme_payload( $next ), $impostor ) ),
			ManifestSource::SUCCESS_TTL
		);
		$this->register_client();

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertContains( 'Update manifest signature does not verify.', $this->refusals );

		$cached = get_site_transient( ManifestSource::TRANSIENT_PREFIX . 'theme' );

		$this->assertIsArray( $cached );
		$this->assertNull( $cached['body'], 'The poisoned entry is replaced, not left to be re-read.' );
	}

	/**
	 * With no key compiled in — the shipped default — nothing is ever offered.
	 *
	 * @return void
	 */
	public function test_the_client_offers_nothing_without_a_public_key(): void {
		$next = $this->newer_than( $this->installed_theme_version() );

		$this->serve( self::THEME_MANIFEST, $this->envelope( $this->theme_payload( $next ) ) );

		UpdateClient::register(
			new ManifestSource(
				new \DP\Core\Update\Verifier( '' ),
				new \DP\Core\Update\Log()
			)
		);

		$transient = $this->refresh_theme_updates();

		$this->assertArrayNotHasKey( 'dpaternina', $transient->response );
		$this->assertContains( 'No usable update signing key is compiled into this build.', $this->refusals );
	}
}
