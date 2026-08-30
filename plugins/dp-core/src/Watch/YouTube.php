<?php
/**
 * Reading YouTube Data API v3 responses.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\VideoSource;

/**
 * Pure parsing of what the YouTube Data API answers.
 *
 * `Helix`'s counterpart, with `Helix`'s contract: WordPress-free, and `null` for
 * anything that is not the response it was asked to read, so the HTTP layer can
 * treat an unreadable body exactly like a request that never landed.
 *
 * **Why the Data API and not the channel's RSS feed.** The feed is free, needs no
 * key and lists the uploads — but it carries no duration, and the design's card
 * prints one. David chose the API for that reason. It costs a key and three
 * calls per sync, which the three methods here mirror:
 *
 * 1. `channels.list` → the uploads playlist id (`uploads_playlist()`). A
 *    channel's uploads are a real playlist, and it is the only way to page
 *    through every video without the 500-result search quota.
 * 2. `playlistItems.list` → the video ids, fifty at a time (`playlist_page()`).
 * 3. `videos.list` → the title, the publication date and the ISO 8601 duration
 *    for up to fifty ids at once (`videos()`).
 *
 * The third call is also the deletion signal: a video that has been removed or
 * made private is simply absent from its response, which is what
 * `VideoSync` reconciles against.
 */
final class YouTube {

	/**
	 * Not to be instantiated: four related pure functions, namespaced.
	 */
	private function __construct() {}

	/**
	 * The uploads playlist id in a `channels.list` response.
	 *
	 * @param string $body The response body.
	 * @return string|null The playlist id, or null when the body names no channel.
	 */
	public static function uploads_playlist( string $body ): ?string {
		foreach ( self::items( $body ) ?? array() as $channel ) {
			$details = is_array( $channel ) ? ( $channel['contentDetails'] ?? null ) : null;
			$related = is_array( $details ) ? ( $details['relatedPlaylists'] ?? null ) : null;
			$uploads = is_array( $related ) ? ( $related['uploads'] ?? null ) : null;

			if ( is_string( $uploads ) && '' !== $uploads ) {
				return $uploads;
			}
		}

		return null;
	}

	/**
	 * One page of video ids in a `playlistItems.list` response.
	 *
	 * @param string $body The response body.
	 * @return array{ids: list<string>, cursor: string}|null The page, or null when the
	 *                     body is not a playlist listing. The cursor is '' on the last page.
	 */
	public static function playlist_page( string $body ): ?array {
		$items = self::items( $body );

		if ( null === $items ) {
			return null;
		}

		$ids = array();

		foreach ( $items as $item ) {
			$details = is_array( $item ) ? ( $item['contentDetails'] ?? null ) : null;
			$id      = is_array( $details ) ? ( $details['videoId'] ?? null ) : null;

			if ( is_string( $id ) && '' !== $id ) {
				$ids[] = $id;
			}
		}

		$decoded = json_decode( $body, true );
		$cursor  = is_array( $decoded ) ? ( $decoded['nextPageToken'] ?? null ) : null;

		return array(
			'ids'    => $ids,
			'cursor' => is_string( $cursor ) ? $cursor : '',
		);
	}

	/**
	 * The videos in a `videos.list` response.
	 *
	 * YouTube answers a live or upcoming broadcast with a duration of `P0D`,
	 * which parses to zero seconds and prints as no runtime at all. That is
	 * correct: the broadcast has not got one yet, and it will on the next sync.
	 *
	 * @param string $body The response body.
	 * @return list<RemoteVideo>|null The videos, or null when the body is not a videos listing.
	 */
	public static function videos( string $body ): ?array {
		$items = self::items( $body );

		if ( null === $items ) {
			return null;
		}

		$videos = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = $item['id'] ?? null;

			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}

			$snippet = is_array( $item['snippet'] ?? null ) ? $item['snippet'] : array();
			$details = is_array( $item['contentDetails'] ?? null ) ? $item['contentDetails'] : array();

			$videos[] = new RemoteVideo(
				VideoSource::YouTube,
				$id,
				self::text( $snippet['title'] ?? null ),
				Duration::from_iso8601( self::text( $details['duration'] ?? null ) ) ?? 0,
				self::timestamp( $snippet['publishedAt'] ?? null )
			);
		}

		return $videos;
	}

	/**
	 * The `items` array in any Data API response.
	 *
	 * @param string $body The response body.
	 * @return array<int|string, mixed>|null Null when the body is not a listing at all.
	 */
	private static function items( string $body ): ?array {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['items'] ?? null ) ) {
			return null;
		}

		return $decoded['items'];
	}

	/**
	 * One string out of a decoded response, or ''.
	 *
	 * @param mixed $value Whatever was at the key.
	 * @return string
	 */
	private static function text( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}

	/**
	 * One RFC 3339 timestamp out of a decoded response, or 0.
	 *
	 * @param mixed $value Whatever was at the key.
	 * @return int
	 */
	private static function timestamp( mixed $value ): int {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		$parsed = strtotime( $value );

		return false === $parsed ? 0 : $parsed;
	}
}
