<?php
/**
 * The `dp/video-grid` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * The archive: every published `dp_video` that is not already up top, as cards.
 *
 * The one decision here is the handoff with `dp/watch-featured`. While the
 * channel is live the featured panel is the stream and this grid is the whole
 * archive; while it is not, the panel already shows the newest archived video,
 * so the grid starts from the second — the design's `vods = live ? archive :
 * archive.slice(1)`, transcribed. `LiveStatus` caches the answer, so both
 * blocks read the same one.
 *
 * A card is static until pressed. Without JavaScript its footer is a plain
 * link to the video on Twitch or YouTube; the theme's script upgrades the
 * press to an in-place iframe. The thumbnail, when there is one, is a cached
 * copy served from this site's own uploads (`Thumbnails`), so rendering the
 * grid costs a visitor's browser no third-party request at all.
 */
final class VideoGrid {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/video-grid';

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/video-grid';

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
	 * Render the grid.
	 *
	 * @return string
	 */
	public function render(): string {
		$this->thumbnails->replenish();

		$archive = $this->videos->archive();

		$featured_is_live = $this->status->live() && null !== $this->videos->live_entry();

		if ( ! $featured_is_live ) {
			$archive = array_slice( $archive, 1 );
		}

		if ( array() === $archive ) {
			return '';
		}

		$login = Settings::login();
		$cards = '';

		foreach ( $archive as $video ) {
			$cards .= $this->card( $video, $login );
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'dp-vg' ) );

		return '<div ' . $wrapper . '>' . $cards . '</div>';
	}

	/**
	 * One card.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @return string
	 */
	private function card( Video $video, string $login ): string {
		$tone = $video->tone->value ?? 'teal';
		$note = '' === $video->note ? '' : '<p class="dp-vg-note">' . esc_html( $video->note ) . '</p>';

		return '<article class="dp-vg-card dp-tone-' . esc_attr( $tone ) . '">'
			. $this->media( $video, $login )
			. '<div class="dp-vg-body">'
			. '<h3 class="dp-vg-title">' . esc_html( $video->title ) . '</h3>'
			. $note
			. $this->footer( $video, $login )
			. '</div>'
			. '</article>';
	}

	/**
	 * The tile art, and the cached thumbnail over it when there is one.
	 *
	 * The art carries the platform, the runtime and the date, exactly as the
	 * design draws them into the tile; a fetched thumbnail covers them, and
	 * the footer below the title is what remains the affordance. The art is
	 * `aria-hidden` because all three facts are decoration here — the runtime
	 * and date read properly in the footer's `aria-label` context, and the
	 * platform is named by the footer link itself.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @return string
	 */
	private function media( Video $video, string $login ): string {
		$art = '<div class="dp-vg-art" aria-hidden="true">'
			. '<span class="dp-vg-badge">' . esc_html( $video->source_label() ) . '</span>'
			. '<span class="dp-vg-tile-foot">'
			. '<span class="dp-vg-dur">' . esc_html( $video->duration ) . '</span>'
			. '<span class="dp-vg-when">' . esc_html( $video->when ) . '</span>'
			. '</span>'
			. '</div>';

		$thumb = $this->thumbnails->url( $video, $login );
		$image = null === $thumb
			? ''
			: sprintf(
				'<img class="dp-vg-thumb" src="%s" alt="" width="%d" height="%d" loading="lazy" decoding="async">',
				esc_url( $thumb ),
				Helix::THUMB_WIDTH,
				Helix::THUMB_HEIGHT
			);

		return '<div class="dp-vg-media">' . $art . $image . '</div>';
	}

	/**
	 * The card's affordance: a plain link to the video on its host.
	 *
	 * The stylesheet stretches it over the whole card, so the card is the
	 * design's click target without a script; with one, the press swaps the
	 * player in. A video with no identifier keeps the element and loses the
	 * link — ADR-0008's rule, so the gap is visible instead of clickable.
	 *
	 * @param Video  $video The entry.
	 * @param string $login The configured Twitch login.
	 * @return string
	 */
	private function footer( Video $video, string $login ): string {
		/* translators: %s: the platform's name, e.g. "Twitch". */
		$label = sprintf( __( 'Watch on %s', 'dp-core' ), $video->source_name() );
		$url   = $video->watch_url( $login );

		if ( '' === $url || '' === $video->embed_kind() ) {
			return '<span class="dp-vg-link is-unlinked">' . esc_html( $label ) . '</span>';
		}

		return sprintf(
			'<a class="dp-vg-link" href="%1$s" data-dp-embed="%2$s" data-dp-ref="%3$s" data-dp-title="%4$s" aria-label="%5$s">%6$s<span class="dp-vg-arrow" aria-hidden="true"> →</span></a>',
			esc_url( $url ),
			esc_attr( $video->embed_kind() ),
			esc_attr( $video->embed_ref( $login ) ),
			esc_attr( $video->title ),
			/* translators: 1: the video's title. 2: the platform's name. */
			esc_attr( sprintf( __( 'Watch “%1$s” on %2$s', 'dp-core' ), $video->title, $video->source_name() ) ),
			esc_html( $label )
		);
	}
}
