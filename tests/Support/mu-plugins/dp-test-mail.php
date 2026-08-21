<?php
/**
 * Plugin Name: dP test mail
 * Description: Answers wp_mail() on the local test site, which has no transport. Never shipped.
 * Version: 0.1.0
 * Requires PHP: 8.4
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support\Mail;

/**
 * Why this exists.
 *
 * The wp-env containers have no mail transport, so `wp_mail()` returns false —
 * which means the contact form's **sent** state is unreachable in a browser, and
 * `tests/e2e/contact.spec.ts` could only ever prove two of the design's three
 * panels. That is a property of Docker, not of the code under test.
 *
 * So this answers the send, at priority 999 and only when nothing else has.
 * The ordering is the whole design:
 *
 * - The **integration suite** attaches its own `pre_wp_mail` filter at priority
 *   10 and returns true or false to decide what the transport did. That value
 *   arrives here already non-null, and this leaves it exactly as it found it —
 *   so `DP\Tests\Integration\Contact\ContactTestCase` still counts every call
 *   and still gets to fail a delivery on purpose.
 * - Nothing else in the project filters `pre_wp_mail`, so on a plain request
 *   the value is null and this reports a successful send.
 *
 * It is mapped into `wp-content/mu-plugins` **only** for the wp-env `tests`
 * environment (`.wp-env.json`), and it refuses to act anywhere that is not a
 * local environment. It is not in either shipped package and `bin/dp-build.sh`
 * never sees it.
 */

add_filter(
	'pre_wp_mail',
	/**
	 * Report a successful send, if nobody else has already answered.
	 *
	 * @param mixed $short_circuit Whatever an earlier filter decided.
	 * @param mixed $attributes    The arguments `wp_mail()` was called with.
	 * @return mixed
	 */
	static function ( mixed $short_circuit, mixed $attributes ): mixed {
		if ( null !== $short_circuit || 'local' !== wp_get_environment_type() ) {
			return $short_circuit;
		}

		/*
		 * Kept so a spec can read back what was sent if it ever needs to.
		 * Capped, because this option is never cleaned up by anything else.
		 */
		$log = get_option( 'dp_test_mail_log' );
		$log = is_array( $log ) ? $log : array();

		$log[] = is_array( $attributes ) ? $attributes : array();

		update_option( 'dp_test_mail_log', array_slice( $log, -20 ), false );

		return true;
	},
	999,
	2
);

add_filter(
	'dp_contact_sender_address',
	/**
	 * Let one browser run own its own rate-limit counter.
	 *
	 * The limiter allows three messages per sender per ten minutes and keys on
	 * `REMOTE_ADDR`, which every Playwright run shares. Without this, the third
	 * `npm run test:e2e` inside ten minutes fails on a gate that is working
	 * perfectly — and the obvious fix, raising the limit for the test site,
	 * would take the gate out of the run altogether.
	 *
	 * So the run identifies itself instead, through the header
	 * `tests/e2e/contact.spec.ts` sets to a fresh value each time. The limiter
	 * stays exactly as it is: still three per sender, still counted, still able
	 * to fail the suite if it stops working. `RateLimiter::fingerprint()`'s own
	 * documentation warns against pointing this filter at a header the client
	 * can set, and it is right — which is why this is a test-support file,
	 * mapped only into the wp-env `tests` environment and inert anywhere that
	 * is not a local environment.
	 *
	 * @param mixed $address The client address as PHP sees it.
	 * @return mixed
	 */
	static function ( mixed $address ): mixed {
		if ( 'local' !== wp_get_environment_type() ) {
			return $address;
		}

		return isset( $_SERVER['HTTP_X_DP_TEST_SENDER'] ) && is_string( $_SERVER['HTTP_X_DP_TEST_SENDER'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_DP_TEST_SENDER'] ) )
			: $address;
	}
);
