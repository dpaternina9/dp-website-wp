<?php
/**
 * How many messages one sender may send in a window.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * A transient counter per sender, keyed on a hash and never on an address.
 *
 * Two properties matter more than the numbers.
 *
 * **No IP address is ever stored.** The key is `wp_hash()` of the address, so
 * what lands in `wp_options` cannot be read back into an address, and the
 * Privacy page's claim that nothing here identifies a reader survives the one
 * feature that had an excuse to break it. A hash is not anonymity — a short
 * list of candidate addresses can be tested against it — but it is the
 * difference between a log of who wrote in and a counter that forgets.
 *
 * **It counts attempts that got as far as the mailer.** The handler runs this
 * gate after the nonce, the capability, the honeypot, the timing check and the
 * field validation, so a mistyped address does not spend one of the sender's
 * three slots and a flood of forged nonces cannot lock out the address it is
 * spoofing.
 *
 * Transients rather than a table: the counter is worthless after the window
 * closes, and a transient is the WordPress object that already knows that.
 * Under an object cache it never touches the database at all.
 */
final class RateLimiter {

	/**
	 * How many messages one sender may send per window, by default.
	 *
	 * @var int
	 */
	public const LIMIT = 3;

	/**
	 * How long the window is, in seconds.
	 *
	 * @var int
	 */
	public const WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Prefix on the transient name.
	 *
	 * @var string
	 */
	private const PREFIX = 'dp_contact_rl_';

	/**
	 * Count one attempt, and say whether it is allowed.
	 *
	 * @param string $sender An opaque sender key, from `fingerprint()`.
	 * @return bool True when this attempt is within the limit.
	 */
	public function allow( string $sender ): bool {
		$key   = self::PREFIX . $sender;
		$count = get_transient( $key );
		$count = is_numeric( $count ) ? (int) $count : 0;

		if ( $count >= $this->limit() ) {
			return false;
		}

		/*
		 * The window is not extended by a second attempt: the transient is only
		 * given a fresh expiry when the count starts, so three messages in the
		 * first minute do not lock the sender out for ten minutes from the
		 * third. `set_transient()` with an existing key resets the expiry, so
		 * the remaining life is read back and re-used.
		 */
		set_transient( $key, $count + 1, 0 === $count ? $this->window() : $this->remaining( $key ) );

		return true;
	}

	/**
	 * Forget one sender's count. Test support, and the "undo" for a false positive.
	 *
	 * @param string $sender An opaque sender key, from `fingerprint()`.
	 * @return void
	 */
	public function forget( string $sender ): void {
		delete_transient( self::PREFIX . $sender );
	}

	/**
	 * An opaque, non-reversible key for whoever is making this request.
	 *
	 * @return string
	 */
	public static function fingerprint(): string {
		return substr( wp_hash( 'dp_contact_sender|' . self::sender_address() ), 0, 32 );
	}

	/**
	 * The sender's address as this site believes it, before it is hashed.
	 *
	 * Split out of `fingerprint()` rather than duplicated because "who is this
	 * request from" is one question with one answer, and the proxy filter below
	 * is the whole of what makes that answer right on a site that terminates TLS
	 * somewhere else. The rate limiter never sees this — it takes the hash — but
	 * `Turnstile` has to hand Cloudflare a real address for `remoteip`, and a
	 * hash would silently degrade the scoring it exists to feed.
	 *
	 * Everything the fingerprint's docblock says about not storing an address
	 * still holds: this value is used and discarded within one request.
	 *
	 * @return string
	 */
	public static function sender_address(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/**
		 * Filters the raw string the rate limiter fingerprints.
		 *
		 * Behind a proxy `REMOTE_ADDR` is the proxy, and every sender then
		 * shares one counter. Sites that terminate TLS elsewhere point this at
		 * whichever header their edge sets and trusts — never at a header the
		 * client can set, which is why there is no default here beyond the
		 * connection's own address.
		 *
		 * @since 0.1.0
		 *
		 * @param string $raw The client address as PHP sees it.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		return (string) apply_filters( 'dp_contact_sender_address', $raw );
	}

	/**
	 * How many are allowed per window.
	 *
	 * @return int
	 */
	private function limit(): int {
		/**
		 * Filters how many messages one sender may send per window.
		 *
		 * @since 0.1.0
		 *
		 * @param int $limit Messages per window. Default 3.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		return max( 1, (int) apply_filters( 'dp_contact_rate_limit', self::LIMIT ) );
	}

	/**
	 * How long the window is.
	 *
	 * @return int
	 */
	private function window(): int {
		/**
		 * Filters the rate-limit window, in seconds.
		 *
		 * @since 0.1.0
		 *
		 * @param int $window Seconds. Default 600.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		return max( 60, (int) apply_filters( 'dp_contact_rate_window', self::WINDOW ) );
	}

	/**
	 * Seconds left on an existing window.
	 *
	 * Under an external object cache a transient's expiry is not an option row,
	 * so this cannot see it and answers with a full window instead. The effect
	 * is that a second message inside the window can extend it — the fail-safe
	 * direction, and invisible at three messages per ten minutes.
	 *
	 * @param string $key The transient name.
	 * @return int
	 */
	private function remaining( string $key ): int {
		$expires = get_option( '_transient_timeout_' . $key );
		$left    = is_numeric( $expires ) ? (int) $expires - time() : 0;

		return $left > 0 ? $left : $this->window();
	}
}
