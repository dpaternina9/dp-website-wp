<?php
/**
 * Integration tests for the series ordering screen.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Admin;

use DP\Core\Admin\SeriesOrder;
use DP\Core\Admin\SeriesOrderScreen;
use DP\Core\Content\SeriesParts;
use DP\Core\Content\Taxonomies;
use RuntimeException;
use WP_Term;
use WPDieException;

/**
 * The screen, its route, its markup and the three gates on its write path.
 *
 * The gates are asserted one at a time and against a request that would
 * otherwise be accepted, for the reason `ContactFormTest` gives at length: a
 * test that posts nonsense and asserts "nothing was written" passes whichever
 * gate closed, and stays green when one of them is deleted.
 */
final class SeriesOrderScreenTest extends SeriesOrderTestCase {

	/**
	 * The screen under test.
	 *
	 * @var SeriesOrderScreen
	 */
	private SeriesOrderScreen $screen;

	/**
	 * The read path the site draws from.
	 *
	 * @var SeriesParts
	 */
	private SeriesParts $parts;

	/**
	 * Build the screen.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->screen = new SeriesOrderScreen(
			new SeriesOrder(),
			dirname( __DIR__, 3 ) . '/plugins/dp-core/dp-core.php',
			'0.1.0'
		);
		$this->parts  = new SeriesParts();
	}

	/**
	 * The plugin attached the write path. Nothing else in the suite proves it.
	 *
	 * @return void
	 */
	public function test_the_plugin_attaches_the_write_path(): void {
		$this->assertNotFalse(
			has_action( 'admin_post_' . SeriesOrderScreen::ACTION ),
			'Plugin::register() no longer wires the ordering screen.'
		);
		$this->assertNotFalse(
			has_filter( Taxonomies::SERIES . '_row_actions' ),
			'Nothing puts the link on the Series list table, so the screen is unreachable.'
		);
	}

	/**
	 * The page is routable and is not in the menu.
	 *
	 * Both halves matter. Registered but not listed is the whole design: a menu
	 * entry would lead to a screen that cannot say which series it is about.
	 *
	 * @return void
	 */
	public function test_the_page_is_registered_and_kept_out_of_the_menu(): void {
		$this->screen->add_page();

		$hook = $this->screen->hook();

		$registered = $GLOBALS['_registered_pages'];

		$this->assertNotSame( '', $hook );
		$this->assertIsArray( $registered );
		$this->assertArrayHasKey( $hook, $registered, 'The page is not routable, so its URL is a 403.' );

		$menu = $GLOBALS['submenu'] ?? array();

		$this->assertIsArray( $menu );

		$under_posts = $menu['edit.php'] ?? array();
		$listed      = array();

		$this->assertIsArray( $under_posts );

		foreach ( $under_posts as $entry ) {
			$listed[] = is_array( $entry ) && isset( $entry[2] ) ? $entry[2] : null;
		}

		$this->assertNotContains( SeriesOrderScreen::SLUG, $listed, 'The page is in the Posts menu.' );
	}

	/**
	 * The stylesheet and the script load on this screen and on no other.
	 *
	 * @return void
	 */
	public function test_the_assets_load_on_this_screen_only(): void {
		$this->screen->add_page();

		$this->screen->enqueue( 'edit.php' );

		$this->assertFalse( wp_script_is( 'dp-core-series-order', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'dp-core-series-order', 'enqueued' ) );

		$this->screen->enqueue( $this->screen->hook() );

		$this->assertTrue( wp_script_is( 'dp-core-series-order', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'dp-core-series-order', 'enqueued' ) );
	}

	/**
	 * Every series row gets the link, and it names that series.
	 *
	 * @return void
	 */
	public function test_a_series_row_gets_a_link_to_its_order(): void {
		$term = get_term( $this->series, Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Term::class, $term );

		$actions = $this->screen->row_action( array( 'edit' => '<a href="#">Edit</a>' ), $term );

		$this->assertArrayHasKey( 'dp-order-parts', $actions );
		$this->assertStringContainsString( 'page=' . SeriesOrderScreen::SLUG, $actions['dp-order-parts'] );
		$this->assertStringContainsString( SeriesOrderScreen::TERM_VAR . '=' . $this->series, $actions['dp-order-parts'] );
		$this->assertArrayHasKey( 'edit', $actions, 'The row lost its own links.' );
	}

