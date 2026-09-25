<?php
/**
 * One photo, as the page draws it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Post;
use WP_Term;

/**
 * Everything the tile and the pop-up print about one `dp_photo`, read once.
 *
 * Every field is a core field or is read from something core already keeps:
 *
 * | Printed as               | Read from                                           |
 * |--------------------------|-----------------------------------------------------|
 * | the image                | the featured image                                   |
 * | the title                | the post title                                      |
 * | the place-and-date line  | the excerpt — the raw field, never the automatic one |
 * | the story                | the post content, rendered                          |
 * | the chips                | the `dp_trip` and `dp_topic` terms                  |
 * | "Read the story →"       | `dp_photo_related_post`, when that post is published |
 * | the camera line          | the image's `image_meta` (`Exif`)                   |
 *
 * **An empty field is empty here**, as the empty string or an empty list, and
 * the markup prints nothing for it: no wrapper, no label, no dash. The excerpt is
 * read from `post_excerpt` directly for that reason — `get_the_excerpt()` would
 * invent one from the story, and a caption David did not write is a hidden
 * rewrite (`CLAUDE.md` rule 2).
 */
final class Photo {

	/**
	 * Constructor.
	 *
	 * @param WP_Post             $post    The `dp_photo`.
	 * @param int                 $image   The featured image's attachment ID.
	 * @param string              $title   The title, or ''.
	 * @param string              $excerpt The place-and-date line, or ''.
	 * @param string              $story   The rendered story, or ''.
	 * @param WP_Term|null        $trip    The trip, if any.
	 * @param array<int, WP_Term> $topics  The topics.
	 * @param string              $related The related post's URL, or ''.
	 * @param string              $camera  The camera line, or ''.
	 */
	public function __construct(
		public readonly WP_Post $post,
		public readonly int $image,
		public readonly string $title,
		public readonly string $excerpt,
		public readonly string $story,
		public readonly ?WP_Term $trip,
		public readonly array $topics,
		public readonly string $related,
		public readonly string $camera
	) {}

	/**
	 * Read one post.
	 *
	 * @param WP_Post $post A `dp_photo`.
	 * @return self|null Null when the post has no image to draw.
	 */
	public static function from_post( WP_Post $post ): ?self {
		$image = (int) get_post_thumbnail_id( $post );

		if ( $image <= 0 ) {
			return null;
		}

		$trips  = get_the_terms( $post, Taxonomies::TRIP );
		$topics = get_the_terms( $post, Taxonomies::TOPIC );

		$trip = is_array( $trips ) && isset( $trips[0] ) && $trips[0] instanceof WP_Term ? $trips[0] : null;

		return new self(
			$post,
			$image,
			trim( get_the_title( $post ) ),
			trim( $post->post_excerpt ),
			self::story( $post ),
			$trip,
			is_array( $topics ) ? array_values( array_filter( $topics, static fn ( mixed $term ): bool => $term instanceof WP_Term ) ) : array(),
			self::related( $post ),
			Exif::from_metadata( wp_get_attachment_metadata( $image ) )->line()
		);
	}

	/**
	 * The name a tile's link is announced by.
	 *
	 * The image's own alt text when David wrote one, then the title, then the
	 * place-and-date line. A photo with none of the three still needs a name —
	 * a link with no name is announced as nothing at all — so the last resort
	 * says what it is and when it was published. It is never printed.
	 *
	 * @return string
	 */
	public function label(): string {
		$alt = get_post_meta( $this->image, '_wp_attachment_image_alt', true );
		$alt = is_string( $alt ) ? trim( $alt ) : '';

		foreach ( array( $alt, $this->title, $this->excerpt ) as $candidate ) {
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		/* translators: %s: the date the photo was published, e.g. "December 12, 2017". */
		return sprintf( __( 'Photo from %s', 'dp-core' ), get_the_date( '', $this->post ) );
	}

	/**
	 * The story, rendered, or '' when there is none to show.
	 *
	 * Rendered the way `the_content` renders a post, minus the filter itself:
	 * running `the_content` from inside a block render re-enters every plugin
	 * hooked to it, and a story is a handful of the site's own blocks.
	 *
	 * @param WP_Post $post A `dp_photo`.
	 * @return string
	 */
	private static function story( WP_Post $post ): string {
		$content = trim( $post->post_content );

		if ( '' === $content ) {
			return '';
		}

		$html = has_blocks( $content ) ? do_blocks( $content ) : wpautop( $content );
		$html = wp_filter_content_tags( do_shortcode( wptexturize( $html ) ) );

		$visible = '' !== trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES ) )
			|| str_contains( $html, '<img' )
			|| str_contains( $html, '<video' )
			|| str_contains( $html, '<iframe' );

		return $visible ? trim( $html ) : '';
	}

	/**
	 * The related post's URL, when it points at something published.
	 *
	 * @param WP_Post $post A `dp_photo`.
	 * @return string
	 */
	private static function related( WP_Post $post ): string {
		$id = PostType::sanitize_post_id( get_post_meta( $post->ID, PostType::RELATED_POST, true ) );

		if ( $id <= 0 ) {
			return '';
		}

		$related = get_post( $id );

		if ( ! $related instanceof WP_Post || 'publish' !== $related->post_status || ! is_post_publicly_viewable( $related ) ) {
			return '';
		}

		$url = get_permalink( $related );

		return is_string( $url ) ? $url : '';
	}
}
