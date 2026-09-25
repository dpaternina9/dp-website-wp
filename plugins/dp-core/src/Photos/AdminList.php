<?php
/**
 * The Photos list screen and the Trips screen.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Query;
use WP_Term;

/**
 * What the two list tables need that core does not already draw.
 *
 * **Filter dropdowns.** One for trips and one for topics above the Photos list.
 * Both taxonomies have `query_var` off — they are not URLs — so core cannot turn
 * `?dp_trip=` into a query by itself; `filter_query()` does, on this screen's
 * main query and nowhere else.
 *
 * **"Set trip…" in the bulk actions.** Core's Bulk Edit only ever *adds* terms,
 * which for a one-trip taxonomy would file a photo under two; `dp_trip` is kept
 * out of Quick and Bulk Edit for that reason (`show_in_quick_edit => false`,
 * see `Taxonomies`). This is the replacement: an option group in the bulk-action
 * menu with one entry per trip, plus "No trip", each of which **replaces** the
 * selected photos' trip. No script and no second form — the menu is core's, the
 * nonce is core's `bulk-posts`, and each photo is still checked against
 * `edit_post` before it is touched.
 *
 * **A Dates column on the Trips screen.** The index prints a date range beside
 * every trip, worked out from its published photos. ADR-0018 says a computed
 * value must be visible where it can be managed, so the same `Group::when()` is
 * printed here, beside the trip David would rename or delete.
 */
final class AdminList {

	/**
	 * The prefix of the bulk actions that set a trip.
	 *
	 * @var string
	 */
	public const SET_TRIP = 'dp_set_trip_';

	/**
	 * The query arg that reports how many photos a bulk action changed.
	 *
	 * @var string
	 */
	public const DONE_ARG = 'dp-trip-set';

	/**
	 * The column on the Trips screen.
	 *
	 * @var string
	 */
	public const DATES_COLUMN = 'dp_dates';

