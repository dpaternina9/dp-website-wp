<?php
/**
 * The `dp_photo` post type and its one field.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use DP\Core\Content\MetaAuth;

/**
 * Registers `dp_photo`, and `dp_photo_related_post` on it.
 *
 * **A photo is a post, not an attachment.** The image is the post's featured
 * image and everything the page prints about it is a core field the editor
 * already draws: the title is the title, the place-and-date line is the
 * excerpt, the story is the post content, and the order is the publish date.
 * That leaves exactly one thing core has no field for — which post tells the
 * longer story — and it is the only meta registered here. Everything else a
 * photo "has" is read at render from the attachment behind it (the camera line,
 * from `image_meta`) or from its terms (the trip and the topics).
 *
 * **It has no URL of its own.** `publicly_queryable`, `has_archive`, `rewrite`
 * and `query_var` are all off, as they are for `dp_role`, `dp_ship` and
 * `dp_video` — so WordPress generates no permastruct, no rewrite rule and no
 * query var for it, and `NoHardcodedRoutesTest` holds that. A photo is reached
 * through the page David assigns the `dp-photos` template to, as `?photo=<slug>`
 * on that page, which the `dp/photo-wall` block reads.
 *
 * **It is not one of `PostTypes::all()`, deliberately.** Those three open on a
 * locked form because they carry no prose; a photo's canvas is its story, so
 * it opens on an ordinary block editor with its fields in the sidebar — the
 * same split `page` makes in `fields/page-panel.js`.
 *
 * `custom-fields` is in `supports` for the reason `PostTypes` gives: without it
 * the REST schema has no `meta` property and the related-post picker would have
 * nothing to write to. `DP\Core\Editor\Editor` does not switch the raw panel off
 * here — this type has no bound paragraphs for it to break.
 */
final class PostType {

	/**
	 * The post type.
	 *
	 * @var string
	 */
	public const NAME = 'dp_photo';

	/**
	 * The post that tells this photo's longer story.
	 *
	 * @var string
	 */
	public const RELATED_POST = 'dp_photo_related_post';

	/**
	 * Constructor.
	 *
	 * @param MetaAuth $auth Who may write the field.
	 */
	public function __construct( private readonly MetaAuth $auth = new MetaAuth() ) {}

	/**
	 * Register the post type and its field.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type(
			self::NAME,
			array(
				'labels'              => $this->labels(),
				'description'         => __( 'One photograph for the Photos page. The image is the featured image; the title, the excerpt and the body are what the pop-up prints, and any of them may be empty.', 'dp-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-format-image',
				'menu_position'       => 24,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'author' ),
			)
		);

		register_post_meta(
			self::NAME,
			self::RELATED_POST,
			array(
				'type'              => 'integer',
				'description'       => __( 'A published post that tells this photo\'s longer story. The pop-up links to it as "Read the story"; empty, or pointing at anything that is not published, prints nothing.', 'dp-core' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => self::sanitize_post_id( ... ),
				'auth_callback'     => $this->auth->post_meta( ... ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'        => 'integer',
						'title'       => __( 'Related post', 'dp-core' ),
						'description' => __( 'The post "Read the story" links to.', 'dp-core' ),
						'minimum'     => 0,
					),
				),
			)
		);
	}

	/**
	 * A post ID, or zero.
	 *
	 * Also runs on a direct `update_post_meta()`, so the REST schema's minimum is
	 * a second gate rather than the only one.
	 *
	 * @param mixed $value Whatever was sent.
	 * @return int
	 */
	public static function sanitize_post_id( mixed $value = 0 ): int {
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	/**
	 * The admin labels.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name'                  => __( 'Photos', 'dp-core' ),
			'singular_name'         => __( 'Photo', 'dp-core' ),
			'menu_name'             => __( 'Photos', 'dp-core' ),
			'name_admin_bar'        => __( 'Photo', 'dp-core' ),
			'add_new_item'          => __( 'Add Photo', 'dp-core' ),
			'edit_item'             => __( 'Edit Photo', 'dp-core' ),
			'new_item'              => __( 'New Photo', 'dp-core' ),
			'view_item'             => __( 'View Photo', 'dp-core' ),
			'search_items'          => __( 'Search Photos', 'dp-core' ),
			'not_found'             => __( 'No photos yet.', 'dp-core' ),
			'not_found_in_trash'    => __( 'No photos in the trash.', 'dp-core' ),
			'all_items'             => __( 'All Photos', 'dp-core' ),
			'featured_image'        => __( 'Photo', 'dp-core' ),
			'set_featured_image'    => __( 'Set photo', 'dp-core' ),
			'remove_featured_image' => __( 'Remove photo', 'dp-core' ),
			'use_featured_image'    => __( 'Use as photo', 'dp-core' ),
			'items_list'            => __( 'Photos list', 'dp-core' ),
			'items_list_navigation' => __( 'Photos list navigation', 'dp-core' ),
			'filter_items_list'     => __( 'Filter photos list', 'dp-core' ),
		);
	}
}
