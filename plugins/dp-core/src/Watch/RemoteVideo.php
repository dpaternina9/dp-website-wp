<?php
/**
 * One video as a platform describes it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\VideoSource;

/**
 * What a sync learned about one remote video, before any of it becomes a post.
 *
 * The two platforms answer different shapes — Twitch's `duration` is `3h8m33s`
 * and YouTube's is `PT18M4S`, one carries a thumbnail template and the other
 * carries none — and this is where that stops mattering. `Helix` and `YouTube`
 * each parse their own responses into this; `VideoSync` reads only this and
 * never learns which API it came from beyond the enum.
 *
 * WordPress-free on purpose, so the mapping half of the sync is unit-testable
 * without a bootstrap and without a network.
 *
 * **There is no note.** The design's card carries one line under the title, and
 * David decided it is his line: no API text is imported into it, ever. A synced
 * card renders without a note until he writes one, and `AuthorEdits` protects it
 * once he has.
 */
final class RemoteVideo {

	/**
	 * Constructor.
	 *
	 * @param VideoSource $source    Which platform answered.
	 * @param string      $id        The platform's own identifier for the video.
	 * @param string      $title     The title, as the platform has it.
	 * @param int         $duration  Runtime in seconds. Zero when the platform did not say.
	 * @param int         $published Unix timestamp of publication. Zero when the platform did not say.
	 * @param string      $thumbnail A thumbnail URL template, or '' when the platform's images are addressable without one.
	 */
	public function __construct(
		public readonly VideoSource $source,
		public readonly string $id,
		public readonly string $title,
		public readonly int $duration,
		public readonly int $published,
		public readonly string $thumbnail = ''
	) {}

	/**
	 * The stable key this video is upserted against.
	 *
	 * Platform plus identifier, because a Twitch VOD id and a YouTube video id
	 * are each unique only within their own platform.
	 *
	 * @return string
	 */
	public function key(): string {
		return self::key_for( $this->source, $this->id );
	}

	/**
	 * The key a platform and an identifier make.
	 *
	 * @param VideoSource $source The platform.
	 * @param string      $id     The platform's identifier.
	 * @return string
	 */
	public static function key_for( VideoSource $source, string $id ): string {
		return $source->value . ':' . $id;
	}

	/**
	 * The prefix every key from one platform starts with.
	 *
	 * @param VideoSource $source The platform.
	 * @return string
	 */
	public static function key_prefix( VideoSource $source ): string {
		return $source->value . ':';
	}
}
