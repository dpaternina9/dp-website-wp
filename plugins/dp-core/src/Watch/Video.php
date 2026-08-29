<?php
/**
 * One entry on the Watch page.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;
use WP_Post;

/**
 * A `dp_video` post, read once into a typed shape.
 *
 * Digest section 3.5, field for field. Everything downstream of `from_post()`
 * is typed: the source is an enum or absent, the tone is an enum or absent,
 * and the strings are exactly what David wrote — escaping stays at the point
 * of output, in the blocks.
 *
 * The URL builders are static and pure so the unit suite can hold them
 * without a WordPress bootstrap. They are the click-to-play contract's
 * no-JavaScript half: the URL a card links to is the video on its host, and
 * the iframe the script swaps in later must agree with it about which video
 * that is.
 */
final class Video {

	/**
	 * Constructor.
	 *
	 * @param int              $id        The post ID.
	 * @param string           $title     The post title.
	 * @param VideoSource|null $source    Where it is hosted, or null when unset.
	 * @param string           $ref       The platform identifier: a Twitch VOD id or a YouTube video id.
	 * @param Tone|null        $tone      The hue the card takes, or null for the default.
	 * @param string           $duration  Runtime as printed, e.g. "2H 41M".
	 * @param string           $when      When it went out, e.g. "AUG 2026".
	 * @param string           $note      One line under the title.
	 * @param bool             $live      Whether this is the live-now entry rather than an archived video.
	 * @param string           $live_meta The live strapline, e.g. "STREAMING NOW · 1H 12M IN".
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly ?VideoSource $source,
		public readonly string $ref,
		public readonly ?Tone $tone,
		public readonly string $duration,
		public readonly string $when,
		public readonly string $note,
		public readonly bool $live,
		public readonly string $live_meta
	) {}

	/**
	 * Read one `dp_video` post.
	 *
	 * @param WP_Post $post The post.
	 * @return self
	 */
	public static function from_post( WP_Post $post ): self {
		return new self(
			$post->ID,
			$post->post_title,
			VideoSource::try_from_meta( self::meta( $post->ID, 'dp_video_source' ) ),
			self::meta( $post->ID, 'dp_video_ref' ),
			Tone::try_from_meta( self::meta( $post->ID, 'dp_tone' ) ),
			self::meta( $post->ID, 'dp_duration' ),
			self::meta( $post->ID, 'dp_when' ),
			self::meta( $post->ID, 'dp_note' ),
			(bool) get_post_meta( $post->ID, 'dp_live', true ),
			self::meta( $post->ID, 'dp_live_meta' )
		);
	}

	/**
	 * The video's URL on its own host, for the plain link a card is without JavaScript.
	 *
	 * @param string $login The configured Twitch login, for the live entry.
	 * @return string The URL, or '' when there is nothing to link — a video with
	 *                no identifier degrades to an unlinked card (ADR-0008).
	 */
	public function watch_url( string $login ): string {
		if ( $this->live ) {
			return self::channel_url( $login );
		}

		if ( null === $this->source || '' === $this->ref ) {
			return '';
		}

		return match ( $this->source ) {
			VideoSource::Twitch  => 'https://www.twitch.tv/videos/' . rawurlencode( $this->ref ),
			VideoSource::YouTube => 'https://www.youtube.com/watch?v=' . rawurlencode( $this->ref ),
		};
	}

	/**
	 * What the click-to-play script should embed, as a `data-dp-embed` value.
	 *
	 * @return string `twitch-live`, `twitch-vod`, `youtube`, or '' when the card
	 *                has nothing to play.
	 */
	public function embed_kind(): string {
		if ( $this->live ) {
			return 'twitch-live';
		}

		if ( null === $this->source || '' === $this->ref ) {
			return '';
		}

		return match ( $this->source ) {
			VideoSource::Twitch  => 'twitch-vod',
			VideoSource::YouTube => 'youtube',
		};
	}

	/**
	 * What the click-to-play script should play: the VOD or video id, or the
	 * channel login for the live entry.
	 *
	 * @param string $login The configured Twitch login.
	 * @return string
	 */
	public function embed_ref( string $login ): string {
		return $this->live ? $login : $this->ref;
	}

	/**
	 * The caps label the card prints for its platform.
	 *
	 * @return string
	 */
	public function source_label(): string {
		return $this->source?->label() ?? '';
	}

	/**
	 * The platform's name as prose — "Watch on Twitch", not "WATCH ON TWITCH".
	 *
	 * The design's caps are presentation; the stylesheet uppercases the labels
	 * that want them, and a screen reader gets a word rather than an acronym.
	 *
	 * @return string
	 */
	public function source_name(): string {
		return match ( $this->source ) {
			VideoSource::Twitch  => 'Twitch',
			VideoSource::YouTube => 'YouTube',
			null                 => '',
		};
	}

	/**
	 * A Twitch channel's URL.
	 *
	 * @param string $login The channel's login.
	 * @return string The URL, or '' when no login is configured.
	 */
	public static function channel_url( string $login ): string {
		return '' === $login ? '' : 'https://www.twitch.tv/' . rawurlencode( $login );
	}

	/**
	 * One meta value, as a string or not at all.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return string
	 */
	private static function meta( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : '';
	}
}
