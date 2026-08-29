<?php
/**
 * The chrome's links, filled in once for a seeded site.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_Block_Patterns_Registry;
use WP_Block_Template;
use WP_HTML_Tag_Processor;

/**
 * Answers `dp-core`'s question "where do these buttons go?" with saved markup.
 *
 * ADR-0018 deleted the `dp-to-*` system: a `core/button` no longer carries a
 * class that PHP matches at render time, and no filter writes an `href` into
 * rendered output. A link to a page is a link David sets in the site editor,
 * once, and the shipped templates carry the design's words and no URLs.
 *
 * That leaves a real gap on a **seeded** site, and this class closes it —
 * without reopening the mechanism the ADR removed. The difference is worth
 * stating precisely, because from the outside the two look similar:
 *
 * - The old system ran on **every request**, matched an **invisible class**, and
 *   **overwrote** whatever href was there. Nothing in the editor said it would.
 * - This runs **once, during `wp dp seed`**, matches a **`metadata.name`** that
 *   is visible in the site editor's List View, and writes an ordinary
 *   `wp_template` / `wp_template_part` post — which is byte-for-byte the kind of
 *   thing the site editor itself saves when David links a button by hand. From
 *   the front end's point of view nothing is computed at all: there is saved
 *   markup with hrefs in it, exactly as if a person had set them.
 *
 * **Which side owns what.** `dp-core` creates the pages and knows their IDs; it
 * may not know this theme's file names, block names, labels or markup
 * (CLAUDE.md section 2.1). So the plugin hands over a map of *its* destination
 * keys to URLs and asks for saved markup back. This class does the reading, the
 * matching and the serialising, and hands back opaque strings the plugin writes
 * without looking inside. With the theme switched off nothing answers the
 * filter and the seeder writes no overrides at all.
 *
 * **Where the markup comes from, and why it matters.** Each override is
 * generated from `get_block_file_template()`, which reads the *file the theme
 * currently ships* and deliberately ignores any stored override. A stored
 * override wins over the file forever, so one generated from a stale copy of
 * itself would freeze the template at whatever the theme looked like the first
 * time anybody seeded. Regenerating from the file on every run is what stops
 * that; a stale `home` override rendering a block the theme had already
 * replaced is a bug this project has actually had.
 *
 * **It never takes a link away.** A button that already carries a `url` in the
 * shipped file is left exactly as it is — ADR-0018's third rule, kept here even
 * though the shipped files carry none today, because the day one does is the day
 * a silent overwrite would start.
 */
final class SeededLinks {

	/**
	 * The filter `dp-core`'s seeder asks this question through.
	 *
	 * @var string
	 */
	public const FILTER = 'dp_seed_chrome_links';

	/**
	 * The shipped files that carry a named link, and what type each one is.
	 *
	 * Five, and no more, on purpose. Every stored override is a template frozen
	 * against later theme releases until the next seed run regenerates it, and it
	 * is also a template whose edits a re-seed discards — so the set is kept to
	 * the chrome that appears on every page plus the three views the design gives
	 * a way out of. The links on the other templates are David's to set, which is
	 * what ADR-0018 decided.
	 *
	 * A list of pairs rather than a slug-keyed map, because `404` is a slug and
	 * PHP would silently turn that key into the integer 404.
	 */
	private const FILES = array(
		array( 'header', 'wp_template_part' ),
		array( 'footer', 'wp_template_part' ),
		array( 'front-page', 'wp_template' ),
		array( 'home', 'wp_template' ),
		array( '404', 'wp_template' ),
	);

	/**
	 * The name a button carries in List View, and the destination it is for.
	 *
	 * A `metadata.name` rather than a class, because ADR-0018's second rule is
	 * that a computation announces itself and "a bare CSS class is not an
	 * announcement". This one is: open the header in the site editor, look at the
	 * List View, and the button is called "Contact link" instead of "Button".
	 *
	 * The keys on the right are `dp-core`'s, not this theme's. It creates the
	 * pages, so it names them.
	 *
	 * @var array<string, string>
	 */
	private const DESTINATIONS = array(
		'Home link'     => 'home',
		'Writing link'  => 'posts',
		'Work link'     => 'work',
		'Watch link'    => 'watch',
		'About link'    => 'about',
		'Contact link'  => 'contact',
		'Résumé link'   => 'resume',
		'Uses link'     => 'uses',
		'Colophon link' => 'colophon',
		'Privacy link'  => 'privacy',
		'Series link'   => 'series',
	);

