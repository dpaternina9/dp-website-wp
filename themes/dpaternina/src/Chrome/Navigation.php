<?php
/**
 * Which nav item is lit while writing is being read.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_HTML_Tag_Processor;
use WP_Taxonomy;
use WP_Term;

/**
 * One correction to core's chrome, and one answer to a question `dp-core` asks.
 *
 * **The blog reads as active for more than the blog.** Digest section 2.1: "Blog
 * reads as active for `blog`, `post`, `series`, and `category`, which is derived
 * from the queried object, not from a URL match." Core cannot do this on its own
 * — a navigation item is marked current when its target *is* the queried object,
 * and on a single post the queried object is the post, not the page the post is
 * listed on. So the item pointing at the posts index is marked here instead,
 * whenever the thing being viewed is writing.
 *
 * The derivation is deliberately about **shape, not names**: a term is "writing"
 * when its taxonomy is attached to `post`. That covers `category` and `dp_series`
 * without this file knowing either name, keeps working if David renames the
 * series rewrite, and would cover a taxonomy a later phase adds.
 *
 * It also adds a class rather than replacing anything: an item's `href` is
 * whatever David set it to, and nothing here reads it except to compare. That is
 * the whole of ADR-0018's third rule, and this file is what is left of it.
 *
 * **What used to be here is gone.** `resolve_destination()` matched a
 * destination class on a `core/button` and wrote the `href` in, unconditionally
 * — so an href David set in the site editor was shown in the editor and
 * discarded on the front end, triggered by a class nothing in the markup
 * explained. ADR-0018 deletes the mechanism: a link to a page David made is a
 * link he sets once, on an ordinary `core/button`, and the three URLs nobody can
 * type became `DP\Theme\Blocks\SeriesPartsLink`, `ResumeDownload` and
 * `FeedLink`.
 */
final class Navigation {

	/**
	 * Constructor.
	 *
	 * @param Destinations $destinations Reads Settings → Reading.
	 */
	public function __construct( private readonly Destinations $destinations ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/navigation', $this->mark_writing_active( ... ), 10, 2 );
		add_filter( 'dp_destination_url', $this->answer_destination( ... ), 10, 2 );
	}

	/**
	 * Answer `dp-core` when it asks where the posts index is.
	 *
	 * `dp-core` renders one link this theme cannot reach into — the contact
	 * panel's "read something" after a message has been sent — and it may not
	 * decide for itself which page that is. This filter is the seam: the plugin
	 * asks by name, the theme answers from the Reading setting, and neither side
	 * names a class in the other. With the theme switched off nothing answers
	 * and the plugin renders no link.
	 *
	 * `posts` is the only name left. The rest of the destinations went with the
	 * class-triggered resolver (ADR-0018), and this one survives because it is
	 * not a slug anybody invented: it is `page_for_posts`, a setting WordPress
	 * keeps, which no author can type into a link and expect to stay right.
	 *
	 * @param mixed $url         Whatever an earlier filter decided.
	 * @param mixed $destination The destination's name.
	 * @return string|null
	 */
	public function answer_destination( mixed $url, mixed $destination ): ?string {
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}

		return 'posts' === $destination ? $this->destinations->posts_index() : null;
	}

	/**
	 * Mark the posts-index item current while writing is being viewed.
	 *
	 * The item is found by its href, which is not a URL match in the sense
	 * section 5.1 forbids: the URL being compared is the one WordPress itself
	 * derives from Settings to Reading a moment earlier, not a path this theme
	 * decided on. What is *asserted* — that this counts as the blog — comes from
	 * the queried object alone.
	 *
	 * Nothing happens at all unless David has chosen a posts page. Without one
	 * the posts index is the site root, and lighting up whichever item points
	 * there would light up Home on every single post.
	 *
	 * @param string               $content The rendered navigation.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function mark_writing_active( string $content, array $block ): string {
		unset( $block );

		if ( '' === trim( $content ) || null === $this->destinations->posts_page() || ! $this->viewing_writing() ) {
			return $content;
		}

		$target    = $this->destinations->posts_index();
		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			$href = $processor->get_attribute( 'href' );

			if ( ! is_string( $href ) || ! $this->same_url( $href, $target ) ) {
				continue;
			}

			$processor->set_attribute( 'aria-current', 'page' );
			$processor->add_class( 'current-menu-item' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Whether what is being viewed is writing rather than a page.
	 *
	 * @return bool
	 */
	public function viewing_writing(): bool {
		if ( is_singular( 'post' ) ) {
			return true;
		}

		if ( is_home() && ! is_front_page() ) {
			return true;
		}

		$object = get_queried_object();

		if ( ! $object instanceof WP_Term ) {
			return false;
		}

		$taxonomy = get_taxonomy( $object->taxonomy );

		return $taxonomy instanceof WP_Taxonomy && in_array( 'post', (array) $taxonomy->object_type, true );
	}

	/**
	 * Whether two URLs address the same resource.
	 *
	 * Trailing slashes and the scheme are the two differences that show up in
	 * practice: a menu built while the site was on `http` keeps `http` in the
	 * stored href after the site moves to `https`.
	 *
	 * @param string $one The first URL.
	 * @param string $two The second URL.
	 * @return bool
	 */
	private function same_url( string $one, string $two ): bool {
		return $this->normalise( $one ) === $this->normalise( $two );
	}

	/**
	 * Reduce a URL to the part worth comparing.
	 *
	 * @param string $url The URL.
	 * @return string
	 */
	private function normalise( string $url ): string {
		$without_scheme = (string) preg_replace( '~^https?://~i', '', $url );

		return untrailingslashit( $without_scheme );
	}
}
