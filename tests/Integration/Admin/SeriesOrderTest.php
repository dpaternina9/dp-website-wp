<?php
/**
 * Integration tests for writing a series' reading order.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Admin;

use DP\Core\Admin\SeriesOrder;
use DP\Core\Content\PlannedPart;
use DP\Core\Content\SeriesParts;
use WP_Post;

/**
 * What the ordering screen writes, and what it refuses to write.
 *
 * Two claims are load-bearing and both are asserted here rather than argued.
 *
 * **`menu_order` is writable on `post` without `page-attributes`.** ADR-0016
 * removed the field on the observation that the Order box is not on the post
 * editor, which is true and is about a *box*. The declaration draws the box;
 * `wp_update_post()` writes the column either way. If that ever stopped being
 * true this feature would silently stop working, so it is a test rather than a
 * sentence in a docblock.
 *
 * **A series nobody has ordered is unchanged.** Every part of it carries zero,
 * the sort falls through to the publish date, and the archive draws exactly what
 * it drew before this screen existed. That is the whole of the backward
 * compatibility argument, so it is asserted directly.
 */
final class SeriesOrderTest extends SeriesOrderTestCase {

	/**
	 * The write path under test.
	 *
	 * @var SeriesOrder
	 */
	private SeriesOrder $order;

	/**
	 * The read path the site draws from.
	 *
	 * @var SeriesParts
	 */
	private SeriesParts $parts;

	/**
	 * Build the collaborators.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->order = new SeriesOrder();
		$this->parts = new SeriesParts();
	}

	/**
	 * `post` still does not declare `page-attributes`, and must not.
	 *
	 * The objection ADR-0016 raised stands: declaring it would put an Order box on
	 * every post on the site, including the twenty-nine that are in no series.
	 * This screen does not need the declaration, so nothing added it.
	 *
	 * @return void
	 */
	public function test_post_does_not_declare_page_attributes(): void {
		$this->assertFalse(
			post_type_supports( 'post', 'page-attributes' ),
			'Something declared page-attributes on post. That puts an Order box on every post on the site.'
		);
	}

	/**
	 * `wp_update_post()` writes `menu_order` anyway.
	 *
	 * @return void
	 */
	public function test_menu_order_is_writable_without_that_declaration(): void {
		$post_id = $this->part( 'A part', 'publish', 1 );

		$this->assertSame( 0, $this->menu_order( $post_id ) );

		$result = wp_update_post(
			array(
				'ID'         => $post_id,
				'menu_order' => 7,
			),
			true
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 7, $this->menu_order( $post_id ) );
	}

	/**
	 * **The compatibility guarantee.** An unordered series is a series in date order.
	 *
	 * @return void
	 */
	public function test_a_series_nobody_has_ordered_reads_in_date_order(): void {
		$third  = $this->part( 'Written last', 'publish', 9 );
		$first  = $this->part( 'Written first', 'publish', 1 );
		$second = $this->part( 'Written in between', 'publish', 5 );

		$this->assertSame( array( $first, $second, $third ), $this->parts->published( $this->series ) );
		$this->assertSame( 1, $this->parts->part_of( $first ) );
		$this->assertSame( 3, $this->parts->part_of( $third ) );

		foreach ( array( $first, $second, $third ) as $post_id ) {
			$this->assertSame( 0, $this->menu_order( $post_id ), 'Nothing wrote an order nobody asked for.' );
		}
	}

	/**
	 * Saving an order writes it, and the site reads it back.
	 *
	 * @return void
	 */
	public function test_a_saved_order_is_the_order_the_site_reads(): void {
		$first  = $this->part( 'Written first', 'publish', 1 );
		$second = $this->part( 'Written second', 'publish', 2 );
		$third  = $this->part( 'Written third', 'publish', 3 );

		$moved = $this->order->save( $this->series, array( $third, $first, $second ) );

		$this->assertSame( 3, $moved );
		$this->assertSame( array( 1, 2, 3 ), array( $this->menu_order( $third ), $this->menu_order( $first ), $this->menu_order( $second ) ) );
		$this->assertSame( array( $third, $first, $second ), $this->parts->published( $this->series ) );
	}

	/**
	 * The part numbers follow the saved order, because they are the saved order.
	 *
	 * @return void
	 */
	public function test_the_part_numbers_follow_the_saved_order(): void {
		$first  = $this->part( 'Written first', 'publish', 1 );
		$second = $this->part( 'Written second', 'publish', 2 );

		$this->order->save( $this->series, array( $second, $first ) );

		$this->assertSame( 1, $this->parts->part_of( $second ) );
		$this->assertSame( 2, $this->parts->part_of( $first ) );
	}

