<?php
/**
 * "RSS" — the feed, wherever the permalink setting has put it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

/**
 * The third of ADR-0018's three links.
 *
 * `SiteFooter.dc.html` calls it out: "RSS is the one real href in the whole
 * design: `<a href="/rss.xml">`." It is also the one href in the design that is
 * wrong for WordPress, because where the feed lives is decided by the permalink
 * structure — `/feed/` with pretty permalinks, `?feed=rss2` without — and it
 * moves the moment David changes that setting. `get_feed_link()` is the only
 * correct answer and no template can hold it, so this is a block rather than a
 * link somebody types.
 *
 * It effectively always resolves: core builds the URL from the site address
 * whether or not anything has been published. The inert path is kept anyway, so
 * that all three of these blocks fail the same way rather than two of them
 * failing one way and this one fataling or printing an empty `href`.
 */
final class FeedLink {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/feed-link';

	/**
	 * What the anchor announces in `data-dp-destination`.
	 */
	public const DESTINATION = 'feed';

	/**
	 * The design's class on the wrapper, where the surrounding rules hang.
	 */
	private const WRAPPER_CLASS = 'dp-footer-meta-links';

	/**
	 * The presentational class on the button.
	 */
	private const BUTTON_CLASS = 'dp-button-quiet';

	/**
	 * Constructor.
	 *
	 * @param DerivedLink $link Renders the button.
	 */
	public function __construct( private readonly DerivedLink $link = new DerivedLink() ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_block( ... ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type(
			get_theme_file_path( 'blocks/feed-link' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the link.
	 *
	 * @return string
	 */
	public function render(): string {
		return $this->link->render(
			get_block_wrapper_attributes( array( 'class' => $this->link->wrapper_class( self::WRAPPER_CLASS ) ) ),
			self::BUTTON_CLASS,
			$this->url(),
			__( 'RSS', 'dpaternina' ),
			self::DESTINATION
		);
	}

	/**
	 * Where the feed is right now.
	 *
	 * @return string|null
	 */
	public function url(): ?string {
		$url = get_feed_link();

		return '' === $url ? null : $url;
	}
}
