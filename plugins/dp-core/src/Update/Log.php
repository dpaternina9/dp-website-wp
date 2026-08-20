<?php
/**
 * Where a refused update goes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/**
 * Records a refusal twice: as an action, and as a line in the PHP error log.
 *
 * The action is the part that matters. An update client that fails closed is
 * silent by construction — nothing appears in the admin, because nothing is
 * being offered — so the only way to tell "no update available" from "an update
 * was offered and rejected" is a signal we emit ourselves. `do_action` makes
 * that signal observable by a test without touching the filesystem, and gives
 * a future monitoring hook somewhere to attach.
 *
 * The `error_log()` line is the operator-facing half, guarded the way
 * `WP_Automatic_Updater` guards its own logging: only when `WP_DEBUG` is on.
 */
final class Log {

	/**
	 * Action fired for every refusal.
	 *
	 * Written out literally at the `do_action()` call as well: WPCS cannot
	 * verify a plugin prefix on a hook name it only sees as `self::ACTION`, and
	 * a hook you cannot grep for by name is a hook nobody will find.
	 */
	public const ACTION = 'dp_core_update_refused';

	/**
	 * Prefix on the error-log line, so it can be grepped for.
	 */
	private const PREFIX = '[dp-core/update] ';

	/**
	 * Record that an update was refused, and why.
	 *
	 * @param string               $message Reason, in English, for a human reading a log.
	 * @param array<string, mixed> $context Structured detail. Never contains secrets.
	 * @return void
	 */
	public function refused( string $message, array $context = array() ): void {
		/**
		 * Fires when the update client declines to offer an update.
		 *
		 * Failing closed is silent by design; this is the sound it makes.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $message Reason the update was refused.
		 * @param array<string, mixed> $context Structured detail about the refusal.
		 */
		do_action( 'dp_core_update_refused', $message, $context );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$line = self::PREFIX . $message;

		if ( array() !== $context ) {
			$encoded = wp_json_encode( $context );
			$line   .= ' ' . ( is_string( $encoded ) ? $encoded : '(context could not be encoded)' );
		}

		/*
		 * error_log() is the mechanism WP_DEBUG_LOG exists to capture, and it is
		 * what WP_Automatic_Updater itself uses for the same job. There is no
		 * WordPress API that writes to debug.log; wp_trigger_error() raises a PHP
		 * notice instead, which a fatal-error handler or a strict test harness
		 * would turn into a failure on a path whose whole point is to fail softly.
		 */
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}
}
