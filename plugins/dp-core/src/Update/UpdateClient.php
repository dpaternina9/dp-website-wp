<?php
/**
 * The update client: what answers WordPress when it asks about our packages.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/**
 * Hooks core's host-scoped update filters and answers them, or refuses.
 *
 * Registration is one line, from `DP\Core\Plugin::register()`:
 *
 *     UpdateClient::register();
 *
 * `init` is early enough. Verified against WordPress 7.1: `wp_update_plugins()`
 * and `wp_update_themes()` are reached from `admin_init`, from the
 * `wp_update_plugins` / `wp_update_themes` cron events, and from the
 * `load-plugins.php` / `load-themes.php` screen hooks — all of which run after
 * `init` — and `WP_Automatic_Updater` runs later still, on `wp_version_check`.
 *
 * ## What core actually does with what we return (WordPress 7.1, wp-includes/update.php)
 *
 * - The filter is `apply_filters( "update_plugins_{$hostname}", false,
 *   $plugin_data, $plugin_file, $locales )` — **four** arguments, the fourth
 *   being installed locales for translation packages.
 * - A falsy return is skipped. A truthy return is cast with `(object)` and
 *   discarded unless it has a `version` property.
 * - Core then overwrites `id` and (plugins only) `plugin`, back-fills
 *   `new_version` from `version`, and **does the version comparison itself**:
 *   newer than installed goes to `$transient->response`, anything else to
 *   `$transient->no_update`. Returning an older version therefore cannot
 *   produce an update offer even if we tried.
 * - Themes are stored in the transient as arrays, plugins as objects.
 *
 * ## Fail closed
 *
 * Every path that cannot end in a verified signature returns `false` — including
 * when some other callback has already put a value in `$update`. We own this
 * hostname; passing a stranger's array through would let any plugin on the site
 * hand the upgrader a package URL under our name.
 *
 * ## Why the hook callbacks are static
 *
 * The filters are attached as `array( self::class, 'on_…' )`, not as closures
 * bound to an instance. WordPress 7.1's `_wp_filter_build_unique_id()` gives a
 * closure the id `(string) spl_object_id( $callback )` and a static array
 * callable the id `'Class::method'`. Only the second is stable, and three
 * things depend on that:
 *
 * 1. **Attaching is genuinely idempotent.** Two registrations write to the same
 *    key in `WP_Hook::$callbacks[10]`, so it is not possible to end up with two
 *    clients answering one filter — each with its own view of the manifest
 *    cache, each overwriting the other's answer.
 * 2. **`remove_filter()` works from anywhere**, including from a static context
 *    holding no instance. A closure can only be removed by the object that
 *    added it, so a callback whose owner has been forgotten is stuck in
 *    `$wp_filter` for good. `spl_object_id()` is also reused after garbage
 *    collection, so a forgotten closure's id can later collide with an
 *    unrelated object's.
 * 3. **A site owner can turn us off.** `remove_filter(
 *    'update_plugins_updates.dpaternina.com', array( UpdateClient::class,
 *    'on_plugin_update' ) )` is a line somebody can actually write. There is no
 *    way to write that line against a closure.
 */
final class UpdateClient {

	/**
	 * Our theme's stylesheet directory.
	 */
	public const THEME_STYLESHEET = 'dpaternina';

	/**
	 * Our plugin's file, relative to the plugins directory.
	 */
	public const PLUGIN_FILE = 'dp-core/dp-core.php';

	/**
	 * The priority every one of our filters is attached at.
	 */
	public const PRIORITY = 10;

	/**
	 * The registered client, or null before registration.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 *
	 * @param ManifestSource|null $source Injected in tests; built per call otherwise.
	 */
	public function __construct( private readonly ?ManifestSource $source = null ) {}

	/**
	 * Register the update client.
	 *
	 * Called twice with no argument, the first registration stands. Called
	 * *with* a source, that source is always installed, replacing whatever was
	 * registered before: the parameter is documented as an override and has to
	 * behave like one. Silently discarding it lets a test that injects a
	 * verifier end up exercising the shipped default instead, and pass.
	 *
	 * @param ManifestSource|null $source Optional override. Tests inject; production does not.
	 * @return self The registered client.
	 */
	public static function register( ?ManifestSource $source = null ): self {
		if ( null !== self::$instance && null === $source ) {
			return self::$instance;
		}

		$client         = new self( $source );
		self::$instance = $client;

		self::hooks();

		return $client;
	}

