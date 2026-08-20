<?php
/**
 * Which nav item is lit, and where the "Get in touch" button goes.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_HTML_Tag_Processor;
use WP_Taxonomy;
use WP_Term;

/**
 * Two corrections to core's chrome, both derived rather than configured.
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
 * **The contact button has no href in the markup.** CLAUDE.md section 5.1 forbids
 * one, and the same section names the legal alternative: the page David assigned
 * the `dp-contact` template to. A button carrying `dp-cta-contact` gets that URL
 * at render time, and renders as nothing at all when no page claims the template
 * — which is the honest outcome, and the same treatment digest section 2.1 gives
 * Watch.
 */
final class Navigation {

	/**
	 * The prefix on a class that asks for a destination.
	 */
	public const DESTINATION_PREFIX = 'dp-to-';

	/**
	 * The destinations that resolve through an assigned custom template.
	 *
	 * The template names are this theme's own, declared in its own theme.json,
	 * which is precisely the branch CLAUDE.md section 5.1 prescribes: "branch on
	 * the assigned template (`get_page_template_slug()`) … never on a slug".
	 * A page carrying `dp-contact` *is* the contact page, by David's decision,
	 * whatever he called it and wherever he moved it.
	 *
	 * They are written without the `.html` extension because that is what
	 * WordPress stores. A block theme's custom templates are offered to the
	 * admin — and validated by the REST API — under their slugs, so a page
	 * assigned Contact from the dropdown carries `dp-contact` in
	 * `_wp_page_template`. `Destinations` normalises either form anyway, since
	 * a page imported from elsewhere may well carry the file name.
	 *
	 * @var array<string, string>
	 */
	public const TEMPLATES = array(
		'contact' => 'dp-contact',
		'work'    => 'dp-work',
		'about'   => 'dp-about',
		'resume'  => 'dp-resume',
	);

	/**
	 * The destinations a link may ask for, by the name after the prefix.
	 *
	 * Each is derived from something David controls rather than from a path this
	 * theme invented: a Reading setting, core's own feed link, or the page
	 * carrying a template he assigned. Adding one means finding another thing of
	 * that kind — not adding an href.
	 *
	 * @var list<string>
	 */
	public const DESTINATIONS = array( 'posts', 'feed', 'contact', 'work', 'about', 'resume' );

	/**
	 * Constructor.
	 *
	 * @param Destinations $destinations Resolves the URLs.
	 */
	public function __construct( private readonly Destinations $destinations ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/navigation', $this->mark_writing_active( ... ), 10, 2 );
		add_filter( 'render_block_core/button', $this->resolve_destination( ... ), 10, 2 );
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
	 * Give a link the URL its class asked for.
	 *
	 * The block markup carries no href at all, which is the point: CLAUDE.md
	 * section 5.1 forbids one, so the template says *what* it is linking to and
	 * this says where that is today. A destination that does not exist yet —
	 * no contact page, because David has not made one — renders as nothing
	 * rather than as a link to a 404, which is the treatment digest section 2.1
	 * gives Watch for the same reason.
	 *
	 * @param string               $content The rendered button.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function resolve_destination( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) ) {
			return $content;
		}

		$wanted = null;

		foreach ( self::DESTINATIONS as $destination ) {
			if ( $this->has_class( $class_name, self::DESTINATION_PREFIX . $destination ) ) {
				$wanted = $destination;

				break;
			}
		}

		if ( null === $wanted ) {
			return $content;
		}

		$url = $this->url_for( $wanted );

		if ( null === $url ) {
			return '';
		}

		$processor = new WP_HTML_Tag_Processor( $content );

		if ( $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			$processor->set_attribute( 'href', $url );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Where one named destination points right now.
	 *
	 * @param string $destination One of self::DESTINATIONS.
	 * @return string|null Null when nothing answers to that name yet.
	 */
	public function url_for( string $destination ): ?string {
		if ( 'posts' === $destination ) {
			return $this->destinations->posts_index();
		}

		if ( 'feed' === $destination ) {
			return get_feed_link();
		}

		return isset( self::TEMPLATES[ $destination ] )
			? $this->destinations->by_template( self::TEMPLATES[ $destination ] )
			: null;
	}

	/**
	 * Whether a class attribute carries one exact class.
	 *
	 * @param string $attribute The class attribute's value.
	 * @param string $wanted    The class to look for.
	 * @return bool
	 */
	private function has_class( string $attribute, string $wanted ): bool {
		$classes = preg_split( '~\s+~', trim( $attribute ) );

		return is_array( $classes ) && in_array( $wanted, $classes, true );
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
