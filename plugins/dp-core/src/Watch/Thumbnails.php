<?php
/**
 * Thumbnails, fetched by the server and served from our origin.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\VideoSource;

/**
 * The mechanism that keeps a visitor's browser off Twitch's and YouTube's hosts.
 *
 * Plan Phase 12: thumbnails "resolve to the public Twitch/YouTube URLs …
 * fetched and cached server-side so the visitor's browser never talks to those
 * hosts before a click". This is that cache, and the mechanism is the simplest
 * one that satisfies it: **a static file under `uploads/dp-watch/`**, written
 * once by this class and served by the web server like any other upload. The
 * front end gets a same-origin `<img src>` or nothing at all; PHP is never on
 * the serving path, and no REST route or rewrite exists for it.
 *
 * Not the media library, deliberately. "Thumbnails are never uploaded" is a
 * design property (digest section 3.5): they are nobody's content, David never
 * manages them, and attachment rows would put a cache in his library. The
 * précédent is `Resume\PdfCache`, which keeps rendered résumés the same way.
 *
 * Failing soft, in layers:
 *
 * - A fetch that fails writes a fifteen-minute transient, so one bad video
 *   cannot cost a round trip per page view.
 * - Each block render has a small budget of remote calls, so a cold cache
 *   warms over a few views instead of holding one render for the whole grid.
 * - A card with no cached file simply renders no `<img>`; the glow art
 *   underneath is the card's own look, not an error state.
 *
 * The live preview is the one entry that goes stale by design — it is a frame
 * of a running stream — so it is refetched when its file is older than five
 * minutes, and the stale frame is served whenever the refetch fails.
 */
final class Thumbnails {

	/**
	 * The directory under `uploads/`.
	 *
	 * @var string
	 */
	public const DIRECTORY = 'dp-watch';

	/**
	 * Prefix on the transient remembering that one thumbnail could not be fetched.
	 *
	 * @var string
	 */
	public const FAILED_TRANSIENT = 'dp_watch_thumb_failed_';

	/**
	 * How long a failed fetch keeps further ones from being tried.
	 *
	 * @var int
	 */
	private const FAILURE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * How old a cached live preview may be before it is refetched.
	 *
	 * @var int
	 */
	private const LIVE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * How long one image fetch may hold a page render, in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 3;

	/**
	 * How many remote calls one block render may spend warming the cache.
	 *
	 * @var int
	 */
	private const BUDGET = 3;

	/**
	 * What is left of the current render's budget.
	 *
	 * The counter is replenished by each block render rather than sized to the
	 * object's lifetime, because the object lives as long as the process does
	 * — one request under FPM, arbitrarily long elsewhere — and a budget that
	 * never came back would quietly stop the cache warming.
	 *
	 * @var int
	 */
	private int $budget = self::BUDGET;

	/**
	 * Start a render: the budget comes back.
	 *
	 * @return void
	 */
	public function replenish(): void {
		$this->budget = self::BUDGET;
	}

	/**
	 * Constructor.
	 *
	 * @param TwitchApi $api The Helix client, for VOD thumbnail templates.
	 */
	public function __construct( private readonly TwitchApi $api = new TwitchApi() ) {}

	/**
	 * The same-origin URL of one video's thumbnail, or null while there is none.
	 *
	 * @param Video  $video The entry being rendered.
	 * @param string $login The configured Twitch login, for the live preview.
	 * @return string|null
	 */
	public function url( Video $video, string $login ): ?string {
		$key = $this->key( $video, $login );

		if ( '' === $key ) {
			return null;
		}

		$path = $this->path( $key );

		if ( '' === $path ) {
			return null;
		}

		$cached = is_readable( $path );

		if ( $cached && ! $this->wants_refresh( $video, $path ) ) {
			return $this->public_url( $key );
		}

		$fetched = $this->fetch( $video, $login, $key, $path );

		if ( $fetched || $cached ) {
			return $this->public_url( $key );
		}

		return null;
	}

	/**
	 * Try to bring one thumbnail into the cache.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @param string $key   The cache key.
	 * @param string $path  Where the file belongs.
	 * @return bool Whether the file is now written.
	 */
	private function fetch( Video $video, string $login, string $key, string $path ): bool {
		if ( false !== get_transient( self::FAILED_TRANSIENT . $key ) ) {
			return false;
		}

		if ( $this->budget <= 0 ) {
			return false;
		}

		foreach ( $this->candidates( $video, $login ) as $url ) {
			if ( $this->budget <= 0 ) {
				return false;
			}

			--$this->budget;

			$image = $this->download( $url );

			if ( null !== $image && $this->write( $path, $image ) ) {
				delete_transient( self::FAILED_TRANSIENT . $key );

				return true;
			}
		}

		set_transient( self::FAILED_TRANSIENT . $key, 1, self::FAILURE_TTL );

		return false;
	}

