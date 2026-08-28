<?php
/**
 * Everything the plugin adds to wp-admin, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Admin;

/**
 * The plugin's admin surface, in one place.
 *
 * There is one screen in it today. The namespace exists anyway, and separately
 * from `Content`, because the two answer different questions: `Content` is what
 * the site *is*, and this is what David uses to say so. A screen that edits
 * posts is not a post type.
 */
final class Admin {

	/**
	 * Constructor.
	 *
	 * @param SeriesOrderScreen $series_order The screen that orders one series' parts.
	 */
	private function __construct( private readonly SeriesOrderScreen $series_order ) {}

	/**
	 * Build with the default collaborators.
	 *
	 * Touches no WordPress function, so it is safe before `init`.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version, for asset cache busting.
	 * @return self
	 */
	public static function create( string $plugin_file, string $version ): self {
		return new self( new SeriesOrderScreen( new SeriesOrder(), $plugin_file, $version ) );
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->series_order->register();
	}
}
