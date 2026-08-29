<?php
/**
 * Unit tests for the Helix response parsing.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

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
