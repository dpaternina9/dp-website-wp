<?php
/**
 * Trips and topics.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * Registers `dp_trip` and `dp_topic` on `dp_photo`.
 *
 * The two are the Photos index, and they differ in exactly one way: **a photo
 * was taken on one trip, and can be about any number of things.**
 *
 * - `dp_trip` is flat, and single. Nothing in WordPress's taxonomy API can say
 *   "exactly one", so three things say it together: the block editor draws a
 *   single-choice control in place of the default panel (`photos/trip-select.js`),
 *   a save that carries two trips is refused rather than trimmed (`OneTrip`),
 *   and the one list-table control that adds terms without removing any — core's
 *   Quick and Bulk Edit checkboxes — is switched off with `show_in_quick_edit`.
 *   The list table gets "Set trip…" in its bulk actions instead, which replaces
 *   (`AdminList`).
 * - `dp_topic` is hierarchical, which in WordPress's vocabulary means "draws as
 *   checkboxes". That is the whole reason: a checkbox list is what core's Quick
 *   Edit and Bulk Edit know how to add to, so topics are assigned in bulk with
 *   nothing of ours involved. A parent topic is allowed and means nothing.
 *
 * Neither is a URL. `public`, `publicly_queryable`, `rewrite` and `query_var`
 * are off, so there is no term archive, no rewrite rule and no query var; the
 * Photos page filters by `?trip=` and `?topic=` itself, and only the page David
 * assigned the template to answers them. `show_ui` and `show_admin_column` are
 * on because being manageable from wp-admin is the point.
 *
 * Counts on the front end do not come from the term's own `count` column. That
 * number is maintained by `_update_post_term_count()`, which counts whatever
 * statuses core decides for whatever types share the taxonomy — so it is read
 * nowhere on the page, and `Library` counts published photos with an image,
 * which is what the wall can actually draw.
 */
final class Taxonomies {

	/**
	 * Where a photo was taken. One per photo.
	 *
	 * @var string
	 */
	public const TRIP = 'dp_trip';

	/**
	 * What a photo is of. Any number per photo.
	 *
	 * @var string
	 */
	public const TOPIC = 'dp_topic';

	/**
	 * Register both.
	 *
	 * @return void
	 */
	public function register(): void {
		register_taxonomy(
			self::TRIP,
			array( PostType::NAME ),
			array(
				'labels'             => array(
					'name'                       => __( 'Trips', 'dp-core' ),
					'singular_name'              => __( 'Trip', 'dp-core' ),
					'menu_name'                  => __( 'Trips', 'dp-core' ),
					'all_items'                  => __( 'All trips', 'dp-core' ),
					'edit_item'                  => __( 'Edit trip', 'dp-core' ),
					'view_item'                  => __( 'View trip', 'dp-core' ),
					'update_item'                => __( 'Update trip', 'dp-core' ),
					'add_new_item'               => __( 'Add trip', 'dp-core' ),
					'new_item_name'              => __( 'New trip name', 'dp-core' ),
					'search_items'               => __( 'Search trips', 'dp-core' ),
					'not_found'                  => __( 'No trips yet.', 'dp-core' ),
					'no_terms'                   => __( 'No trip', 'dp-core' ),
					'separate_items_with_commas' => __( 'A photo belongs to one trip.', 'dp-core' ),
					'back_to_items'              => __( 'Back to trips', 'dp-core' ),
					'filter_by_item'             => __( 'Filter by trip', 'dp-core' ),
				),
				'description'        => __( 'Where a photo was taken. A photo belongs to one trip at most; its date range on the Photos page is worked out from its published photos.', 'dp-core' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => false,
				'show_tagcloud'      => false,
				'hierarchical'       => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);

		register_taxonomy(
			self::TOPIC,
			array( PostType::NAME ),
			array(
				'labels'             => array(
					'name'              => __( 'Topics', 'dp-core' ),
					'singular_name'     => __( 'Topic', 'dp-core' ),
					'menu_name'         => __( 'Topics', 'dp-core' ),
					'all_items'         => __( 'All topics', 'dp-core' ),
					'edit_item'         => __( 'Edit topic', 'dp-core' ),
					'view_item'         => __( 'View topic', 'dp-core' ),
					'update_item'       => __( 'Update topic', 'dp-core' ),
					'add_new_item'      => __( 'Add topic', 'dp-core' ),
					'new_item_name'     => __( 'New topic name', 'dp-core' ),
					'search_items'      => __( 'Search topics', 'dp-core' ),
					'not_found'         => __( 'No topics yet.', 'dp-core' ),
					'no_terms'          => __( 'No topics', 'dp-core' ),
					'parent_item'       => __( 'Parent topic', 'dp-core' ),
					'parent_item_colon' => __( 'Parent topic:', 'dp-core' ),
					'back_to_items'     => __( 'Back to topics', 'dp-core' ),
					'filter_by_item'    => __( 'Filter by topic', 'dp-core' ),
				),
				'description'        => __( 'What a photo is of. A photo can have any number of topics.', 'dp-core' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => true,
				'show_tagcloud'      => false,
				'hierarchical'       => true,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);
	}
}
