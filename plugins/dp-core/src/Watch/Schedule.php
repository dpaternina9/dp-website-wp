<?php
/**
 * When the sync runs on its own.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * One hourly WP-Cron event, and the only thing that makes the Watch page
 * automatic without anybody pressing anything.
 *
 * **Hourly, and not more often.** A VOD exists the moment a stream ends and an
 * upload the moment it finishes processing, so an hour is the worst case for a
 * video appearing late — which is the right trade against two APIs with quotas
 * and a job that writes posts. The thing people actually watch for, "is he live
 * right now", is not this: `LiveStatus` answers that from a two-minute transient
 * on the render path and is untouched by any of this.
 *
 * **Not more often, and not less, because the failure is invisible either way.**
 * A sync that stops running looks exactly like a channel that stopped
 * publishing. The line on Settings → General names the last run and what it did,
 * which is the cheapest thing that makes a stalled schedule visible; the
 * `dp_core_watch_synced` action is there for anything louder.
 *
 * The event is scheduled the first time this registers and cleared when the
 * plugin is deactivated. Nothing here reschedules on every request: WP-Cron is
 * an option read, and rewriting it per page view would be a write on the read
 * path for no gain.
 */
final class Schedule {

	/**
	 * The cron hook the sync hangs off.
	 *
	 * @var string
	 */
	public const HOOK = 'dp_core_watch_sync';

	/**
	 * How often it runs. One of core's own recurrences, so no filter is needed.
	 *
	 * @var string
	 */
	public const RECURRENCE = 'hourly';

	/**
	 * Constructor.
	 *
	 * @param VideoSync $sync What the event runs.
	 */
	public function __construct( private readonly VideoSync $sync ) {}

	/**
	 * Attach the handler and make sure the event exists.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, $this->run( ... ) );

		$this->ensure_scheduled();
	}

	/**
	 * Schedule the event if it is not already scheduled.
	 *
	 * The first run is an hour out rather than immediate: activating a plugin
	 * should not spend two API quotas before David has typed a credential into
	 * the settings screen he has not opened yet.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, self::RECURRENCE, self::HOOK );
	}

	/**
	 * Run one scheduled sync.
	 *
	 * The report is deliberately dropped here. `VideoSync::run()` has already
	 * written the last-run option and fired `dp_core_watch_synced`, which are the
	 * two places a scheduled run can be observed from; there is no console for it
	 * to print to.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->sync->run();
	}

	/**
	 * Forget the event.
	 *
	 * Called from the plugin's deactivation hook, because a cron entry that
	 * outlives the code it calls is an hourly error in somebody's log.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
