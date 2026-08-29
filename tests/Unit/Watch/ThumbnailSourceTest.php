<?php
/**
 * Unit tests for the thumbnail URL building.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Watch\ThumbnailSource;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The public URLs digest section 3.5 names, built exactly and never fetched here.
 */
final class ThumbnailSourceTest extends TestCase {

	/**
	 * YouTube: `maxresdefault` first, `hqdefault` as the fallback that always exists.
	 *
	 * @return void
	 */
	public function test_youtube_candidates_are_maxres_then_hq(): void {
		$this->assertSame(
			array(
				'https://i.ytimg.com/vi/abc-123_XYZ/maxresdefault.jpg',
				'https://i.ytimg.com/vi/abc-123_XYZ/hqdefault.jpg',
			),
			ThumbnailSource::youtube_candidates( 'abc-123_XYZ' )
		);

		$this->assertSame( array(), ThumbnailSource::youtube_candidates( '' ) );
	}

	/**
	 * The live preview is the digest's URL, at the digest's size.
	 *
	 * @return void
	 */
	public function test_the_live_preview_is_the_public_preview_url(): void {
		$this->assertSame(
			'https://static-cdn.jtvnw.net/previews-ttv/live_user_patsypatz-1280x720.jpg',
			ThumbnailSource::live_preview_url( 'patsypatz' )
		);

		$this->assertSame( '', ThumbnailSource::live_preview_url( '' ) );
	}

	/**
	 * A YouTube id is percent-encoded into the path, never interpolated raw.
	 *
	 * @return void
	 */
	public function test_identifiers_are_encoded_into_the_url(): void {
		$candidates = ThumbnailSource::youtube_candidates( 'a/b?c' );

		$this->assertSame( 'https://i.ytimg.com/vi/a%2Fb%3Fc/maxresdefault.jpg', $candidates[0] );
	}

	/**
	 * The cache key is deterministic, file-safe, and empty when there is no ref.
	 *
	 * @return void
	 */
	public function test_the_cache_key_is_file_safe(): void {
		$this->assertSame( 'youtube-abc-123_xyz', ThumbnailSource::cache_key( 'youtube', 'abc-123_XYZ' ) );
		$this->assertSame( 'twitch-335921245', ThumbnailSource::cache_key( 'twitch', '335921245' ) );
		$this->assertSame( 'live-patsypatz', ThumbnailSource::cache_key( 'live', 'patsypatz' ) );

		$this->assertSame( '', ThumbnailSource::cache_key( 'youtube', '' ) );
		$this->assertSame( '', ThumbnailSource::cache_key( '', 'abc' ) );
		$this->assertSame( '', ThumbnailSource::cache_key( 'youtube', '../../' ), 'A ref that is all traversal reduces to nothing.' );
		$this->assertSame( 'youtube-etcpasswd', ThumbnailSource::cache_key( 'youtube', '../etc/passwd' ), 'Path characters never reach the file name.' );
	}
}
