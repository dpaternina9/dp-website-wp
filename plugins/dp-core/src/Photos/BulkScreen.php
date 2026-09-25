<?php
/**
 * "Add photos in bulk", on the Photos screen.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Term;

/**
 * The button beside "Add Photo", and the small dialog it opens.
 *
 * The flow is core's media modal — multi-select, uploads allowed — followed by
 * one dialog asking the three things an import applies to every photo: a trip,
 * any topics, and draft or publish. Then one request to `BulkRoute`, and the
 * list reloads showing what was made.
 *
 * **The markup is printed here, in PHP, and the script only drives it.** The
 * trips and topics are real form controls rendered from the terms, the route's
 * path is a data attribute, and the words are in the markup — so the script
 * carries no copy, needs no `wp_localize_script()` blob and ships no inline
 * script, the same rule the series ordering screen keeps (`CLAUDE.md` §1.4).
 * The REST nonce is core's own, added by `wp-api-fetch`.
 *
 * Only on `edit-dp_photo`, and only for a user who could make the request.
 */
final class BulkScreen {

	/**
	 * The script handle.
	 *
	 * @var string
	 */
	public const HANDLE = 'dp-core-photo-bulk';

	/**
	 * The script, relative to the plugin root.
	 *
	 * @var string
	 */
	private const SCRIPT = 'assets/js/photo-bulk.js';

	/**
	 * The stylesheet, relative to the plugin root.
	 *
	 * @var string
	 */
	private const STYLE = 'assets/css/photo-bulk.css';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version, for cache busting.
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly string $version
	) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', $this->enqueue( ... ) );
		add_action( 'admin_footer', $this->markup( ... ) );
	}

	/**
	 * Load the media modal, the script and its styles, on the Photos screen.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->applies() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( self::SCRIPT, $this->plugin_file ),
			array( 'wp-api-fetch', 'media-editor', 'inline-edit-post' ),
			$this->asset_version( self::SCRIPT ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			plugins_url( self::STYLE, $this->plugin_file ),
			array(),
			$this->asset_version( self::STYLE )
		);
	}

	/**
	 * Print the button and the dialog.
	 *
	 * @return void
	 */
	public function markup(): void {
		if ( ! $this->applies() ) {
			return;
		}

		$trips  = $this->terms( Taxonomies::TRIP );
		$topics = $this->terms( Taxonomies::TOPIC );
		$type   = get_post_type_object( PostType::NAME );
		$can    = null !== $type && current_user_can( (string) $type->cap->publish_posts );

		echo '<div hidden data-dp-photo-bulk data-path="' . esc_attr( BulkRoute::NAMESPACE . BulkRoute::ROUTE ) . '"'
			. ' data-modal-title="' . esc_attr__( 'Choose the images to add as photos', 'dp-core' ) . '"'
			. ' data-modal-button="' . esc_attr__( 'Use these images', 'dp-core' ) . '"'
			/* translators: %d: how many images were chosen; always 1. */
			. ' data-chosen-one="' . esc_attr__( '%d image chosen.', 'dp-core' ) . '"'
			/* translators: %d: how many images were chosen. */
			. ' data-chosen-many="' . esc_attr__( '%d images chosen.', 'dp-core' ) . '"'
			. ' data-working="' . esc_attr__( 'Adding photos…', 'dp-core' ) . '"'
			. ' data-failed="' . esc_attr__( 'The photos could not be added.', 'dp-core' ) . '">';

		echo '<button type="button" class="page-title-action dp-photo-bulk-open">' . esc_html__( 'Add photos in bulk', 'dp-core' ) . '</button>';

		echo '<dialog class="dp-photo-bulk-dialog" aria-labelledby="dp-photo-bulk-title">';
		echo '<form method="dialog" class="dp-photo-bulk-form">';
		echo '<h2 id="dp-photo-bulk-title">' . esc_html__( 'Add photos in bulk', 'dp-core' ) . '</h2>';
		echo '<p class="dp-photo-bulk-chosen" aria-live="polite"></p>';
		echo '<p class="description">' . esc_html__( 'One photo is made for each image, with no title. Its publish date is the date the camera recorded, when the file has one. Images that are already photos are skipped.', 'dp-core' ) . '</p>';

		echo '<p><label for="dp-photo-bulk-trip">' . esc_html__( 'Trip', 'dp-core' ) . '</label><br><select id="dp-photo-bulk-trip" name="trip">';
		echo '<option value="0">' . esc_html__( '— No trip —', 'dp-core' ) . '</option>';

		foreach ( $trips as $term ) {
			echo '<option value="' . esc_attr( (string) $term->term_id ) . '">' . esc_html( $term->name ) . '</option>';
		}

		echo '</select></p>';

		if ( array() !== $topics ) {
			echo '<fieldset class="dp-photo-bulk-topics"><legend>' . esc_html__( 'Topics', 'dp-core' ) . '</legend>';

			foreach ( $topics as $term ) {
				echo '<label><input type="checkbox" name="topics" value="' . esc_attr( (string) $term->term_id ) . '"> ' . esc_html( $term->name ) . '</label>';
			}

			echo '</fieldset>';
		}

		echo '<fieldset class="dp-photo-bulk-status"><legend>' . esc_html__( 'Status', 'dp-core' ) . '</legend>';
		echo '<label><input type="radio" name="status" value="draft" checked> ' . esc_html__( 'Draft', 'dp-core' ) . '</label>';

		if ( $can ) {
			echo '<label><input type="radio" name="status" value="publish"> ' . esc_html__( 'Publish', 'dp-core' ) . '</label>';
		}

		echo '</fieldset>';

		echo '<p class="dp-photo-bulk-result" role="status"></p>';
		echo '<p class="dp-photo-bulk-actions">';
		echo '<button type="submit" value="cancel" class="button">' . esc_html__( 'Cancel', 'dp-core' ) . '</button> ';
		echo '<button type="submit" value="create" class="button button-primary">' . esc_html__( 'Add photos', 'dp-core' ) . '</button>';
		echo '</p>';
		echo '</form>';
		echo '</dialog>';
		echo '</div>';
	}

	/**
	 * Whether this is the Photos screen and the user could use the button.
	 *
	 * @return bool
	 */
	private function applies(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || 'edit-' . PostType::NAME !== $screen->id ) {
			return false;
		}

		$type = get_post_type_object( PostType::NAME );

		return null !== $type && current_user_can( 'upload_files' ) && current_user_can( (string) $type->cap->create_posts );
	}

	/**
	 * Every term of one taxonomy, by name.
	 *
	 * @param string $taxonomy The taxonomy.
	 * @return list<WP_Term>
	 */
	private function terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		return is_array( $terms ) ? array_values( array_filter( $terms, static fn ( mixed $term ): bool => $term instanceof WP_Term ) ) : array();
	}

	/**
	 * The version an asset is served under: the file's mtime on a local install.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function asset_version( string $relative ): string {
		if ( 'local' !== wp_get_environment_type() ) {
			return $this->version;
		}

		$path     = plugin_dir_path( $this->plugin_file ) . $relative;
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return false === $modified ? $this->version : $this->version . '.' . (string) $modified;
	}
}