	/**
	 * Attach the filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( self::FILTER, $this->answer( ... ), 10, 2 );
	}

	/**
	 * Build one saved override per shipped file that has a link to fill.
	 *
	 * The return value is a filter's, so it is only as well typed as whatever
	 * else is on the hook; `DP\Core\Fixture\ChromeLinks` is the gate on the
	 * other side, and checking the shape there rather than asserting it here is
	 * what lets the plugin trust an answer it did not produce.
	 *
	 * @param mixed $overrides    Whatever an earlier filter decided.
	 * @param mixed $destinations Destination key to URL, from the seeder.
	 * @return array<int, mixed> One entry per override, each with `type`, `slug`,
	 *                           `title`, `area` and `content`.
	 */
	public function answer( mixed $overrides, mixed $destinations ): array {
		$answer = is_array( $overrides ) ? array_values( $overrides ) : array();
		$urls   = $this->urls( $destinations );

		if ( array() === $urls ) {
			return $answer;
		}

		foreach ( self::FILES as list( $slug, $type ) ) {
			$template = get_block_file_template( get_stylesheet() . '//' . $slug, $type );

			if ( ! $template instanceof WP_Block_Template || ! is_string( $template->content ) ) {
				continue;
			}

			$filled  = 0;
			$content = $this->serialize( parse_blocks( $template->content ), $urls, $filled );

			if ( 0 === $filled ) {
				continue;
			}

			$answer[] = array(
				'type'    => $type,
				'slug'    => $slug,
				'title'   => is_string( $template->title ) && '' !== $template->title ? $template->title : $slug,

				/*
				 * A template part's area is a `wp_template_part_area` term on
				 * the saved post, and it is what core turns into the wrapping
				 * `<header>` or `<footer>` tag. A part saved without it renders
				 * inside a `<div>`, which is a landmark quietly lost.
				 */
				'area'    => 'wp_template_part' === $type && is_string( $template->area ) ? $template->area : '',
				'content' => $content,
			);
		}

		return $answer;
	}

