<?php
/**
 * Where a thumbnail lives before we cache it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * The public thumbnail URLs digest section 3.5 names, built purely.
 *
 * Thumbnails are never uploaded — every one resolves to a Twitch or YouTube
 * URL. This class only *builds* those URLs; fetching and caching them is
 * `Thumbnails`' job, so everything here is unit-testable without a bootstrap.
 *
 * YouTube gets two candidates because `maxresdefault.jpg` genuinely does not
 * exist for many videos — YouTube only renders it for uploads with an HD
 * source — and a 404 there is routine, not an error. `hqdefault.jpg` exists
 * for everything. The digest names the first; the second is the fallback that
 * keeps an SD upload from spending its life on the glow art.
 */
final class ThumbnailSource {

	/**
	 * Not to be instantiated.
	 */
	private function __construct() {}

	/**
	 * The candidate URLs for a YouTube video's thumbnail, best first.
	 *
	 * @param string $video_id The YouTube video id.
	 * @return list<string>
	 */
	public static function youtube_candidates( string $video_id ): array {
		if ( '' === $video_id ) {
			return array();
		}

		$base = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/';

		return array( $base . 'maxresdefault.jpg', $base . 'hqdefault.jpg' );
	}

	/**
	 * The public preview image of a live Twitch stream. No key needed.
	 *
	 * @param string $login The channel's login.
	 * @return string The URL, or '' without a login.
	 */
	public static function live_preview_url( string $login ): string {
		if ( '' === $login ) {
			return '';
		}

		return 'https://static-cdn.jtvnw.net/previews-ttv/live_user_' . rawurlencode( $login ) . '-'
			. Helix::THUMB_WIDTH . 'x' . Helix::THUMB_HEIGHT . '.jpg';
	}

	/**
	 * The file name one cached thumbnail is stored under.
	 *
	 * Deterministic from what identifies the image, so a changed identifier is
	 * a new file rather than a stale one, and reduced to characters that are
	 * safe in a file name whatever the identifier contained.
	 *
	 * @param string $kind What the image is: `youtube`, `twitch`, or `live`.
	 * @param string $ref  The video id, VOD id, or login.
	 * @return string A file name without extension, or '' when there is no ref.
	 */
	public static function cache_key( string $kind, string $ref ): string {
		if ( '' === $kind || '' === $ref ) {
			return '';
		}

		$safe = strtolower( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $ref ) );

		if ( '' === $safe ) {
			return '';
		}

		return $kind . '-' . $safe;
	}
}
