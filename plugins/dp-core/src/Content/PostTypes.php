<?php
/**
 * The three custom post types.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * Registers `dp_role`, `dp_ship` and `dp_video`.
 *
 * None of the three has a single view, and that is the whole design of them.
 * Roles and ships expand inline on the timeline; videos render in the Watch
 * grid. They are structured data David edits, never URLs — so every one is
 * `public => false`, `publicly_queryable => false`, `rewrite => false`,
 * `has_archive => false` and `query_var => false`, which between them mean
 * WordPress generates no permastruct, no rewrite rule and no query var for any
 * of them. `NoHardcodedRoutesTest` is what keeps that true.
 *
 * They are still `show_in_rest => true`, because the block editor and the
 * integration tests both reach them that way. `show_in_rest` grants a REST route
 * for editors; it does not grant the public a URL.
 *
 * `supports` carries `custom-fields` on all three deliberately: without it
 * `WP_REST_Posts_Controller` omits the `meta` property from the schema entirely,
 * and every registered field would be invisible to the editor and to the tests.
 */
final class PostTypes {

	/**
	 * A role on the timeline. One lane each.
	 *
	 * @var string
	 */
	public const ROLE = 'dp_role';

	/**
	 * Something shipped, hanging off the role it came from.
	 *
	 * @var string
	 */
	public const SHIP = 'dp_ship';

	/**
	 * A stream or a video, for the Watch grid.
	 *
	 * @var string
	 */
	public const VIDEO = 'dp_video';

	/**
	 * All three, in menu order.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::ROLE, self::SHIP, self::VIDEO );
	}

	/**
	 * Register the three post types.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_one(
			self::ROLE,
			$this->labels( __( 'Role', 'dp-core' ), __( 'Roles', 'dp-core' ) ),
			__( 'A job on the timeline. Its lane, its dates, and what it was.', 'dp-core' ),
			'dashicons-id-alt',
			21,
			array( 'title', 'custom-fields', 'page-attributes' )
		);

		$this->register_one(
			self::SHIP,
			$this->labels( __( 'Shipped thing', 'dp-core' ), __( 'Shipped', 'dp-core' ) ),
			__( 'Something that shipped, nested under the role it came from.', 'dp-core' ),
			'dashicons-hammer',
			22,
			// `thumbnail` is the WorkCard shot from digest section 3.4. It is a real
			// attachment, so it belongs to the media library, not to a meta field.
			array( 'title', 'custom-fields', 'page-attributes', 'thumbnail' )
		);

		$this->register_one(
			self::VIDEO,
			$this->labels( __( 'Video', 'dp-core' ), __( 'Videos', 'dp-core' ) ),
			__( 'A stream or a video for the Watch grid. Thumbnails are never uploaded.', 'dp-core' ),
			'dashicons-video-alt3',
			23,
			array( 'title', 'custom-fields', 'page-attributes' )
		);
	}

	/**
	 * Register one post type with the arguments all three share.
	 *
	 * The argument array is built here rather than returned from a helper so it
	 * stays a literal at the call site, which is the only way static analysis can
	 * check it against `register_post_type()`'s shape rather than against
	 * `array<string, mixed>`.
	 *
	 * @param non-empty-string&lowercase-string $post_type The post type name. WordPress rejects anything else.
	 * @param array<string, string>             $labels      Label set.
	 * @param string                            $description What the type is for, shown in the admin.
	 * @param string                            $icon        Dashicon name.
	 * @param int                               $position    Admin menu position.
	 * @param array<int, string>                $supports    Editor features to switch on.
	 * @return void
	 */
	private function register_one( string $post_type, array $labels, string $description, string $icon, int $position, array $supports ): void {
		register_post_type(
			$post_type,
			array(
				'labels'              => $labels,
				'description'         => $description,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => $icon,
				'menu_position'       => $position,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => $supports,
			)
		);
	}

	/**
	 * Build a label set from a singular and a plural.
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return array<string, string>
	 */
	private function labels( string $singular, string $plural ): array {
		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			/* translators: %s: the singular name of the post type. */
			'add_new_item'          => sprintf( __( 'Add %s', 'dp-core' ), $singular ),
			/* translators: %s: the singular name of the post type. */
			'edit_item'             => sprintf( __( 'Edit %s', 'dp-core' ), $singular ),
			/* translators: %s: the singular name of the post type. */
			'new_item'              => sprintf( __( 'New %s', 'dp-core' ), $singular ),
			/* translators: %s: the singular name of the post type. */
			'view_item'             => sprintf( __( 'View %s', 'dp-core' ), $singular ),
			/* translators: %s: the plural name of the post type. */
			'search_items'          => sprintf( __( 'Search %s', 'dp-core' ), $plural ),
			/* translators: %s: the plural name of the post type, lower case. */
			'not_found'             => sprintf( __( 'No %s yet.', 'dp-core' ), strtolower( $plural ) ),
			/* translators: %s: the plural name of the post type, lower case. */
			'not_found_in_trash'    => sprintf( __( 'No %s in the trash.', 'dp-core' ), strtolower( $plural ) ),
			/* translators: %s: the plural name of the post type. */
			'all_items'             => sprintf( __( 'All %s', 'dp-core' ), $plural ),
			/* translators: %s: the plural name of the post type, lower case. */
			'items_list'            => sprintf( __( '%s list', 'dp-core' ), $plural ),
			/* translators: %s: the plural name of the post type, lower case. */
			'items_list_navigation' => sprintf( __( '%s list navigation', 'dp-core' ), $plural ),
		);
	}
}
