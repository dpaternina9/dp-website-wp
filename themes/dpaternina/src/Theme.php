<?php
/**
 * The theme container.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

/**
 * Owns the theme's lifetime.
 *
 * Everything the theme registers hangs off here, so there is exactly one place
 * that knows what this theme does.
 */
final class Theme {

	/**
	 * The single booted instance, or null before boot.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute path to the theme directory.
	 * @param string $version   Theme version.
	 */
	private function __construct(
		private readonly string $directory,
		private readonly string $version
	) {}

	/**
	 * Boot the theme.
	 *
	 * Idempotent: booting twice returns the instance created the first time.
	 *
	 * @param string $directory Absolute path to the theme directory.
	 * @param string $version   Theme version.
	 * @return self The booted instance.
	 */
	public static function boot( string $directory, string $version ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( rtrim( $directory, '/' ), $version );
			self::$instance->register();
		}

		return self::$instance;
	}

	/**
	 * Attach everything the theme provides.
	 *
	 * @return void
	 */
	private function register(): void {
		$destinations = new Chrome\Destinations();

		( new Assets( $this ) )->register();
		( new CorePresets() )->register();
		( new Patterns() )->register();
		( new ExternalRequests() )->register();
		( new Blocks\AllowedBlocks() )->register();
		( new Blocks\ContactForm( $this ) )->register();
		( new Blocks\CoreStyles() )->register();
		( new Blocks\Markup() )->register();
		( new Blocks\SeriesPlanned() )->register();
		( new Blocks\TemplateHierarchy() )->register();
		( new Blocks\Timeline( $this ) )->register();
		$destinations->register();
		( new Chrome\Navigation( $destinations ) )->register();
		( new Chrome\FilterPills( $destinations ) )->register();
		( new Chrome\PostPresentation() )->register();
		( new Query\QueryLoops() )->register();
	}

	/**
	 * Absolute path to a file inside the theme directory.
	 *
	 * @param string $relative Path relative to the theme root, without a leading slash.
	 * @return string
	 */
	public function path( string $relative = '' ): string {
		return $this->directory . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Public URL of a file inside the theme directory.
	 *
	 * `get_theme_file_uri()` resolves through the child theme first, which costs
	 * nothing here and keeps the door open.
	 *
	 * @param string $relative Path relative to the theme root, without a leading slash.
	 * @return string
	 */
	public function url( string $relative = '' ): string {
		return get_theme_file_uri( ltrim( $relative, '/' ) );
	}

	/**
	 * Theme version, for asset cache busting and update comparisons.
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * A cache-busting version string for one asset.
	 *
	 * Released builds use the theme version, which changes with every tag. Local
	 * development uses the file's modification time, because during a phase the
	 * version does not move and a stale stylesheet is a wasted hour.
	 *
	 * @param string $relative Path relative to the theme root.
	 * @return string
	 */
	public function asset_version( string $relative ): string {
		if ( 'local' !== wp_get_environment_type() ) {
			return $this->version;
		}

		$path     = $this->path( $relative );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return false === $modified ? $this->version : $this->version . '.' . $modified;
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
