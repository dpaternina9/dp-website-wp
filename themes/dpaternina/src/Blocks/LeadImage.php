<?php
/**
 * The lead image at the top of a post, and the caption under it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use WP_Block;
use WP_Post;

/**
 * The figure the design draws over a post, rendered whole rather than edited into core's.
 *
 * `dpaternina.dc.html`'s post view wraps the lead image in a `<figure>` with two
 * children — the picture and a mono caps caption — and core's block renders the
 * `<figure>` and the `<img>` and stops there. It has no caption attribute and no
 * inner blocks, so there is nothing a template can put inside the figure it
 * produces.
 *
 * **What changed is who writes the markup.** This used to be a filter on
 * `render_block_core/post-featured-image` that found the last `</figure>` with
 * `strrpos()` and pushed a `<figcaption>` in front of it with
 * `substr_replace()`, triggered by the class `dp-post-lead-image`. Three things
 * were wrong with that, and the fragility was the least of them:
 *
 * 1. **The trigger was a bare CSS class** — ADR-0018 rule 2. Nothing about
 *    `dp-post-lead-image` said that PHP would open the rendered figure and put
 *    something in it, and the class reads as a styling hook, which is what a
 *    class is for.
 * 2. **The editor and the page drew different things.** The canvas rendered
 *    core's block, which has no caption; the page had one. That is the exact
 *    divergence `FilterPills` was resolved to remove.
 * 3. **It captioned the wrong post.** It read `get_the_ID()` rather than the
 *    block's `postId` context, so inside any loop over other posts it would have
 *    captioned whichever post happened to be set up.
 *
 * A block that renders the figure itself has none of the three: nothing parses
 * HTML so nothing can misparse it, the editor previews the same callback the
 * page runs, and the post comes from block context the way
 * `DP\Theme\Blocks\WorkCardTitle` takes it.
 *
 * **The words are the attachment's caption, and nothing else** — unchanged, and
 * ADR-0016 is why. There used to be a `dp_hero_caption` post meta field in front
 * of it, on the reasoning that the same photograph could carry a different line
 * on a different post. It had no editor control, so it was blank on every post
 * David wrote and the "fallback" was in fact the only path anybody ever took,
 * while the media library has had a caption box on every attachment since 2003.
 * The field is deleted and the box is the answer. Nothing second: a figure with
 * no caption renders as the design draws it on a post that has none, rather than
 * inventing a line out of the alt text or the title.
 *
 * `inserter => false`: it is the post view's own lead treatment, and
 * `core/post-featured-image` is the block for a featured image anywhere else.
 */
final class LeadImage {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/lead-image';

	/**
	 * The class on the figure.
	 */
	public const LEAD_CLASS = 'dp-post-lead-image';

	/**
	 * The class on the caption itself.
	 */
	public const CAPTION_CLASS = 'dp-post-lead-caption';

	/**
	 * The image size the design's 16/9 crop is taken from.
	 *
	 * `post-thumbnail` is whatever `set_post_thumbnail_size()` says, which is
	 * core's own answer to "the featured image, at the size this theme wants".
	 * The crop itself is `object-fit` in the stylesheet, because the design's
	 * ratio is a property of the box rather than of the file.
	 */
	private const SIZE = 'post-thumbnail';

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
			get_theme_file_path( 'blocks/lead-image' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Draw the figure.
	 *
	 * A post with no featured image, and an attachment with no file behind it,
	 * both render nothing at all — which is what core's own block does, and what
	 * the design draws on a post without a lead.
	 *
	 * @param array<string, mixed> $attributes The block's attributes. Unused.
	 * @param string               $content    The block's inner content. Unused.
	 * @param WP_Block|null        $block      The block instance, which carries `postId`.
	 * @return string
	 */
	public function render( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $attributes, $content );

		$post = $this->post( $block );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$attachment_id = get_post_thumbnail_id( $post );

		if ( ! is_int( $attachment_id ) || $attachment_id <= 0 ) {
			return '';
		}

		$image = wp_get_attachment_image( $attachment_id, self::SIZE );

		if ( '' === $image ) {
			return '';
		}

		return sprintf(
			'<figure %1$s>%2$s%3$s</figure>',
			get_block_wrapper_attributes( array( 'class' => self::LEAD_CLASS ) ),
			$image,
			$this->figcaption( $attachment_id )
		);
	}

	/**
	 * The post whose lead image is being drawn.
	 *
	 * The block's own context first, the global post second. The same order
	 * `DP\Theme\Blocks\WorkCardTitle` uses, and for the same reason: a block
	 * inside a loop is rendering the loop's post, not whichever one the page is
	 * nominally about.
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
	 * The caption element, or the empty string when the attachment carries none.
	 *
	 * @param int $attachment_id The attachment behind the featured image.
	 * @return string
	 */
	private function figcaption( int $attachment_id ): string {
		$caption = $this->caption( $attachment_id );

		if ( '' === $caption ) {
			return '';
		}

		return sprintf(
			'<figcaption class="%1$s">%2$s</figcaption>',
			esc_attr( self::CAPTION_CLASS ),
			esc_html( $caption )
		);
	}

	/**
	 * One attachment's own caption.
	 *
	 * @param int $attachment_id The attachment.
	 * @return string Empty when it carries none.
	 */
	private function caption( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$caption = wp_get_attachment_caption( $attachment_id );

		return is_string( $caption ) ? trim( $caption ) : '';
	}
}
