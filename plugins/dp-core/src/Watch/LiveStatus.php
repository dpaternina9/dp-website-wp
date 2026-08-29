<?php
/**
 * Whether the stream is on.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * One cached answer to "is David live right now", shared by both Watch blocks.
 *
 * The two blocks have to agree — the featured panel decides *what* it features
 * with this answer, and the grid decides whether the first archive entry is
 * already up top — so the answer is decided at most once per request: the
 * first reader fills the transient and the second reads it back.
 *
 * Failing soft is the whole contract (plan Phase 12): no login configured, no
 * credentials, or no answer from Helix all read as "not live", and the page
 * simply shows its archive. "Not live" is also what gets cached when Helix
 * did not answer, so an outage costs one slow-ish request every couple of
 * minutes rather than one per visitor.
 */
final class LiveStatus {

	/**
	 * The transient holding the cached answer, as 'yes' or 'no'.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'dp_watch_live';

	/**
	 * How long an answer holds. Two minutes keeps "went live" reasonably
	 * fresh without a Helix call per page view.
	 *
	 * @var int
	 */
	private const TTL = 2 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param TwitchApi $api The Helix client.
	 */
	public function __construct( private readonly TwitchApi $api = new TwitchApi() ) {}

	/**
	 * Whether the configured channel is live: the cache, then Helix, then no.
	 *
	 * Deliberately not memoized on the instance — the object outlives the
	 * request under a long-running runtime, and the transient below is
	 * already the cache. Within one render the second reader hits it.
	 *
	 * @return bool
	 */
	public function live(): bool {
		$login = Settings::login();

		if ( '' === $login ) {
			return false;
		}

		$cached = get_transient( self::TRANSIENT );

		if ( 'yes' === $cached || 'no' === $cached ) {
			return 'yes' === $cached;
		}

		$live = $this->api->is_live( $login ) ?? false;

		set_transient( self::TRANSIENT, $live ? 'yes' : 'no', self::TTL );

		return $live;
	}
}
