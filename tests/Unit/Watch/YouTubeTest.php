<?php
/**
 * Unit tests for the YouTube Data API response parsing.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Content\VideoSource;
use DP\Core\Watch\YouTube;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * What YouTube answers, read without ever calling YouTube.
 *
 * `HelixTest`'s contract for the other platform: the fixtures are the response
 * shapes the Data API documents, and every malformed body is a `null` the HTTP
 * layer treats as "the question was not answered" rather than as an empty
 * channel — a distinction that matters here more than anywhere else in the
 * sync, because an empty channel is what makes it unpublish things.
 */
final class YouTubeTest extends TestCase {

	/**
	 * The uploads playlist id comes out of a `channels.list` response.
	 *
	 * @return void
	 */
	public function test_the_uploads_playlist_is_read(): void {
		$body = '{"items":[{"id":"UCabcdefghijklmnopqrstuv","contentDetails":'
			. '{"relatedPlaylists":{"likes":"","uploads":"UUabcdefghijklmnopqrstuv"}}}]}';

		$this->assertSame( 'UUabcdefghijklmnopqrstuv', YouTube::uploads_playlist( $body ) );
	}

	/**
	 * A channel that does not exist, or a body that is not a listing, is null.
	 *
	 * @return void
	 */
	public function test_a_channel_that_is_not_there_is_null(): void {
		$this->assertNull( YouTube::uploads_playlist( '{"items":[]}' ) );
		$this->assertNull( YouTube::uploads_playlist( '{"error":{"code":403}}' ) );
		$this->assertNull( YouTube::uploads_playlist( 'not json' ) );
		$this->assertNull( YouTube::uploads_playlist( '' ) );
	}

	/**
	 * A playlist page yields its video ids and the token for the next page.
	 *
	 * @return void
	 */
	public function test_a_playlist_page_yields_ids_and_a_cursor(): void {
		$body = '{"nextPageToken":"EAAaBlBUOkNBVQ","items":['
			. '{"contentDetails":{"videoId":"dQw4w9WgXcQ"}},'
			. '{"contentDetails":{"videoId":"aBcDeFgHiJk"}},'
			. '{"contentDetails":{}}'
			. ']}';

		$this->assertSame(
			array(
				'ids'    => array( 'dQw4w9WgXcQ', 'aBcDeFgHiJk' ),
				'cursor' => 'EAAaBlBUOkNBVQ',
			),
			YouTube::playlist_page( $body )
		);
	}

	/**
	 * The last page has no token, which is how the caller knows to stop.
	 *
	 * @return void
	 */
	public function test_the_last_playlist_page_has_no_cursor(): void {
		$this->assertSame(
			array(
				'ids'    => array( 'dQw4w9WgXcQ' ),
				'cursor' => '',
			),
			YouTube::playlist_page( '{"items":[{"contentDetails":{"videoId":"dQw4w9WgXcQ"}}]}' )
		);

		$this->assertNull( YouTube::playlist_page( '{"error":{"code":400}}' ) );
	}

	/**
	 * A `videos.list` response becomes videos, duration and date included.
	 *
	 * @return void
	 */
	public function test_videos_are_mapped_with_their_duration_and_date(): void {
		$body = '{"items":[{'
			. '"id":"dQw4w9WgXcQ",'
			. '"snippet":{"title":"Why your analytics plugin is slowing the site down","publishedAt":"2026-07-14T09:30:00Z"},'
			. '"contentDetails":{"duration":"PT18M4S"}'
			. '}]}';

		$videos = YouTube::videos( $body );

		$this->assertIsArray( $videos );
		$this->assertCount( 1, $videos );

		$video = $videos[0];

		$this->assertSame( VideoSource::YouTube, $video->source );
		$this->assertSame( 'dQw4w9WgXcQ', $video->id );
		$this->assertSame( 'Why your analytics plugin is slowing the site down', $video->title );
		$this->assertSame( 1084, $video->duration );
		$this->assertSame( strtotime( '2026-07-14T09:30:00Z' ), $video->published );
		$this->assertSame( 'youtube:dQw4w9WgXcQ', $video->key() );
		$this->assertSame( '', $video->thumbnail, 'YouTube images are addressable from the id alone.' );
	}

	/**
	 * A video missing a field keeps everything else rather than being dropped.
	 *
	 * A missing duration is a caption the card omits. A missing id is not a
	 * video at all, because there is nothing to key it by.
	 *
	 * @return void
	 */
	public function test_a_partial_video_survives_but_an_unidentified_one_does_not(): void {
		$body = '{"items":['
			. '{"id":"aBcDeFgHiJk","snippet":{"title":"No duration on this one"}},'
			. '{"snippet":{"title":"No id at all"},"contentDetails":{"duration":"PT4M"}}'
			. ']}';

		$videos = YouTube::videos( $body );

		$this->assertIsArray( $videos );
		$this->assertCount( 1, $videos );
		$this->assertSame( 'No duration on this one', $videos[0]->title );
		$this->assertSame( 0, $videos[0]->duration );
		$this->assertSame( 0, $videos[0]->published );
	}

	/**
	 * An empty listing is an answer; an unreadable body is not.
	 *
	 * @return void
	 */
	public function test_an_empty_listing_and_an_unreadable_body_differ(): void {
		$this->assertSame( array(), YouTube::videos( '{"items":[]}' ) );
		$this->assertNull( YouTube::videos( '{"error":{"code":403}}' ) );
		$this->assertNull( YouTube::videos( 'not json' ) );
	}
}
