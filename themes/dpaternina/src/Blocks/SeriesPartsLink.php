<?php
/**
 * "All parts →" — the archive of the series the post being read belongs to.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use WP_Block;
use WP_Post;
use WP_Taxonomy;
use WP_Term;

/**
 * One of the three links ADR-0018 keeps, because nobody can type it.
 *
 * The design's series footer ends on "ALL PARTS →", pointing at the archive of
 * the series this post is part of. That URL is a property of the post being
 * read: it is different on every post, absent on a post in no series, and there
 * is no fixed address a template could carry. So it is computed — and under
 * ADR-0018 a computation announces itself. It used to be a destination class on
 * a `core/button`, which announced nothing at all; it is now a block with a
 * name, a title and an entry in the inserter.
 *
 * The post is taken from the block's `postId` context when there is one, and
 * from the loop otherwise, so the block works inside a query loop as well as on
 * `single.html`. With neither — the site editor's canvas, where the template is
 * being edited rather than applied to a post — the link does not resolve, and it
 * degrades exactly as it does on a post filed under no series: the element stays
 * and the `href` goes (ADR-0008). The stylesheet then removes the gradient frame
 * around it, because a series footer on a post in no series is not something the
 * design draws.
 *
 * The taxonomy is described rather than named — attached to `post`, not one of
 * core's own, flat — which is the same test `DP\Theme\Query\QueryLoops` and
 * `DP\Theme\Blocks\SeriesPlanned` apply, and for the same reason: the theme
 * never repeats a slug that `dp-core` owns.
 */
final class SeriesPartsLink {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/series-parts-link';

	/**
	 * What the anchor announces in `data-dp-destination`.
	 */
	public const DESTINATION = 'post-series';

	/**
	 * The design's class on the wrapper, where the surrounding rules hang.
	 */
	private const WRAPPER_CLASS = 'dp-series-footer-action';

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
			get_theme_file_path( 'blocks/series-parts-link' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the link.
	 *
	 * @param array<string, mixed> $attributes The block's attributes. Unused.
	 * @param string               $content    The block's saved content. Unused.
	 * @param WP_Block|null        $block      The block instance, which carries the post context.
	 * @return string
	 */
	public function render( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $attributes, $content );

		return $this->link->render(
			get_block_wrapper_attributes( array( 'class' => $this->link->wrapper_class( self::WRAPPER_CLASS ) ) ),
			self::BUTTON_CLASS,
			$this->url( $this->post_id( $block ) ),
			__( 'All parts →', 'dpaternina' ),
			self::DESTINATION
		);
	}

	/**
	 * The post whose series is being pointed at.
	 *
	 * @param WP_Block|null $block The block instance.
	 * @return int Zero when there is no post in scope.
	 */
	private function post_id( ?WP_Block $block ): int {
		$context = $block instanceof WP_Block ? ( $block->context['postId'] ?? null ) : null;

		if ( is_numeric( $context ) && (int) $context > 0 ) {
			return (int) $context;
		}

		$id = get_the_ID();

		return false === $id ? 0 : $id;
	}

	/**
	 * The archive of the series one post is filed under.
	 *
	 * A post is in at most one series in practice and the design assumes exactly
	 * that — `SERIES.parts` is one ordered list — so the first term is the answer.
	 *
	 * @param int $post_id The post.
	 * @return string|null
	 */
	public function url( int $post_id ): ?string {
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return null;
		}

		foreach ( get_object_taxonomies( 'post', 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy instanceof WP_Taxonomy || $taxonomy->_builtin || $taxonomy->hierarchical || ! $taxonomy->public ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy->name );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				$link = get_term_link( $term );

				if ( is_string( $link ) ) {
					return $link;
				}
			}
		}

		return null;
	}
}
