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
	 * The streams endpoint, answering "is this login live".
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
	 * Whether the configured login is live right now.
	 *
	 * @param string $login The Twitch login.
	 * @return bool|null True or false when Helix answered, null when it did not.
	 */
	public function is_live( string $login ): ?bool {
		if ( '' === $login ) {
			return null;
		}

		$body = $this->get( add_query_arg( 'user_login', rawurlencode( $login ), self::STREAMS_URL ) );

		return null === $body ? null : Helix::is_live( $body );
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
	 * One authenticated Helix GET, reduced to its body.
	 *
	 * @param string $url The full URL, query args included.
	 * @return string|null The response body, or null for any failure.
	 */
	private function get( string $url ): ?string {
		$client_id = Settings::client_id();
		$token     = $this->token();

		if ( '' === $client_id || null === $token ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
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
