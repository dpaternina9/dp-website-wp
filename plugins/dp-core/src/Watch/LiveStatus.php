<?php
/**
 * Whether the stream is on.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * One cached answer to "what is David streaming right now", shared by both
 * Watch blocks.
 *
 * The two blocks have to agree — the featured panel decides *what* it features
 * with this answer, and the grid decides whether the first archive entry is
 * already up top — so the answer is decided at most once per request: the
 * first reader fills the transient and the second reads it back.
 *
 * **The transient holds the stream, not a yes/no.** It used to cache the string
 * `'yes'` or `'no'`, which meant the live card's copy had to come from somewhere
 * else — and the only somewhere else was a `dp_video` post David typed by hand.
 * One `helix/streams` response already carries the title, the start instant and
 * the category next to the proof that the channel is on air, so caching the
 * whole thing costs the same request and removes the hand-managed step. The
 * shape is internal: nothing outside this package reads it, and a value cached
 * in the old shape simply misses and is refetched.
 *
 * Failing soft is the whole contract (plan Phase 12): no login configured, no
 * credentials, or no answer from Helix all read as "not live", and the page
 * simply shows its archive. "Not live" is also what gets cached when Helix
 * did not answer, so an outage costs one slow-ish request every couple of
 * minutes rather than one per visitor.
 */
final class LiveStatus {

	/**
	 * The transient holding the cached stream.
	 *
	 * An array describing the stream while one is on air, and an empty array for
	 * "checked, and not live" — which is a cache hit, unlike the `false` that
	 * `get_transient()` answers for a miss.
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
	 * Whether the configured channel is live.
	 *
	 * Defined as "there is a stream" rather than answered separately, so the
	 * grid's question and the panel's question cannot come apart.
	 *
	 * @return bool
	 */
	public function live(): bool {
		return null !== $this->stream();
	}

	/**
	 * What the configured channel is streaming: the cache, then Helix, then nothing.
	 *
	 * Deliberately not memoized on the instance — the object outlives the
	 * request under a long-running runtime, and the transient below is
	 * already the cache. Within one render the second reader hits it.
	 *
	 * @return LiveStream|null
	 */
	public function stream(): ?LiveStream {
		$login = Settings::login();

		if ( '' === $login ) {
			return null;
		}

		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return LiveStream::from_cache( $cached );
		}

		$stream = $this->api->live_stream( $login );

		/*
		 * A failed call caches the empty array too. The alternative — leaving the
		 * cache cold on failure — turns a Twitch outage into one slow upstream
		 * call per page view, which is the failure mode this class exists to
		 * avoid. Two minutes later it tries again.
		 */
		set_transient( self::TRANSIENT, null === $stream ? array() : $stream->to_cache(), self::TTL );

		return $stream;
	}
}
