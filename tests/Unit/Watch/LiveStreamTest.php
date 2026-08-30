<?php
/**
 * Unit tests for the live stream's elapsed time and its cached shape.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Watch\LiveStream;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The one number on the Watch page that is wrong a minute after it is right.
 *
 * The design prints "STREAMING NOW · 1H 12M IN" and Helix reports a start
 * instant, so the elapsed time is arithmetic done at render. That makes it
 * exactly the kind of value a mistake hides in: a stream that started three
 * hours ago and reads "3H 00M" looks identical to one that reads "2H 59M", and
 * nobody would ever notice. Hence a clock passed in rather than read, and the
 * boundaries asserted one by one.
 */
final class LiveStreamTest extends TestCase {

	/**
	 * A start instant far enough in the past to make the arithmetic obvious.
	 *
	 * @var int
	 */
	private const STARTED = 1756468800;

	/**
	 * One stream, started at the fixed instant above.
	 *
	 * @return LiveStream
	 */
	private function stream(): LiveStream {
		return new LiveStream( 'Building the thing, live', self::STARTED, 'Software and Game Development' );
	}

	/**
	 * Over an hour, the design's two-part shape with padded minutes.
	 *
	 * @return void
	 */
	public function test_over_an_hour_prints_hours_and_padded_minutes(): void {
		$stream = $this->stream();

		$this->assertSame( '1H 12M', $stream->elapsed( self::STARTED + ( 72 * 60 ) ) );
		$this->assertSame( '3H 05M', $stream->elapsed( self::STARTED + ( 185 * 60 ) ) );
	}

	/**
	 * Under an hour, minutes alone and unpadded.
	 *
	 * @return void
	 */
	public function test_under_an_hour_prints_minutes_alone(): void {
		$this->assertSame( '18 MIN', $this->stream()->elapsed( self::STARTED + ( 18 * 60 ) + 4 ) );
	}

	/**
	 * A stream seconds old prints one minute rather than zero, and a stream
	 * whose start is this instant prints nothing at all.
	 *
	 * The distinction matters to the markup: '' is what makes the panel say
	 * "Streaming now" with no elapsed clause, rather than "· 0 MIN in".
	 *
	 * @return void
	 */
	public function test_the_first_minute_rounds_up_and_the_first_instant_prints_nothing(): void {
		$stream = $this->stream();

		$this->assertSame( '1 MIN', $stream->elapsed( self::STARTED + 3 ) );
		$this->assertSame( '', $stream->elapsed( self::STARTED ) );
	}

	/**
	 * A clock behind the stream's start, and a stream with no start at all,
	 * both print nothing rather than a negative runtime.
	 *
	 * Twitch reports UTC and the site may not; a server whose clock has drifted
	 * must degrade to saying less, never to saying something false.
	 *
	 * @return void
	 */
	public function test_an_unknown_or_impossible_start_prints_nothing(): void {
		$this->assertSame( '', $this->stream()->elapsed( self::STARTED - 600 ) );
		$this->assertSame( '', ( new LiveStream( 'x', 0, '' ) )->elapsed( self::STARTED ) );
	}

	/**
	 * The cached shape survives a round trip.
	 *
	 * @return void
	 */
	public function test_a_stream_round_trips_through_the_cache(): void {
		$restored = LiveStream::from_cache( $this->stream()->to_cache() );

		$this->assertInstanceOf( LiveStream::class, $restored );
		$this->assertSame( 'Building the thing, live', $restored->title );
		$this->assertSame( self::STARTED, $restored->started );
		$this->assertSame( 'Software and Game Development', $restored->category );
	}

	/**
	 * An empty array is how "checked, and not live" is cached, and a payload
	 * that has been mangled reads the same way: no stream.
	 *
	 * @return void
	 */
	public function test_an_empty_or_mangled_payload_is_not_a_stream(): void {
		$this->assertNull( LiveStream::from_cache( array() ) );
		$this->assertNull( LiveStream::from_cache( array( 'title' => 'x' ) ) );
		$this->assertNull(
			LiveStream::from_cache(
				array(
					'title'    => 'x',
					'started'  => '1756468800',
					'category' => '',
				)
			)
		);
	}
}
