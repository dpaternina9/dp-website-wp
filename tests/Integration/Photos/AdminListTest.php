<?php
/**
 * Integration tests for the Photos and Trips list screens.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\AdminList;
use DP\Core\Photos\Library;
use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;

/**
 * "Set trip…" replaces, and the Trips screen shows the computed range.
 */
final class AdminListTest extends PhotosTestCase {

	/**
	 * The screen's hooks, built as the plugin builds them.
	 *
	 * @var AdminList
	 */
	private AdminList $list;

	/**
	 * Build it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->list = new AdminList( new Library() );
	}

	/**
	 * The bulk menu offers every trip, and "No trip", under "Set trip…".
	 *
	 * @return void
	 */
	public function test_the_bulk_menu_offers_every_trip(): void {
		$trip    = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$actions = $this->list->bulk_actions( array( 'trash' => 'Move to Trash' ) );
		$group   = $actions['Set trip…'] ?? null;

		$this->assertIsArray( $group );
		$this->assertSame( 'No trip', $group[ AdminList::SET_TRIP . '0' ] );
		$this->assertSame( 'Putumayo', $group[ AdminList::SET_TRIP . $trip->term_id ] );
	}

	/**
	 * Setting a trip replaces the one there, on photos the user may edit.
	 *
	 * @return void
	 */
	public function test_set_trip_replaces_rather_than_adds(): void {
		$this->become( 'editor' );

		$old   = $this->term( Taxonomies::TRIP, 'Old' );
		$new   = $this->term( Taxonomies::TRIP, 'New' );
		$photo = $this->photo( array( 'trip' => $old->term_id ) );
		$other = $this->ok( self::factory()->post->create() );

		$redirect = $this->list->handle_bulk_action( 'edit.php', AdminList::SET_TRIP . $new->term_id, array( $photo, $other ) );

		$this->assertSame( array( $new->term_id ), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array(), wp_get_object_terms( $other, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
		$this->assertStringContainsString( AdminList::DONE_ARG . '=1', $redirect );

		$this->list->handle_bulk_action( 'edit.php', AdminList::SET_TRIP . '0', array( $photo ) );

		$this->assertSame( array(), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Quick Edit's Trip select replaces the trip — and only from Quick Edit.
	 *
	 * @return void
	 */
	public function test_quick_edit_sets_one_trip(): void {
		$this->become( 'editor' );

		$old   = $this->term( Taxonomies::TRIP, 'Old' );
		$new   = $this->term( Taxonomies::TRIP, 'New' );
		$photo = $this->photo( array( 'trip' => $old->term_id ) );

		ob_start();
		$this->list->quick_edit_trip( 'taxonomy-' . Taxonomies::TRIP, PostType::NAME );
		$box = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . AdminList::QUICK_TRIP . '"', $box );
		$this->assertStringContainsString( '>New</option>', $box );

		ob_start();
		$this->list->quick_edit_trip( 'taxonomy-' . Taxonomies::TOPIC, PostType::NAME );
		$this->assertSame( '', ob_get_clean(), 'Only the trip column gets the select.' );

		$_POST[ AdminList::QUICK_TRIP ] = (string) $new->term_id;

		// Not a Quick Edit request: the trip stays.
		$this->list->save_quick_edit_trip( $photo );
		$this->assertSame( array( $old->term_id ), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );

		add_filter( 'wp_doing_ajax', '__return_true' );
		$nonce                    = wp_create_nonce( 'inlineeditnonce' );
		$_POST['_inline_edit']    = $nonce;
		$_REQUEST['_inline_edit'] = $nonce;

		$this->list->save_quick_edit_trip( $photo );
		$this->assertSame( array( $new->term_id ), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );

		$_POST[ AdminList::QUICK_TRIP ] = '0';
		$this->list->save_quick_edit_trip( $photo );
		$this->assertSame( array(), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		unset( $_POST[ AdminList::QUICK_TRIP ], $_POST['_inline_edit'], $_REQUEST['_inline_edit'] );
	}

	/**
	 * A user who cannot edit a photo cannot re-file it.
	 *
	 * @return void
	 */
	public function test_set_trip_checks_the_capability(): void {
		$trip  = $this->term( Taxonomies::TRIP, 'Somewhere' );
		$photo = $this->photo();

		$this->become( 'subscriber' );

		$this->list->handle_bulk_action( 'edit.php', AdminList::SET_TRIP . $trip->term_id, array( $photo ) );

		$this->assertSame( array(), wp_get_object_terms( $photo, Taxonomies::TRIP, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * The Trips screen prints the range the page prints.
	 *
	 * @return void
	 */
	public function test_the_trips_screen_shows_the_computed_dates(): void {
		$trip  = $this->term( Taxonomies::TRIP, 'Winter' );
		$empty = $this->term( Taxonomies::TRIP, 'Nothing yet' );

		$this->photo(
			array(
				'trip' => $trip->term_id,
				'date' => '2017-12-30 10:00:00',
			)
		);
		$this->photo(
			array(
				'trip' => $trip->term_id,
				'date' => '2018-01-02 10:00:00',
			)
		);

		$columns = $this->list->trip_columns(
			array(
				'cb'    => '',
				'name'  => 'Name',
				'posts' => 'Count',
			)
		);

		$this->assertSame( array( 'cb', 'name', AdminList::DATES_COLUMN, 'posts' ), array_keys( $columns ) );
		$this->assertSame( 'Dec 2017 – Jan 2018', $this->list->trip_column( '', AdminList::DATES_COLUMN, $trip->term_id ) );
		$this->assertStringContainsString( 'No published photos yet', $this->list->trip_column( '', AdminList::DATES_COLUMN, $empty->term_id ) );
		$this->assertSame( 'kept', $this->list->trip_column( 'kept', 'posts', $trip->term_id ) );
	}
}
