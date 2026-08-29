<?php
/**
 * Everything the Watch page is, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * One object that knows the whole of Phase 12's server side.
 *
 * The shape is `Contact\Contact`'s: the collaborators are built here, shared
 * between the two blocks — one `Videos`, one `LiveStatus`, one `Thumbnails`,
 * one `TwitchApi` behind them — and registered together on `init`.
 *
 * Nothing here is a route. The blocks render wherever David places them, the
 * settings live on Settings → General, and the template that arranges the
 * page is the theme's.
 */
final class Watch {

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings The login and Helix credentials.
	 * @param WatchFeatured $featured The panel at the top.
	 * @param VideoGrid     $grid     The archive below it.
	 */
	private function __construct(
		private readonly Settings $settings,
		private readonly WatchFeatured $featured,
		private readonly VideoGrid $grid
	) {}

	/**
	 * Build the Watch page's parts with their shared collaborators.
	 *
	 * Nothing in this call path touches WordPress, so it is safe before `init`.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return self
	 */
	public static function create( string $plugin_dir ): self {
		$directory  = rtrim( $plugin_dir, '/' );
		$api        = new TwitchApi();
		$videos     = new Videos();
		$status     = new LiveStatus( $api );
		$thumbnails = new Thumbnails( $api );

		return new self(
			new Settings(),
			new WatchFeatured( $directory, $videos, $status, $thumbnails ),
			new VideoGrid( $directory, $videos, $status, $thumbnails )
		);
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->settings->register();
		$this->featured->register();
		$this->grid->register();
	}
}
