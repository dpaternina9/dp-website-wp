<?php
/**
 * The series taxonomy.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * Registers `dp_series` on `post`.
 *
 * This is the **only page-facing rewrite the project registers** (CLAUDE.md
 * section 5.1 allows exactly two: this and the resume `format` query var in
 * Phase 7). Everything else David creates as a page and slugs himself.
 *
 * Because it is the only one, it is also the only slug the theme could quietly
 * bake in — so it does not bake it in. The slug goes through
 * `dp_series_rewrite_slug`, which means changing `/series/life-story` to
 * `/writing/life-story` is a filter in a snippet, not a code change and a
 * release. That is the same rule as pages, applied to the one route we own.
 */
final class Taxonomies {

	/**
	 * The taxonomy name.
	 *
	 * @var string
	 */
	public const SERIES = 'dp_series';

	/**
	 * The slug shipped by default.
	 *
	 * @var string
	 */
	public const DEFAULT_SERIES_SLUG = 'series';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register(): void {
		register_taxonomy(
			self::SERIES,
			array( 'post' ),
			array(
				'labels'             => array(
					'name'                       => __( 'Series', 'dp-core' ),
					'singular_name'              => __( 'Series', 'dp-core' ),
					'menu_name'                  => __( 'Series', 'dp-core' ),
					'all_items'                  => __( 'All series', 'dp-core' ),
					'edit_item'                  => __( 'Edit series', 'dp-core' ),
					'view_item'                  => __( 'View series', 'dp-core' ),
					'update_item'                => __( 'Update series', 'dp-core' ),
					'add_new_item'               => __( 'Add series', 'dp-core' ),
					'new_item_name'              => __( 'New series name', 'dp-core' ),
					'search_items'               => __( 'Search series', 'dp-core' ),
					'not_found'                  => __( 'No series yet.', 'dp-core' ),
					'no_terms'                   => __( 'No series', 'dp-core' ),
					'separate_items_with_commas' => __( 'Separate series with commas', 'dp-core' ),
					'back_to_items'              => __( 'Back to series', 'dp-core' ),
				),
				'description'        => __( 'A run of posts read in order. The term is also the switch that publishes a planned part: a draft is announced when it is filed under a series, not when it is written.', 'dp-core' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_tagcloud'      => false,
				'hierarchical'       => false,
				'rewrite'            => array(
					'slug'         => $this->rewrite_slug(),
					'with_front'   => false,
					'hierarchical' => false,
				),
			)
		);
	}

	/**
	 * The slug the archive lives under.
	 *
	 * Filtered, then sanitised, then floored: a filter that returns something
	 * that is not a usable slug gets the default back rather than taking the
	 * archive off the air.
	 *
	 * @return string
	 */
	public function rewrite_slug(): string {
		/**
		 * Filters the URL segment the series archive lives under.
		 *
		 * The one registered page-facing rewrite in this project, and therefore
		 * the one David may want to rename without waiting for a release.
		 *
		 * The name is `dp_series_rewrite_slug` rather than a `dp_core_`-prefixed
		 * one because CLAUDE.md section 5.1 and docs/plan.md Phase 3 both specify
		 * it, and it is part of this project's public surface. The prefix list in
		 * phpcs.xml.dist predates it, which is what the suppression below is for.
		 *
		 * @since 0.2.0
		 *
		 * @param string $slug The URL segment. Default 'series'.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered = apply_filters( 'dp_series_rewrite_slug', self::DEFAULT_SERIES_SLUG );

		$slug = sanitize_title( is_string( $filtered ) ? $filtered : '' );

		return '' !== $slug ? $slug : self::DEFAULT_SERIES_SLUG;
	}
}
