<?php
/**
 * The `dp/watch-featured` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * The panel at the top of the Watch page: the stream, or the latest video.
 *
 * The design's rule, kept exactly: while the channel is live the panel is the
 * live entry David wrote (`dp_live`), and the whole archive stays below it;
 * while it is not, the panel is the newest archived video wearing a LATEST
 * badge, and the grid starts from the second. `LiveStatus` is the one answer
 * both this block and `dp/video-grid` read, so the two cannot disagree about
 * which entry is up top.
 *
 * Everything visible is either David's content (the `dp_video` posts, the
 * Settings → General login) or a translatable label. The live check and the
 * thumbnail cache both fail soft: with no credentials this panel simply never
 * claims to be live, and with no cached image the card's own glow art is the
 * picture.
 *
 * Like `dp/timeline`, the block is dynamic, lives in the plugin because it
 * renders content, and is styled entirely by the theme.
 */
final class WatchFeatured {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/watch-featured';

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/watch-featured';

	/**
	 * Constructor.
	 *
	 * @param string     $plugin_dir Absolute path to the plugin directory, without a trailing slash.
	 * @param Videos     $videos     The published entries.
	 * @param LiveStatus $status     The cached live check.
	 * @param Thumbnails $thumbnails The server-side thumbnail cache.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly Videos $videos,
		private readonly LiveStatus $status,
		private readonly Thumbnails $thumbnails
	) {}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type(
			$this->plugin_dir . self::DEFINITION,
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the panel.
	 *
	 * @return string
	 */
	public function render(): string {
		$this->thumbnails->replenish();

		$login = Settings::login();
		$live  = $this->status->live() ? $this->videos->live_entry() : null;
		$entry = $live ?? $this->videos->archive()[0] ?? null;

		if ( null === $entry ) {
			return '';
		}

		$is_live = null !== $live;
		$tone    = $entry->tone->value ?? 'teal';

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'dp-watch-featured dp-tone-' . $tone . ( $is_live ? ' is-live' : '' ),
			)
		);

		return '<div ' . $wrapper . '>'
			. $this->media( $entry, $login, $is_live )
			. $this->body( $entry, $login, $is_live )
			. '</div>';
	}

	/**
	 * The picture half: glow art, the cached thumbnail over it, the live badge.
	 *
	 * @param Video  $entry   The featured entry.
	 * @param string $login   The configured Twitch login.
	 * @param bool   $is_live Whether the panel is the live stream.
	 * @return string
	 */
	private function media( Video $entry, string $login, bool $is_live ): string {
		$art = '<div class="dp-vg-art dp-vg-art-center" aria-hidden="true">'
			. '<span class="dp-vg-art-label">' . esc_html( $entry->source_label() ) . '</span>'
			. '</div>';

		$thumb = $this->thumbnails->url( $entry, $login );
		$image = null === $thumb
			? ''
			: sprintf(
				'<img class="dp-vg-thumb" src="%s" alt="" width="%d" height="%d" decoding="async">',
				esc_url( $thumb ),
				Helix::THUMB_WIDTH,
				Helix::THUMB_HEIGHT
			);

		$badge = ! $is_live
			? ''
			: '<span class="dp-vg-live-badge"><span class="dp-vg-live-dot" aria-hidden="true"></span>'
				. esc_html__( 'Live on Twitch', 'dp-core' )
				. '</span>';

		return '<div class="dp-vg-media dp-watch-featured-media">' . $art . $image . $badge . '</div>';
	}

	/**
	 * The text half: kicker, title, note, actions, and the promise under them.
	 *
	 * @param Video  $entry   The featured entry.
	 * @param string $login   The configured Twitch login.
	 * @param bool   $is_live Whether the panel is the live stream.
	 * @return string
	 */
	private function body( Video $entry, string $login, bool $is_live ): string {
		if ( $is_live ) {
			/* translators: %s: the platform's name, e.g. "Twitch". */
			$badge = sprintf( __( 'Live now on %s', 'dp-core' ), $entry->source_name() );
			$meta  = $entry->live_meta;
			$label = __( 'Watch the stream', 'dp-core' );
		} else {
			/* translators: %s: the platform's name, e.g. "YouTube". */
			$badge = sprintf( __( 'Latest on %s', 'dp-core' ), $entry->source_name() );
			$meta  = trim( $entry->duration . ' · ' . $entry->when, ' ·' );
			/* translators: %s: the platform's name, e.g. "YouTube". */
			$label = sprintf( __( 'Watch on %s', 'dp-core' ), $entry->source_name() );
		}

		$kicker = '<p class="dp-watch-featured-kicker"><span class="dp-vg-badge">' . esc_html( $badge ) . '</span>'
			. ( '' === $meta ? '' : '<span class="dp-vg-meta">' . esc_html( $meta ) . '</span>' )
			. '</p>';

		$note = '' === $entry->note
			? ''
			: '<p class="dp-watch-featured-note">' . esc_html( $entry->note ) . '</p>';

		return '<div class="dp-watch-featured-body">'
			. $kicker
			. '<h2 class="dp-watch-featured-title">' . esc_html( $entry->title ) . '</h2>'
			. $note
			. $this->actions( $entry, $login, $label )
			. '<p class="dp-watch-players-note">' . esc_html__( 'Players load only when you press play', 'dp-core' ) . '</p>'
			. '</div>';
	}

	/**
	 * The primary press-to-play link, and Follow beside it when there is a channel.
	 *
	 * Without JavaScript the primary action is a plain link to the video on
	 * its host; the script upgrades the press to an in-place player. An entry
	 * with no identifier renders no action at all rather than a dead control
	 * (ADR-0008).
	 *
	 * The design draws Follow unconditionally but wires it nowhere; the only
	 * channel URL this project knows is the configured Twitch login, so Follow
	 * renders exactly when that is set.
	 *
	 * @param Video  $entry The featured entry.
	 * @param string $login The configured Twitch login.
	 * @param string $label The primary action's label.
	 * @return string
	 */
	private function actions( Video $entry, string $login, string $label ): string {
		$url     = $entry->watch_url( $login );
		$actions = '';

		if ( '' !== $url && '' !== $entry->embed_kind() ) {
			$actions .= sprintf(
				'<a class="dp-watch-play" href="%1$s" data-dp-embed="%2$s" data-dp-ref="%3$s" data-dp-title="%4$s">%5$s</a>',
				esc_url( $url ),
				esc_attr( $entry->embed_kind() ),
				esc_attr( $entry->embed_ref( $login ) ),
				esc_attr( $entry->title ),
				esc_html( $label )
			);
		}

		$channel = Video::channel_url( $login );

		if ( '' !== $channel ) {
			$actions .= sprintf(
				'<a class="dp-watch-follow" href="%s" rel="noopener">%s</a>',
				esc_url( $channel ),
				esc_html__( 'Follow on Twitch', 'dp-core' )
			);
		}

		return '' === $actions ? '' : '<div class="dp-watch-featured-actions">' . $actions . '</div>';
	}
}
