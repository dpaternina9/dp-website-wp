<?php
/**
 * Unit tests for the Helix response parsing.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Content\VideoSource;
use DP\Core\Watch\Helix;
use DP\Core\Watch\LiveStream;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * What Twitch answers, read without ever calling Twitch.
 *
 * `Helix` is pure so that the failing-soft contract can be asserted body by
 * body: every malformed shape is a `null` the HTTP layer treats as "the question
 * was not answered", and for the streams endpoint an offline channel answers the
 * same null, because nothing downstream renders the two differently. The
 * fixtures are the response shapes Helix documents — `data` arrays, an
 * `access_token`, a `thumbnail_url` template with literal `%{width}`.
 */
final class HelixTest extends TestCase {

	/**
	 * A token response yields the token and its lifetime.
	 *
	 * @return void
	 */
	public function test_a_token_response_is_read(): void {
		$body = '{"access_token":"abc123","expires_in":5011271,"token_type":"bearer"}';

		$this->assertSame(
			array(
				'token'   => 'abc123',
				'expires' => 5011271,
			),
			Helix::token( $body )
		);
	}

	/**
	 * Anything that is not a token is null, not a surprise.
	 *
	 * @return void
	 */
	public function test_a_malformed_token_response_is_null(): void {
		$this->assertNull( Helix::token( '' ) );
		$this->assertNull( Helix::token( 'not json' ) );
		$this->assertNull( Helix::token( '{"error":"Unauthorized"}' ) );
		$this->assertNull( Helix::token( '{"access_token":"","expires_in":100}' ) );
		$this->assertNull( Helix::token( '{"access_token":"abc","expires_in":"soon-ish"}' ) );
	}

	/**
	 * A live stream comes back whole: the title, the start instant and the
	 * category the live card is built out of.
	 *
	 * @return void
	 */
	public function test_a_streams_response_is_mapped_to_the_live_card(): void {
		$body = '{"data":[{'
			. '"id":"41375541868","user_login":"patsypatz","game_name":"Software and Game Development",'
			. '"type":"live","title":"Building the Kiveo reading-stats screen, live",'
			. '"viewer_count":41,"started_at":"2026-08-29T13:00:00Z",'
			. '"thumbnail_url":"https://static-cdn.jtvnw.net/previews-ttv/live_user_patsypatz-{width}x{height}.jpg"'
			. '}]}';

		$stream = Helix::stream( $body );

		$this->assertInstanceOf( LiveStream::class, $stream );
		$this->assertSame( 'Building the Kiveo reading-stats screen, live', $stream->title );
		$this->assertSame( 'Software and Game Development', $stream->category );
		$this->assertSame( strtotime( '2026-08-29T13:00:00Z' ), $stream->started );
	}

	/**
	 * Offline and unreadable both answer null.
	 *
	 * They converge on purpose: nothing downstream renders differently for "the
	 * channel is off" than for "Twitch did not say", so the parser does not
	 * offer a distinction the callers would only have to collapse again.
	 *
	 * @return void
	 */
	public function test_offline_and_unreadable_both_answer_nothing(): void {
		$this->assertNull( Helix::stream( '{"data":[]}' ) );
		$this->assertNull( Helix::stream( 'not json' ) );
		$this->assertNull( Helix::stream( '{"error":"Unauthorized"}' ) );
	}

	/**
	 * A stream that is not `type: live` — Helix reserves the field for error
	 * states — does not count as live.
	 *
	 * @return void
	 */
	public function test_a_stream_that_is_not_type_live_is_not_live(): void {
		$this->assertNull( Helix::stream( '{"data":[{"id":"1","type":"","title":"x"}]}' ) );
	}

	/**
	 * A live stream Twitch describes thinly still renders: the missing fields
	 * are captions the card omits, not a reason to hide a broadcast.
	 *
	 * @return void
	 */
	public function test_a_live_stream_with_missing_fields_still_answers(): void {
		$stream = Helix::stream( '{"data":[{"id":"1","type":"live"}]}' );

		$this->assertInstanceOf( LiveStream::class, $stream );
		$this->assertSame( '', $stream->title );
		$this->assertSame( '', $stream->category );
		$this->assertSame( 0, $stream->started );
	}

