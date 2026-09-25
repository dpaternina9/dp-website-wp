<?php
/**
 * Integration tests for "Add photos in bulk" and `wp dp photos import`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Cli\NullOutput;
use DP\Core\Cli\PhotosImportCommand;
use DP\Core\Photos\BulkCreate;
use DP\Core\Photos\BulkReport;
use DP\Core\Photos\BulkRoute;
use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * One photo per image: the EXIF date, the terms, the duplicates, the gate.
 */
final class BulkCreateTest extends PhotosTestCase {

	/**
	 * POST to the route as whoever is logged in.
	 *
	 * @param array<string, mixed> $body The request body.
	 * @return WP_REST_Response
	 */
	private function post( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . BulkRoute::NAMESPACE . BulkRoute::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * Nobody logged in, or a subscriber, gets nothing made.
	 *
	 * @return void
	 */
	public function test_the_route_is_closed_to_anyone_who_cannot_upload_and_create(): void {
		$image = $this->image();

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->post( array( 'attachments' => array( $image ) ) )->get_status() );

		$this->become( 'subscriber' );
		$this->assertSame( 403, $this->post( array( 'attachments' => array( $image ) ) )->get_status() );

		// A contributor can create posts and cannot upload.
		$this->become( 'contributor' );
		$this->assertSame( 403, $this->post( array( 'attachments' => array( $image ) ) )->get_status() );

		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => PostType::NAME,
					'post_status' => 'any',
				)
			)
		);
	}

	/**
	 * An author can make drafts; publishing needs the publish capability.
	 *
	 * @return void
	 */
	public function test_publishing_needs_the_publish_capability(): void {
		$image = $this->image();
		$role  = get_role( 'author' );

		$this->assertNotNull( $role );

		$role->remove_cap( 'publish_posts' );

		try {
			$this->become( 'author' );

			$this->assertSame(
				403,
				$this->post(
					array(
						'attachments' => array( $image ),
						'status'      => 'publish',
					)
				)->get_status()
			);
			$this->assertSame( 200, $this->post( array( 'attachments' => array( $image ) ) )->get_status() );
		} finally {
			$role->add_cap( 'publish_posts' );
		}
	}

	/**
	 * The whole import, as the dialog sends it.
	 *
	 * @return void
	 */
	public function test_it_makes_one_untitled_photo_per_image_dated_by_the_camera(): void {
		$this->become( 'editor' );

		$trip   = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$topic  = $this->term( Taxonomies::TOPIC, 'Water' );
		$dated  = $this->image( 4000, 3000, array( 'created_timestamp' => (string) gmmktime( 14, 30, 5, 12, 11, 2017 ) ) );
		$plain  = $this->image();
		$result = $this->post(
			array(
				'attachments' => array( $dated, $plain ),
				'trip'        => $trip->term_id,
				'topics'      => array( $topic->term_id ),
				'status'      => 'publish',
			)
		);

		$this->assertSame( 200, $result->get_status() );

		$data = $this->report( $result );

		$this->assertIsArray( $data );
		$this->assertCount( 2, $data['created'] );
		$this->assertSame( array(), $data['skipped'] );

		$photo = get_post( $data['created'][0]['photo'] );

		$this->assertInstanceOf( WP_Post::class, $photo );
		$this->assertSame( PostType::NAME, $photo->post_type );
		$this->assertSame( 'publish', $photo->post_status );
		$this->assertSame( '', $photo->post_title, 'A filename is not a title.' );
		$this->assertSame( '2017-12-11 14:30:05', $photo->post_date, 'The camera\'s wall-clock time, unchanged.' );
		$this->assertSame( $dated, (int) get_post_thumbnail_id( $photo ) );
		$this->assertSame( array( $trip->term_id ), wp_get_object_terms( $photo->ID, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array( $topic->term_id ), wp_get_object_terms( $photo->ID, Taxonomies::TOPIC, array( 'fields' => 'ids' ) ) );

		$second = get_post( $data['created'][1]['photo'] );

		$this->assertInstanceOf( WP_Post::class, $second );
		$this->assertNotSame( '2017-12-11 14:30:05', $second->post_date, 'No recorded date: WordPress dates it.' );
	}

	/**
	 * Draft is the default, and a draft keeps the camera's date.
	 *
	 * @return void
	 */
	public function test_drafts_are_the_default_and_keep_their_date(): void {
		$this->become( 'editor' );

		$image = $this->image( 1200, 800, array( 'created_timestamp' => (string) gmmktime( 9, 0, 0, 8, 5, 2016 ) ) );
		$data  = $this->report( $this->post( array( 'attachments' => array( $image ) ) ) );

		$photo = get_post( $data['created'][0]['photo'] );

		$this->assertInstanceOf( WP_Post::class, $photo );
		$this->assertSame( 'draft', $photo->post_status );
		$this->assertSame( '2016-08-05 09:00:00', $photo->post_date );
		$this->assertNotSame( '0000-00-00 00:00:00', $photo->post_date_gmt, 'A floating draft date would be reset on publish.' );
	}

	/**
	 * Running the same import twice makes nothing twice.
	 *
	 * @return void
	 */
	public function test_an_image_that_already_backs_a_photo_is_skipped(): void {
		$this->become( 'editor' );

		$used  = $this->image();
		$fresh = $this->image();

		$this->photo(
			array(
				'image'  => $used,
				'status' => 'draft',
			)
		);

		$data = $this->report( $this->post( array( 'attachments' => array( $used, $fresh, $fresh ) ) ) );

		$this->assertIsArray( $data );
		$this->assertCount( 1, $data['created'] );
		$this->assertSame( $fresh, $data['created'][0]['attachment'] );
		$this->assertSame(
			array(
				array(
					'attachment' => $used,
					'reason'     => BulkReport::ALREADY_A_PHOTO,
				),
			),
			$data['skipped']
		);
		$this->assertStringContainsString( 'Created 1 photo.', $data['summary'] );
		$this->assertStringContainsString( 'Skipped 1 image that is already a photo.', $data['summary'] );
	}

	/**
	 * A post that is not an image, and a term that does not exist.
	 *
	 * @return void
	 */
	public function test_it_refuses_what_is_not_an_image_or_not_a_term(): void {
		$this->become( 'editor' );

		$post = $this->ok( self::factory()->post->create() );
		$data = $this->report( $this->post( array( 'attachments' => array( $post ) ) ) );

		$this->assertIsArray( $data );
		$this->assertSame( BulkReport::NOT_AN_IMAGE, $data['skipped'][0]['reason'] );

		$this->assertSame(
			400,
			$this->post(
				array(
					'attachments' => array( $this->image() ),
					'trip'        => 999999,
				)
			)->get_status()
		);
	}

	/**
	 * `wp dp photos import` is the same import.
	 *
	 * @return void
	 */
	public function test_the_cli_command_runs_the_same_import(): void {
		$trip  = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$image = $this->image( 1000, 1000, array( 'created_timestamp' => (string) gmmktime( 8, 0, 0, 1, 2, 2018 ) ) );

		$command = new PhotosImportCommand( new BulkCreate(), new NullOutput() );
		$command( array( (string) $image ), array( 'trip' => $trip->slug ) );
		$command( array( (string) $image ), array( 'trip' => $trip->slug ) );

		$photos = get_posts(
			array(
				'post_type'   => PostType::NAME,
				'post_status' => 'any',
			)
		);

		$this->assertCount( 1, $photos, 'The second run skipped the image the first run used.' );
		$this->assertSame( '2018-01-02 08:00:00', $photos[0]->post_date );
		$this->assertSame( array( $trip->term_id ), wp_get_object_terms( $photos[0]->ID, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );

		// An unknown trip imports nothing at all.
		$command( array( (string) $this->image() ), array( 'trip' => 'nowhere' ) );

		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type'   => PostType::NAME,
					'post_status' => 'any',
				)
			)
		);
	}
}
