<?php
/**
 * The YouTube Data API HTTP layer.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * Talks to the YouTube Data API v3, on the sync path and nowhere else.
 *
 * `TwitchApi`'s counterpart, with two differences worth stating because they
 * are the reason this is a separate class rather than a branch inside that one:
 *
 * - **There is no token exchange.** A Data API key is a key; it goes on the
 *   request as `key=` and there is nothing to refresh. That is also why the key
 *   appears in the request URL — it is the form the Data API documents, and a
 *   read-only key restricted to this API in the Google console is what the
 *   settings field asks for.
 * - **Nothing here is on a page render.** Twitch's client is called while a
 *   visitor waits, so it has a three-second budget and a negative cache.
 *   Uploads are only ever read by the sync, so the timeout is a background
 *   job's and there is no cache but the transient holding the uploads playlist
 *   id — which never changes for a channel, and would otherwise cost a call per
 *   sync forever.
 *
 * The contract is `TwitchApi`'s exactly: `null` means "YouTube did not answer",
 * never a `WP_Error` and never an exception, and a partial answer is discarded
 * rather than returned — the caller reconciles deletions against this list, so
 * half a list would read as half a channel being deleted.
 */
final class YouTubeApi {

	/**
	 * The channels endpoint, answering the uploads playlist id.
	 *
	 * @var string
	 */
	public const CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

	/**
	 * The playlist-items endpoint, listing the uploads.
	 *
	 * @var string
	 */
	public const PLAYLIST_ITEMS_URL = 'https://www.googleapis.com/youtube/v3/playlistItems';

	/**
	 * The videos endpoint, answering titles, dates and durations.
	 *
	 * @var string
	 */
	public const VIDEOS_URL = 'https://www.googleapis.com/youtube/v3/videos';

	/**
	 * Prefix on the transient holding a channel's uploads playlist id.
	 *
	 * @var string
	 */
	public const UPLOADS_TRANSIENT = 'dp_watch_yt_uploads_';

	/**
	 * How long the uploads playlist id is kept.
	 *
	 * It never changes while the channel exists, so the TTL is only there so a
	 * channel David re-points the setting at is not answered from the old one
	 * forever.
	 *
	 * @var int
	 */
	private const UPLOADS_TTL = WEEK_IN_SECONDS;

	/**
	 * How long one Data API request may take, in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 10;

	/**
	 * How many videos one page asks for. Fifty is the Data API's maximum.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 50;

	/**
	 * How many upload pages one sync will read.
	 *
	 * @var int
	 */
	private const MAX_PAGES = 5;

	/**
	 * Every video on the configured channel's uploads playlist.
	 *
	 * Three kinds of call, in order: the uploads playlist id, the ids on it, and
	 * then the details for those ids fifty at a time. The last call is also what
	 * makes a deleted or newly-private video disappear — it is simply not in the
	 * response, and the caller reconciles against that.
	 *
	 * @return list<RemoteVideo>|null The uploads, or null when YouTube did not
	 *                                answer all of it.
	 */
	public function videos(): ?array {
		$key      = Settings::youtube_key();
		$playlist = $this->uploads_playlist();

		if ( '' === $key || null === $playlist ) {
			return null;
		}

		$ids = $this->playlist_ids( $playlist, $key );

		if ( null === $ids ) {
			return null;
		}

		$videos = array();

		foreach ( array_chunk( $ids, self::PAGE_SIZE ) as $chunk ) {
			$body = $this->get(
				add_query_arg(
					array(
						'part'       => 'snippet,contentDetails',
						'id'         => implode( ',', $chunk ),
						'maxResults' => (string) self::PAGE_SIZE,
						'key'        => $key,
					),
					self::VIDEOS_URL
				)
			);

			if ( null === $body ) {
				return null;
			}

			$read = YouTube::videos( $body );

			if ( null === $read ) {
				return null;
			}

			foreach ( $read as $video ) {
				$videos[] = $video;
			}
		}

		return $videos;
	}

	/**
	 * Every video id on one playlist, in the order YouTube lists them.
	 *
	 * @param string $playlist The uploads playlist id.
	 * @param string $key      The Data API key.
	 * @return list<string>|null Null when any page failed.
	 */
	private function playlist_ids( string $playlist, string $key ): ?array {
		$ids    = array();
		$cursor = '';

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$arguments = array(
				'part'       => 'contentDetails',
				'playlistId' => $playlist,
				'maxResults' => (string) self::PAGE_SIZE,
				'key'        => $key,
			);

			if ( '' !== $cursor ) {
				$arguments['pageToken'] = $cursor;
			}

			$body = $this->get( add_query_arg( $arguments, self::PLAYLIST_ITEMS_URL ) );

			if ( null === $body ) {
				return null;
			}

			$read = YouTube::playlist_page( $body );

			if ( null === $read ) {
				return null;
			}

			foreach ( $read['ids'] as $id ) {
				$ids[] = $id;
			}

			$cursor = $read['cursor'];

			if ( '' === $cursor || array() === $read['ids'] ) {
				break;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * The configured channel's uploads playlist id, from the transient or from YouTube.
	 *
	 * A channel is named either by its `UC…` id or by its `@handle`, and
	 * `channels.list` takes each under a different parameter. Which one this is
	 * is decided by the leading character, because that is the only thing that
	 * distinguishes them and `Settings` has already refused everything else.
	 *
	 * @return string|null
	 */
	private function uploads_playlist(): ?string {
		$channel = Settings::youtube_channel();
		$key     = Settings::youtube_key();

		if ( '' === $channel || '' === $key ) {
			return null;
		}

		$transient = self::UPLOADS_TRANSIENT . md5( $channel );
		$cached    = get_transient( $transient );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$selector = str_starts_with( $channel, '@' ) ? 'forHandle' : 'id';

		$body = $this->get(
			add_query_arg(
				array(
					'part'    => 'contentDetails',
					$selector => $channel,
					'key'     => $key,
				),
				self::CHANNELS_URL
			)
		);

		if ( null === $body ) {
			return null;
		}

		$playlist = YouTube::uploads_playlist( $body );

		if ( null === $playlist ) {
			return null;
		}

		set_transient( $transient, $playlist, self::UPLOADS_TTL );

		return $playlist;
	}

	/**
	 * One Data API GET, reduced to its body.
	 *
	 * @param string $url The full URL, query args included.
	 * @return string|null The response body, or null for any failure.
	 */
	private function get( string $url ): ?string {
		$response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}
}