	/**
	 * VOD thumbnails come back keyed by id, and a processing VOD is omitted.
	 *
	 * @return void
	 */
	public function test_vod_thumbnails_are_keyed_by_id(): void {
		$body = '{"data":['
			. '{"id":"335921245","thumbnail_url":"https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg"},'
			. '{"id":"335921246","thumbnail_url":""}'
			. ']}';

		$this->assertSame(
			array( '335921245' => 'https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg' ),
			Helix::vod_thumbnails( $body )
		);
		$this->assertNull( Helix::vod_thumbnails( 'not json' ) );
		$this->assertSame( array(), Helix::vod_thumbnails( '{"data":[]}' ) );
	}

	/**
	 * A login resolves to the numeric id the archive endpoint is keyed by.
	 *
	 * @return void
	 */
	public function test_a_login_resolves_to_a_user_id(): void {
		$this->assertSame(
			'141981764',
			Helix::user_id( '{"data":[{"id":"141981764","login":"patsypatz","display_name":"PatsyPatz"}]}' )
		);
	}

	/**
	 * A login nobody has answers `{"data": []}`, which is null rather than ''.
	 *
	 * @return void
	 */
	public function test_a_login_nobody_has_is_null(): void {
		$this->assertNull( Helix::user_id( '{"data":[]}' ) );
		$this->assertNull( Helix::user_id( '{"error":"Unauthorized"}' ) );
		$this->assertNull( Helix::user_id( 'not json' ) );
	}

	/**
	 * An archive page becomes videos, with Twitch's duration spelling parsed and
	 * the thumbnail template kept unsubstituted.
	 *
	 * The template is stored with its `%{width}` placeholders intact, because the
	 * size is the renderer's decision and not the sync's.
	 *
	 * @return void
	 */
	public function test_an_archive_page_is_mapped(): void {
		$body = '{"data":[{'
			. '"id":"335921245",'
			. '"title":"Provisioning a client site from one command",'
			. '"published_at":"2026-08-03T21:30:18Z",'
			. '"duration":"2h41m0s",'
			. '"thumbnail_url":"https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg",'
			. '"type":"archive"'
			. '}],"pagination":{"cursor":"eyJiIjpudWxsfQ"}}';

		$read = Helix::archive( $body );

		$this->assertIsArray( $read );
		$this->assertSame( 'eyJiIjpudWxsfQ', $read['cursor'] );
		$this->assertCount( 1, $read['videos'] );

		$video = $read['videos'][0];

		$this->assertSame( VideoSource::Twitch, $video->source );
		$this->assertSame( '335921245', $video->id );
		$this->assertSame( 'Provisioning a client site from one command', $video->title );
		$this->assertSame( 9660, $video->duration );
		$this->assertSame( strtotime( '2026-08-03T21:30:18Z' ), $video->published );
		$this->assertSame(
			'https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg',
			$video->thumbnail
		);
		$this->assertSame( 'twitch:335921245', $video->key() );
	}

	/**
	 * The last page carries no cursor, and an empty archive is an answer.
	 *
	 * "No VODs" and "Twitch did not say" must not read the same: the caller
	 * unpublishes against the first and does nothing at all about the second.
	 *
	 * @return void
	 */
	public function test_an_empty_archive_and_an_unreadable_one_differ(): void {
		$this->assertSame(
			array(
				'videos' => array(),
				'cursor' => '',
			),
			Helix::archive( '{"data":[],"pagination":{}}' )
		);

		$this->assertNull( Helix::archive( '{"error":"Unauthorized"}' ) );
		$this->assertNull( Helix::archive( 'not json' ) );
	}

	/**
	 * A VOD with no id cannot be keyed, so it is dropped; a VOD missing anything
	 * else survives with what it has.
	 *
	 * @return void
	 */
	public function test_a_vod_without_an_id_is_dropped(): void {
		$read = Helix::archive( '{"data":[{"title":"No id"},{"id":"1","title":"Kept"}]}' );

		$this->assertIsArray( $read );
		$this->assertCount( 1, $read['videos'] );
		$this->assertSame( 'Kept', $read['videos'][0]->title );
		$this->assertSame( 0, $read['videos'][0]->duration );
		$this->assertSame( '', $read['videos'][0]->thumbnail );
	}

	/**
	 * The `%{width}`/`%{height}` placeholders become the tile size.
	 *
	 * @return void
	 */
	public function test_the_template_placeholders_are_substituted(): void {
		$this->assertSame(
			'https://example.invalid/index-1280x720.jpg',
			Helix::fill_thumbnail_template( 'https://example.invalid/index-%{width}x%{height}.jpg' )
		);

		$this->assertSame(
			'https://example.invalid/plain.jpg',
			Helix::fill_thumbnail_template( 'https://example.invalid/plain.jpg' ),
			'A template with nothing to substitute passes through.'
		);
	}
}