	/**
	 * Constructor.
	 *
	 * @param Library $library The published set, for the Dates column.
	 */
	public function __construct( private readonly Library $library ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'restrict_manage_posts', $this->dropdowns( ... ) );
		add_action( 'pre_get_posts', $this->filter_query( ... ) );
		add_filter( 'bulk_actions-edit-' . PostType::NAME, $this->bulk_actions( ... ) );
		add_filter( 'handle_bulk_actions-edit-' . PostType::NAME, $this->handle_bulk_action( ... ), 10, 3 );
		add_action( 'admin_notices', $this->notice( ... ) );
		add_filter( 'manage_edit-' . Taxonomies::TRIP . '_columns', $this->trip_columns( ... ) );
		add_filter( 'manage_' . Taxonomies::TRIP . '_custom_column', $this->trip_column( ... ), 10, 3 );
	}

	/**
	 * The two dropdowns above the Photos list.
	 *
	 * @param string $post_type The list's post type.
	 * @return void
	 */
	public function dropdowns( string $post_type ): void {
		if ( PostType::NAME !== $post_type ) {
			return;
		}

		foreach ( array(
			Taxonomies::TRIP  => __( 'All trips', 'dp-core' ),
			Taxonomies::TOPIC => __( 'All topics', 'dp-core' ),
		) as $taxonomy => $all ) {
			$object = get_taxonomy( $taxonomy );

			printf(
				'<label class="screen-reader-text" for="%1$s">%2$s</label>',
				esc_attr( 'dp-filter-' . $taxonomy ),
				esc_html( false === $object ? $taxonomy : (string) $object->labels->filter_by_item )
			);

			wp_dropdown_categories(
				array(
					'taxonomy'        => $taxonomy,
					'name'            => $taxonomy,
					'id'              => 'dp-filter-' . $taxonomy,
					'value_field'     => 'slug',
					'show_option_all' => $all,
					'hide_empty'      => false,
					'hierarchical'    => Taxonomies::TOPIC === $taxonomy,
					'orderby'         => 'name',
					'selected'        => self::requested( $taxonomy ),
				)
			);
		}
	}

	/**
	 * Narrow the Photos list to the chosen trip and topic.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return void
	 */
	public function filter_query( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || PostType::NAME !== $query->get( 'post_type' ) ) {
			return;
		}

		$clauses = array();

		foreach ( array( Taxonomies::TRIP, Taxonomies::TOPIC ) as $taxonomy ) {
			$slug = self::requested( $taxonomy );

			if ( '' !== $slug ) {
				$clauses[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $slug ),
				);
			}
		}

		if ( array() !== $clauses ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- an admin list filter, run only when a dropdown is set.
			$query->set( 'tax_query', array_merge( array( 'relation' => 'AND' ), $clauses ) );
		}
	}

	/**
	 * Add "Set trip…" to the bulk-action menu.
	 *
	 * @param array<string, string|array<string, string>> $actions The actions so far.
	 * @return array<string, string|array<string, string>>
	 */
	public function bulk_actions( array $actions ): array {
		$trips = get_terms(
			array(
				'taxonomy'   => Taxonomies::TRIP,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$group = array( self::SET_TRIP . '0' => __( 'No trip', 'dp-core' ) );

		foreach ( is_array( $trips ) ? $trips : array() as $trip ) {
			if ( $trip instanceof WP_Term ) {
				$group[ self::SET_TRIP . $trip->term_id ] = $trip->name;
			}
		}

		$actions[ __( 'Set trip…', 'dp-core' ) ] = $group;

		return $actions;
	}

	/**
	 * Replace the trip on every selected photo the user may edit.
	 *
	 * @param string          $redirect Where core will send the browser.
	 * @param string          $action   The chosen action.
	 * @param array<int, int> $post_ids The selected posts.
	 * @return string
	 */
	public function handle_bulk_action( string $redirect, string $action, array $post_ids ): string {
		if ( ! str_starts_with( $action, self::SET_TRIP ) ) {
			return $redirect;
		}

		$term_id = (int) substr( $action, strlen( self::SET_TRIP ) );
		$term    = $term_id > 0 ? get_term( $term_id, Taxonomies::TRIP ) : null;

		if ( $term_id > 0 && ! $term instanceof WP_Term ) {
			return $redirect;
		}

		$changed = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( PostType::NAME !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_set_object_terms( $post_id, $term instanceof WP_Term ? array( $term->term_id ) : array(), Taxonomies::TRIP, false );

			if ( ! is_wp_error( $result ) ) {
				++$changed;
			}
		}

		return add_query_arg( self::DONE_ARG, (string) $changed, $redirect );
	}

	/**
	 * Say how many photos "Set trip…" changed.
	 *
	 * @return void
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || 'edit-' . PostType::NAME !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a count to print, set by our own redirect after core's nonce check.
		$raw = isset( $_GET[ self::DONE_ARG ] ) && is_string( $_GET[ self::DONE_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::DONE_ARG ] ) ) : '';

		if ( ! ctype_digit( $raw ) ) {
			return;
		}

		$count = (int) $raw;

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: how many photos had their trip changed. */
					_n( 'Trip set on %s photo.', 'Trip set on %s photos.', $count, 'dp-core' ),
					number_format_i18n( $count )
				)
			)
		);
	}

	/**
	 * Add the Dates column to the Trips screen, before the count.
	 *
	 * @param array<string, string> $columns The columns so far.
	 * @return array<string, string>
	 */
	public function trip_columns( array $columns ): array {
		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'posts' === $key ) {
				$out[ self::DATES_COLUMN ] = __( 'Dates on the Photos page', 'dp-core' );
			}

			$out[ $key ] = $label;
		}

		if ( ! isset( $out[ self::DATES_COLUMN ] ) ) {
			$out[ self::DATES_COLUMN ] = __( 'Dates on the Photos page', 'dp-core' );
		}

		return $out;
	}

	/**
	 * One trip's date range, worked out from its published photos.
	 *
	 * @param mixed  $content What earlier filters printed.
	 * @param string $column  The column.
	 * @param int    $term_id The trip.
	 * @return string
	 */
	public function trip_column( mixed $content, string $column, int $term_id ): string {
		$content = is_string( $content ) ? $content : '';

		if ( self::DATES_COLUMN !== $column ) {
			return $content;
		}

		$group = $this->library->trip( $term_id );

		if ( null === $group ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No published photos yet', 'dp-core' ) . '</span>';
		}

		return esc_html( $group->when() );
	}

	/**
	 * The slug a list-table dropdown asked for.
	 *
	 * @param string $taxonomy The taxonomy, which is also the query arg.
	 * @return string
	 */
	private static function requested( string $taxonomy ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
		$raw = isset( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';

		return '0' === $raw ? '' : $raw;
	}
}
