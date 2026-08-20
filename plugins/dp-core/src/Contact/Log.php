<?php
/**
 * Where a refused message goes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * Records a refusal as an action and, with `WP_DEBUG` on, as a log line.
 *
 * The same shape as `DP\Core\Update\Log` and for the same reason: a path that
 * fails closed is silent, so the only way to tell "nobody wrote in" from "six
 * people wrote in and were refused" is a signal we emit. Its own class rather
 * than the update log's, because the two answer different questions and a
 * monitor watching one should not have to filter out the other.
 *
 * **Nothing here records what was written.** The gate that closed and, for a
 * delivery failure, what the mail transport said. Not the name, not the
 * address, not the message. A site whose Privacy page is about not keeping
 * things does not get to keep a copy of every refused message in `debug.log`.
 */
final class Log {

	/**
	 * Action fired for every refusal.
	 *
	 * Written out literally at the `do_action()` call as well: WPCS cannot
	 * verify a prefix on a hook name it only sees as `self::ACTION`, and a hook
	 * nobody can grep for is a hook nobody will find.
	 *
	 * @var string
	 */
	public const ACTION = 'dp_core_contact_refused';

	/**
	 * Prefix on the error-log line, so it can be grepped for.
	 *
	 * @var string
	 */
	private const PREFIX = '[dp-core/contact] ';

	/**
	 * Record that a message was refused, and by which gate.
	 *
	 * @param Rejection            $rejection The gate that closed.
	 * @param array<string, mixed> $context   Structured detail. Never the message.
	 * @return void
	 */
	public function refused( Rejection $rejection, array $context = array() ): void {
		/**
		 * Fires when the contact form refuses a submission.
		 *
		 * @since 0.1.0
		 *
		 * @param Rejection            $rejection The gate that closed.
		 * @param array<string, mixed> $context   Structured detail about the refusal.
		 */
		do_action( 'dp_core_contact_refused', $rejection, $context );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$line = self::PREFIX . $rejection->reason();

		if ( array() !== $context ) {
			$encoded = wp_json_encode( $context );
			$line   .= ' ' . ( is_string( $encoded ) ? $encoded : '(context could not be encoded)' );
		}

		/*
		 * error_log() is what WP_DEBUG_LOG exists to capture and what core's own
		 * updater uses for the same job. wp_trigger_error() would raise a PHP
		 * notice instead, which a strict test harness turns into a failure on a
		 * path whose whole point is to fail softly.
		 */
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}
}
