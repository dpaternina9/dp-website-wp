<?php
/**
 * Integration tests for the server-side thumbnail cache.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;
use DP\Core\Watch\Settings;
use DP\Core\Watch\Thumbnails;
use DP\Core\Watch\ThumbnailSource;
use DP\Core\Watch\TwitchApi;
use DP\Core\Watch\Video;

/**
 * The property the whole mechanism exists for: the visitor's browser never
 * talks to Twitch or YouTube before a click.
 *
 * Every test constructs its own `Thumbnails`, because the budget is
 * per-render and a fresh instance is a fresh render. The upstream hosts are
 * `pre_http_request` stubs throughout — what is asserted is which requests
 * the *server* makes, what it writes to `uploads/dp-watch/`, and that every
 * URL handed back is this site's own.
 */
final class ThumbnailsTest extends WatchTestCase {

	/**
	 * One in-memory entry; the cache never needs the post behind it.
	 *
	 * @param VideoSource|null $source Where it is hosted.
	 * @param string           $ref    The platform identifier.
	 * @param bool             $live   Whether it is the live entry.
	 * @return Video
	 */
	private function video( ?VideoSource $source, string $ref, bool $live = false ): Video {
		return new Video( 5, 'A video', $source, $ref, Tone::Teal, '18 MIN', 'JUL 2026', '', $live, '' );
	}

	/**
	 * A fetched YouTube thumbnail is written once and served from our origin.
	 *
	 * @return void
	 */
	public function test_a_youtube_thumbnail_is_cached_and_served_from_our_origin(): void {
		$this->http_stubs['https://i.ytimg.com/vi/dp-fixture-yt/maxresdefault.jpg'] = self::image_response( 'jpeg-bytes' );

		$thumbnails = new Thumbnails();
		$video      = $this->video( VideoSource::YouTube, 'dp-fixture-yt' );

		$url = $thumbnails->url( $video, '' );

		$this->assertIsString( $url );
		$this->assertStringStartsWith( content_url(), $url, 'The visitor is handed this site\'s own copy.' );
		$this->assertStringContainsString( '/' . Thumbnails::DIRECTORY . '/youtube-dp-fixture-yt.jpg', $url );

		$uploads = wp_upload_dir();
		$path    = $uploads['basedir'] . '/' . Thumbnails::DIRECTORY . '/youtube-dp-fixture-yt.jpg';

		$this->assertFileExists( $path );
		$this->assertStringEqualsFile( $path, 'jpeg-bytes' );

		// The second ask is the file, not a second fetch.
		$before = count( $this->http_requests );

		$this->assertSame( $url, ( new Thumbnails() )->url( $video, '' ) );
		$this->assertCount( $before, $this->http_requests );
	}

	/**
	 * `maxresdefault` genuinely 404s for SD uploads; `hqdefault` is the fallback.
	 *
	 * @return void
	 */
	public function test_a_missing_maxres_falls_back_to_hqdefault(): void {
		$this->http_stubs['https://i.ytimg.com/vi/dp-fixture-yt/maxresdefault.jpg'] = self::response( 404, 'not found' );
		$this->http_stubs['https://i.ytimg.com/vi/dp-fixture-yt/hqdefault.jpg']     = self::image_response( 'hq-bytes' );

		$url = ( new Thumbnails() )->url( $this->video( VideoSource::YouTube, 'dp-fixture-yt' ), '' );

		$this->assertIsString( $url );
		$this->assertSame(
			array(
				'https://i.ytimg.com/vi/dp-fixture-yt/maxresdefault.jpg',
				'https://i.ytimg.com/vi/dp-fixture-yt/hqdefault.jpg',
			),
			$this->http_requests
		);
	}

	/**
	 * A failed fetch is remembered, so one bad video cannot cost a round trip
	 * per page view.
	 *
	 * @return void
	 */
	public function test_a_failed_fetch_is_negative_cached(): void {
		$video = $this->video( VideoSource::YouTube, 'dp-fixture-yt' );

		$this->assertNull( ( new Thumbnails() )->url( $video, '' ) );

		$attempted = count( $this->http_requests );

		$this->assertGreaterThan( 0, $attempted );
		$this->assertNotFalse( get_transient( Thumbnails::FAILED_TRANSIENT . ThumbnailSource::cache_key( 'youtube', 'dp-fixture-yt' ) ) );

		$this->assertNull( ( new Thumbnails() )->url( $video, '' ) );
		$this->assertCount( $attempted, $this->http_requests, 'The failure is cached; nothing was asked again.' );
	}

	/**
	 * A Twitch VOD without Helix credentials makes no request at all.
	 *
	 * @return void
	 */
	public function test_a_twitch_vod_without_credentials_fetches_nothing(): void {
		$this->assertNull( ( new Thumbnails() )->url( $this->video( VideoSource::Twitch, '2280918841' ), '' ) );
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * With credentials, the VOD's thumbnail resolves through Helix — token,
	 * videos lookup, template substituted — and lands in the cache.
	 *
	 * @return void
	 */
	public function test_a_twitch_vod_resolves_through_helix(): void {
		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->http_stubs[ TwitchApi::TOKEN_URL ]          = self::response( 200, '{"access_token":"tok","expires_in":5000}' );
		$this->http_stubs[ TwitchApi::VIDEOS_URL ]         = self::response(
			200,
			'{"data":[{"id":"2280918841","thumbnail_url":"https://static-cdn.jtvnw.net/cf_vods/thumb/index-%{width}x%{height}.jpg"}]}'
		);
		$this->http_stubs['https://static-cdn.jtvnw.net/'] = self::image_response( 'vod-bytes' );

		$url = ( new Thumbnails() )->url( $this->video( VideoSource::Twitch, '2280918841' ), '' );

		$this->assertIsString( $url );
		$this->assertStringContainsString( '/twitch-2280918841.jpg', $url );
		$this->assertContains( 'https://static-cdn.jtvnw.net/cf_vods/thumb/index-1280x720.jpg', $this->http_requests );
	}

	/**
	 * A video with no identifier has no thumbnail, no fetch, no file.
	 *
	 * @return void
	 */
	public function test_a_video_with_no_identifier_asks_for_nothing(): void {
		$this->assertNull( ( new Thumbnails() )->url( $this->video( VideoSource::YouTube, '' ), '' ) );
		$this->assertNull( ( new Thumbnails() )->url( $this->video( null, 'dp-fixture-yt' ), '' ) );
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * A response that is not an image never becomes a cached "thumbnail".
	 *
	 * @return void
	 */
	public function test_a_non_image_response_is_not_cached(): void {
		$this->http_stubs['https://i.ytimg.com/'] = self::response( 200, '<html>a captive portal</html>' );

		$this->assertNull( ( new Thumbnails() )->url( $this->video( VideoSource::YouTube, 'dp-fixture-yt' ), '' ) );

		$uploads = wp_upload_dir();

		$this->assertFileDoesNotExist( $uploads['basedir'] . '/' . Thumbnails::DIRECTORY . '/youtube-dp-fixture-yt.jpg' );
	}
}