	/**
	 * The remote URLs worth trying for one entry, best first.
	 *
	 * A Twitch VOD's URL is not knowable without asking Helix — unless the sync
	 * already asked. `VideoSync` records the template it was given while
	 * importing the video, so a synced VOD costs no Helix call here at all; the
	 * call below is the fallback for a `dp_video` that was written by hand.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @return list<string>
	 */
	private function candidates( Video $video, string $login ): array {
		if ( $video->live ) {
			$url = ThumbnailSource::live_preview_url( $login );

			return '' === $url ? array() : array( $url );
		}

		if ( VideoSource::YouTube === $video->source ) {
			return ThumbnailSource::youtube_candidates( $video->ref );
		}

		if ( VideoSource::Twitch === $video->source && '' !== $video->thumbnail ) {
			return array( Helix::fill_thumbnail_template( $video->thumbnail ) );
		}

		if ( VideoSource::Twitch === $video->source && $this->budget > 0 ) {
			--$this->budget;

			$template = $this->api->vod_thumbnail_template( $video->ref );

			if ( null === $template ) {
				return array();
			}

			return array( Helix::fill_thumbnail_template( $template ) );
		}

		return array();
	}

	/**
	 * One image over HTTP, or nothing.
	 *
	 * @param string $url The remote URL.
	 * @return string|null The image bytes, or null for anything that is not an image.
	 */
	private function download( string $url ): ?string {
		$response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( ! is_string( $type ) || ! str_starts_with( strtolower( $type ), 'image/' ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}

	/**
	 * Whether a cached file should be replaced anyway.
	 *
	 * Only the live preview ages: a VOD's thumbnail is the VOD's forever, but
	 * a live frame five minutes old is another part of the stream.
	 *
	 * @param Video  $video The entry.
	 * @param string $path  The cached file.
	 * @return bool
	 */
	private function wants_refresh( Video $video, string $path ): bool {
		if ( ! $video->live ) {
			return false;
		}

		$modified = filemtime( $path );

		return false === $modified || ( time() - $modified ) > self::LIVE_TTL;
	}

	/**
	 * The cache key for one entry, or '' when it cannot have a thumbnail.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @return string
	 */
	private function key( Video $video, string $login ): string {
		if ( $video->live ) {
			return ThumbnailSource::cache_key( 'live', $login );
		}

		if ( null === $video->source ) {
			return '';
		}

		return ThumbnailSource::cache_key( $video->source->value, $video->ref );
	}

	/**
	 * Absolute path of the file one key is stored at.
	 *
	 * @param string $key The cache key.
	 * @return string Empty when the uploads directory is unusable.
	 */
	private function path( string $key ): string {
		$directory = $this->directory();

		return '' === $directory ? '' : $directory . '/' . $key . '.jpg';
	}

	/**
	 * Public URL of the file one key is stored at.
	 *
	 * @param string $key The cache key.
	 * @return string
	 */
	private function public_url( string $key ): string {
		$uploads = wp_upload_dir();
		$base    = $uploads['baseurl'] ?? null;

		return is_string( $base ) ? $base . '/' . self::DIRECTORY . '/' . $key . '.jpg' : '';
	}

	/**
	 * Write one image file.
	 *
	 * @param string $path  Where it belongs.
	 * @param string $bytes The image.
	 * @return bool
	 */
	private function write( string $path, string $bytes ): bool {
		/*
		 * Written directly rather than through WP_Filesystem, exactly as
		 * `Resume\PdfCache` does and for the reason its comment records: the
		 * credentials flow WP_Filesystem is built around does not exist for
		 * an anonymous front-end request.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- see above.
		return false !== file_put_contents( $path, $bytes, LOCK_EX );
	}

	/**
	 * The cache directory, created if need be.
	 *
	 * @return string Absolute path without a trailing slash, or '' when unusable.
	 */
	private function directory(): string {
		$uploads = wp_upload_dir();

		if ( ! is_string( $uploads['basedir'] ?? null ) || ( $uploads['error'] ?? false ) ) {
			return '';
		}

		$directory = $uploads['basedir'] . '/' . self::DIRECTORY;

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return '';
		}

		$index = $directory . '/index.html';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one empty marker file; see write().
			file_put_contents( $index, '' );
		}

		return $directory;
	}
}
