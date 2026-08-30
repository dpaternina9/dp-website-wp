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
 * The page has two automatic halves and they are kept apart on purpose. **What
 * is on air** is answered on the render path by `LiveStatus`, from a two-minute
 * transient holding the whole stream — title, start instant and category — so
 * the live card's copy comes out of the same call that proved the channel was
 * live. **What is in the archive** is answered by `VideoSync`, hourly under
 * cron, from the two platforms' own listings; nothing about it happens while a
 * visitor waits. `TwitchApi` is shared between them because it is one
 * authenticated client, not because the two paths are one thing.
 *
 * Nothing here is a route. The blocks render wherever David places them, the
 * settings live on Settings → General, and the template that arranges the
 * page is the theme's.
 */
final class Watch {

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings The login, the Helix credentials and the YouTube pair.
	 * @param WatchFeatured $featured The panel at the top.
	 * @param VideoGrid     $grid     The archive below it.
	 * @param Schedule      $schedule The hourly import.
	 * @param SyncButton    $button   "Sync now", beside the credentials.
	 */
	private function __construct(
		private readonly Settings $settings,
		private readonly WatchFeatured $featured,
		private readonly VideoGrid $grid,
		private readonly Schedule $schedule,
		private readonly SyncButton $button
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
		$sync       = new VideoSync( $api, new YouTubeApi() );

		return new self(
			new Settings(),
			new WatchFeatured( $directory, $videos, $status, $thumbnails ),
			new VideoGrid( $directory, $videos, $status, $thumbnails ),
			new Schedule( $sync ),
			new SyncButton( $sync )
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
		$this->schedule->register();
		$this->button->register();
	}
}
