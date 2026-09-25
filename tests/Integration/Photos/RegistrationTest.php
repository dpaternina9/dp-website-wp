<?php
/**
 * Integration tests for what the Photos section registers.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\PhotoWall;
use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;
use WP_Block_Type_Registry;
use WP_Error;
use WP_REST_Request;

/**
 * A photo is data, not a URL; a trip is one choice, a topic is a checkbox.
 *
 * The flags are read back from the registry rather than from the source, so
 * what is asserted is what WordPress ended up with. `NoHardcodedRoutesTest`
 * already runs over the whole plugin and still passes unchanged; these add the
 * three objects' own "no route" flags, one by one, because each of them alone
 * would open a URL.
 */
final class RegistrationTest extends PhotosTestCase {

	/**
	 * `dp_photo` has an admin screen and a REST route, and no URL.
	 *
	 * @return void
	 */
	public function test_a_photo_is_editable_and_has_no_page_of_its_own(): void {
		$type = get_post_type_object( PostType::NAME );

		$this->assertNotNull( $type );
		$this->assertTrue( $type->show_ui );
		$this->assertTrue( $type->show_in_rest );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertFalse( $type->has_archive );
		$this->assertFalse( $type->rewrite );
		$this->assertFalse( $type->query_var );
		$this->assertTrue( $type->exclude_from_search );

		foreach ( array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ) as $feature ) {
			$this->assertTrue( post_type_supports( PostType::NAME, $feature ), $feature );
		}
	}

	/**
	 * A photo with nothing but an image saves, from the editor as from an import.
	 *
	 * Core refuses a post whose title, content and excerpt are all empty with
	 * `empty_content`; the editor's save of a titleless photo came back 400
	 * until the post type opted out. Asserted over REST, the path that failed.
	 *
	 * @return void
	 */
	public function test_a_photo_with_no_text_saves(): void {
		$this->become( 'editor' );

		$photo = $this->ok(
			self::factory()->post->create(
				array(
					'post_type'   => PostType::NAME,
					'post_status' => 'draft',
					'post_title'  => 'Temporary',
				)
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . PostType::NAME . '/' . $photo );
		$request->set_body_params(
			array(
				'title'   => '',
				'content' => '',
				'excerpt' => '',
				'status'  => 'publish',
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'publish', get_post_status( $photo ) );
		$this->assertSame( '', get_the_title( $photo ) );
		$this->assertInstanceOf( WP_Error::class, wp_insert_post( array( 'post_type' => 'post' ), true ), 'Other post types keep the check.' );
	}

	/**
	 * Both taxonomies are managed in the admin and are not URLs.
	 *
	 * @dataProvider provide_taxonomies
	 *
	 * @param string $taxonomy The taxonomy.
	 * @return void
	 */
	public function test_a_taxonomy_is_managed_and_has_no_archive( string $taxonomy ): void {
		$object = get_taxonomy( $taxonomy );

		$this->assertNotFalse( $object );
		$this->assertSame( array( PostType::NAME ), $object->object_type );
		$this->assertFalse( $object->public );
		$this->assertFalse( $object->publicly_queryable );
		$this->assertFalse( $object->rewrite );
		$this->assertFalse( $object->query_var );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_in_rest );
		$this->assertTrue( $object->show_admin_column );
	}

	/**
	 * The two taxonomies.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_taxonomies(): array {
		return array(
			'trips'  => array( Taxonomies::TRIP ),
			'topics' => array( Taxonomies::TOPIC ),
		);
	}

	/**
	 * Core's Quick and Bulk Edit only add terms, so trips are kept out of them.
	 *
	 * @return void
	 */
	public function test_trips_stay_out_of_quick_edit_and_topics_stay_in(): void {
		$trip  = get_taxonomy( Taxonomies::TRIP );
		$topic = get_taxonomy( Taxonomies::TOPIC );

		$this->assertNotFalse( $trip );
		$this->assertNotFalse( $topic );
		$this->assertFalse( $trip->show_in_quick_edit );
		$this->assertFalse( $trip->hierarchical );
		$this->assertTrue( $topic->show_in_quick_edit );
		$this->assertTrue( $topic->hierarchical, 'Hierarchical is what draws topics as checkboxes in Quick and Bulk Edit.' );
	}

	/**
	 * The one field: an integer with a REST schema and an auth callback.
	 *
	 * @return void
	 */
	public function test_the_related_post_is_the_only_field_and_is_authorised(): void {
		$keys = get_registered_meta_keys( 'post', PostType::NAME );

		$this->assertSame( array( PostType::RELATED_POST ), array_keys( $keys ) );

		$field = $keys[ PostType::RELATED_POST ];

		$this->assertSame( 'integer', $field['type'] );
		$this->assertIsArray( $field['show_in_rest'] );
		$this->assertIsCallable( $field['auth_callback'] );
		$this->assertNotSame( '__return_true', $field['auth_callback'] );

		$editor = $this->ok( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$reader = $this->ok( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$photo  = $this->photo();

		$this->assertTrue( user_can( $editor, 'edit_post_meta', $photo, PostType::RELATED_POST ) );
		$this->assertFalse( user_can( $reader, 'edit_post_meta', $photo, PostType::RELATED_POST ) );
	}

	/**
	 * The wall is a dynamic block, one per page.
	 *
	 * @return void
	 */
	public function test_the_wall_is_a_dynamic_block(): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( PhotoWall::BLOCK_NAME );

		$this->assertNotNull( $block );
		$this->assertTrue( $block->is_dynamic() );
		$this->assertFalse( $block->supports['multiple'] ?? true );
	}

	/**
	 * No rewrite rule mentions a photo, a trip or a topic.
	 *
	 * @return void
	 */
	public function test_nothing_adds_a_rewrite_rule(): void {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		$rules = $wp_rewrite->wp_rewrite_rules();

		foreach ( array_merge( array_keys( $rules ), array_values( $rules ) ) as $rule ) {
			$this->assertStringNotContainsString( 'dp_photo', (string) $rule );
			$this->assertStringNotContainsString( 'dp_trip', (string) $rule );
			$this->assertStringNotContainsString( 'dp_topic', (string) $rule );
		}
	}
}
