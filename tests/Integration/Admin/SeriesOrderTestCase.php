<?php
/**
 * The fixture the two ordering suites share.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Admin;

use DP\Core\Content\ContentModel;
use DP\Core\Content\Taxonomies;
use WP_UnitTestCase;

/**
 * A series, some parts, and an administrator to move them.
 *
 * `part()` takes a day and an order, because those are the two things every test
 * in both suites has an opinion about: the day is what a series sorts by when
 * nobody has ordered it, and the order is what it sorts by when somebody has.
 * A test that passes no order is describing a series in the state every series
 * on the site is in today, which is the compatibility guarantee.
 */
abstract class SeriesOrderTestCase extends WP_UnitTestCase {

	/**
	 * The series under test.
	 *
	 * @var int
	 */
	protected int $series;

	/**
	 * Establish the fixture.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		/*
		 * `add_submenu_page()`, `remove_submenu_page()` and `submit_button()` live
		 * in wp-admin, which the test suite does not load. The screen only ever
		 * runs there, so requiring it here is the harness catching up with reality
		 * rather than the code under test asking for something unusual.
		 */
		require_once ABSPATH . 'wp-admin/includes/admin.php';

		/*
		 * `WP_UnitTestCase::tear_down()` unregisters every meta key and the
		 * taxonomy with it, so the content model is re-registered per test. It is
		 * idempotent. Without this the second test in a file files its posts under
		 * a taxonomy that no longer exists.
		 */
		ContentModel::create()->register();

		wp_set_current_user( self::administrator() );

		$this->series = $this->term( 'Life story', 'life-story' );
	}

	/**
	 * Forget the request between tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * An administrator, created once per test.
	 *
	 * @return int
	 */
	protected static function administrator(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		self::assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Create a part of a series.
	 *
	 * @param string $title  The title.
	 * @param string $status `publish` or `draft`.
	 * @param int    $day    Day of January 2026, which is the order when nothing else says.
	 * @param int    $order  `menu_order`, or zero for a part nobody has placed.
	 * @param int    $term   The series, or zero for the one this case establishes.
	 * @return int The post ID.
	 */
	protected function part( string $title, string $status, int $day, int $order = 0, int $term = 0 ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_name'   => sanitize_title( $title ),
				'post_date'   => sprintf( '2026-01-%02d 09:00:00', $day ),
				'menu_order'  => $order,
			)
		);

		$this->assertIsInt( $post_id );

		wp_set_post_terms( $post_id, array( 0 === $term ? $this->series : $term ), Taxonomies::SERIES, false );

		return $post_id;
	}

	/**
	 * Create a series term.
	 *
	 * @param string $name The term name.
	 * @param string $slug The term slug.
	 * @return int
	 */
	protected function term( string $name, string $slug ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::SERIES,
				'name'     => $name,
				'slug'     => $slug,
			)
		);

		$this->assertIsInt( $term_id );

		return $term_id;
	}

	/**
	 * What `menu_order` a post is carrying right now.
	 *
	 * @param int $post_id The post.
	 * @return int
	 */
	protected function menu_order( int $post_id ): int {
		clean_post_cache( $post_id );

		$post = get_post( $post_id );

		$this->assertNotNull( $post );

		return $post->menu_order;
	}
}
