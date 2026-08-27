<?php
/**
 * The dP mark: swappable from the admin, and still a link to the site root.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

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
 * **Nothing corrects the block any more.** This class used to filter
 * `render_block_core/site-logo` and overwrite the logo's href with
 * `home_url( '/' )` — which is what core had already put there. Its own docblock
 * named the motive: "the right URL and the wrong provenance". It existed so the
 * mark would resolve through `Navigation` like every other link in the chrome,
 * and to do it it silently overrode core's own homepage-logo linking setting.
 * ADR-0018 deletes it: code that rewrites a value it did not create is the
 * defect, not the pattern, and core's link was correct before we touched it.
 * What is left here is theme support and two answers to `dp-core`'s questions
 * about where the theme's files are.
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
	 * The 2000px master, which the seeder stands in for a lead image with.
	 *
	 * The design's post view draws a 16/9 picture and this theme ships no
	 * photographs, so the seed uses the monogram — deliberately obviously not a
	 * photograph. It is the master rather than the 128px derivative because the
	 * figure is most of a column wide and an upscaled 128 looks like a bug in the
	 * theme rather than a placeholder in the content.
	 */
	public const MARK_SOURCE = 'assets/img/dp-mark-gradient.src.png';

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute path to the theme directory.
	 */
	public function __construct( private readonly string $directory ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', $this->add_support( ... ) );
		add_filter( 'dp_brand_logo_path', $this->mark_path( ... ) );
		add_filter( 'dp_seed_lead_image_path', $this->mark_source_path( ... ) );
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

	/**
	 * Answer the seeder's request for something to stand in for a lead image.
	 *
	 * The same seam as `dp_brand_logo_path`, for the same reason: the plugin does
	 * not reach into the theme's directory, so it asks and the theme answers.
	 * With the theme switched off nothing answers and seeded posts get no
	 * featured image, which is the state they were in before.
	 *
	 * @param mixed $path Whatever an earlier filter decided.
	 * @return string
	 */
	public function mark_source_path( mixed $path ): string {
		if ( is_string( $path ) && '' !== $path ) {
			return $path;
		}

		return $this->directory . '/' . self::MARK_SOURCE;
	}
}
