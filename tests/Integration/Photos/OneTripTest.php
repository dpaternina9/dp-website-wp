<?php
/**
 * Integration tests for the one-trip rule.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\OneTrip;
use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;
use WP_REST_Request;

/**
 * A save carrying two trips is refused, and nothing is written.
 *
 * Refused rather than trimmed: which of two trips David meant is not knowable,
 * so the server does not guess (CLAUDE.md rule 2).
 */
final class OneTripTest extends PhotosTestCase {

	/**
	 * Log in as someone who may edit photos.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->become( 'editor' );
	}

	/**
	 * Two trips on an update: a 400, and the photo keeps what it had.
	 *
	 * @return void
	 */
	public function test_two_trips_are_refused_and_nothing_changes(): void {
		$a     = $this->term( Taxonomies::TRIP, 'Trip A' );
		$b     = $this->term( Taxonomies::TRIP, 'Trip B' );
		$photo = $this->photo(
			array(
				'title' => 'Before',
				'trip'  => $a->term_id,
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . PostType::NAME . '/' . $photo );
		$request->set_body_params(
			array(
				'title'          => 'After',
				Taxonomies::TRIP => array( $a->term_id, $b->term_id ),
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$error = $response->as_error();

		$this->assertNotNull( $error );
		$this->assertSame( OneTrip::ERROR, $error->get_error_code() );
		$this->assertSame( 'Before', get_the_title( $photo ) );
		$this->assertSame( array( $a->term_id ), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Two trips on a create: a 400, and no photo is made.
	 *
	 * @return void
	 */
	public function test_a_new_photo_with_two_trips_is_not_created(): void {
		$a = $this->term( Taxonomies::TRIP, 'Trip A' );
		$b = $this->term( Taxonomies::TRIP, 'Trip B' );

		$before  = wp_count_posts( PostType::NAME );
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . PostType::NAME );
		$request->set_body_params(
			array(
				'title'          => 'Two places at once',
				'status'         => 'draft',
				Taxonomies::TRIP => array( $a->term_id, $b->term_id ),
			)
		);

		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertEquals( $before, wp_count_posts( PostType::NAME ) );
	}

	/**
	 * One trip, or none, saves normally — and so does the same trip twice.
	 *
	 * @return void
	 */
	public function test_one_trip_saves(): void {
		$a     = $this->term( Taxonomies::TRIP, 'Trip A' );
		$photo = $this->photo();

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . PostType::NAME . '/' . $photo );
		$request->set_body_params( array( Taxonomies::TRIP => array( $a->term_id, $a->term_id ) ) );

		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( array( $a->term_id ), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
	}
}
