<?php
/**
 * The kicker and the tone a post is drawn in.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Term;

/**
 * The coloured token above a post's title, derived the way the design derives it.
 *
 * `dpaternina.dc.html` computes it in one line, in three places:
 *
 *     kicker: p.part ? 'SERIES · PART ' + p.part : p.cat
 *     tone:   p.part ? 'pink' : 'teal'
 *
 * and `dp_kicker`'s own registered description says an empty value means derive
 * it. Neither of those is expressible in a template: a block binding returns a
 * string but cannot choose between two meta fields, and a class cannot be bound
 * at all. So the kicker arrives through a **block bindings source**, which is
 * the sanctioned way for a theme to feed a core block a computed string, and the
 * tone arrives as a class added at render time to any block asking for one.
 *
 * Both are presentation and both are the theme's: switching themes changes what
 * a kicker looks like and takes this derivation with it, while `dp_kicker`,
 * `dp_tone` and the series membership stay in the database where `dp-core` put
 * them.
 *
 * The same source also reads the handful of `dp_role` and `dp_ship` fields the
 * homepage's record strip and the work cards print, and that is not a
 * convenience. `core/post-meta` refuses them outright: its callback returns null
 * unless `is_post_publicly_viewable()` agrees, and none of `dp-core`'s three
 * types is viewable — ADR-0003 registers them `public => false` because a role
 * has no URL of its own, not because its job title is a secret. Core's guard is
 * the right default for arbitrary meta on a hidden post; what it cannot know is
 * that these particular fields are the design's public copy. So they are named
 * here, one at a time, per post type. A key that is not on the list below is not
 * readable through this source, which is the difference between an allowlist and
 * a hole.
 */
final class PostPresentation {

	/**
	 * The bindings source name.
	 */
	public const SOURCE = 'dpaternina/post';

	/**
	 * The class that asks for the post's tone to be added.
	 */
	public const TONE_CLASS = 'dp-tone-auto';

	/**
	 * The tone a post in a series takes when nothing overrides it.
	 */
	private const SERIES_TONE = 'pink';

	/**
	 * The tone everything else takes.
	 */
	private const DEFAULT_TONE = 'teal';

	/**
	 * The fields on a hidden post type this source will print, by post type.
	 *
	 * Every one of them is copy the design puts on a public page. Nothing here
	 * is a general meta reader: an unlisted key returns null, and a listed key
	 * returns nothing when asked for on the wrong post type.
	 *
	 * @var array<string, list<string>>
	 */
	private const PUBLIC_FIELDS = array(
		'dp_role' => array( 'dp_role_title', 'dp_range', 'dp_detail', 'dp_stack' ),
		'dp_ship' => array( 'dp_range', 'dp_headline', 'dp_detail', 'dp_line', 'dp_stack', 'dp_ship_role' ),
	);

	/**
	 * The role post type.
	 *
	 * Named as a string rather than through `DP\Core\Content\PostTypes`, the way
	 * the table above already is: a theme that names a plugin's class unguarded
	 * fatals on a site where the plugin is deactivated.
	 */
	private const ROLE_TYPE = 'dp_role';