	/**
	 * Published and planned parts order against each other, in one sequence.
	 *
	 * This is the reason the screen shows one list. A planned part that is going
	 * to read third has to be able to sit third, next to the published parts it
	 * reads between, before it is published.
	 *
	 * @return void
	 */
	public function test_published_and_draft_parts_share_one_order(): void {
		$published_late  = $this->part( 'Published, reads last', 'publish', 1 );
		$planned         = $this->part( 'Planned, reads in the middle', 'draft', 2 );
		$published_early = $this->part( 'Published, reads first', 'publish', 3 );

		$moved = $this->order->save(
			$this->series,
			array( $published_early, $planned, $published_late )
		);

		$this->assertSame( 3, $moved );
		$this->assertSame(
			array( $published_early, $planned, $published_late ),
			$this->order->ids( $this->series )
		);
		$this->assertSame( array( $published_early, $published_late ), $this->parts->published( $this->series ) );

		$titles = array_map(
			static fn ( PlannedPart $part ): string => $part->title,
			$this->parts->planned( $this->series )
		);

		$this->assertSame( array( 'Planned, reads in the middle' ), $titles );
	}

	/**
	 * A planned part keeps its place when it is published.
	 *
	 * The reason plan section 3.1 wanted `menu_order` in the first place.
	 *
	 * @return void
	 */
	public function test_a_planned_part_keeps_its_place_when_it_goes_up(): void {
		$one   = $this->part( 'Part one', 'publish', 1 );
		$two   = $this->part( 'Part two', 'publish', 2 );
		$draft = $this->part( 'The one in between', 'draft', 9 );

		$this->order->save( $this->series, array( $one, $draft, $two ) );

		wp_publish_post( $draft );

		$this->assertSame( array( $one, $draft, $two ), $this->parts->published( $this->series ) );
		$this->assertSame( 2, $this->parts->part_of( $draft ) );
	}

	/**
	 * **The membership check.** An ID from outside the series is not written.
	 *
	 * Not "is not shown" and not "is ignored by the sort" — is not written. The
	 * request is a list of post IDs from a browser, so the only safe reading of it
	 * is as a preference about posts the database already agrees are parts of this
	 * term.
	 *
	 * @return void
	 */
	public function test_an_id_from_outside_the_series_is_never_written(): void {
		$mine     = $this->part( 'Mine', 'publish', 1 );
		$other    = $this->term( 'Another series', 'another-series' );
		$theirs   = $this->part( 'Theirs', 'publish', 2, 0, $other );
		$loose_id = self::factory()->post->create( array( 'post_title' => 'Filed under nothing' ) );
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertIsInt( $loose_id );
		$this->assertIsInt( $page_id );

		$moved = $this->order->save(
			$this->series,
			array( $theirs, $loose_id, $page_id, 999999, $mine )
		);

		$this->assertSame( 1, $moved, 'Only the one real part was written.' );
		$this->assertSame( 1, $this->menu_order( $mine ) );
		$this->assertSame( 0, $this->menu_order( $theirs ) );
		$this->assertSame( 0, $this->menu_order( $loose_id ) );
		$this->assertSame( 0, $this->menu_order( $page_id ) );
		$this->assertSame( array( $mine ), $this->order->ids( $this->series ) );
	}

	/**
	 * A part the request did not mention follows the ones it did.
	 *
	 * What happens when a post is filed under the series in another tab while this
	 * screen is open. It is not dropped and it does not keep a position that
	 * something else now holds.
	 *
	 * @return void
	 */
	public function test_a_part_the_request_left_out_follows_the_ones_it_sent(): void {
		$first   = $this->part( 'Sent first', 'publish', 1 );
		$second  = $this->part( 'Sent second', 'publish', 2 );
		$unnamed = $this->part( 'Never sent', 'publish', 3 );

		$this->order->save( $this->series, array( $second, $first ) );

		$this->assertSame( array( $second, $first, $unnamed ), $this->parts->published( $this->series ) );
		$this->assertSame( 3, $this->menu_order( $unnamed ) );
	}

	/**
	 * A repeated ID counts once, where it first appeared.
	 *
	 * @return void
	 */
	public function test_a_repeated_id_is_placed_once(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );

		$this->order->save( $this->series, array( $second, $first, $second ) );

		$this->assertSame( 1, $this->menu_order( $second ) );
		$this->assertSame( 2, $this->menu_order( $first ) );
	}

	/**
	 * Saving the order it already has writes nothing.
	 *
	 * @return void
	 */
	public function test_saving_an_unchanged_order_writes_nothing(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'publish', 2 );

		$this->assertSame( 2, $this->order->save( $this->series, array( $first, $second ) ) );
		$this->assertSame( 0, $this->order->save( $this->series, array( $first, $second ) ) );
	}

	/**
	 * An empty series is a no-op rather than an error.
	 *
	 * @return void
	 */
	public function test_a_series_with_no_parts_writes_nothing(): void {
		$this->assertSame( 0, $this->order->save( $this->series, array( 1, 2, 3 ) ) );
		$this->assertSame( 0, $this->order->save( 0, array() ) );
	}

	/**
	 * The posts the screen renders are the posts the order names, in that order.
	 *
	 * @return void
	 */
	public function test_the_screen_reads_posts_in_the_saved_order(): void {
		$first  = $this->part( 'One', 'publish', 1 );
		$second = $this->part( 'Two', 'draft', 2 );

		$this->order->save( $this->series, array( $second, $first ) );

		$ids = array_map(
			static fn ( WP_Post $post ): int => $post->ID,
			$this->order->posts( $this->series )
		);

		$this->assertSame( array( $second, $first ), $ids );
	}
}