	/**
	 * Detach every filter and forget the registered client.
	 *
	 * Safe to call when nothing is registered, and — because the callbacks are
	 * identified by name rather than by object — it detaches them even when the
	 * instance that attached them is long gone. The WordPress test suite depends
	 * on exactly that: `WP_UnitTestCase::tear_down()` restores a snapshot of
	 * `$wp_filter` taken after the plugin booted, so every test after the first
	 * one in a run begins with our filters re-attached and no instance behind
	 * them.
	 *
	 * @return void
	 */
	public static function reset(): void {
		foreach ( self::hooked_filters() as $hook => $binding ) {
			remove_filter( $hook, $binding[0], self::PRIORITY );
		}

		self::$instance = null;
	}

	/**
	 * Attach every filter this client answers.
	 *
	 * @return void
	 */
	private static function hooks(): void {
		foreach ( self::hooked_filters() as $hook => $binding ) {
			add_filter( $hook, $binding[0], self::PRIORITY, $binding[1] );
		}
	}

	/**
	 * The filters this client owns: hook name to callback and accepted argument count.
	 *
	 * One list, read by both `hooks()` and `reset()`, so the two can never
	 * disagree about what is attached.
	 *
	 * @return array<string, array{callable, int}>
	 */
	private static function hooked_filters(): array {
		$host = Host::current();

		return array(
			PackageType::Theme->offer_filter( $host )  => array( array( self::class, 'on_theme_update' ), 3 ),
			PackageType::Plugin->offer_filter( $host ) => array( array( self::class, 'on_plugin_update' ), 3 ),
			PackageType::Theme->auto_update_filter()   => array( array( self::class, 'on_auto_update' ), 2 ),
			PackageType::Plugin->auto_update_filter()  => array( array( self::class, 'on_auto_update' ), 2 ),
		);
	}

	/**
	 * Hook callback for `update_themes_{$host}`. Delegates to the registered client.
	 *
	 * @param mixed                $update     Whatever a previous callback returned.
	 * @param array<string, mixed> $theme_data Theme headers.
	 * @param string               $stylesheet Stylesheet directory of the theme being checked.
	 * @return array<string, string>|false
	 */
	public static function on_theme_update( mixed $update, array $theme_data, string $stylesheet ): array|false {
		if ( null === self::$instance ) {
			return false;
		}

		return self::$instance->offer_theme_update( $update, $theme_data, $stylesheet );
	}

	/**
	 * Hook callback for `update_plugins_{$host}`. Delegates to the registered client.
	 *
	 * @param mixed                $update      Whatever a previous callback returned.
	 * @param array<string, mixed> $plugin_data Plugin headers.
	 * @param string               $plugin_file Plugin file, relative to the plugins directory.
	 * @return array<string, string>|false
	 */
	public static function on_plugin_update( mixed $update, array $plugin_data, string $plugin_file ): array|false {
		if ( null === self::$instance ) {
			return false;
		}

		return self::$instance->offer_plugin_update( $update, $plugin_data, $plugin_file );
	}

	/**
	 * Hook callback for `auto_update_theme` and `auto_update_plugin`.
	 *
	 * With nothing registered the incoming value is handed straight back, `null`
	 * included — core reads `null` as "nothing has hooked this filter at all".
	 *
	 * @param mixed $enabled Whether core would auto-update, or null if undecided.
	 * @param mixed $item    The update offer.
	 * @return mixed
	 */
	public static function on_auto_update( mixed $enabled, mixed $item ): mixed {
		if ( null === self::$instance ) {
			return $enabled;
		}

		return self::$instance->auto_update( $enabled, $item );
	}

	/**
	 * Answer `update_themes_{$host}`.
	 *
	 * @param mixed                $update     Whatever a previous callback returned. Deliberately not trusted.
	 * @param array<string, mixed> $theme_data Theme headers, as `wp_update_themes()` assembled them.
	 * @param string               $stylesheet Stylesheet directory of the theme being checked.
	 * @return array<string, string>|false
	 */
	public function offer_theme_update( mixed $update, array $theme_data, string $stylesheet ): array|false {
		// $update is ignored on purpose: see the "Fail closed" note on this class.
		return $this->offer( PackageType::Theme, $theme_data, $stylesheet, $stylesheet );
	}

