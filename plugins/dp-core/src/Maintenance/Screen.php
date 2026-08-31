<?php
/**
 * The document the public sees while the site is being set up.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

/**
 * One complete HTML document, built without the theme.
 *
 * The screen has to work when the theme is half-configured, being switched, or
 * missing — that is the situation it exists for — so it borrows nothing from it.
 * No template, no block, no `wp_head()`, no enqueued asset and no JavaScript at
 * all. What comes back from `render()` is the whole response body.
 *
 * **The CSS is a file, not a `<style>` element.** Headers on the live site come
 * from David's security plugin (docs/plan.md Phase 10), and this repository's
 * standing obligation is to emit nothing that would make him loosen them. A
 * `style-src 'self'` policy drops an inline `<style>` exactly as `script-src`
 * drops an inline `<script>`, so the rule is the same for both: the stylesheet
 * is a real file inside this plugin, linked from its own origin at a
 * `plugin_dir_url()` path and cache-busted by the plugin version. There is no
 * `style=` attribute, no `on*` handler and no off-origin request anywhere in
 * this document — which also means there is no webfont, because the design's
 * faces are the theme's and are loaded from Google Fonts in `design-source`;
 * the type here falls back to the same system stack the design's own font
 * tokens name after their webfonts.
 *
 * **Every string is somebody else's.** The heading, the body and the address
 * come from `Settings`; the eyebrow and the footer line are WordPress's own site
 * name and tagline. Nothing on the page is a sentence written in PHP, which is
 * CLAUDE.md rule 2 applied to a page David cannot open the editor on.
 *
 * **Accessibility.** A real `lang` from the site's language, a `<title>`, one
 * `<h1>` (`Settings::heading()` guarantees it is never empty), and text that
 * clears WCAG 2.2 AA against the ground: `#ffffff` at 19.5:1, `#b4b4bd` at
 * 9.50:1, `#9095a0` at 6.51:1 and the teal link at 11.09:1, all on `#0c0c0e`.
 * The one place purple appears is the four-stop rule across the top of the card,
 * which carries no text — `--hue-purple` measures 2.80:1 on this ground and is a
 * known open failure, so it is never used for anything readable.
 */
final class Screen {

	/**
	 * The stylesheet, relative to the plugin root.
	 *
	 * @var string
	 */
	public const STYLESHEET = 'assets/css/maintenance.css';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version, for cache busting the stylesheet.
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly string $version
	) {}

	/**
	 * The whole response body.
	 *
	 * @return string
	 */
	public function render(): string {
		return '<!DOCTYPE html>' . "\n"
			. sprintf( '<html lang="%s">', esc_attr( $this->language() ) ) . "\n"
			. $this->head()
			. $this->body()
			. '</html>' . "\n";
	}

	/**
	 * The URL the stylesheet is linked from.
	 *
	 * @return string
	 */
	public function stylesheet_url(): string {
		return add_query_arg(
			'ver',
			$this->version,
			plugin_dir_url( $this->plugin_file ) . self::STYLESHEET
		);
	}

	/**
	 * The document head.
	 *
	 * `robots` repeats the `X-Robots-Tag` header `Curtain` sends. The header is
	 * the one that counts — it reaches a crawler fetching a feed or an image too —
	 * but a holding page indexed as the site is expensive enough to say twice.
	 *
	 * @return string
	 */
	private function head(): string {
		return '<head>' . "\n"
			. sprintf( '<meta charset="%s">', esc_attr( $this->charset() ) ) . "\n"
			. '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
			. '<meta name="robots" content="noindex">' . "\n"
			. sprintf( '<title>%s</title>', esc_html( $this->title() ) ) . "\n"
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- `wp_enqueue_style()` prints through `wp_head()`, and this document has no `wp_head()`: it is built without the theme precisely so it works when the theme is missing or broken. One same-origin `<link>` is the whole of it.
			. sprintf( '<link rel="stylesheet" href="%s">', esc_url( $this->stylesheet_url() ) ) . "\n"
			. '</head>' . "\n";
	}

	/**
	 * The document body.
	 *
	 * @return string
	 */
	private function body(): string {
		return '<body class="dp-maintenance">' . "\n"
			. '<main class="dp-maintenance-frame">' . "\n"
			. '<div class="dp-maintenance-card">' . "\n"
			. $this->eyebrow()
			. sprintf( '<h1 class="dp-maintenance-heading">%s</h1>', esc_html( Settings::heading() ) ) . "\n"
			. $this->message()
			. $this->contact()
			. $this->tagline()
			. '</div>' . "\n"
			. '</main>' . "\n"
			. '</body>' . "\n";
	}

	/**
	 * The site's name above the heading, or nothing when it has none.
	 *
	 * @return string
	 */
	private function eyebrow(): string {
		$name = $this->bloginfo( 'name' );

		if ( '' === $name ) {
			return '';
		}

		return sprintf( '<p class="dp-maintenance-site">%s</p>', esc_html( $name ) ) . "\n";
	}

	/**
	 * The body copy, or nothing when David has left it empty.
	 *
	 * `wpautop()` runs over already-escaped text, so the only markup it can
	 * produce is its own paragraph and break tags; `wp_kses_post()` is what says
	 * so to the reader and to the escaping sniff.
	 *
	 * @return string
	 */
	private function message(): string {
		$message = Settings::message();

		if ( '' === $message ) {
			return '';
		}

		return sprintf(
			'<div class="dp-maintenance-message">%s</div>',
			wp_kses_post( wpautop( esc_html( $message ) ) )
		) . "\n";
	}

	/**
	 * The address, as the only link on the page, or nothing.
	 *
	 * @return string
	 */
	private function contact(): string {
		$address = Settings::contact();

		if ( '' === $address ) {
			return '';
		}

		return sprintf(
			'<p class="dp-maintenance-contact"><a href="%s">%s</a></p>',
			esc_url( 'mailto:' . $address ),
			esc_html( $address )
		) . "\n";
	}

	/**
	 * The site's tagline under a rule, or nothing when it has none.
	 *
	 * @return string
	 */
	private function tagline(): string {
		$description = $this->bloginfo( 'description' );

		if ( '' === $description ) {
			return '';
		}

		return sprintf( '<p class="dp-maintenance-tagline">%s</p>', esc_html( $description ) ) . "\n";
	}

	/**
	 * What the browser tab says.
	 *
	 * The heading and the site's name, because a tab reading only "This site is
	 * being set up" does not say whose site it is. With no site name the heading
	 * stands alone rather than trailing a separator.
	 *
	 * @return string
	 */
	private function title(): string {
		$heading = Settings::heading();
		$name    = $this->bloginfo( 'name' );

		if ( '' === $name ) {
			return $heading;
		}

		return sprintf(
			/* translators: 1: the maintenance heading David set, 2: the site's name. */
			__( '%1$s — %2$s', 'dp-core' ),
			$heading,
			$name
		);
	}

	/**
	 * The site's language, for the `lang` attribute.
	 *
	 * @return string
	 */
	private function language(): string {
		$language = $this->bloginfo( 'language' );

		return '' !== $language ? $language : 'en';
	}

	/**
	 * The site's character set, for the `meta charset`.
	 *
	 * @return string
	 */
	private function charset(): string {
		$charset = $this->bloginfo( 'charset' );

		return '' !== $charset ? $charset : 'UTF-8';
	}

	/**
	 * One value from `get_bloginfo()`, trimmed, as a string.
	 *
	 * @param string $key What to ask for.
	 * @return string
	 */
	private function bloginfo( string $key ): string {
		return trim( get_bloginfo( $key ) );
	}
}
