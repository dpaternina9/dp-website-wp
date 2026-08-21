<?php
/**
 * The dP mark: swappable from the admin, and still a link to the site root.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_HTML_Tag_Processor;

/**
 * The brand mark is content, not a background image.
 *
 * It used to be neither. `parts/header.html` rendered `core/site-title` with
 * the text pushed off-screen by `text-indent: -100vw`, and `chrome.css` painted
 * `assets/img/dp-mark-gradient-128.png` behind it as a `background`. Nothing in
 * the admin could change that — swapping the mark meant editing a stylesheet and
 * shipping a release — and the link's accessible name came from a site title
 * nobody could see rather than from the mark itself.
 *
 * All three places now render `core/site-logo`, which reads the `site_logo`
 * option. David swaps it from Appearance to Editor to Styles, or from the
 * Customizer, at any time, with no code and no deploy. `dp-core`'s seeder puts
 * the theme's bundled mark there on a fresh site so the header is not empty
 * before he has chosen anything; the file stays in the theme and stays the
 * default, but it is no longer the only possible answer.
 *
 * This class is the one correction the block needs. Core links the logo to
 * `home_url()` directly, which is the right URL and the wrong provenance:
 * every other link in this chrome says which destination it wants and is given
 * a URL by `Navigation` at render time, and `data-dp-destination` is how you
 * find out what a link asked for and what it got (Phase 5b). The mark now
 * answers the same way, from the same resolver, so there is one place that
 * knows where "home" is and one attribute that says so.
 */
final class Brand {

	/**
	 * The mark shipped with the theme, relative to the theme root.
	 *
	 * Not loaded by any stylesheet any more. It is the file `dp-core`'s seeder
	 * asks for through `dp_brand_logo_path`, and answering that filter is the
	 * only thing keeping it from being an orphan.
	 */
	public const MARK = 'assets/img/dp-mark-gradient-128.png';

	/**
	 * The destination the mark links to.
	 */
	public const DESTINATION = 'home';

	/**
	 * Constructor.
	 *
	 * @param string     $directory  Absolute path to the theme directory.
	 * @param Navigation $navigation Resolves the destination.
	 */
	public function __construct(
		private readonly string $directory,
		private readonly Navigation $navigation
	) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', $this->add_support( ... ) );
		add_filter( 'render_block_core/site-logo', $this->link_home( ... ) );
		add_filter( 'dp_brand_logo_path', $this->mark_path( ... ) );
	}

	/**
	 * Declare the logo, so every admin route that offers one offers this size.
	 *
	 * The design draws the mark at 34px in the header and 30px in the footer and
	 * it is square, so the "flex" height and width below are the largest the
	 * chrome ever asks for rather than a crop the uploader has to match.
	 * `flex-*` is what stops WordPress from forcing a crop dialogue on an image
	 * that is already the right shape.
	 *
	 * @return void
	 */
	public function add_support(): void {
		add_theme_support(
			'custom-logo',
			array(
				'height'               => 128,
				'width'                => 128,
				'flex-height'          => true,
				'flex-width'           => true,
				'unlink-homepage-logo' => false,
			)
		);
	}

	/**
	 * Point the mark at the `home` destination, and say so.
	 *
	 * `home` is the one destination that always resolves — the site root is not
	 * a page and is not David's to move — so this never has to deal with the
	 * unresolved case the buttons handle. If it somehow did, the block is left
	 * exactly as core rendered it rather than being emptied.
	 *
	 * @param string $content The rendered block.
	 * @return string
	 */
	public function link_home( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		$url       = $this->navigation->url_for( self::DESTINATION );
		$processor = new WP_HTML_Tag_Processor( $content );

		if ( null === $url || ! $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			return $content;
		}

		$processor->set_attribute( 'href', $url );
		$processor->set_attribute( 'data-dp-destination', self::DESTINATION );

		return $processor->get_updated_html();
	}

	/**
	 * Answer `dp-core` when it asks where the theme's default mark lives.
	 *
	 * The seeder sets the site logo on a fresh site and may not read a path out
	 * of a theme it is not allowed to know about, so it asks and this answers —
	 * the same seam as `dp_destination_url`. With the theme switched off nothing
	 * answers, and the seeder leaves the logo alone.
	 *
	 * @param mixed $path Whatever an earlier filter decided.
	 * @return string
	 */
	public function mark_path( mixed $path ): string {
		if ( is_string( $path ) && '' !== $path ) {
			return $path;
		}

		return $this->directory . '/' . self::MARK;
	}
}
