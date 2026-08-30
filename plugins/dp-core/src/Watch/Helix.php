<?php
/**
 * Reading Twitch Helix responses.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\VideoSource;

/**
 * Pure parsing of what the Twitch API answers.
 *
 * Deliberately WordPress-free, like `Timeline\Geometry` and for the same
 * reason: everything here is testable without a bootstrap, so the HTTP layer
 * (`TwitchApi`) stays a transcription — fetch, hand the body here, cache what
 * comes back.
 *
 * Every method accepts the raw response body and answers `null` for anything
 * malformed, because the caller's contract is "failing soft": a Helix response
 * that cannot be read is treated exactly like a request that never landed.
 */
final class Helix {

	/**
	 * The width a substituted thumbnail template asks for.
	 *
	 * The design's tiles are 16:9 and the largest one renders around 640px
	 * wide in a 1320px container; 1280×720 is the size the design itself
	 * names for the live preview, so the VODs match it.
	 *
	 * @var int
	 */
	public const THUMB_WIDTH = 1280;

	/**
	 * The height a substituted thumbnail template asks for.
	 *
	 * @var int
	 */
	public const THUMB_HEIGHT = 720;

	/**
	 * Not to be instantiated: two related pure functions, namespaced.
	 */
	private function __construct() {}

	/**
	 * The app access token in an `oauth2/token` response.
	 *
	 * @param string $body The response body.
	 * @return array{token: non-empty-string, expires: int}|null The token and its
	 *                                          lifetime in seconds, or null.
	 */
	public static function token( string $body ): ?array {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$token   = $decoded['access_token'] ?? null;
		$expires = $decoded['expires_in'] ?? null;

		if ( ! is_string( $token ) || '' === $token || ! is_numeric( $expires ) ) {
			return null;
		}

		return array(
			'token'   => $token,
			'expires' => (int) $expires,
		);
	}

	/**
	 * The live stream in a `helix/streams` response, if the channel has one.
	 *
	 * One call answers both questions the Watch page has — *is he live* and
	 * *what is he streaming* — because Helix returns the stream's title,
	 * `started_at` and category in the same object that proves it is on air.
	 * Asking twice would be two round trips for one fact.
	 *
	 * The three "no" cases converge deliberately. Helix answers an offline
	 * channel with `{"data": []}`; a refused request answers something that is
	 * not a streams response at all; a listed entry whose `type` is not `live`
	 * is a rerun rather than a broadcast. All three are `null` here, and the
	 * caller treats every one of them as "the panel does not render" — which is
	 * the failing-soft contract this class exists to keep. Nothing downstream
	 * needs to distinguish "offline" from "did not answer", so nothing here
	 * pretends it can.
	 *
	 * @param string $body The response body.
	 * @return LiveStream|null The stream, or null for offline and for anything
	 *                         that could not be read.
	 */
	public static function stream( string $body ): ?LiveStream {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['data'] ?? null ) ) {
			return null;
		}

		foreach ( $decoded['data'] as $stream ) {
			if ( ! is_array( $stream ) || 'live' !== ( $stream['type'] ?? null ) ) {
				continue;
			}

			return new LiveStream(
				self::text( $stream['title'] ?? null ),
				self::timestamp( $stream['started_at'] ?? null ),
				self::text( $stream['game_name'] ?? null )
			);
		}

		return null;
	}

	/**
	 * The thumbnail URL templates in a `helix/videos` response, by video id.
	 *
	 * A VOD still being processed carries an empty `thumbnail_url`; it is
	 * omitted here rather than returned, so the caller's negative cache treats
	 * it as "not available yet" and asks again later.
	 *
	 * @param string $body The response body.
	 * @return array<string, non-empty-string>|null Id to URL template, or null
	 *                                              when the body is not a videos
	 *                                              response at all.
	 */
	public static function vod_thumbnails( string $body ): ?array {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['data'] ?? null ) ) {
			return null;
		}

		$templates = array();

		foreach ( $decoded['data'] as $video ) {
			if ( ! is_array( $video ) ) {
				continue;
			}

			$id       = $video['id'] ?? null;
			$template = $video['thumbnail_url'] ?? null;

			if ( is_string( $id ) && '' !== $id && is_string( $template ) && '' !== $template ) {
				$templates[ $id ] = $template;
			}
		}

		return $templates;
	}

	/**
	 * The numeric user id in a `helix/users` response.
	 *
	 * The archive endpoint is keyed by user id, and the only thing David types
	 * is a login, so every sync starts here.
	 *
	 * @param string $body The response body.
	 * @return string|null The id, or null when the body names no user — a login
	 *                     that does not exist answers `{"data": []}`.
	 */
	public static function user_id( string $body ): ?string {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['data'] ?? null ) ) {
			return null;
		}

		foreach ( $decoded['data'] as $user ) {
			$id = is_array( $user ) ? ( $user['id'] ?? null ) : null;

			if ( is_string( $id ) && '' !== $id ) {
				return $id;
			}
		}

		return null;
	}

	/**
	 * One page of a `helix/videos` archive listing.
	 *
	 * A VOD with no id is dropped rather than returned half-built; everything
	 * else survives with whatever fields Twitch supplied, because a missing
	 * duration or publication date is a caption the card omits and not a reason
	 * to lose the video.
	 *
	 * @param string $body The response body.
	 * @return array{videos: list<RemoteVideo>, cursor: string}|null The page, or null
	 *                     when the body is not a videos response at all. The cursor
	 *                     is '' on the last page.
	 */
	public static function archive( string $body ): ?array {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! is_array( $decoded['data'] ?? null ) ) {
			return null;
		}

		$videos = array();

		foreach ( $decoded['data'] as $video ) {
			if ( ! is_array( $video ) ) {
				continue;
			}

			$id = $video['id'] ?? null;

			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}

			$videos[] = new RemoteVideo(
				VideoSource::Twitch,
				$id,
				self::text( $video['title'] ?? null ),
				Duration::from_twitch( self::text( $video['duration'] ?? null ) ) ?? 0,
				self::timestamp( $video['published_at'] ?? null ),
				self::text( $video['thumbnail_url'] ?? null )
			);
		}

		$pagination = $decoded['pagination'] ?? null;
		$cursor     = is_array( $pagination ) ? ( $pagination['cursor'] ?? null ) : null;

		return array(
			'videos' => $videos,
			'cursor' => is_string( $cursor ) ? $cursor : '',
		);
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

	/**
	 * Substitute the size placeholders in a thumbnail URL template.
	 *
	 * Helix hands back literal `%{width}` and `%{height}` — digest section 3.5
	 * names them — and the result is only a URL once both are gone.
	 *
	 * @param string $template The URL template.
	 * @return string The URL, at the size the Watch tiles use.
	 */
	public static function fill_thumbnail_template( string $template ): string {
		return str_replace(
			array( '%{width}', '%{height}' ),
			array( (string) self::THUMB_WIDTH, (string) self::THUMB_HEIGHT ),
			$template
		);
	}
}
