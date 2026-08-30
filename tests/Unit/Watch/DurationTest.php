<?php
/**
 * Unit tests for runtime parsing and printing.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Watch\Duration;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Two platforms' spellings in, the design's caption out.
 *
 * The fixtures are the shapes each API documents, and the expected captions are
 * the literal strings in `Fixture::videos()` — `18 MIN`, `2H 41M`, `3H 05M` —
 * because the design is the contract and a runtime that reads almost right is
 * the kind of wrong nobody reports.
 */
final class DurationTest extends TestCase {

	/**
	 * ISO 8601, in the subset YouTube emits.
	 *
	 * @return void
	 */
	public function test_iso_8601_durations_are_read(): void {
		$this->assertSame( 1084, Duration::from_iso8601( 'PT18M4S' ) );
		$this->assertSame( 3600, Duration::from_iso8601( 'PT1H' ) );
		$this->assertSame( 11100, Duration::from_iso8601( 'PT3H5M0S' ) );
		$this->assertSame( 93784, Duration::from_iso8601( 'P1DT2H3M4S' ) );
		$this->assertSame( 600, Duration::from_iso8601( ' PT10M ' ) );
	}

	/**
	 * `P0D` is a live or upcoming broadcast: a real zero, not a refusal.
	 *
	 * The distinction is the whole reason this returns `?int` rather than `int`.
	 * Zero prints as no runtime; null means the field was unreadable and the
	 * caller falls back to the same thing — but only one of the two is a lie
	 * about the response.
	 *
	 * @return void
	 */
	public function test_a_broadcast_with_no_runtime_yet_is_zero_and_not_null(): void {
		$this->assertSame( 0, Duration::from_iso8601( 'P0D' ) );
		$this->assertSame( 0, Duration::from_iso8601( 'PT0S' ) );
	}

	/**
	 * Anything that is not an ISO duration is null.
	 *
	 * Years and months are refused rather than guessed at: neither has a fixed
	 * length in seconds, and YouTube never sends one for a video.
	 *
	 * @return void
	 */
	public function test_a_malformed_iso_duration_is_null(): void {
		$this->assertNull( Duration::from_iso8601( '' ) );
		$this->assertNull( Duration::from_iso8601( '18:04' ) );
		$this->assertNull( Duration::from_iso8601( 'PT18M4' ) );
		$this->assertNull( Duration::from_iso8601( 'P1Y' ) );
		$this->assertNull( Duration::from_iso8601( 'P1M' ) );
	}

	/**
	 * Twitch's own spelling.
	 *
	 * @return void
	 */
	public function test_twitch_durations_are_read(): void {
		$this->assertSame( 11313, Duration::from_twitch( '3h8m33s' ) );
		$this->assertSame( 1263, Duration::from_twitch( '21m3s' ) );
		$this->assertSame( 48, Duration::from_twitch( '48s' ) );
		$this->assertSame( 3600, Duration::from_twitch( '1h0m0s' ) );
	}

	/**
	 * An empty Twitch duration is Twitch declining to say, not a zero-length VOD.
	 *
	 * The pattern's three parts are all optional, so without the guard the empty
	 * string would match and report a confident zero.
	 *
	 * @return void
	 */
	public function test_a_malformed_twitch_duration_is_null(): void {
		$this->assertNull( Duration::from_twitch( '' ) );
		$this->assertNull( Duration::from_twitch( '3h 8m' ) );
		$this->assertNull( Duration::from_twitch( 'PT18M' ) );
		$this->assertNull( Duration::from_twitch( '3H8M' ) );
	}

	/**
	 * The design's two shapes: minutes under an hour, padded minutes over it.
	 *
	 * @return void
	 */
	public function test_the_caption_is_the_design_s(): void {
		$this->assertSame( '18 MIN', Duration::format( 1084 ) );
		$this->assertSame( '24 MIN', Duration::format( 1440 ) );
		$this->assertSame( '3H 05M', Duration::format( 11100 ) );
		$this->assertSame( '2H 41M', Duration::format( 9660 ) );
		$this->assertSame( '4H 18M', Duration::format( 15480 ) );
		$this->assertSame( '1H 00M', Duration::format( 3600 ) );
		$this->assertSame( '26H 03M', Duration::format( 93784 ) );
	}

	/**
	 * Minutes truncate, and anything at all is at least a minute.
	 *
	 * Truncation is what both platforms' own interfaces do — 18m59s is `18 MIN`
	 * on YouTube too — and the floor exists because a forty-second clip reading
	 * `0 MIN` is a caption the reader has to interpret.
	 *
	 * @return void
	 */
	public function test_minutes_truncate_and_never_reach_zero(): void {
		$this->assertSame( '18 MIN', Duration::format( 1139 ) );
		$this->assertSame( '1 MIN', Duration::format( 40 ) );
		$this->assertSame( '1 MIN', Duration::format( 1 ) );
	}

	/**
	 * No runtime prints as nothing at all, and never as a zero.
	 *
	 * @return void
	 */
	public function test_no_runtime_prints_nothing(): void {
		$this->assertSame( '', Duration::format( 0 ) );
		$this->assertSame( '', Duration::format( -5 ) );
	}
}
