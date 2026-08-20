<?php
/**
 * The plugin container.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core;

use DP\Core\Cli\Commands;
use DP\Core\Content\ContentModel;

/**
 * Owns the plugin's lifetime.
 *
 * Everything the plugin does — post types, taxonomies, meta, blocks, REST
 * routes, CLI commands — is attached from here, so there is exactly one place
 * that knows what this plugin is.
 *
 * Collaborators are built in `boot()` and injected, not reached for inside
 * `register()`. Constructing them touches no WordPress function, so it is safe
 * before `init`; registering them is what has to wait for it.
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
	 * @param string       $file    Absolute path to the plugin's entry file.
	 * @param string       $version Plugin version.
	 * @param ContentModel $content The content model: post types, taxonomies, meta.
	 * @param Commands     $cli     The WP-CLI commands, if WP-CLI is what is running.
	 */
	private function __construct(
		private readonly string $file,
		private readonly string $version,
		private readonly ContentModel $content,
		private readonly Commands $cli
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
			self::$instance = new self( $file, $version, ContentModel::create(), Commands::create() );
			add_action( 'init', self::$instance->register( ... ) );
		}

		return self::$instance;
	}

	/**
	 * Register everything the plugin provides.
	 *
	 * Runs on `init`, which is the earliest hook where `register_post_type()`,
	 * `register_taxonomy()` and `register_post_meta()` are all valid and the
	 * latest at which the REST API and the rewrite rules will still see them.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->content->register();
		$this->cli->register();
		( new Blocks\Blocks( plugin_dir_path( $this->file ) ) )->register();
		Update\UpdateClient::register();
	}

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
