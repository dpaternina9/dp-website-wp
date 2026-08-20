<?php
/**
 * Where a failed rendering goes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

/**
 * Records that a PDF could not be rendered, as an action and as a log line.
 *
 * The same shape as the update log and the contact log, and here for the same
 * reason: preferring a stale PDF to no PDF is, by construction, a failure
 * nobody sees. Without this, a renderer that has been down for a month looks
 * exactly like one that is working, right up until somebody notices the PDF is
 * missing a job.
 */
final class Log {

	/**
	 * Action fired for every failed rendering.
	 *
	 * @var string
	 */
	public const ACTION = 'dp_core_resume_render_failed';

	/**
	 * Prefix on the error-log line, so it can be grepped for.
	 *
	 * @var string
	 */
	private const PREFIX = '[dp-core/resume] ';

	/**
	 * Record that a rendering failed.
	 *
	 * @param string $message Why, in English, for a human reading a log.
	 * @return void
	 */
	public function failed( string $message ): void {
		/**
		 * Fires when the résumé PDF could not be rendered.
		 *
		 * The stale copy — or the print view — is served regardless, so this is
		 * the only signal that anything went wrong.
		 *
		 * @since 0.1.0
		 *
		 * @param string $message Why the rendering failed.
		 */
		do_action( 'dp_core_resume_render_failed', $message );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		/*
		 * error_log() is what WP_DEBUG_LOG captures. wp_trigger_error() would
		 * raise a PHP notice on a path whose whole purpose is to fail softly.
		 */
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PREFIX . $message );
	}
}
