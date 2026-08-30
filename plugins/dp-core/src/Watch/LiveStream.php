<?php
/**
 * What Twitch says is on air right now.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * One live stream, as `helix/streams` describes it, reduced to what a card needs.
 *
 * Three fields out of the endpoint's dozen, and the omissions are decisions:
 *
 * - **`title`** is the stream's own title, which is the line David types into
 *   Twitch before he goes on. It is the same fact the live card's heading wants,
 *   which is the whole reason this class exists — see `LiveEntry`.
 * - **`started`** is `started_at`, parsed. The design prints an *elapsed* time
 *   ("1H 12M IN") and Helix reports a *start instant*; keeping the instant and
 *   deriving the elapsed at render is what lets the number stay honest, because
 *   a stored elapsed is wrong the second after it is stored.
 * - **`category`** is `game_name` — Twitch's own label for what is happening,
 *   e.g. "Software and Game Development". It fills the card's note when David
 *   has written none. It is deliberately printed as Twitch words it rather than
 *   wrapped in a sentence: CLAUDE.md section 6 forbids inventing prose that
 *   reads like a fact about David, and a category is a fact he set on Twitch.
 *
 * `viewer_count` and `thumbnail_url` are read by nothing and therefore not
 * carried. The design draws no viewer count, and the live preview image already
 * resolves without Helix through `ThumbnailSource::live_preview_url()`.
 *
 * WordPress-free on purpose, like `Helix` and `Duration`: the elapsed
 * arithmetic is the part most likely to be subtly wrong and least likely to be
 * noticed, so it is unit-testable without a bootstrap.
 */
final class LiveStream {

	/**
	 * Constructor.
	 *
	 * @param string $title    The stream title, or '' when Twitch did not say.
	 * @param int    $started  Unix timestamp of `started_at`, or 0 when unreadable.
	 * @param string $category Twitch's category, or '' when there is none.
	 */
	public function __construct(
		public readonly string $title,
		public readonly int $started,
		public readonly string $category
	) {}

	/**
	 * How long the stream has been running, printed the design's way.
	 *
	 * @param int $now The current Unix time.
	 * @return string e.g. `1H 12M` or `18 MIN`, and '' when there is nothing
	 *                honest to print — no start instant, or none elapsed yet.
	 */
	public function elapsed( int $now ): string {
		if ( $this->started <= 0 ) {
			return '';
		}

		return Duration::format( $now - $this->started );
	}

	/**
	 * The shape this is cached as, inside `LiveStatus`'s transient.
	 *
	 * @return array{title: string, started: int, category: string}
	 */
	public function to_cache(): array {
		return array(
			'title'    => $this->title,
			'started'  => $this->started,
			'category' => $this->category,
		);
	}

	/**
	 * Read one back out of the transient.
	 *
	 * An empty array is how "checked, and not live" is cached, so it answers
	 * null — the same answer as a payload that has been corrupted by a plugin
	 * update or a database restore. Neither is a reason to claim a stream.
	 *
	 * @param array<mixed> $cached Whatever the transient held.
	 * @return self|null
	 */
	public static function from_cache( array $cached ): ?self {
		$title    = $cached['title'] ?? null;
		$started  = $cached['started'] ?? null;
		$category = $cached['category'] ?? null;

		if ( ! is_string( $title ) || ! is_int( $started ) || ! is_string( $category ) ) {
			return null;
		}

		return new self( $title, $started, $category );
	}
}
