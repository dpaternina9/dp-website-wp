<?php
/**
 * A span of months, said briefly.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * "Dec 2017", "Nov – Dec 2017", "Dec 2017 – Jan 2018".
 *
 * Month precision on purpose: a trip is remembered by when it was, not by which
 * day the first and last photos happened to be taken. Month names come from
 * `mysql2date()`, so they follow the site's language.
 */
final class DateRange {

	/**
	 * The label for a span.
	 *
	 * @param string $first The earliest date, `Y-m-d H:i:s`.
	 * @param string $last  The latest date, `Y-m-d H:i:s`.
	 * @return string The label, or '' when either date is missing.
	 */
	public static function label( string $first, string $last ): string {
		if ( '' === $first || '' === $last ) {
			return '';
		}

		if ( $first > $last ) {
			list( $first, $last ) = array( $last, $first );
		}

		$first_year  = substr( $first, 0, 4 );
		$first_month = substr( $first, 0, 7 );

		if ( substr( $last, 0, 7 ) === $first_month ) {
			return self::format( 'M Y', $last );
		}

		/* translators: 1: the first month, e.g. "Nov" or "Dec 2017". 2: the last, e.g. "Dec 2017". */
		$span = __( '%1$s – %2$s', 'dp-core' );

		if ( substr( $last, 0, 4 ) === $first_year ) {
			return sprintf( $span, self::format( 'M', $first ), self::format( 'M Y', $last ) );
		}

		return sprintf( $span, self::format( 'M Y', $first ), self::format( 'M Y', $last ) );
	}

	/**
	 * The year span of a set: "2017–2019", or "2018".
	 *
	 * @param string $first The earliest date, `Y-m-d H:i:s`.
	 * @param string $last  The latest date, `Y-m-d H:i:s`.
	 * @return string The span, or '' when either date is missing.
	 */
	public static function years( string $first, string $last ): string {
		if ( '' === $first || '' === $last ) {
			return '';
		}

		$from = substr( min( $first, $last ), 0, 4 );
		$to   = substr( max( $first, $last ), 0, 4 );

		return $from === $to ? $from : $from . '–' . $to;
	}

	/**
	 * One date in a format, localised.
	 *
	 * @param string $format A PHP date format.
	 * @param string $date   `Y-m-d H:i:s`.
	 * @return string
	 */
	private static function format( string $format, string $date ): string {
		$formatted = mysql2date( $format, $date );

		return is_string( $formatted ) ? $formatted : '';
	}
}
