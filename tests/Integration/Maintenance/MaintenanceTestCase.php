<?php
/**
 * The shared harness for the maintenance screen's integration tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Maintenance;

use DP\Core\Maintenance\Curtain;
use DP\Core\Maintenance\Gate;
use DP\Core\Maintenance\Screen;
use DP\Core\Maintenance\Settings;
use WP_UnitTestCase;

/**
 * A real database, a real user table, and one thing these tests do not do.
 *
 * **Nothing here fires `template_redirect`.** `Curtain::serve()` ends in `exit`,
 * which is the right ending for a front controller taking over a response and
 * the wrong one inside a test runner — the same shape, and the same limit, as
 * `Resume\ResumePdf::serve()`. So the request is exercised in its three parts
 * instead: `Gate::closes()` decides after a real `go_to()`, `send_headers()`
 * sends a real status through the `status_header` filter, and `Screen::render()`
 * produces the real document. What is not covered is the `exit` itself.
 */
abstract class MaintenanceTestCase extends WP_UnitTestCase {

	/**
	 * The status code `status_header()` was last given, or 0.
	 *
	 * @var int
	 */
	protected int $status = 0;

	/**
	 * Watch `status_header()`, which is the only header a CLI-run suite can see.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->status = 0;

		add_filter( 'status_header', $this->record_status( ... ), 10, 2 );
	}

	/**
	 * Record a status and leave the header alone.
	 *
	 * @param mixed $header The header line core built.
	 * @param mixed $code   The status code.
	 * @return mixed
	 */
	public function record_status( mixed $header, mixed $code ): mixed {
		$this->status = is_numeric( $code ) ? (int) $code : 0;

		return $header;
	}

	/**
	 * A curtain wired to the plugin's real screen.
	 *
	 * @return Curtain
	 */
	protected function curtain(): Curtain {
		return new Curtain( new Gate(), $this->screen() );
	}

	/**
	 * The screen, built from the plugin under test rather than from wp-content.
	 *
	 * @return Screen
	 */
	protected function screen(): Screen {
		return new Screen( dirname( __DIR__, 3 ) . '/plugins/dp-core/dp-core.php', '0.1.0' );
	}

	/**
	 * Turn maintenance on, without going through the sanitizers.
	 *
	 * @return void
	 */
	protected function switch_on(): void {
		update_option( Settings::ENABLED, '1' );
	}

	/**
	 * Sign in as a user of one role.
	 *
	 * @param string $role The role to create the user with.
	 * @return int The user ID.
	 */
	protected function sign_in_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		$this->assertIsInt( $user_id );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * One published post or page.
	 *
	 * @param string $post_type The post type.
	 * @return int The post ID.
	 */
	protected function seed_post( string $post_type = 'post' ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $post_id );

		return $post_id;
	}

	/**
	 * One post's permalink.
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	protected function permalink( int $post_id ): string {
		$permalink = get_permalink( $post_id );

		$this->assertIsString( $permalink );

		return $permalink;
	}
}
