<?php
/**
 * Integration tests for meta round-trips and permissions.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Taxonomies;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Every field, through the REST API, as the block editor would reach it.
 *
 * `docs/plan.md` chose `register_post_meta()` over ACF on the grounds that it
 * keeps the meta in the REST API "where the editor and the tests can both reach
 * it". These are the tests that cash that in — if a field is not really
 * readable and writable over REST, the sidebar Phase 4 builds has nothing to
 * bind to.
 */
final class ContentMetaTest extends WP_UnitTestCase {

	/**
	 * The REST server for the duration of a test.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * An administrator's user ID.
	 *
	 * @var int
	 */
	private int $administrator;

	/**
	 * A subscriber's user ID.
	 *
	 * @var int
	 */
	private int $subscriber;

	/**
	 * Stand up a REST server and two users.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		/*
		 * `WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`, so
		 * everything the plugin registered on `init` is gone from the second test
		 * onwards. Re-registering here — it is idempotent — is what stops a test
		 * asserting against an empty content model and passing.
		 */
		ContentModel::create()->register();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );

		$this->administrator = $this->user( 'administrator' );
		$this->subscriber    = $this->user( 'subscriber' );
	}

	/**
	 * Put the REST server back.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * A role's fields survive a write and a read over REST.
	 *
	 * @return void
	 */
	public function test_role_meta_round_trips_through_rest(): void {
		wp_set_current_user( $this->administrator );

		$post_id = $this->post( PostTypes::ROLE );

		$written = $this->update_meta(
			PostTypes::ROLE,
			$post_id,
			array(
				'dp_role_title' => 'Developer team lead',
				'dp_start'      => 2022.0,
				'dp_end'        => 2026.4,
				'dp_range'      => '2022 — 2026',
				'dp_stack'      => 'PHP · VUE.JS · REST APIS · WP-CLI',
				'dp_accent'     => 'pink',
			)
		);

		$this->assertSame( 200, $written->get_status(), 'The write was accepted.' );

		$meta = $this->read_meta( PostTypes::ROLE, $post_id );

		$this->assertSame( 'Developer team lead', $meta['dp_role_title'] );
		$this->assertEqualsWithDelta( 2022.0, $meta['dp_start'], 0.000001 );
		$this->assertEqualsWithDelta( 2026.4, $meta['dp_end'], 0.000001 );
		$this->assertSame( '2022 — 2026', $meta['dp_range'] );
		$this->assertSame( 'pink', $meta['dp_accent'] );

		$this->assertEqualsWithDelta( 2026.4, $this->meta_number( $post_id, 'dp_end' ), 0.000001 );
	}

	/**
	 * A shipped thing's list field survives as a list of strings.
	 *
	 * @return void
	 */
	public function test_an_array_field_round_trips(): void {
		wp_set_current_user( $this->administrator );

		$post_id = $this->post( PostTypes::SHIP );

		$bullets = array(
			'No third-party analytics SDKs.',
			'No account required to use the app.',
			'Sync goes through the user’s own iCloud, or not at all.',
		);

		$this->assertSame(
			200,
			$this->update_meta( PostTypes::SHIP, $post_id, array( 'dp_bullets' => $bullets ) )->get_status()
		);

		$this->assertSame( $bullets, $this->read_meta( PostTypes::SHIP, $post_id )['dp_bullets'] );
		$this->assertSame( $bullets, get_post_meta( $post_id, 'dp_bullets', true ) );
	}

	/**
	 * A multiline field keeps its line breaks; a single-line field does not need to.
	 *
	 * The artifact block is a terminal session. Newlines in it are content, and
	 * `sanitize_text_field()` would flatten them into spaces.
	 *
	 * @return void
	 */
	public function test_a_multiline_field_keeps_its_line_breaks(): void {
		wp_set_current_user( $this->administrator );

		$post_id  = $this->post( PostTypes::SHIP );
		$artifact = "$ fx site:create acme --stack=laravel\n→ droplet      ok\n→ dns + tls    ok";

		$this->update_meta( PostTypes::SHIP, $post_id, array( 'dp_artifact' => $artifact ) );

		$stored = $this->meta_text( $post_id, 'dp_artifact' );

		$this->assertStringContainsString( "\n", $stored );
		$this->assertSame( 3, substr_count( $stored, "\n" ) + 1, 'All three lines survived.' );
	}

	/**
	 * A value outside a closed vocabulary is refused at the edge.
	 *
	 * @return void
	 */
	public function test_an_unknown_tone_is_rejected(): void {
		wp_set_current_user( $this->administrator );

		$post_id  = $this->post( PostTypes::VIDEO );
		$response = $this->update_meta( PostTypes::VIDEO, $post_id, array( 'dp_tone' => 'chartreuse' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( '', get_post_meta( $post_id, 'dp_tone', true ) );
	}

	/**
	 * A source outside the two platforms is refused.
	 *
	 * @return void
	 */
	public function test_an_unknown_video_source_is_rejected(): void {
		wp_set_current_user( $this->administrator );

		$post_id  = $this->post( PostTypes::VIDEO );
		$response = $this->update_meta( PostTypes::VIDEO, $post_id, array( 'dp_video_source' => 'vimeo' ) );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * A year outside the calendar is refused.
	 *
	 * @return void
	 */
	public function test_an_impossible_year_is_rejected(): void {
		wp_set_current_user( $this->administrator );

		$post_id = $this->post( PostTypes::ROLE );

		$this->assertSame(
			400,
			$this->update_meta( PostTypes::ROLE, $post_id, array( 'dp_start' => 3000.0 ) )->get_status(),
			'A year past the ceiling.'
		);

		$this->assertSame(
			400,
			$this->update_meta( PostTypes::ROLE, $post_id, array( 'dp_start' => 1500.0 ) )->get_status(),
			'A year before the floor.'
		);
	}

	/**
	 * The sanitiser is a second gate, not a decoration on the first.
	 *
	 * The REST schema only runs for requests. `update_post_meta()` from an
	 * importer or a WP-CLI command bypasses it entirely, which is why the
	 * `sanitize_callback` has to hold on its own.
	 *
	 * @return void
	 */
	public function test_the_sanitiser_holds_without_rest(): void {
		$post_id = $this->post( PostTypes::ROLE );

		update_post_meta( $post_id, 'dp_start', 3000.0 );
		$this->assertSame( 0.0, $this->meta_number( $post_id, 'dp_start' ), 'An unusable year stores as unset.' );

		update_post_meta( $post_id, 'dp_accent', 'chartreuse' );
		$this->assertSame( '', get_post_meta( $post_id, 'dp_accent', true ) );

		update_post_meta( $post_id, 'dp_detail', '<script>alert(1)</script>Real text' );
		$this->assertStringNotContainsString( '<script>', $this->meta_text( $post_id, 'dp_detail' ) );

		update_post_meta( $post_id, 'dp_start', 2022.5 );
		$this->assertEqualsWithDelta( 2022.5, $this->meta_number( $post_id, 'dp_start' ), 0.000001 );
	}

	/**
	 * An unset field reports the default its registration declares.
	 *
	 * @return void
	 */
	public function test_unset_fields_report_their_declared_defaults(): void {
		$post_id = $this->post( PostTypes::SHIP );

		$this->assertSame( '', get_post_meta( $post_id, 'dp_headline', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'dp_line', true ), 'A card with no line of its own prints nothing, not the detail.' );
		$this->assertSame( array(), get_post_meta( $post_id, 'dp_bullets', true ) );
		$this->assertFalse( get_post_meta( $post_id, 'dp_featured', true ) );
		$this->assertSame( 0, get_post_meta( $post_id, 'dp_role_id', true ) );
	}

	/**
	 * The permission callback answers for the post, not for the field.
	 *
	 * `current_user_can( 'edit_post_meta', … )` is the path `map_meta_cap()` takes
	 * to the registered `auth_callback`, so this exercises the callback itself
	 * rather than the route in front of it.
	 *
	 * @return void
	 */
	public function test_the_permission_callback_follows_the_post(): void {
		$author   = $this->user( 'author' );
		$stranger = $this->user( 'author' );

		$post_id = $this->post( PostTypes::ROLE, $author, 'draft' );

		wp_set_current_user( $this->administrator );
		$this->assertTrue( current_user_can( 'edit_post_meta', $post_id, 'dp_detail' ) );

		wp_set_current_user( $author );
		$this->assertTrue( current_user_can( 'edit_post_meta', $post_id, 'dp_detail' ), 'Its author may edit it.' );

		wp_set_current_user( $stranger );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, 'dp_detail' ), 'Another author may not.' );

		wp_set_current_user( $this->subscriber );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, 'dp_detail' ) );

		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, 'dp_detail' ), 'Nobody is not somebody.' );
	}

	/**
	 * A logged-out request cannot write a field.
	 *
	 * @return void
	 */
	public function test_an_anonymous_request_cannot_write_meta(): void {
		$post_id = $this->post( PostTypes::ROLE );

		wp_set_current_user( 0 );

		$response = $this->update_meta( PostTypes::ROLE, $post_id, array( 'dp_detail' => 'Written by nobody.' ) );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertSame( '', get_post_meta( $post_id, 'dp_detail', true ) );
	}

	/**
	 * A term's deck is authorised against the term.
	 *
	 * @return void
	 */
	public function test_term_meta_permission_follows_the_term(): void {
		$term = self::factory()->term->create( array( 'taxonomy' => Taxonomies::SERIES ) );

		$this->assertIsInt( $term );

		wp_set_current_user( $this->administrator );
		$this->assertTrue( current_user_can( 'edit_term_meta', $term, 'dp_series_deck' ) );

		wp_set_current_user( $this->subscriber );
		$this->assertFalse( current_user_can( 'edit_term_meta', $term, 'dp_series_deck' ) );

		wp_set_current_user( $this->administrator );
		update_term_meta( $term, 'dp_series_deck', 'The long version of how I got here.' );
		$this->assertSame( 'The long version of how I got here.', get_term_meta( $term, 'dp_series_deck', true ) );
	}

	/**
	 * Every registered field appears in the post type's REST schema.
	 *
	 * @return void
	 */
	public function test_the_rest_schema_advertises_the_model(): void {
		wp_set_current_user( $this->administrator );

		$response = $this->server->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/' . PostTypes::SHIP ) );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'schema', $data );
		$this->assertIsArray( $data['schema'] );
		$this->assertArrayHasKey( 'properties', $data['schema'] );
		$this->assertIsArray( $data['schema']['properties'] );

		$this->assertArrayHasKey(
			'meta',
			$data['schema']['properties'],
			'custom-fields support puts meta in the schema.'
		);
		$this->assertIsArray( $data['schema']['properties']['meta'] );
		$this->assertIsArray( $data['schema']['properties']['meta']['properties'] );

		$properties = $data['schema']['properties']['meta']['properties'];

		foreach ( array( 'dp_headline', 'dp_line', 'dp_bullets', 'dp_artifact', 'dp_stat1', 'dp_featured' ) as $key ) {
			$this->assertArrayHasKey( $key, $properties, $key . ' is advertised.' );
		}
	}

	/**
	 * Create a post of one type.
	 *
	 * The factory is declared as returning `int|WP_Error`, so every call needs
	 * narrowing before the ID can be used. Doing it once, here, keeps the tests
	 * about the content model rather than about the factory's signature.
	 *
	 * @param string $post_type The post type.
	 * @param int    $author    The author, or 0 for the default.
	 * @param string $status    The post status.
	 * @return int
	 */
	private function post( string $post_type, int $author = 0, string $status = 'publish' ): int {
		$arguments = array(
			'post_type'   => $post_type,
			'post_status' => $status,
		);

		if ( $author > 0 ) {
			$arguments['post_author'] = $author;
		}

		$post_id = self::factory()->post->create( $arguments );

		$this->assertIsInt( $post_id );

		return $post_id;
	}

	/**
	 * Create a user with one role.
	 *
	 * @param string $role The role.
	 * @return int
	 */
	private function user( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		$this->assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Read a meta value that should be text.
	 *
	 * @param int    $post_id  The post.
	 * @param string $meta_key The field.
	 * @return string
	 */
	private function meta_text( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		$this->assertIsString( $value, $meta_key . ' is text.' );

		return $value;
	}

	/**
	 * Read a meta value that should be a number.
	 *
	 * WordPress hands back what the database holds, which for a registered
	 * `number` field is a numeric string rather than a float.
	 *
	 * @param int    $post_id  The post.
	 * @param string $meta_key The field.
	 * @return float
	 */
	private function meta_number( int $post_id, string $meta_key ): float {
		$value = get_post_meta( $post_id, $meta_key, true );

		if ( ! is_numeric( $value ) ) {
			$this->fail( sprintf( '%s is not a number; it is a %s.', $meta_key, get_debug_type( $value ) ) );
		}

		return (float) $value;
	}

	/**
	 * Write meta over REST.
	 *
	 * @param string                                            $post_type The post type.
	 * @param int                                               $post_id   The post.
	 * @param array<string, string|float|int|bool|list<string>> $meta      Fields to write.
	 * @return \WP_REST_Response
	 */
	private function update_meta( string $post_type, int $post_id, array $meta ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/%s/%d', $post_type, $post_id ) );
		$request->set_body_params( array( 'meta' => $meta ) );

		return $this->server->dispatch( $request );
	}

	/**
	 * Read meta over REST.
	 *
	 * @param string $post_type The post type.
	 * @param int    $post_id   The post.
	 * @return array<string, mixed>
	 */
	private function read_meta( string $post_type, int $post_id ): array {
		$response = $this->server->dispatch(
			new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s/%d', $post_type, $post_id ) )
		);

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertIsArray( $data['meta'] );

		$meta = array();

		foreach ( $data['meta'] as $key => $value ) {
			$meta[ (string) $key ] = $value;
		}

		return $meta;
	}
}