	/**
	 * The seeder's answer, reduced to the destinations that resolved.
	 *
	 * @param mixed $destinations Whatever the seeder passed.
	 * @return array<string, string>
	 */
	private function urls( mixed $destinations ): array {
		if ( ! is_array( $destinations ) ) {
			return array();
		}

		$urls = array();

		foreach ( $destinations as $key => $url ) {
			if ( is_string( $key ) && is_string( $url ) && '' !== $url ) {
				$urls[ $key ] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Serialise a parse tree, linking every named button on the way through.
	 *
	 * `traverse_and_serialize_blocks()` walks the whole tree and serialises each
	 * block *after* handing it to the callback by reference — so mutating the
	 * block is how a change reaches the saved markup. That function carries
	 * core's "meant for internal use" note and is used deliberately anyway: it is
	 * what core itself uses to inject an attribute into template markup
	 * (`_inject_theme_attribute_in_block_template_content`), and the alternative
	 * is hand-rolling the same recursion.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks The parse tree.
	 * @param array<string, string>                   $urls   Destination key to URL.
	 * @param int                                     $filled How many links have been written, by reference.
	 * @return string Serialised block markup.
	 */
	private function serialize( array $blocks, array $urls, int &$filled ): string {
		return traverse_and_serialize_blocks(
			$blocks,
			function ( array &$block ) use ( $urls, &$filled ): string {
				$this->fill( $block, $urls, $filled );

				return '';
			}
		);
	}

	/**
	 * Handle one block: a button to link, or a pattern that contains one.
	 *
	 * @param array<string, mixed>  $block  One parsed block, by reference.
	 * @param array<string, string> $urls   Destination key to URL.
	 * @param int                   $filled How many links have been written, by reference.
	 * @return void
	 */
	private function fill( array &$block, array $urls, int &$filled ): void {
		$name = $block['blockName'] ?? null;

		if ( 'core/pattern' === $name ) {
			$this->inline( $block, $urls, $filled );

			return;
		}

		if ( 'core/button' === $name ) {
			$this->link( $block, $urls, $filled );
		}
	}

	/**
	 * Replace a `core/pattern` reference with the pattern's own linked markup.
	 *
	 * A pattern is resolved from the theme's registry at render time, so a saved
	 * template that merely *references* one cannot carry a link into it — which
	 * is why the closing CTA band's button was the one thing on a seeded home
	 * page that still went nowhere.
	 *
	 * Expanding it is not a workaround: opening a template in the site editor
	 * replaces every `core/pattern` block with its content before anything is
	 * saved, so this is the shape David's own first save would have produced.
	 *
	 * **Only where it buys a link.** A pattern whose expansion gained nothing is
	 * left as a reference, because inlining freezes it: `dpaternina/post-row-list`
	 * carries the whole query loop and the pager, and a seeded copy of those going
	 * stale against the theme is a much worse trade than a button that has to be
	 * linked by hand. That is also why the two series buttons inside it are
	 * deliberately not named.
	 *
	 * @param array<string, mixed>  $block  The `core/pattern` block, by reference.
	 * @param array<string, string> $urls   Destination key to URL.
	 * @param int                   $filled How many links have been written, by reference.
	 * @return void
	 */
	private function inline( array &$block, array $urls, int &$filled ): void {
		$attributes = $block['attrs'] ?? null;
		$slug       = is_array( $attributes ) ? ( $attributes['slug'] ?? null ) : null;

		if ( ! is_string( $slug ) || '' === $slug ) {
			return;
		}

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
		$content = is_array( $pattern ) ? ( $pattern['content'] ?? null ) : null;

		if ( ! is_string( $content ) || '' === $content ) {
			return;
		}

		$gained = 0;
		$markup = $this->serialize( parse_blocks( $content ), $urls, $gained );

		if ( 0 === $gained ) {
			return;
		}

		$filled += $gained;

		/*
		 * A block with no name serialises as its own content and nothing else,
		 * so the reference becomes the markup it stood for.
		 */
		$block['blockName']    = null;
		$block['attrs']        = array();
		$block['innerHTML']    = $markup;
		$block['innerContent'] = array( $markup );
	}

	/**
	 * Link one parsed button, if it is a named one waiting for a link.
	 *
	 * Both halves have to be written. `core/button` is a static block, so the
	 * front end renders the saved anchor and only the `href` matters there; the
	 * editor rebuilds the anchor from `url` and flags the block as invalid if the
	 * two disagree.
	 *
	 * @param array<string, mixed>  $block  One parsed block, by reference.
	 * @param array<string, string> $urls   Destination key to URL.
	 * @param int                   $filled How many links have been written, by reference.
	 * @return void
	 */
	private function link( array &$block, array $urls, int &$filled ): void {
		$attributes = $block['attrs'] ?? null;

		if ( ! is_array( $attributes ) ) {
			return;
		}

		/*
		 * ADR-0018's third rule. A link that is already there is somebody's
		 * decision, and nothing here may take it away.
		 */
		if ( is_string( $attributes['url'] ?? null ) && '' !== $attributes['url'] ) {
			return;
		}

		$metadata = $attributes['metadata'] ?? null;
		$name     = is_array( $metadata ) ? ( $metadata['name'] ?? null ) : null;

		if ( ! is_string( $name ) ) {
			return;
		}

		$key = self::DESTINATIONS[ $name ] ?? '';
		$url = '' === $key ? '' : ( $urls[ $key ] ?? '' );

		if ( '' === $url ) {
			return;
		}

		$content = $block['innerContent'] ?? null;

		if ( ! is_array( $content ) || ! is_string( $content[0] ?? null ) ) {
			return;
		}

		$linked = $this->set_href( $content[0], $url );

		if ( null === $linked ) {
			return;
		}

		$attributes['url'] = $url;
		$content[0]        = $linked;

		$block['attrs']        = $attributes;
		$block['innerHTML']    = $linked;
		$block['innerContent'] = $content;

		++$filled;
	}

	/**
	 * Put an href on the anchor inside a button's saved HTML.
	 *
	 * @param string $html The button's `innerHTML`.
	 * @param string $url  The link.
	 * @return string|null The updated HTML, or null when there was no anchor.
	 */
	private function set_href( string $html, string $url ): ?string {
		$tags = new WP_HTML_Tag_Processor( $html );

		if ( ! $tags->next_tag( array( 'tag_name' => 'A' ) ) ) {
			return null;
		}

		$tags->set_attribute( 'href', $url );

		return $tags->get_updated_html();
	}
}
