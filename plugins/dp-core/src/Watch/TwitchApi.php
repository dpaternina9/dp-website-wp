<?php
/**
 * The Helix HTTP layer.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * Talks to Twitch, and never makes a page wait long for it.
 *
 * Plan Phase 12: "a Twitch Helix call … cached in a transient, failing soft".
 * Both halves are enforced here rather than remembered by callers:
 *
 * - **Failing soft** means every method answers `null` for "Twitch did not
 *   say", never a WP_Error and never an exception. No credentials, a refused
 *   token, a timeout and an unreadable body are all the same answer.
 * - **Cached** means the app token is a transient for as long as Twitch says
 *   it lives, and a *failed* token request is also a transient — ten minutes
 *   of not asking again — because the failure mode this class must not have
 *   is one slow upstream call per public page view.
 *
 * The parsing is `Helix`'s, which is pure and unit-tested; this class is the
 * transcription between `wp_remote_*` and it.
 */
final class TwitchApi {

	/**
	 * Where an app access token comes from.
	 *
	 * @var string
	 */
	public const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';

	/**
	 * The streams endpoint, answering both "is this login live" and "with what".
	 *
	 * @var string
	 */
	public const STREAMS_URL = 'https://api.twitch.tv/helix/streams';

	/**
	 * The videos endpoint, answering a VOD's thumbnail template.
	 *
	 * @var string
	 */
	public const VIDEOS_URL = 'https://api.twitch.tv/helix/videos';

	/**
	 * The users endpoint, turning a login into the id `/videos` is keyed by.
	 *
	 * @var string
	 */
	public const USERS_URL = 'https://api.twitch.tv/helix/users';

	/**
	 * The transient holding a working app token.
	 *
	 * @var string
	 */
	public const TOKEN_TRANSIENT = 'dp_watch_helix_token';

	/**
	 * The transient remembering that the last token request failed.
	 *
	 * @var string
	 */
	public const TOKEN_FAILED_TRANSIENT = 'dp_watch_helix_token_failed';

	/**
	 * How long a failed token request keeps further ones from being made.
	 *
	 * @var int
	 */
	private const FAILURE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * How long one Helix request may hold a page render, in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 3;

	/**
	 * How long one Helix request may take on the sync path, in seconds.
	 *
	 * Longer than the render timeout because nothing is waiting: the sync runs
	 * under cron, under WP-CLI, or behind a button whose whole job is to take a
	 * moment. Three seconds is the budget for a visitor, not for a background job.
	 *
	 * @var int
	 */
	private const SYNC_TIMEOUT = 10;

	/**
	 * How many VODs one archive page asks for. A hundred is Helix's maximum.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 100;

	/**
	 * How many archive pages one sync will read.
	 *
	 * A bound rather than a limit anybody is expected to reach: past broadcasts
	 * expire from Twitch on their own, so five hundred is far more than a
	 * channel keeps. It is here so a paginated endpoint cannot loop forever.
	 *
	 * @var int
	 */
	private const MAX_PAGES = 5;

	/**
	 * What the configured login is streaming right now, if anything.
	 *
	 * The whole of the live card comes from here: the title, the start instant
	 * the elapsed time is derived from, and the category. One request, because
	 * Helix reports all of it in the object that proves the channel is on air.
	 *
	 * @param string $login The Twitch login.
	 * @return LiveStream|null The stream, or null for offline, unconfigured, and
	 *                         every kind of no answer at all.
	 */
	public function live_stream( string $login ): ?LiveStream {
		if ( '' === $login ) {
			return null;
		}

		$body = $this->get( add_query_arg( 'user_login', rawurlencode( $login ), self::STREAMS_URL ) );

		return null === $body ? null : Helix::stream( $body );
	}

	/**
	 * The thumbnail URL template for one Twitch VOD.
	 *
	 * @param string $vod_id The VOD's id.
	 * @return string|null The template, or null when Helix did not hand one over —
	 *                     unknown id, still processing, or no answer at all.
	 */
	public function vod_thumbnail_template( string $vod_id ): ?string {
		if ( '' === $vod_id ) {
			return null;
		}

		$body = $this->get( add_query_arg( 'id', rawurlencode( $vod_id ), self::VIDEOS_URL ) );

		if ( null === $body ) {
			return null;
		}

		$templates = Helix::vod_thumbnails( $body );

		return $templates[ $vod_id ] ?? null;
	}

