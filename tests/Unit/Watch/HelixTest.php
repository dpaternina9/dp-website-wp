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
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * What Twitch answers, read without ever calling Twitch.
 *
 * `Helix` is pure so that the failing-soft contract can be asserted body by
 * body: an offline channel is a real "no", and every malformed shape is a
 * `null` the HTTP layer treats as "the question was not answered". The
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
	 * A live stream answers true; an offline channel answers false.
	 *
	 * Offline is `{"data": []}` — a valid answer meaning "no", which must not
	 * be confused with "Twitch did not say".
	 *
	 * @return void
	 */
	public function test_the_live_check_distinguishes_no_from_unknown(): void {
		$live    = '{"data":[{"id":"1","user_login":"patsypatz","type":"live"}]}';
		$offline = '{"data":[]}';

		$this->assertTrue( Helix::is_live( $live ) );
		$this->assertFalse( Helix::is_live( $offline ) );
		$this->assertNull( Helix::is_live( 'not json' ) );
		$this->assertNull( Helix::is_live( '{"error":"Unauthorized"}' ) );
	}

	/**
	 * A stream that is not `type: live` — Helix reserves the field for error
	 * states — does not count as live.
	 *
	 * @return void
	 */
	public function test_a_stream_that_is_not_type_live_is_not_live(): void {
		$this->assertFalse( Helix::is_live( '{"data":[{"id":"1","type":""}]}' ) );
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
