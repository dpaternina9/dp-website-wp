<?php
/**
 * The `wp dp watch sync` command.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

use DP\Core\Watch\VideoSync;

/**
 * Pulls the Twitch and YouTube archives into `dp_video` posts, on demand.
 *
 * `SeedCommand`'s shape, and for its reason: the class knows nothing about
 * WP-CLI, it takes the two arrays a command is handed and writes through an
 * `Output`, so an integration test can invoke the real command with a
 * `NullOutput` and assert on what it produced.
 *
 * The command is the same code the hourly schedule runs and the same code the
 * "Sync now" button runs. There is one sync.
 */
final class WatchSyncCommand {

	/**
	 * Constructor.
	 *
	 * @param VideoSync $sync   Writes the posts.
	 * @param Output    $output Where the run reports to.
	 */
	public function __construct(
		private readonly VideoSync $sync,
		private readonly Output $output
	) {}

	/**
	 * Import every Twitch VOD and YouTube upload the configured channels list.
	 *
	 * Safe to run twice: a video is keyed by its platform identifier, so a second
	 * run updates what the first one made instead of duplicating it, and reports
	 * everything as unchanged if nothing moved.
	 *
	 * Fields you have edited in the editor are never written over. The first time
	 * a sync finds a field holding something other than what it last put there,
	 * that field becomes yours permanently and the command says how many did.
	 *
	 * A video the platform no longer lists is set to draft rather than deleted,
	 * so nothing you wrote about it is lost and it comes back if the video does.
	 *
	 * Credentials live on Settings → General. With none configured this reports
	 * that and writes nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import now rather than waiting for the hourly schedule.
	 *     $ wp dp watch sync
	 *
	 * @param array<int, string>         $args       Positional arguments. None are taken.
	 * @param array<string, string|bool> $assoc_args Associative arguments. None are taken.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$report = $this->sync->run();

		foreach ( $report->lines() as $line ) {
			$this->output->line( $line );
		}

		if ( $report->ok() ) {
			$this->output->success( $report->summary() );

			return;
		}

		$this->output->warning( $report->summary() );
	}
}