	/**
	 * Every past broadcast on the configured channel, newest first.
	 *
	 * Two calls and then some paging: `/users` turns the login David typed into
	 * the numeric id `/videos` is keyed by, and `/videos?type=archive` lists the
	 * VODs. Highlights and uploads are deliberately not asked for — a highlight
	 * is a clip of a broadcast that is already in this list, and importing both
	 * would put the same stream on the Watch page twice.
	 *
	 * **Any failure anywhere abandons the whole list.** A partial answer is
	 * worse than none here, because the caller reconciles what it is given
	 * against what is stored: half a list would read as "the other half was
	 * deleted" and unpublish videos that are still there.
	 *
	 * @return list<RemoteVideo>|null The archive, or null when Twitch did not
	 *                                answer all of it.
	 */
	public function archive_videos(): ?array {
		$login = Settings::login();

		if ( '' === $login ) {
			return null;
		}

		$body = $this->get( add_query_arg( 'login', $login, self::USERS_URL ), self::SYNC_TIMEOUT );

		if ( null === $body ) {
			return null;
		}

		$user_id = Helix::user_id( $body );

		if ( null === $user_id ) {
			return null;
		}

		$videos = array();
		$cursor = '';

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$arguments = array(
				'user_id' => $user_id,
				'type'    => 'archive',
				'first'   => (string) self::PAGE_SIZE,
			);

			if ( '' !== $cursor ) {
				$arguments['after'] = $cursor;
			}

			$body = $this->get( add_query_arg( $arguments, self::VIDEOS_URL ), self::SYNC_TIMEOUT );

			if ( null === $body ) {
				return null;
			}

			$read = Helix::archive( $body );

			if ( null === $read ) {
				return null;
			}

			foreach ( $read['videos'] as $video ) {
				$videos[] = $video;
			}

			$cursor = $read['cursor'];

			if ( '' === $cursor || array() === $read['videos'] ) {
				break;
			}
		}

		return $videos;
	}

	/**
	 * One authenticated Helix GET, reduced to its body.
	 *
	 * @param string   $url     The full URL, query args included.
	 * @param int|null $timeout How long to wait, or null for the render budget.
	 * @return string|null The response body, or null for any failure.
	 */
	private function get( string $url, ?int $timeout = null ): ?string {
		$client_id = Settings::client_id();
		$token     = $this->token();

		if ( '' === $client_id || null === $token ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $timeout ?? self::TIMEOUT,
				'headers' => array(
					'Client-ID'     => $client_id,
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}

	/**
	 * A working app access token, from the transient or from Twitch.
	 *
	 * @return string|null
	 */
	private function token(): ?string {
		$cached = get_transient( self::TOKEN_TRANSIENT );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		if ( false !== get_transient( self::TOKEN_FAILED_TRANSIENT ) ) {
			return null;
		}

		if ( ! Settings::has_credentials() ) {
			return null;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'client_id'     => Settings::client_id(),
					'client_secret' => Settings::client_secret(),
					'grant_type'    => 'client_credentials',
				),
			)
		);

		$token = null;

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$token = Helix::token( wp_remote_retrieve_body( $response ) );
		}

		if ( null === $token ) {
			set_transient( self::TOKEN_FAILED_TRANSIENT, 1, self::FAILURE_TTL );

			return null;
		}

		/*
		 * A minute is shaved off so the cached token cannot be handed out in
		 * its final seconds; a token Twitch reports as shorter-lived than that
		 * is not worth caching at all.
		 */
		if ( $token['expires'] > MINUTE_IN_SECONDS ) {
			set_transient( self::TOKEN_TRANSIENT, $token['token'], min( $token['expires'] - MINUTE_IN_SECONDS, DAY_IN_SECONDS ) );
		}

		return $token['token'];
	}
}
