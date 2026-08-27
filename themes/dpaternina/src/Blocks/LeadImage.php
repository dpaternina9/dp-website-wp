<?php
/**
 * The caption under a post's lead image.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

/**
 * Gives `core/post-featured-image` the `<figcaption>` the design draws under it.
 *
 * `dpaternina.dc.html`'s post view wraps the lead image in a `<figure>` with two
 * children — the picture and a mono caps caption — and the digest names the
 * field behind it: `dp_hero_caption`, "mono caps caption under the lead image
 * (`CAPTIONS`)". Core's block renders the `<figure>` and the `<img>` and stops
 * there; it has no caption attribute and no inner blocks, so there is nothing a
 * template can put inside the figure it produces.
 *
 * So the caption is appended to the rendered block, and only to a block that
 * asked for one by class. An ordinary featured image somewhere else on the site
 * is untouched.
 *
 * **The words are the attachment's caption, and nothing else.** There used to be
 * a `dp_hero_caption` post meta field in front of it, on the reasoning that the
 * same photograph could carry a different line on a different post. It had no
 * editor control, so it was blank on every post David wrote and the "fallback"
 * was in fact the only path anybody ever took — while the media library has had
 * a caption box on every attachment since 2003. The field is deleted (ADR-0016)
 * and the box is the answer. Nothing second: a figure with no caption renders as
 * the design draws it on a post that has none, rather than inventing a line out
 * of the alt text or the title.
 */
final class LeadImage {

	/**
	 * The class a `core/post-featured-image` carries to be captioned.
	 */
	public const LEAD_CLASS = 'dp-post-lead-image';

	/**
	 * The class on the caption itself.
	 */
	public const CAPTION_CLASS = 'dp-post-lead-caption';

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_core/post-featured-image', $this->add_caption( ... ), 10, 2 );
	}

	/**
	 * Append the caption to a lead image that has one.
	 *
	 * @param string               $content The rendered figure.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function add_caption( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || ! str_contains( ' ' . $class_name . ' ', ' ' . self::LEAD_CLASS . ' ' ) ) {
			return $content;
		}

		$closing = strrpos( $content, '</figure>' );

		if ( false === $closing ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( false === $post_id ) {
			return $content;
		}

		$caption = $this->caption( $post_id );

		if ( '' === $caption ) {
			return $content;
		}

		$figcaption = sprintf(
			'<figcaption class="%1$s">%2$s</figcaption>',
			esc_attr( self::CAPTION_CLASS ),
			esc_html( $caption )
		);

		return substr_replace( $content, $figcaption, $closing, 0 );
	}

	/**
	 * The caption for one post's lead image.
	 *
	 * @param int $post_id The post.
	 * @return string Empty when the attachment carries none.
	 */
	public function caption( int $post_id ): string {
		$attachment_id = get_post_thumbnail_id( $post_id );

		if ( ! is_int( $attachment_id ) || $attachment_id <= 0 ) {
			return '';
		}

		$attachment = wp_get_attachment_caption( $attachment_id );

		return is_string( $attachment ) ? trim( $attachment ) : '';
	}
}