	/**
	 * A user who may not reorder is not offered the link.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_capability_is_offered_nothing(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertIsInt( $subscriber );

		wp_set_current_user( $subscriber );

		$term = get_term( $this->series, Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( array(), $this->screen->row_action( array(), $term ) );
	}

	/**
	 * The list draws in the order the site draws, with an input per row.
	 *
	 * @return void
	 */
	public function test_the_list_is_the_order_with_an_input_on_every_row(): void {
		$first  = $this->part( 'Written first', 'publish', 1 );
		$second = $this->part( 'Written second', 'publish', 2 );

		( new SeriesOrder() )->save( $this->series, array( $second, $first ) );

		$html = $this->render();

		$this->assertStringContainsString( 'name="' . SeriesOrderScreen::FIELD . '[]" value="' . $second . '"', $html );
		$this->assertStringContainsString( 'name="' . SeriesOrderScreen::FIELD . '[]" value="' . $first . '"', $html );
		$this->assertLessThan(
			(int) strpos( $html, 'value="' . $first . '"' ),
			(int) strpos( $html, 'value="' . $second . '"' ),
			'The rows are not in the saved order.'
		);
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertStringContainsString( 'value="' . SeriesOrderScreen::ACTION . '"', $html );
	}

	/**
	 * Published rows are numbered from the top; drafts are not numbered at all.
	 *
	 * The number on the screen is the number `SeriesParts::part_of()` will answer
	 * with, computed the same way and stored nowhere.
	 *
	 * @return void
	 */
	public function test_published_rows_are_numbered_and_drafts_are_not(): void {
		$one   = $this->part( 'Part one', 'publish', 1 );
		$draft = $this->part( 'Still to come', 'draft', 2 );
		$two   = $this->part( 'Part two', 'publish', 3 );

		$html = $this->render();

		$this->assertStringContainsString( 'data-dp-post-id="' . $one . '" data-dp-published="1"', $html );
		$this->assertStringContainsString( 'data-dp-post-id="' . $draft . '" data-dp-published="0"', $html );
		$this->assertSame( 2, substr_count( $html, 'data-dp-published="1"' ) );
		$this->assertStringContainsString( '<span class="dp-series-order-part" data-dp-part>1</span>', $html );
		$this->assertStringContainsString( '<span class="dp-series-order-part" data-dp-part>2</span>', $html );
		$this->assertStringContainsString( '<span class="dp-series-order-part" data-dp-part>&mdash;</span>', $html );
		$this->assertSame( 2, $this->parts->part_of( $two ), 'The screen and the numbering disagree.' );
	}

	/**
	 * A title is escaped, because a title is somebody's typing.
	 *
	 * @return void
	 */
	public function test_a_title_is_escaped(): void {
		$this->part( '<script>alert(1)</script> & "quoted"', 'draft', 1 );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A series with nothing in it says so rather than drawing an empty form.
	 *
	 * @return void
	 */
	public function test_an_empty_series_draws_no_form(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'no parts yet', $html );
	}