	/**
	 * The shipped-work post type.
	 */
	private const SHIP_TYPE = 'dp_ship';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_source( ... ) );
		add_filter( 'render_block', $this->add_tone_class( ... ), 10, 2 );
	}

	/**
	 * Register the bindings source.
	 *
	 * @return void
	 */
	public function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Post presentation', 'dpaternina' ),
				'get_value_callback' => $this->value( ... ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Resolve one bound value.
	 *
	 * @param array<string, mixed> $arguments The binding's arguments; `key` selects the field.
	 * @param mixed                $block     The block being rendered. Unused.
	 * @return string|null Null leaves the block's own content in place.
	 */
	public function value( array $arguments, mixed $block = null ): ?string {
		unset( $block );

		$key = isset( $arguments['key'] ) && is_string( $arguments['key'] ) ? $arguments['key'] : '';
		$id  = get_the_ID();

		if ( false === $id ) {
			return null;
		}

		return match ( $key ) {
			'kicker' => $this->kicker( $id ),
			'tone'   => $this->tone( $id ),
			'org'    => $this->org( $id ),
			default  => $this->public_field( $id, $key ),
		};
	}

	/**
	 * One allowlisted field on a `dp_role` or a `dp_ship`.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key asked for.
	 * @return string|null Null when the key is not public for that post type.
	 */
	public function public_field( int $post_id, string $key ): ?string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$allowed = self::PUBLIC_FIELDS[ $post->post_type ] ?? array();

		if ( ! in_array( $key, $allowed, true ) ) {
			return null;
		}

		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * The organisation a shipped thing came out of.
	 *
	 * The design's `WorkCard` prints `org` — "FANXIE LAB", "MONSTERINSIGHTS" —
	 * beside the year, and its `featuredWork` fixture writes the string out on
	 * every card. Nothing in the content model repeats it, and deliberately so:
	 * `DP\Core\Content\Meta`'s own docblock says "`org` is never a meta field",
	 * because for a role the post title **is** the organisation. Storing it a
	 * second time on the ship would create two places to rename Fanxie Lab from.
	 *
	 * So it is derived: follow `dp_role_id` to the role and print its title.
	 * A role asked for its own org has nothing to derive — `core/post-title` is
	 * already the answer — so this key is a shipped thing's only.
	 *
	 * Null when there is nothing to print: an orphan ship, or a `dp_role_id`
	 * pointing at something that is not a role. Null leaves the bound block's own
	 * content in place, which on the card is empty — better than a stray title.
	 *
	 * @param int $post_id The post in the loop.
	 * @return string|null
	 */
	public function org( int $post_id ): ?string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::SHIP_TYPE !== $post->post_type ) {
			return null;
		}

		$role_id = get_post_meta( $post_id, 'dp_role_id', true );
		$role    = is_numeric( $role_id ) ? get_post( (int) $role_id ) : null;

		return $role instanceof WP_Post && self::ROLE_TYPE === $role->post_type
			? $role->post_title
			: null;
	}

	/**
	 * The kicker for one post.
	 *
	 * @param int $post_id The post.
	 * @return string|null
	 */
	public function kicker( int $post_id ): ?string {
		$stored = get_post_meta( $post_id, 'dp_kicker', true );

		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return $stored;
		}

		$part = $this->part( $post_id );

		if ( $part > 0 ) {
			/* translators: %d: the part's number within its series. */
			return sprintf( __( 'Series · Part %d', 'dpaternina' ), $part );
		}

		$category = $this->first_category( $post_id );

		return null === $category ? null : $category->name;
	}

	/**
	 * The tone for one post.
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	public function tone( int $post_id ): string {
		$stored = get_post_meta( $post_id, 'dp_tone', true );

		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return $stored;
		}

		return $this->part( $post_id ) > 0 ? self::SERIES_TONE : self::DEFAULT_TONE;
	}

	/**
	 * Add `is-tone-…` to any block that asked for it.
	 *
	 * @param string               $content The rendered block.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function add_tone_class( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || ! str_contains( ' ' . $class_name . ' ', ' ' . self::TONE_CLASS . ' ' ) ) {
			return $content;
		}

		$id = get_the_ID();

		if ( false === $id ) {
			return $content;
		}

		$tone = sanitize_html_class( $this->tone( $id ) );

		if ( '' === $tone ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );

		if ( $processor->next_tag() ) {
			$processor->add_class( 'is-tone-' . $tone );
		}

		return $processor->get_updated_html();
	}

	/**
	 * The post's part number within its series, or zero.
	 *
	 * @param int $post_id The post.
	 * @return int
	 */
	private function part( int $post_id ): int {
		$value = get_post_meta( $post_id, 'dp_series_part', true );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * The post's first category, in the order WordPress returns them.
	 *
	 * @param int $post_id The post.
	 * @return WP_Term|null
	 */
	private function first_category( int $post_id ): ?WP_Term {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return null;
		}

		$terms = get_the_terms( $post_id, 'category' );

		if ( ! is_array( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		return null;
	}
}
