/**
 * The decimal year the timeline is drawn from, as a year and a month.
 *
 * `DP\Core\Content\Year` pins the encoding: the fractional part is the month in
 * twelfths, so 2026.4 reads as May 2026 and 2026.6 as August. Nothing about
 * that is guessable from a number box, which is why `dp_start` and `dp_end` get
 * a control instead of a binding.
 *
 * These two functions are the JavaScript half of `Year::month()` and
 * `Year::from_year_month()`, and they are pure so a test can hold them against
 * the PHP without a browser. The rounding in `toParts` is not cosmetic: 2026 +
 * 4/12 is stored as 2026.3333333333330, whose fraction times twelve is
 * 3.999999999996, and flooring that directly reports April for a value built as
 * May. `Year::month()` rounds to nine places for the same reason.
 *
 * Zero is the sentinel for "no date yet" — the field's registered default — and
 * arrives here as an empty year rather than as the year 0.
 */

/**
 * Months in a year.
 *
 * @type {number}
 */
export const MONTHS = 12;

/**
 * Split a decimal year into the year and the 1-based month it encodes.
 *
 * @param {number|string} value The stored decimal year.
 * @return {{year: string, month: number}} The year as typed, and the month.
 */
export function toParts( value ) {
	const decimal = Number( value );

	if ( ! Number.isFinite( decimal ) || decimal <= 0 ) {
		return { year: '', month: 1 };
	}

	const year = Math.floor( decimal );
	const months = Math.round( ( decimal - year ) * MONTHS * 1e9 ) / 1e9;
	const month = Math.min( MONTHS, Math.max( 1, Math.floor( months ) + 1 ) );

	return { year: String( year ), month };
}

/**
 * Build a decimal year from a year and a 1-based month.
 *
 * @param {number|string} year  The calendar year, or an empty string for unset.
 * @param {number|string} month The month, 1 to 12.
 * @return {number} The decimal year, or 0 when there is no year.
 */
export function toDecimal( year, month ) {
	const calendar = Number( year );

	if ( ! Number.isFinite( calendar ) || calendar <= 0 || '' === year ) {
		return 0;
	}

	const index = Math.min( MONTHS, Math.max( 1, Number( month ) || 1 ) );

	return calendar + ( index - 1 ) / MONTHS;
}
