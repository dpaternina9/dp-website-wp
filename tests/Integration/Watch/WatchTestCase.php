<?php
/**
 * The shared harness for the Watch page's integration tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Watch\Thumbnails;
use WP_Error;
use WP_UnitTestCase;

/**
 * Everything the Watch tests need, and one thing they must never do.
 *
 * **No test talks to Twitch or YouTube.** `pre_http_request` intercepts every
 * outgoing call: a test that wants an upstream answer registers a stub by URL
 * prefix, and everything else gets a `WP_Error` — which is also an assertion
 * surface, because `$this->http_requests` records what was attempted, so "no
 * request was made at all" is checkable rather than hoped.
 *
 * The filter is added in `set_up()` and needs no matching removal:
 * `WP_UnitTestCase` snapshots the hook tables around every test.
 *
 * The thumbnail cache directory is real files under the test container's
 * uploads, so unlike everything else it survives the transaction rollback;
 * `tear_down()` empties it.
 */
abstract class WatchTestCase extends WP_UnitTestCase {

	/**
	 * Every URL an intercepted HTTP request asked for, in order.
	 *
	 * @var list<string>
	 */
	protected array $http_requests = array();

	/**
	 * Stubbed responses, keyed by URL prefix.
	 *
	 * @var array<string, mixed>
	 */
	protected array $http_stubs = array();

	/**
	 * Re-register the content model and stand the HTTP interceptor up.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();

		$this->http_requests = array();
		$this->http_stubs    = array();

		add_filter( 'pre_http_request', $this->intercept( ... ), 10, 3 );
	}

	/**
	 * Empty the thumbnail cache the transaction cannot roll back.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$uploads = wp_upload_dir();
		$basedir = $uploads['basedir'] ?? null;

		if ( is_string( $basedir ) ) {
			$found = glob( $basedir . '/' . Thumbnails::DIRECTORY . '/*' );

			foreach ( is_array( $found ) ? $found : array() as $file ) {
				wp_delete_file( $file );
			}
		}

		parent::tear_down();
	}

	/**
	 * Answer an outgoing HTTP request from the stubs, or refuse it.
	 *
	 * @param mixed $preempt Whatever an earlier filter decided.
	 * @param mixed $args    The request arguments.
	 * @param mixed $url     The URL being requested.
	 * @return mixed A response array, or a WP_Error.
	 */
	public function intercept( mixed $preempt, mixed $args, mixed $url ): mixed {
		unset( $preempt, $args );

		$requested             = is_string( $url ) ? $url : '';
		$this->http_requests[] = $requested;

		foreach ( $this->http_stubs as $prefix => $response ) {
			if ( str_starts_with( $requested, $prefix ) ) {
				return $response;
			}
		}

		return new WP_Error( 'http_request_blocked', 'External HTTP is blocked in the test suite.' );
	}

	/**
	 * A stubbed 200 response carrying an image.
	 *
	 * @param string $bytes The pretend image.
	 * @return array<string, mixed>
	 */
	protected static function image_response( string $bytes = 'not-really-a-jpeg' ): array {
		return array(
			'headers'  => array( 'content-type' => 'image/jpeg' ),
			'body'     => $bytes,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * A stubbed response with a status and a body.
	 *
	 * @param int    $code The status code.
	 * @param string $body The body.
	 * @return array<string, mixed>
	 */
	protected static function response( int $code, string $body ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => 'stubbed',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * One published `dp_video`, in the design's field vocabulary.
	 *
	 * @param string $title  The post title.
	 * @param string $source Where it is hosted: `twitch` or `youtube`.
	 * @param string $ref    The platform identifier, or '' for none yet.
	 * @param string $tone   The card's hue.
	 * @param int    $order  Menu order — the design's list order.
	 * @param bool   $live   Whether this is the live-now entry.
	 * @return int The post ID.
	 */
	protected function seed_video( string $title, string $source, string $ref, string $tone, int $order, bool $live = false ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::VIDEO,
				'post_title'  => $title,
				'post_status' => 'publish',
				'menu_order'  => $order,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_video_source', $source );
		update_post_meta( $post_id, 'dp_video_ref', $ref );
		update_post_meta( $post_id, 'dp_tone', $tone );
		update_post_meta( $post_id, 'dp_duration', '2H 41M' );
		update_post_meta( $post_id, 'dp_when', 'AUG 2026' );
		update_post_meta( $post_id, 'dp_note', 'One line under the title.' );
		update_post_meta( $post_id, 'dp_live', $live );

		if ( $live ) {
			update_post_meta( $post_id, 'dp_live_meta', 'STREAMING NOW · 1H 12M IN' );
		}

		return $post_id;
	}
}
