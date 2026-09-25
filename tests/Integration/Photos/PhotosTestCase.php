<?php
/**
 * The shared harness for the Photos integration tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;
use WP_REST_Response;
use WP_Term;
use WP_UnitTestCase;

/**
 * Photos, trips and topics made cheaply.
 *
 * **No image file is ever written.** An attachment here is a post with a mime
 * type and the metadata `wp_generate_attachment_metadata()` would have written
 * — width, height, sizes and `image_meta` — which is everything the wall, the
 * pop-up and the importer read. Uploading fifty real files to test pagination
 * would test the uploader.
 *
 * `set_up()` re-registers the type, its taxonomies and its field, for the reason
 * `ContentModelTest` gives: `WP_UnitTestCase::tear_down()` unregisters every
 * meta key in the process, so from the second test onwards the field would be
 * gone. The block and the routes are registered once, by the plugin, and not
 * again — registering a block type twice is a notice, and a notice is a failure.
 */
abstract class PhotosTestCase extends WP_UnitTestCase {

	/**
	 * Re-register the photo content model.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		( new Taxonomies() )->register();
		( new PostType() )->register();
	}

	/**
	 * An image attachment, with the metadata an upload would have given it.
	 *
	 * @param int                  $width      Pixel width.
	 * @param int                  $height     Pixel height.
	 * @param array<string, mixed> $image_meta What the camera recorded.
	 * @return int The attachment ID.
	 */
	protected function image( int $width = 1600, int $height = 1200, array $image_meta = array() ): int {
		$name = 'dp-photo-' . wp_generate_password( 8, false ) . '.jpg';
		$id   = $this->ok(
			self::factory()->attachment->create_object(
				array(
					'file'           => $name,
					'post_mime_type' => 'image/jpeg',
					'post_title'     => $name,
				)
			)
		);

		wp_update_attachment_metadata(
			$id,
			array(
				'width'      => $width,
				'height'     => $height,
				'file'       => $name,
				'sizes'      => array(),
				'image_meta' => array_merge(
					array(
						'camera'            => '',
						'focal_length'      => '0',
						'aperture'          => '0',
						'shutter_speed'     => '0',
						'iso'               => '0',
						'created_timestamp' => '0',
					),
					$image_meta
				),
			)
		);

		return $id;
	}

	/**
	 * A published photo with an image.
	 *
	 * @param array<string, mixed> $args Overrides: title, excerpt, content, date,
	 *                                   status, trip (term ID), topics (term IDs),
	 *                                   image (attachment ID), slug.
	 * @return int The photo's ID.
	 */
	protected function photo( array $args = array() ): int {
		$postarr = array(
			'post_type'    => PostType::NAME,
			'post_status'  => is_string( $args['status'] ?? null ) ? $args['status'] : 'publish',
			'post_title'   => is_string( $args['title'] ?? null ) ? $args['title'] : 'A photo',
			'post_excerpt' => is_string( $args['excerpt'] ?? null ) ? $args['excerpt'] : '',
			'post_content' => is_string( $args['content'] ?? null ) ? $args['content'] : '',
			'post_date'    => is_string( $args['date'] ?? null ) ? $args['date'] : '2018-01-01 12:00:00',
		);

		if ( is_string( $args['slug'] ?? null ) ) {
			$postarr['post_name'] = $args['slug'];
		}

		$allow = static fn (): bool => false;

		add_filter( 'wp_insert_post_empty_content', $allow );
		$id = $this->ok( self::factory()->post->create( $postarr ) );
		remove_filter( 'wp_insert_post_empty_content', $allow );

		$image = is_int( $args['image'] ?? null ) ? $args['image'] : $this->image();

		if ( $image > 0 ) {
			set_post_thumbnail( $id, $image );
		}

		if ( is_int( $args['trip'] ?? null ) ) {
			wp_set_object_terms( $id, array( $args['trip'] ), Taxonomies::TRIP );
		}

		if ( is_array( $args['topics'] ?? null ) ) {
			wp_set_object_terms( $id, array_values( $args['topics'] ), Taxonomies::TOPIC );
		}

		return $id;
	}

	/**
	 * A factory result, which must be an ID.
	 *
	 * @param mixed $result What the factory returned.
	 * @return int
	 */
	protected function ok( mixed $result ): int {
		$this->assertIsInt( $result );

		return $result;
	}

	/**
	 * Log in as a new user with a role.
	 *
	 * @param string $role The role.
	 * @return int The user's ID.
	 */
	protected function become( string $role ): int {
		$user = $this->ok( self::factory()->user->create( array( 'role' => $role ) ) );

		wp_set_current_user( $user );

		return $user;
	}

	/**
	 * The bulk route's answer, typed.
	 *
	 * @param WP_REST_Response $response The response.
	 * @return array{created: list<array{attachment: int, photo: int}>, skipped: list<array{attachment: int, reason: string}>, summary: string}
	 */
	protected function report( WP_REST_Response $response ): array {
		$data = $response->get_data();

		$this->assertIsArray( $data );

		$created = array();
		$skipped = array();

		foreach ( is_array( $data['created'] ?? null ) ? $data['created'] : array() as $row ) {
			if ( is_array( $row ) && is_int( $row['attachment'] ?? null ) && is_int( $row['photo'] ?? null ) ) {
				$created[] = array(
					'attachment' => $row['attachment'],
					'photo'      => $row['photo'],
				);
			}
		}

		foreach ( is_array( $data['skipped'] ?? null ) ? $data['skipped'] : array() as $row ) {
			if ( is_array( $row ) && is_int( $row['attachment'] ?? null ) && is_string( $row['reason'] ?? null ) ) {
				$skipped[] = array(
					'attachment' => $row['attachment'],
					'reason'     => $row['reason'],
				);
			}
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'summary' => is_string( $data['summary'] ?? null ) ? $data['summary'] : '',
		);
	}

	/**
	 * A term.
	 *
	 * @param string $taxonomy `dp_trip` or `dp_topic`.
	 * @param string $name     Its name.
	 * @return WP_Term
	 */
	protected function term( string $taxonomy, string $name ): WP_Term {
		$id   = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'name'     => $name,
			)
		);
		$term = get_term( $id, $taxonomy );

		$this->assertInstanceOf( WP_Term::class, $term );

		return $term;
	}
}