	/**
	 * With no series named, the screen says which link to use.
	 *
	 * @return void
	 */
	public function test_the_screen_without_a_series_explains_itself(): void {
		$_GET = array();

		ob_start();
		$this->screen->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Pick a series first', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	/**
	 * The screen refuses a user without the capability. Core will not do it for us.
	 *
	 * `remove_submenu_page()` takes the entry out of `$submenu`, so
	 * `user_can_access_admin_page()` falls through to the parent's `edit_posts`.
	 * This check is the only one on a GET.
	 *
	 * @return void
	 */
	public function test_the_screen_refuses_a_user_without_the_capability(): void {
		$this->part( 'A part', 'publish', 1 );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertIsInt( $author );
		$this->assertTrue( user_can( $author, 'edit_posts' ), 'An author can reach the parent page.' );

		wp_set_current_user( $author );

		$_GET[ SeriesOrderScreen::TERM_VAR ] = (string) $this->series;

		$this->expectException( WPDieException::class );

		ob_start();

		try {
			$this->screen->render();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * **Gate one: the nonce.** A POST without one writes nothing.
	 *
	 * @return void
	 */
	public function test_a_post_without_a_nonce_is_refused(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );

		$this->post( array( $second, $first ), '' );

		$refused = false;

		try {
			$this->screen->handle();
		} catch ( WPDieException ) {
			$refused = true;
		}

		$this->assertTrue( $refused, 'A POST with no nonce was accepted.' );
		$this->assertSame( 0, $this->menu_order( $second ) );
	}

	/**
	 * **Gate two: the capability.** A valid nonce is not permission.
	 *
	 * The nonce is minted by the user who then posts it, so it verifies. What
	 * stops the write is the capability and nothing else.
	 *
	 * @return void
	 */
	public function test_a_post_from_a_user_without_the_capability_is_refused(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertIsInt( $author );

		wp_set_current_user( $author );

		$this->post( array( $second, $first ), wp_create_nonce( SeriesOrderScreen::ACTION . '_' . $this->series ) );

		$refused = false;

		try {
			$this->screen->handle();
		} catch ( WPDieException ) {
			$refused = true;
		}

		$this->assertTrue( $refused, 'A user without edit_others_posts reordered somebody else\'s series.' );
		$this->assertSame( 0, $this->menu_order( $second ) );
	}

	/**
	 * **Gate three: the series.** A nonce for one series cannot reorder another.
	 *
	 * @return void
	 */
	public function test_a_nonce_for_another_series_is_refused(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );
		$other  = $this->term( 'Another series', 'another-series' );

		$this->post( array( $second, $first ), wp_create_nonce( SeriesOrderScreen::ACTION . '_' . $other ) );

		$refused = false;

		try {
			$this->screen->handle();
		} catch ( WPDieException ) {
			$refused = true;
		}

		$this->assertTrue( $refused, 'A nonce scoped to one series reordered another.' );
		$this->assertSame( 0, $this->menu_order( $second ) );
	}

	/**
	 * A request that passes all three gates writes the order and redirects back.
	 *
	 * @return void
	 */
	public function test_a_valid_post_writes_the_order(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );

		$this->post( array( $second, $first ), wp_create_nonce( SeriesOrderScreen::ACTION . '_' . $this->series ) );

		$location = $this->handle_expecting_a_redirect();

		$this->assertStringContainsString( 'page=' . SeriesOrderScreen::SLUG, $location );
		$this->assertStringContainsString( SeriesOrderScreen::TERM_VAR . '=' . $this->series, $location );
		$this->assertStringContainsString( 'dp-moved=2', $location );
		$this->assertSame( array( $second, $first ), $this->parts->published( $this->series ) );
	}

	/**
	 * The notice after a save says what happened.
	 *
	 * @return void
	 */
	public function test_the_notice_reports_what_moved(): void {
		$this->part( 'One', 'publish', 1 );

		$_GET['dp-moved'] = '2';

		$this->assertStringContainsString( '2 parts moved', $this->render() );

		$_GET['dp-moved'] = '0';

		$this->assertStringContainsString( 'Nothing had moved', $this->render() );
	}

	/**
	 * Draw the screen for the series this case established.
	 *
	 * @return string The markup.
	 */
	private function render(): string {
		$_GET[ SeriesOrderScreen::TERM_VAR ] = (string) $this->series;

		ob_start();
		$this->screen->render();

		return (string) ob_get_clean();
	}

	/**
	 * Build a POST of one order.
	 *
	 * @param array<int, int> $ids   The IDs, in the order the form would send them.
	 * @param string          $nonce The nonce, or an empty string for none.
	 * @return void
	 */
	private function post( array $ids, string $nonce ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- this method builds the request the handler is about to check. Verifying it here would be the test verifying itself.
		$_POST = array(
			SeriesOrderScreen::TERM_VAR => (string) $this->series,
			SeriesOrderScreen::FIELD    => array_map( 'strval', $ids ),
		);

		if ( '' !== $nonce ) {
			$_POST['_wpnonce'] = $nonce;
		}

		$_REQUEST = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Run the handler and return where it tried to send the browser.
	 *
	 * `handle()` ends in `exit`, which would take the test runner with it. The
	 * `wp_redirect` filter runs first, so throwing from it stops the handler at
	 * the redirect and hands the location back.
	 *
	 * @return string The redirect location.
	 */
	private function handle_expecting_a_redirect(): string {
		$stop = $this->refuse_to_redirect( ... );

		add_filter( 'wp_redirect', $stop );

		try {
			$this->screen->handle();
		} catch ( RuntimeException $stopped ) {
			return $stopped->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		$this->fail( 'The handler did not redirect.' );
	}

	/**
	 * Stop a redirect and report where it was going.
	 *
	 * The `return` below is unreachable in practice — core only ever passes a
	 * string — and is written anyway, because a filter callback that cannot
	 * return is a filter callback PHPStan will not accept.
	 *
	 * @param mixed $location Where core was about to send the browser.
	 * @return string
	 *
	 * @throws RuntimeException Whenever core is redirecting anywhere at all.
	 */
	private function refuse_to_redirect( mixed $location ): string {
		if ( is_string( $location ) ) {
			throw new RuntimeException( $location );
		}

		return '';
	}
}
