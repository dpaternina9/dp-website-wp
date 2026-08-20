<?php
/**
 * The plugin container.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core;

/**
 * Owns the plugin's lifetime.
 *
 * Phase 0 registers nothing. Everything that follows — post types, taxonomies,
 * meta, blocks, REST routes, CLI commands — is attached from here so there is
 * exactly one place that knows what this plugin does.
 */
final class Plugin {

	/**
	 * The single booted instance, or null before boot.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 *
	 * @param string $file    Absolute path to the plugin's entry file.
	 * @param string $version Plugin version.
	 */
	private function __construct(
		private readonly string $file,
		private readonly string $version
	) {}

	/**
	 * Boot the plugin.
	 *
	 * Idempotent: booting twice returns the instance created the first time.
	 *
	 * @param string $file    Absolute path to the plugin's entry file.
	 * @param string $version Plugin version.
	 * @return self The booted instance.
	 */
	public static function boot( string $file, string $version ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $file, $version );
			add_action( 'init', self::$instance->register( ... ) );
		}

		return self::$instance;
	}

	/**
	 * Register everything the plugin provides.
	 *
	 * Deliberately empty in Phase 0.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Absolute path to the plugin's entry file.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Absolute path to a file inside the plugin directory.
	 *
	 * @param string $relative Path relative to the plugin root, without a leading slash.
	 * @return string
	 */
	public function path( string $relative = '' ): string {
		return plugin_dir_path( $this->file ) . ltrim( $relative, '/' );
	}

	/**
	 * Public URL of a file inside the plugin directory.
	 *
	 * @param string $relative Path relative to the plugin root, without a leading slash.
	 * @return string
	 */
	public function url( string $relative = '' ): string {
		return plugin_dir_url( $this->file ) . ltrim( $relative, '/' );
	}

	/**
	 * Plugin version, for asset cache busting and update comparisons.
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * Reset the booted instance. Test-support only.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
