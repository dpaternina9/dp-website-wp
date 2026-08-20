<?php
/**
 * The signed timestamp that makes the timing check meaningful.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * "When was this form drawn?", asked in a way the sender cannot answer freely.
 *
 * The timing check the plan asks for compares how long the form was on screen
 * against how long a person needs to fill it in. That comparison is only worth
 * making if the "when" came from us: a bare hidden `<input value="1750000000">`
 * is edited by whatever posted it, so a bot would simply send a timestamp old
 * enough to look human and the whole check would be theatre.
 *
 * So the value is `{issued}.{hmac}`, signed with `wp_hash()` — which keys off
 * the site's own salts, meaning a stamp cannot be minted anywhere else and
 * cannot be moved between sites. `hash_equals()` compares it, because a
 * timing-safe comparison is free and the alternative is a class of bug nobody
 * finds by reading.
 *
 * There is no nonce-style expiry here on purpose. Old stamps are rejected by
 * `MAX_AGE` and the nonce beside them already expires; two overlapping expiry
 * windows on one form is two things to explain when a submission is refused.
 */
final class Stamp {

	/**
	 * The shortest time a person could plausibly have taken, in seconds.
	 *
	 * Three seconds is under a slow reader's reaction time to the first field
	 * and far over what a form-filling script spends, which is the gap the
	 * check exists to sit in.
	 *
	 * @var int
	 */
	public const MIN_AGE = 3;

	/**
	 * The longest a drawn form stays acceptable, in seconds.
	 *
	 * Twelve hours matches the first half of a WordPress nonce's life, so the
	 * stamp never outlives the nonce sitting next to it in the same form.
	 *
	 * @var int
	 */
	public const MAX_AGE = 12 * HOUR_IN_SECONDS;

	/**
	 * What `wp_hash()` is asked to sign, so a stamp is only ever a stamp.
	 *
	 * @var string
	 */
	private const SCHEME = 'dp_contact_stamp|';

	/**
	 * Constructor.
	 *
	 * @param int $now The current Unix time. Injected so the tests can move it.
	 */
	public function __construct( private readonly int $now ) {}

	/**
	 * A stamp for a form being drawn now.
	 *
	 * @return string
	 */
	public function issue(): string {
		return $this->sign( $this->now );
	}

	/**
	 * How old a stamp is, or null when it was not one of ours.
	 *
	 * @param string $stamp The value that came back with the submission.
	 * @return int|null Age in seconds, or null when the signature does not verify.
	 */
	public function age( string $stamp ): ?int {
		$parts = explode( '.', $stamp, 2 );

		if ( 2 !== count( $parts ) || '' === $parts[0] || ! ctype_digit( $parts[0] ) ) {
			return null;
		}

		$issued = (int) $parts[0];

		if ( ! hash_equals( $this->sign( $issued ), $stamp ) ) {
			return null;
		}

		return $this->now - $issued;
	}

	/**
	 * Whether a stamp is one of ours and old enough to have been typed into.
	 *
	 * @param string $stamp The value that came back with the submission.
	 * @return bool
	 */
	public function is_plausible( string $stamp ): bool {
		$age = $this->age( $stamp );

		return null !== $age && $age >= self::MIN_AGE && $age <= self::MAX_AGE;
	}

	/**
	 * Sign one issue time.
	 *
	 * @param int $issued Unix time the form was drawn.
	 * @return string
	 */
	private function sign( int $issued ): string {
		return $issued . '.' . wp_hash( self::SCHEME . $issued );
	}
}
