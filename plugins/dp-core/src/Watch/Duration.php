<?php
/**
 * Runtimes, as two platforms spell them and as the design prints them.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * Parsing a duration from either platform, and printing it the design's way.
 *
 * Pure and WordPress-free, like `Helix` and `Timeline\Geometry`, because this is
 * the part of the sync most likely to be wrong and least likely to be noticed:
 * a runtime is a small caption on a card, and a mis-parsed one looks exactly
 * like a correct one.
 *
 * The two platforms disagree about spelling and neither matches the design:
 *
 * - **YouTube** answers `contentDetails.duration` as an ISO 8601 duration —
 *   `PT18M4S`, `PT1H`, `P1DT2H3M4S` for a stream that ran over a day. A live or
 *   upcoming broadcast answers `P0D`, which is "no runtime yet" rather than a
 *   zero-length video.
 * - **Twitch** answers `duration` as its own compact spelling — `3h8m33s`,
 *   `21m3s`, `48s`.
 * - **The design** prints `18 MIN` under an hour and `3H 05M` over it, with the
 *   minutes zero-padded in the second shape and not in the first. Seconds are
 *   never printed.
 *
 * Two rounding rules, both deliberate and both here rather than at a call site:
 * minutes **truncate** — an 18m4s video reads `18 MIN`, which is what both
 * platforms' own interfaces show — and anything above zero seconds prints at
 * least `1 MIN`, because a 40-second clip reading `0 MIN` is a bug the reader
 * has to interpret.
 */
final class Duration {

	/**
	 * An ISO 8601 duration, in the subset YouTube emits.
	 *
	 * Weeks and days are accepted because a multi-day premiere or archived
	 * broadcast is expressible; years and months are not, because they have no
	 * fixed length in seconds and YouTube never sends them for a video.
	 *
	 * @var string
	 */
	private const ISO_PATTERN = '/\AP(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?\z/';

	/**
	 * Twitch's own duration spelling.
	 *
	 * @var string
	 */
	private const TWITCH_PATTERN = '/\A(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?\z/';

	/**
	 * Not to be instantiated: three related pure functions, namespaced.
	 */
	private function __construct() {}

	/**
	 * Seconds in an ISO 8601 duration.
	 *
	 * @param string $value The duration, e.g. `PT18M4S`.
	 * @return int|null The seconds, or null when the string is not a duration at all.
	 *                  `P0D` — a live or upcoming broadcast — is a real zero.
	 */
	public static function from_iso8601( string $value ): ?int {
		$matches = array();

		if ( 1 !== preg_match( self::ISO_PATTERN, trim( $value ), $matches ) ) {
			return null;
		}

		return self::seconds( $matches, array( 604800, 86400, 3600, 60, 1 ) );
	}

	/**
	 * Seconds in a Twitch duration.
	 *
	 * @param string $value The duration, e.g. `3h8m33s`.
	 * @return int|null The seconds, or null when the string is not a duration at all.
	 */
	public static function from_twitch( string $value ): ?int {
		$trimmed = trim( $value );
		$matches = array();

		/*
		 * The pattern's three parts are all optional, so it also matches the
		 * empty string. That is not a zero-length VOD, it is Twitch declining to
		 * say, and the two must not read the same.
		 */
		if ( '' === $trimmed || 1 !== preg_match( self::TWITCH_PATTERN, $trimmed, $matches ) ) {
			return null;
		}

		return self::seconds( $matches, array( 3600, 60, 1 ) );
	}

	/**
	 * The design's runtime caption for a number of seconds.
	 *
	 * @param int $seconds The runtime. Zero or less is "no runtime", which prints as nothing.
	 * @return string `18 MIN`, `3H 05M`, or '' when there is nothing to print.
	 */
	public static function format( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return '';
		}

		$hours   = intdiv( $seconds, 3600 );
		$minutes = intdiv( $seconds % 3600, 60 );

		if ( $hours > 0 ) {
			return sprintf( '%dH %02dM', $hours, $minutes );
		}

		return sprintf( '%d MIN', max( 1, $minutes ) );
	}

	/**
	 * Add up whichever groups a pattern matched.
	 *
	 * The multipliers are passed rather than hardcoded because the two patterns
	 * have different groups in different places: the multiplier at index `n` is
	 * the unit of capture group `n + 1`, so each caller states its own pattern's
	 * shape and this shares nothing but the arithmetic.
	 *
	 * @param array<int, string> $matches     What `preg_match()` filled in.
	 * @param array<int, int>    $multipliers Seconds per unit, in capture-group order.
	 * @return int
	 */
	private static function seconds( array $matches, array $multipliers ): int {
		$total = 0;

		foreach ( $multipliers as $index => $multiplier ) {
			$group = $matches[ $index + 1 ] ?? '';

			if ( '' !== $group ) {
				$total += (int) $group * $multiplier;
			}
		}

		return $total;
	}
}
