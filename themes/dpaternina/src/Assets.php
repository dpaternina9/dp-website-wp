<?php
/**
 * Stylesheets, fonts and the editor/front-end parity between them.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

/**
 * Loads the theme's CSS in both contexts, and preloads the two faces that the
 * first paint actually needs.
 *
 * CLAUDE.md §5: "the editor must look like the front end. Every block style is
 * loaded in both contexts." So the same two files are registered twice — once
 * through `wp_enqueue_style()` for the front end, once through
 * `add_editor_style()` for the editor — from one list, so they cannot diverge.
 */
final class Assets {

	/**
	 * The theme's stylesheets, in cascade order, as paths relative to the theme root.
	 *
	 * `tokens.css` is generated from `design-source/` and gives every design token
	 * back its own name; `base.css` is the design system's base layer verbatim.
	 * Both are enforced by DP\Tests\Integration\TokenParityTest.
	 *
	 * `blocks.css` is the house style: the part of
	 * design-source/components/PostBlocks.dc.html that theme.json has no way to
	 * say. It comes last because it overrides global styles, and it is in this
	 * list — rather than attached to a block — precisely so the editor cannot
	 * end up with a different version of it.
	 */
	private const STYLESHEETS = array(
		'dpaternina-tokens'     => 'assets/css/tokens.css',
		'dpaternina-base'       => 'assets/css/base.css',
		'dpaternina-blocks'     => 'assets/css/blocks.css',
		'dpaternina-chrome'     => 'assets/css/chrome.css',
		'dpaternina-components' => 'assets/css/components.css',
	);

	/**
	 * The theme's front-end scripts, as paths relative to the theme root.
	 *
	 * There is one, it is small, and everything it does is an upgrade to
	 * behaviour that already works without it (CLAUDE.md section 1.7). It is not
	 * given to the editor: the canvas has no site chrome to enhance.
	 */
	private const SCRIPTS = array(
		'dpaternina-nav-panel' => 'assets/js/nav-panel.js',
	);

	/**
	 * Font files preloaded on every page, as paths relative to the theme root.
	 *
	 * Only two of the six subsets are here, and each earns its place:
	 *
	 * - Bricolage Grotesque, latin. Every view opens on a display-face heading,
	 *   which is the LCP element; preloading takes one round trip off that path.
	 * - Manrope, latin. Body copy is the bulk of the first paint.
	 *
	 * JetBrains Mono is not preloaded: it sets labels, dates and captions — small,
	 * secondary text that tolerates the `swap` — and a third file on the critical
	 * path competes with the two that carry the LCP.
	 *
	 * No `latin-ext` subset is preloaded either. The `unicode-range` split means
	 * the browser fetches those files only when a page actually contains a
	 * character in that range, and preloading would spend 34 KB that most pages
	 * never use. "Résumé" and "Medellín" live in `latin` (U+0000–00FF), so the
	 * common case never needs the second file; a page that does carry, say, `ș`
	 * still resolves to the real face rather than a system fallback.
	 */
	private const PRELOADED_FONTS = array(
		'assets/fonts/bricolage-grotesque/bricolage-grotesque-latin.woff2',
		'assets/fonts/manrope/manrope-latin.woff2',
	);

	/**
	 * Constructor.
	 *
	 * @param Theme $theme The booted theme.
	 */
	public function __construct( private readonly Theme $theme ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', $this->add_theme_supports( ... ) );
		add_action( 'wp_enqueue_scripts', $this->enqueue_front_end( ... ) );
		add_action( 'wp_head', $this->preload_fonts( ... ), 1 );
	}

	/**
	 * Declare theme support and hand the editor the same stylesheets.
	 *
	 * @return void
	 */
	public function add_theme_supports(): void {
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );

		/*
		 * `editor-styles` is what makes the block editor apply the files that
		 * `add_editor_style()` registers. Both calls are needed; neither works
		 * on its own.
		 */
		add_theme_support( 'editor-styles' );
		add_editor_style( array_values( self::STYLESHEETS ) );
	}

	/**
	 * Enqueue the theme's stylesheets on the front end.
	 *
	 * @return void
	 */
	public function enqueue_front_end(): void {
		foreach ( self::STYLESHEETS as $handle => $relative ) {
			wp_enqueue_style(
				$handle,
				$this->theme->url( $relative ),
				array(),
				$this->theme->asset_version( $relative )
			);
		}

		foreach ( self::SCRIPTS as $handle => $relative ) {
			wp_enqueue_script(
				$handle,
				$this->theme->url( $relative ),
				array(),
				$this->theme->asset_version( $relative ),
				array(
					/*
					 * Deferred, not async: the panel controller has to find the
					 * markup it upgrades, and `in_footer` alone would still put
					 * a parser stop in the document. CLAUDE.md section 1.7:
					 * no render-blocking JS.
					 */
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}

	/**
	 * Print the font preloads.
	 *
	 * Priority 1 on `wp_head`, so the browser starts the two fetches before it
	 * has parsed the global stylesheet that will ask for them.
	 *
	 * @return void
	 */
	public function preload_fonts(): void {
		foreach ( self::PRELOADED_FONTS as $relative ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
				esc_url( $this->theme->url( $relative ) )
			);
		}
	}

	/**
	 * The stylesheets this theme loads, keyed by handle.
	 *
	 * Exposed so the parity test can assert the editor and the front end are
	 * given the same list rather than two lists that happen to match today.
	 *
	 * @return array<string, string> Handle to path relative to the theme root.
	 */
	public static function stylesheets(): array {
		return self::STYLESHEETS;
	}

	/**
	 * The font files this theme preloads.
	 *
	 * @return list<string> Paths relative to the theme root.
	 */
	public static function preloaded_fonts(): array {
		return self::PRELOADED_FONTS;
	}

	/**
	 * The front-end scripts this theme loads, keyed by handle.
	 *
	 * @return array<string, string> Handle to path relative to the theme root.
	 */
	public static function scripts(): array {
		return self::SCRIPTS;
	}
}
