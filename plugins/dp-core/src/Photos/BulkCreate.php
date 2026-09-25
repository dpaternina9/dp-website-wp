<?php
/**
 * One photo per image, in one go.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Post;
use WP_Term;

/**
 * Turns a set of Media Library images into `dp_photo` posts.
 *
 * The one implementation behind both doors — the "Add photos in bulk" dialog on
 * the Photos screen (`BulkRoute`) and `wp dp photos import` (`Cli\PhotosImportCommand`)
 * — so the two can never disagree about what an import does:
 *
 * - **One photo per image**, the image as its featured image, and **no title**.
 *   A filename is not a title, and the page prints nothing for an empty one.
 * - **The publish date is the date the camera recorded** (`Exif::taken()`), when
 *   the file carries one. That is what orders the wall, and it is a value in
 *   plain sight in the sidebar, which David can change. With no recorded date
 *   the post is left to WordPress's own rule: a draft's date floats until it is
 *   published, a published photo is dated now.
 * - **The trip and topics are applied as given**, and the status is draft unless
 *   publish was asked for.
 * - **An image that already backs a photo is skipped**, in any status but the
 *   trash, so running the same import twice makes nothing twice. So is anything
 *   that is not an image.
 *
 * Capabilities are the caller's to check: the REST route checks the current
 * user before it gets here, and WP-CLI runs as whoever it was told to.
 */
final class BulkCreate {

	/**
	 * The statuses an import may create.
	 *
	 * @var list<string>
	 */
	public const STATUSES = array( 'draft', 'publish' );

	/**
	 * Create the photos.
	 *
	 * @param array<int, int> $attachments Attachment IDs, in the order to create them.
	 * @param int             $trip        A `dp_trip` term ID, or 0.
	 * @param array<int, int> $topics      `dp_topic` term IDs.
	 * @param string          $status      `draft` or `publish`.
	 * @param int             $author      The user the photos belong to.
	 * @return BulkReport
	 */
	public function run( array $attachments, int $trip, array $topics, string $status, int $author ): BulkReport {
		$status = in_array( $status, self::STATUSES, true ) ? $status : 'draft';
		$trip   = $trip > 0 && get_term( $trip, Taxonomies::TRIP ) instanceof WP_Term ? $trip : 0;
		$topics = array_values(
			array_filter(
				array_unique( $topics ),
				static fn ( int $id ): bool => $id > 0 && get_term( $id, Taxonomies::TOPIC ) instanceof WP_Term
			)
		);
		$ids    = array_values( array_unique( array_filter( $attachments, static fn ( int $id ): bool => $id > 0 ) ) );
		$backed = $this->already_backed( $ids );
		$report = new BulkReport();

		/*
		 * An imported photo has no title, excerpt or story, on purpose; that it
		 * saves at all is `PostType::allow_empty()`'s doing, for every photo.
		 */
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );

			if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment ) ) {
				$report->skip( $id, BulkReport::NOT_AN_IMAGE );
				continue;
			}

			if ( isset( $backed[ $id ] ) ) {
				$report->skip( $id, BulkReport::ALREADY_A_PHOTO );
				continue;
			}

			$photo = $this->create( $id, $trip, $topics, $status, $author );

			if ( $photo > 0 ) {
				$report->create( $id, $photo );
				$backed[ $id ] = true;
			} else {
				$report->skip( $id, BulkReport::REFUSED );
			}
		}

		return $report;
	}

	/**
	 * Create one photo.
	 *
	 * @param int             $attachment The image.
	 * @param int             $trip       A trip, or 0.
	 * @param array<int, int> $topics     Topics.
	 * @param string          $status     `draft` or `publish`.
	 * @param int             $author     The owner.
	 * @return int The new post's ID, or 0 when WordPress refused it.
	 */
	private function create( int $attachment, int $trip, array $topics, string $status, int $author ): int {
		$postarr = array(
			'post_type'    => PostType::NAME,
			'post_status'  => $status,
			'post_title'   => '',
			'post_content' => '',
			'post_excerpt' => '',
			'post_author'  => $author,
		);

		$taken = Exif::from_metadata( wp_get_attachment_metadata( $attachment ) )->taken();

		if ( null !== $taken ) {
			$postarr['post_date']     = $taken;
			$postarr['post_date_gmt'] = get_gmt_from_date( $taken );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}

		set_post_thumbnail( $post_id, $attachment );

		if ( $trip > 0 ) {
			wp_set_object_terms( $post_id, array( $trip ), Taxonomies::TRIP );
		}

		if ( array() !== $topics ) {
			wp_set_object_terms( $post_id, $topics, Taxonomies::TOPIC );
		}

		return $post_id;
	}

	/**
	 * The images among these that already back a photo.
	 *
	 * @param array<int, int> $ids Attachment IDs.
	 * @return array<int, true> Attachment ID to true.
	 */
	private function already_backed( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'        => PostType::NAME,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one query per import, never on a page view.
				'meta_query'       => array(
					array(
						'key'     => '_thumbnail_id',
						'value'   => array_map( 'strval', $ids ),
						'compare' => 'IN',
					),
				),
			)
		);

		$backed = array();

		foreach ( $found as $post_id ) {
			$image = (int) get_post_thumbnail_id( is_numeric( $post_id ) ? (int) $post_id : 0 );

			if ( $image > 0 ) {
				$backed[ $image ] = true;
			}
		}

		return $backed;
	}
}
