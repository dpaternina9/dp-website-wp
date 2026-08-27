<?php
/**
 * "Download PDF" — the résumé, as `dp-core` addresses it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Resume\ResumePdf;
use WP_Post;

/**
 * The second of ADR-0018's three links, and the only one that spans both packages.
 *
 * The PDF is not a file and not a route: it is `?format=pdf` on the résumé page
 * itself, a query variable `dp-core` registers and whose name this theme is not
 * allowed to write out (ADR-0006 §5; `DP\Core\Resume\ResumePdf`). So the URL is
 * built by asking the plugin to build it, from the page the block is being drawn
 * on — which means renaming or moving the page moves the download with it,
 * exactly as it did before, without a class that rewrites an href behind
 * anybody's back.
 *
 * Two things have to be true and either can be false on a working site.
 *
 * **The page has to carry the résumé template.** `dp-core` ignores `format` on
 * every other page, so a link to `?format=pdf` from anywhere else is a link that
 * does nothing. The template is read with `get_page_template_slug()`, which is
 * the branch CLAUDE.md §5.1 prescribes; `dp-resume` is this theme's own template
 * name out of its own `theme.json`, not a slug of David's.
 *
 * **`dp-core` has to be active.** Naming the class unguarded would fatal the
 * theme on a site with the plugin switched off, which is exactly what
 * `composer test:integration` leaves behind.
 *
 * Either miss leaves the button in place and inert (ADR-0008), which is also
 * what the site editor's canvas shows: there is no queried page while a template
 * is being edited, so the block draws the state a résumé page that has not been
 * set up yet would draw.
 */
final class ResumeDownload {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/resume-download';

	/**
	 * What the anchor announces in `data-dp-destination`.
	 */
	public const DESTINATION = 'resume-pdf';

	/**
	 * This theme's own résumé template, without the `.html` WordPress drops.
	 *
	 * Declared in `theme.json`'s `customTemplates` and assigned by David from
	 * the admin dropdown, which stores the slug. A page imported from elsewhere
	 * may carry the file name instead, so both spellings are accepted.
	 */
	public const TEMPLATE = 'dp-resume';

	/**
	 * The design's class on the wrapper, where the surrounding rules hang.
	 */
	private const WRAPPER_CLASS = 'dp-resume-download';

	/**
	 * The presentational class on the button.
	 */
	private const BUTTON_CLASS = 'dp-button-lg';

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
			get_theme_file_path( 'blocks/resume-download' ),
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
			__( 'Download PDF', 'dpaternina' ),
			self::DESTINATION
		);
	}

	/**
	 * The URL that downloads the résumé, when there is a résumé to download.
	 *
	 * @return string|null
	 */
	public function url(): ?string {
		if ( ! class_exists( ResumePdf::class ) ) {
			return null;
		}

		$page = get_queried_object();

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
			return null;
		}

		$template = get_page_template_slug( $page->ID );
		$template = is_string( $template ) ? $template : '';

		if ( str_ends_with( $template, '.html' ) ) {
			$template = substr( $template, 0, -5 );
		}

		return self::TEMPLATE === $template ? ResumePdf::download_url( $page->ID ) : null;
	}
}
