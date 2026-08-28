<?php
/**
 * The work card's title, and the way into the chart underneath it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Blocks\Timeline as TimelineBlock;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Timeline\Chart;
use WP_Block;
use WP_Post;

/**
 * "Clicking a card opens the matching entry on the timeline below."
 *
 * That is `WorkCard.dc.html`'s own note, and it is the whole of this block. The
 * card's title is a link to a row on the chart further down the same page: the
 * href carries a query variable `dp-core` owns, so the server can render that
 * entry already open and the card works with scripting switched off, and it ends
 * on the fragment so the browser lands on the row rather than at the top.
 *
 * Neither half of that address can be typed. The entry key is computed by
 * `dp-core`'s `Chart::entry_key()` from a post type, a slug and an ID, and the
 * query variable's name is the plugin's to choose — the theme is not allowed to
 * know either, which is why both are asked for rather than rebuilt. A format
 * string copied into a second file is a format string that will one day disagree
 * with the first.
 *
 * **It was a class, and that is what this phase is fixing.** A `core/post-title`
 * carrying `dp-card-open` used to have an `<a>` spliced into its rendered markup
 * with `strpos`, `substr` and `strrpos` at render time. Nothing about the class
 * said that would happen, the site editor drew a plain title where the page drew
 * a link, and it was the same shape as the `dp-to-*` system deleted one
 * directory over. ADR-0018's second rule is that a computation announces itself
 * — a name in the inserter, not a bare class — so this is a block.
 *
 * The post comes from the block's `postId` context on the front end, and from
 * the loop otherwise. In the block editor `ServerSideRender` has no block
 * context to give, so the editor asks the block-renderer route for this exact
 * post through `urlQueryArgs`, which sets the post up before rendering: the
 * canvas therefore draws the same title and the same href the page does, rather
 * than a preview of them.
 *
 * With no entry to point at — `dp-core` deactivated, or a post that is not a
 * shipped thing — the title is still drawn and the link goes inert: no `href`,
 * so it is not focusable and cannot reach a page that does not exist, and
 * `aria-disabled` so it announces as an unavailable link rather than as a stray
 * run of text. That is ADR-0008's treatment, the same one `DerivedLink` gives
 * the theme's three computed buttons, and it exists so that "the link is
 * missing" cannot mean both "not set up yet" and "the code is broken".
 */
final class WorkCardTitle {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/work-card-title';

	/**
	 * The class the anchor carries.
	 *
	 * `assets/js/timeline.js` finds the card's link by it, and the stylesheet
	 * hangs the card-wide `::after` target on it, so the name is load-bearing in
	 * three files and stays what it has always been.
	 */
	public const LINK_CLASS = 'dp-card-open';

	/**
	 * What the anchor announces in `data-dp-destination`.
	 */
	public const DESTINATION = 'timeline-entry';

	/**
	 * The design's class on the heading.
	 */
	private const TITLE_CLASS = 'dp-card-title';

	/**
	 * The heading level the design draws, and the attribute's default.
	 */
	private const DEFAULT_LEVEL = 3;

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
	 * Registered whether or not `dp-core` is active, because a block the server
	 * does not know about draws as `core/missing` in the editor; what the plugin
	 * being absent changes is the href, not whether the block exists.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type(
			get_theme_file_path( 'blocks/work-card-title' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the title.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $content    The block's saved content. Unused.
	 * @param WP_Block|null        $block      The block instance, which carries the post context.
	 * @return string
	 */
	public function render( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $content );

		$post = $this->post( $block );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$title = get_the_title( $post );

		if ( '' === trim( $title ) ) {
			return '';
		}

		$level = $this->level( $attributes );

		return sprintf(
			'<h%1$d %2$s>%3$s</h%1$d>',
			$level,
			get_block_wrapper_attributes( array( 'class' => self::TITLE_CLASS ) ),
			$this->anchor( $this->entry_key( $post ), $title )
		);
	}

	/**
	 * The anchor, linked or inert.
	 *
	 * The attribute order is the one the integration suite reads: class, entry,
	 * href. `data-dp-destination` says the href was computed and which
	 * computation produced it, which is the one thing in the rendered DOM that
	 * distinguishes a derived link from a typed one.
	 *
	 * @param string $key   The entry key on the chart, or '' when there is none.
	 * @param string $title The post's title.
	 * @return string
	 */
	private function anchor( string $key, string $title ): string {
		if ( '' === $key ) {
			return sprintf(
				'<a class="%1$s %2$s" role="link" aria-disabled="true" data-dp-destination="%3$s">%4$s</a>',
				esc_attr( self::LINK_CLASS ),
				esc_attr( DerivedLink::UNRESOLVED_CLASS ),
				esc_attr( self::DESTINATION ),
				esc_html( $title )
			);
		}

		return sprintf(
			'<a class="%1$s" data-dp-entry="%2$s" href="%3$s" data-dp-destination="%4$s">%5$s</a>',
			esc_attr( self::LINK_CLASS ),
			esc_attr( $key ),
			esc_url( add_query_arg( TimelineBlock::OPEN_ARG, $key ) . '#' . $key ),
			esc_attr( self::DESTINATION ),
			esc_html( $title )
		);
	}

	/**
	 * The heading level, clamped to the six that exist.
	 *
	 * The design draws `h3`, which is the attribute's default; the level is an
	 * attribute rather than a constant because it is exactly the kind of thing
	 * ADR-0018's first rule says a template gets to say for itself, and a
	 * heading level that cannot be set is a heading level that will one day skip.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return int<1, 6>
	 */
	private function level( array $attributes ): int {
		$level = $attributes['level'] ?? self::DEFAULT_LEVEL;

		if ( ! is_numeric( $level ) ) {
			return self::DEFAULT_LEVEL;
		}

		return match ( (int) $level ) {
			1       => 1,
			2       => 2,
			4       => 4,
			5       => 5,
			6       => 6,
			default => self::DEFAULT_LEVEL,
		};
	}

	/**
	 * The post whose title is being drawn.
	 *
	 * @param WP_Block|null $block The block instance.
	 * @return WP_Post|null
	 */
	private function post( ?WP_Block $block ): ?WP_Post {
		$context = $block instanceof WP_Block ? ( $block->context['postId'] ?? null ) : null;

		if ( is_numeric( $context ) && (int) $context > 0 ) {
			$post = get_post( (int) $context );

			return $post instanceof WP_Post ? $post : null;
		}

		$post = get_post();

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * The timeline entry this post has a row for.
	 *
	 * @param WP_Post $post The post.
	 * @return string The entry key, or '' when there is no row to link to.
	 */
	private function entry_key( WP_Post $post ): string {
		if ( ! class_exists( Chart::class ) || ! class_exists( PostTypes::class ) || ! class_exists( TimelineBlock::class ) ) {
			return '';
		}

		if ( PostTypes::SHIP !== $post->post_type ) {
			return '';
		}

		return Chart::entry_key( $post->post_type, $post->post_name, $post->ID );
	}
}