	/**
	 * Answer `update_plugins_{$host}`.
	 *
	 * @param mixed                $update      Whatever a previous callback returned. Deliberately not trusted.
	 * @param array<string, mixed> $plugin_data Plugin headers, as `wp_update_plugins()` assembled them.
	 * @param string               $plugin_file Plugin file, relative to the plugins directory.
	 * @return array<string, string>|false
	 */
	public function offer_plugin_update( mixed $update, array $plugin_data, string $plugin_file ): array|false {
		// $update is ignored on purpose: see the "Fail closed" note on this class.
		return $this->offer( PackageType::Plugin, $plugin_data, $plugin_file, dirname( $plugin_file ) );
	}

	/**
	 * Answer `auto_update_theme` and `auto_update_plugin`.
	 *
	 * Scoped twice over: the offer must carry an `id` (which core sets from the
	 * `Update URI` header) on our host, *and* its identity must be one of the two
	 * packages this repository publishes. Anything else is passed through
	 * untouched — including `null`, which core uses to detect that nothing has
	 * hooked the filter at all.
	 *
	 * @param mixed $enabled Whether core would auto-update, or null if undecided.
	 * @param mixed $item    The update offer.
	 * @return mixed
	 */
	public function auto_update( mixed $enabled, mixed $item ): mixed {
		if ( ! is_object( $item ) || ! isset( $item->id ) || ! is_string( $item->id ) ) {
			return $enabled;
		}

		$host = wp_parse_url( $item->id, PHP_URL_HOST );

		if ( ! is_string( $host ) || strtolower( $host ) !== Host::current() ) {
			return $enabled;
		}

		$theme  = isset( $item->theme ) && is_string( $item->theme ) ? $item->theme : '';
		$plugin = isset( $item->plugin ) && is_string( $item->plugin ) ? $item->plugin : '';

		if ( self::THEME_STYLESHEET === $theme || self::PLUGIN_FILE === $plugin ) {
			return true;
		}

		return $enabled;
	}

	/**
	 * The shared body of both offer filters.
	 *
	 * @param PackageType          $type     Theme or plugin.
	 * @param array<string, mixed> $headers  Package headers from core.
	 * @param string               $identity Stylesheet, or plugin file.
	 * @param string               $slug     Directory name the manifest must claim.
	 * @return array<string, string>|false
	 */
	private function offer( PackageType $type, array $headers, string $identity, string $slug ): array|false {
		$update_uri = isset( $headers['UpdateURI'] ) && is_string( $headers['UpdateURI'] ) ? $headers['UpdateURI'] : '';
		$installed  = isset( $headers['Version'] ) && is_string( $headers['Version'] ) ? $headers['Version'] : '';

		if ( '' === $update_uri ) {
			return false;
		}

		$manifest = $this->source()->manifest_for( $type, $update_uri );

		if ( null === $manifest ) {
			return false;
		}

		if ( $manifest->type !== $type || $manifest->slug !== $slug ) {
			$this->log()->refused(
				'Verified manifest describes a different package than the one being checked.',
				array(
					'expected' => $type->value . ':' . $slug,
					'found'    => $manifest->type->value . ':' . $manifest->slug,
				)
			);

			return false;
		}

		if ( '' !== $installed && ! $manifest->is_newer_than( $installed ) ) {
			/*
			 * Still returned, not suppressed. Core routes a non-newer offer into
			 * $transient->no_update, which is what makes the "Auto-updates
			 * enabled" column appear on the Plugins and Themes screens. Core's own
			 * version_compare() is what decides; ours only decides what to log.
			 */
			$this->log()->refused(
				'Manifest is not newer than the installed version; no update will be offered.',
				array(
					'installed' => $installed,
					'offered'   => $manifest->version,
				)
			);
		}

		return $manifest->to_offer( $identity, $update_uri );
	}

	/**
	 * The manifest source: injected, or built from the compiled-in trust anchor.
	 *
	 * @return ManifestSource
	 */
	private function source(): ManifestSource {
		return $this->source ?? new ManifestSource(
			new Verifier( PublicKey::resolve(), Host::package_hosts() ),
			new Log()
		);
	}

	/**
	 * A log sink. Cheap enough to build on demand.
	 *
	 * @return Log
	 */
	private function log(): Log {
		return new Log();
	}
}
