<?php
/**
 * Integration tests for the curtain, against real requests and a real user table.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Maintenance;

use DP\Core\Maintenance\Curtain;
use DP\Core\Maintenance\Gate;
use DP\Core\Maintenance\Settings;
use WP_Error;

/**
 * Off changes nothing; on stops the public and nobody else.
 *
 * The cases are written against real WordPress roles rather than against a
 * capability list, because "an editor gets in and a subscriber does not" is the
 * claim, and a stubbed `current_user_can()` can only restate the implementation.
 * The URLs are real too — a page, a single post, a 404 and the feed — because
 * "every public URL" is the promise and the feed is the one most easily missed:
 * it is served before `template_include` is ever applied, which is why the
 * curtain hangs on `template_redirect` instead.
 */
final class CurtainTest extends MaintenanceTestCase {

	/**
	 * With the switch off, nothing about the site changes.
	 *
	 * @return void
	 */
	public function test_off_leaves_the_site_alone(): void {
		$this->go_to( $this->permalink( $this->seed_post() ) );

		$this->assertFalse( ( new Gate() )->closes() );
		$this->assertNull( $this->curtain()->refuse_rest( null ) );
	}

	/**
	 * On, and signed out: every kind of public URL is behind the curtain.
	 *
	 * @return void
	 */
	public function test_on_covers_every_public_url(): void {
		$post_id = $this->seed_post();
		$page_id = $this->seed_post( 'page' );

		$this->switch_on();
		wp_set_current_user( 0 );

		$urls = array(
			'the home page'     => home_url( '/' ),
			'a post'            => $this->permalink( $post_id ),
			'a page'            => $this->permalink( $page_id ),
			'the feed'          => get_feed_link(),
			'a URL that is 404' => home_url( '/nothing-is-here/' ),
		);

		foreach ( $urls as $what => $url ) {
			$this->go_to( $url );

			$this->assertTrue( ( new Gate() )->closes(), $what . ' was not behind the curtain.' );
		}
	}

	/**
	 * On, and an editor: the real site, on every one of those URLs.
	 *
	 * @return void
	 */
	public function test_a_user_who_can_edit_posts_gets_the_real_site(): void {
		$post_id = $this->seed_post();

		$this->switch_on();
		$this->sign_in_as( 'editor' );

		foreach ( array( home_url( '/' ), $this->permalink( $post_id ), get_feed_link() ) as $url ) {
			$this->go_to( $url );

			$this->assertFalse( ( new Gate() )->closes(), $url . ' was curtained for an editor.' );
		}
	}

	/**
	 * On, and an administrator: the real site.
	 *
	 * @return void
	 */
	public function test_an_administrator_gets_the_real_site(): void {
		$this->switch_on();
		$this->sign_in_as( 'administrator' );

		$this->go_to( home_url( '/' ) );

		$this->assertFalse( ( new Gate() )->closes() );
	}

	/**
	 * On, and a subscriber: the screen, like anybody else.
	 *
	 * Having an account is not the same as being allowed to watch a site being
	 * built, which is the whole reason the capability is `edit_posts`.
	 *
	 * @return void
	 */
	public function test_a_subscriber_is_still_the_public(): void {
		$this->switch_on();
		$this->sign_in_as( 'subscriber' );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( ( new Gate() )->closes() );
	}

	/**
	 * `dp_maintenance_capability` lets a subscriber in without a deploy.
	 *
	 * @return void
	 */
	public function test_the_capability_filter_widens_the_gate(): void {
		$this->switch_on();
		$this->sign_in_as( 'subscriber' );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( ( new Gate() )->closes() );

		add_filter( 'dp_maintenance_capability', static fn (): string => 'read' );

		$this->assertFalse( ( new Gate() )->closes() );
	}

	/**
	 * The response is a real 503, sent through core's own status machinery.
	 *
	 * @return void
	 */
	public function test_the_response_is_a_503(): void {
		$this->switch_on();
		wp_set_current_user( 0 );

		$this->curtain()->send_headers();

		$this->assertSame( Curtain::STATUS, $this->status );
		$this->assertSame( 503, $this->status );
	}

	/**
	 * An anonymous REST request is refused with the same status the pages carry.
	 *
	 * The front end being dark while `/wp-json/wp/v2/posts` answers is the same
	 * content published twice: everything the screen hides is readable there.
	 *
	 * @return void
	 */
	public function test_an_anonymous_rest_request_is_refused(): void {
		$this->switch_on();
		wp_set_current_user( 0 );

		$refusal = $this->curtain()->refuse_rest( null );

		$this->assertInstanceOf( WP_Error::class, $refusal );
		$this->assertSame( Curtain::REST_ERROR, $refusal->get_error_code() );
		$this->assertSame( array( 'status' => Curtain::STATUS ), $refusal->get_error_data() );
	}

	/**
	 * An editor's REST traffic is untouched, which is what keeps the editor working.
	 *
	 * @return void
	 */
	public function test_an_editor_keeps_the_rest_api(): void {
		$this->switch_on();
		$this->sign_in_as( 'editor' );

		$this->assertNull( $this->curtain()->refuse_rest( null ) );
	}

	/**
	 * A refusal core has already made is passed through, not relabelled.
	 *
	 * Replacing it would tell somebody with a stale nonce that the site is down
	 * and hide the real reason from the only person who could act on it.
	 *
	 * @return void
	 */
	public function test_an_existing_rest_error_survives(): void {
		$this->switch_on();
		wp_set_current_user( 0 );

		$existing = new WP_Error( 'rest_cookie_invalid_nonce', 'Cookie check failed.' );

		$this->assertSame( $existing, $this->curtain()->refuse_rest( $existing ) );
	}

	/**
	 * An internal REST dispatch is not the REST API being served.
	 *
	 * `rest_do_request()` calls `WP_REST_Server::dispatch()` directly and never
	 * reaches `rest_authentication_errors`, which is why a server-rendered block
	 * and the editor's own previews keep working while the curtain is down. This
	 * pins the property rather than the reasoning: if a future WordPress moved
	 * the authentication check into `dispatch()`, this is what would notice.
	 *
	 * @return void
	 */
	public function test_an_internal_rest_dispatch_still_answers(): void {
		$this->seed_post();

		$this->switch_on();
		wp_set_current_user( 0 );

		add_filter( 'rest_authentication_errors', $this->curtain()->refuse_rest( ... ), 1000 );

		rest_get_server();

		$response = rest_do_request( '/wp/v2/posts' );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * The switch is the only thing that turns it on, and the only thing that
	 * turns it off.
	 *
	 * @return void
	 */
	public function test_the_switch_is_the_whole_of_it(): void {
		wp_set_current_user( 0 );
		$this->go_to( home_url( '/' ) );

		$gate = new Gate();

		$this->assertFalse( $gate->closes() );

		update_option( Settings::ENABLED, '1' );

		$this->assertTrue( $gate->closes() );

		update_option( Settings::ENABLED, '' );

		$this->assertFalse( $gate->closes() );
	}
}
