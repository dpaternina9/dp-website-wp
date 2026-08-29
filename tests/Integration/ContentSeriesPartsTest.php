<?php
/**
 * Integration tests for the series-parts queries, and the draft-leak guard.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Content\ContentModel;
use DP\Core\Content\PlannedPart;
use DP\Core\Content\SeriesParts;
use DP\Core\Content\Taxonomies;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Plan section 3.1, enforced.
 *
 * A planned part is a draft post carrying the series term. That decision has one
 * cost — draft titles in a series become public — and the whole of the rest of
 * the design depends on that cost being *exactly* one title and one note. A
 * draft's body and a draft's URL must not be reachable from anything the series
 * template is handed.
 *
 * The plan says the template is "written to make leaking body content impossible
 * rather than merely unlikely". Impossible means structural, so these tests
 * assert the structure — that there is no ID to build a permalink from and no
 * property that could hold a body — rather than checking that today's caller
 * happens not to ask.
 */
final class ContentSeriesPartsTest extends WP_UnitTestCase {

	/**
	 * A body a draft carries and nobody may see.
	 *
	 * @var string
	 */
	private const SECRET_BODY = 'UNPUBLISHED-BODY-THAT-MUST-NEVER-ESCAPE';

	/**
	 * The series term.
	 *
	 * @var int
	 */
	private int $series;

	/**
	 * The queries under test.
	 *
	 * @var SeriesParts
	 */
	private SeriesParts $parts;

	/**
	 * Build the fixture the design describes: two published parts, four planned.
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

		$this->series = $this->term( 'My life story', 'life-story' );

		$this->parts = new SeriesParts();
	}

	/**
	 * Create a part of the series.
	 *
	 * Two positioning arguments, because a series has two. `$day` is a day in
	 * January 2026 and orders a series nobody has arranged; `$order` is
	 * `menu_order` and is what the ordering screen writes. A call that passes no
	 * `$order` is describing a series in the state every series is in until
	 * somebody drags a row, which is what most of these tests are about.
	 *
	 * @param string $title  The title.
	 * @param string $status `publish` or `draft`.
	 * @param int    $day    Day of the month, which fixes the order when nothing else does.
	 * @param string $note   The line under a planned part, stored as the excerpt.
	 * @param int    $order  `menu_order`, or zero for a part nobody has placed.
	 * @return int The post ID.
	 */
	private function part( string $title, string $status, int $day, string $note = '', int $order = 0 ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => self::SECRET_BODY,
				'post_excerpt' => $note,
				'post_name'    => sanitize_title( $title ),
				'post_date'    => sprintf( '2026-01-%02d 09:00:00', $day ),
				'menu_order'   => $order,
			)
		);

		$this->assertIsInt( $post_id );

		wp_set_post_terms( $post_id, array( $this->series ), Taxonomies::SERIES, false );

		return $post_id;
	}

	/**
	 * Create a series term.
	 *
	 * The factory is declared as returning `int|WP_Error`; this narrows it once.
	 *
	 * @param string $name The term name.
	 * @param string $slug The term slug.
	 * @return int
	 */
	private function term( string $name, string $slug ): int {
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
	 * The two queries split the term by status and do not overlap.
	 *
	 * @return void
	 */
	public function test_the_two_lists_are_disjoint(): void {
		$first  = $this->part( 'The job that taught me what care looks like', 'publish', 1 );
		$second = $this->part( 'The workaholic years', 'publish', 2 );
		$this->part( 'Before any of it was a job', 'draft', 3, 'A borrowed computer.' );

		$published = $this->parts->published( $this->series );
		$planned   = $this->parts->planned( $this->series );

		$this->assertSame( array( $first, $second ), $published );
		$this->assertCount( 1, $planned );
		$this->assertSame( 'Before any of it was a job', $planned[0]->title );
	}

	/**
	 * Planned parts come back oldest first, which is the order they will be read.
	 *
	 * @return void
	 */
	public function test_planned_parts_are_ordered(): void {
		$this->part( 'The exhausting year', 'draft', 6 );
		$this->part( 'Before any of it was a job', 'draft', 3 );
		$this->part( 'The first office', 'draft', 5 );
		$this->part( 'Learning the hard way', 'draft', 4 );

		$titles = array_map(
			static fn ( PlannedPart $part ): string => $part->title,
			$this->parts->planned( $this->series )
		);

		$this->assertSame(
			array(
				'Before any of it was a job',
				'Learning the hard way',
				'The first office',
				'The exhausting year',
			),
			$titles
		);
	}

	/**
	 * A planned part carries the two things the design draws.
	 *
	 * @return void
	 */
	public function test_a_planned_part_carries_what_the_design_renders(): void {
		$this->part(
			'Before any of it was a job',
			'draft',
			3,
			'A borrowed computer, a dial-up connection, and no idea this was work people paid for.'
		);

		$planned = $this->parts->planned( $this->series )[0];

		$this->assertSame( 'Before any of it was a job', $planned->title );
		$this->assertSame( 'A borrowed computer, a dial-up connection, and no idea this was work people paid for.', $planned->note );
	}

	/**
	 * A draft with no excerpt gets no note, rather than the top of its body.
	 *
	 * This is the sharp edge of moving the note onto the excerpt.
	 * `get_the_excerpt()` falls back to trimming `post_content`, so reaching for
	 * the convenient function here would print the opening of an unfinished piece
	 * of writing under a public heading — the exact leak this whole file exists to
	 * make impossible.
	 *
	 * @return void
	 */
	public function test_a_draft_without_an_excerpt_has_an_empty_note(): void {
		$this->part( 'Something not yet described', 'draft', 3 );

		$this->assertSame( '', $this->parts->planned( $this->series )[0]->note );
	}

	/**
	 * A published part's number is its position, oldest first.
	 *
	 * Nothing stores it. `dp_series_part` was a registered field with no editor
	 * control, so it was zero on every post David wrote and the design's numbered
	 * badges drew blank; the number is the reading order, and the reading order is
	 * the dates (ADR-0016).
	 *
	 * @return void
	 */
	public function test_a_part_is_numbered_by_its_position(): void {
		$first  = $this->part( 'The job that taught me what care looks like', 'publish', 1 );
		$second = $this->part( 'The workaholic years', 'publish', 2 );
		$third  = $this->part( 'What came after', 'publish', 3 );

		$this->assertSame( 1, $this->parts->part_of( $first ) );
		$this->assertSame( 2, $this->parts->part_of( $second ) );
		$this->assertSame( 3, $this->parts->part_of( $third ) );
	}

	/**
	 * Publishing a part inserts it at its date rather than appending it.
	 *
	 * The numbering has to be a property of the order, not of the order things
	 * were created in, or a back-filled part would take a number already in use.
	 *
	 * @return void
	 */
	public function test_a_part_published_between_two_others_renumbers_them(): void {
		$first = $this->part( 'The first one', 'publish', 1 );
		$last  = $this->part( 'The last one', 'publish', 9 );

		$this->assertSame( 2, $this->parts->part_of( $last ) );

		$middle = $this->part( 'The one in between', 'publish', 5 );

		$this->assertSame( 1, $this->parts->part_of( $first ) );
		$this->assertSame( 2, $this->parts->part_of( $middle ) );
		$this->assertSame( 3, $this->parts->part_of( $last ) );
	}

	/**
	 * A draft has no number, and neither has a post in no series.
	 *
	 * @return void
	 */
	public function test_only_published_parts_of_a_series_are_numbered(): void {
		$draft = $this->part( 'Still to come', 'draft', 3 );
		$loose = self::factory()->post->create( array( 'post_title' => 'Filed under nothing' ) );

		$this->assertIsInt( $loose );
		$this->assertSame( 0, $this->parts->part_of( $draft ) );
		$this->assertSame( 0, $this->parts->part_of( $loose ) );
		$this->assertSame( 0, $this->parts->part_of( 0 ) );
	}

	/**
	 * **The guard.** Nothing a caller receives can reach a draft's body or its URL.
	 *
	 * Three assertions, in increasing order of how hard they are to defeat:
	 * the body is not in any value; there is no property that could carry a URL;
	 * and there is no post ID, which is the only thing `get_permalink()` needs.
	 *
	 * @return void
	 */
	public function test_a_draft_cannot_leak_its_body_or_its_permalink(): void {
		$draft_id = $this->part( 'Before any of it was a job', 'draft', 3, 'A borrowed computer.' );
		$slug     = get_post_field( 'post_name', $draft_id );

		$this->assertIsString( $slug );
		$this->assertNotSame( '', $slug );

		$planned = $this->parts->planned( $this->series );

		$this->assertCount( 1, $planned );

		$serialised = wp_json_encode( $planned );

		$this->assertIsString( $serialised );
		$this->assertStringNotContainsString( self::SECRET_BODY, $serialised, 'The body reached a caller.' );
		$this->assertStringNotContainsString( $slug, $serialised, 'The slug reached a caller.' );

		$decoded = json_decode( $serialised, true );

		$this->assertIsArray( $decoded );
		$this->assertIsArray( $decoded[0] );
		$this->assertSame(
			array( 'title', 'note' ),
			array_keys( $decoded[0] ),
			'Serialising a planned part exposes two keys and no identifier.'
		);
		$this->assertNotContains( $draft_id, $decoded[0], 'The post ID reached a caller.' );

		$properties = array_keys( get_object_vars( $planned[0] ) );

		$this->assertSame(
			array( 'title', 'note' ),
			$properties,
			'PlannedPart grew a property. Anything beyond these two is a way to reach the draft.'
		);

		$reflection = new ReflectionClass( PlannedPart::class );

		$this->assertSame(
			array(),
			array_values(
				array_map(
					static fn ( \ReflectionMethod $method ): string => $method->getName(),
					array_filter(
						$reflection->getMethods(),
						static fn ( \ReflectionMethod $method ): bool => '__construct' !== $method->getName()
					)
				)
			),
			'PlannedPart has no methods, so it has no way to fetch anything.'
		);

		$this->assertTrue( $reflection->isFinal(), 'PlannedPart cannot be subclassed into something that does.' );
	}

	/**
	 * The published query returns IDs, because published posts do have permalinks.
	 *
	 * The asymmetry is the design: "Start with these" links out, "Still to come"
	 * does not.
	 *
	 * @return void
	 */
	public function test_published_parts_are_linkable(): void {
		$post_id = $this->part( 'The workaholic years', 'publish', 2 );

		$published = $this->parts->published( $this->series );

		$this->assertSame( array( $post_id ), $published );
		$this->assertIsString( get_permalink( $published[0] ) );
	}

	/**
	 * A draft without the term is not announced.
	 *
	 * The term is the switch (plan section 3.1). A half-written post is invisible
	 * until David deliberately files it under the series, which is what makes
	 * public draft titles a decision rather than an accident.
	 *
	 * @return void
	 */
	public function test_a_draft_outside_the_series_stays_invisible(): void {
		self::factory()->post->create(
			array(
				'post_title'  => 'A half-written thing nobody should see',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), $this->parts->planned( $this->series ) );
	}

	/**
	 * Neither query reads across into another series.
	 *
	 * @return void
	 */
	public function test_the_queries_are_scoped_to_one_term(): void {
		$other = $this->term( 'Another series', 'another-series' );

		$this->part( 'Ours', 'draft', 1 );

		$theirs = self::factory()->post->create(
			array(
				'post_title'  => 'Theirs',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $theirs );

		wp_set_post_terms( $theirs, array( $other ), Taxonomies::SERIES, false );

		$planned = $this->parts->planned( $this->series );

		$this->assertCount( 1, $planned );
		$this->assertSame( 'Ours', $planned[0]->title );
	}

	/**
	 * An unknown term is an empty list, not a query for everything.
	 *
	 * @return void
	 */
	public function test_a_missing_term_returns_nothing(): void {
		$this->assertSame( array(), $this->parts->planned( 0 ) );
		$this->assertSame( array(), $this->parts->published( -1 ) );
	}

	/**
	 * Announced drafts are announced to everybody, logged in or not.
	 *
	 * This is the deliberate half of the decision. `WP_Query` only gates a
	 * protected status behind `perm`, and the design's "Still to come" is a
	 * published roadmap — so the list must not quietly become empty for a visitor.
	 *
	 * @return void
	 */
	public function test_planned_parts_are_public(): void {
		$this->part( 'Before any of it was a job', 'draft', 3 );

		wp_set_current_user( 0 );

		$this->assertCount( 1, $this->parts->planned( $this->series ) );
	}

	/**
	 * **The compatibility guarantee.** A series nobody has ordered is unchanged.
	 *
	 * `menu_order` is back as the first sort key. Every post on this site carries
	 * zero in it, so the sort falls straight through to the date and every
	 * assertion above this one is a statement about that. This one says it out
	 * loud, so that the guarantee fails as one named test rather than as eleven.
	 *
	 * @return void
	 */
	public function test_an_unordered_series_reads_in_date_order(): void {
		$last  = $this->part( 'Written last', 'publish', 9 );
		$first = $this->part( 'Written first', 'publish', 1 );

		$this->assertSame( array( $first, $last ), $this->parts->published( $this->series ) );
		$this->assertSame( 1, $this->parts->part_of( $first ) );
		$this->assertSame( 2, $this->parts->part_of( $last ) );
	}

	/**
	 * An order somebody set leads the date it was published on.
	 *
	 * The point of the whole field. A part written out of sequence, or a draft
	 * created in the wrong week, sits where David put it.
	 *
	 * @return void
	 */
	public function test_an_order_somebody_set_leads_the_date(): void {
		$early = $this->part( 'Published first, reads second', 'publish', 1, '', 2 );
		$late  = $this->part( 'Published second, reads first', 'publish', 9, '', 1 );

		$this->assertSame( array( $late, $early ), $this->parts->published( $this->series ) );
		$this->assertSame( 1, $this->parts->part_of( $late ) );
		$this->assertSame( 2, $this->parts->part_of( $early ) );
	}

	/**
	 * Planned parts obey the same order, so a draft can sit between two posts.
	 *
	 * @return void
	 */
	public function test_planned_parts_take_the_order_too(): void {
		$this->part( 'Reads third', 'draft', 1, '', 3 );
		$this->part( 'Reads first', 'draft', 9, '', 1 );

		$titles = array_map(
			static fn ( PlannedPart $part ): string => $part->title,
			$this->parts->planned( $this->series )
		);

		$this->assertSame( array( 'Reads first', 'Reads third' ), $titles );
	}

	/**
	 * `all()` is one sequence with both statuses in it.
	 *
	 * The reading order spans the two lists, which is why the ordering screen
	 * shows one list rather than two.
	 *
	 * @return void
	 */
	public function test_all_returns_both_statuses_in_one_sequence(): void {
		$published = $this->part( 'Up', 'publish', 5, '', 1 );
		$planned   = $this->part( 'Still to come', 'draft', 5, '', 2 );
		$later     = $this->part( 'Up, later', 'publish', 5, '', 3 );

		$this->assertSame( array( $published, $planned, $later ), $this->parts->all( $this->series ) );
		$this->assertSame( array( $published, $later ), $this->parts->published( $this->series ) );
	}

	/**
	 * `all()` on a term nobody named is an empty list, like its neighbours.
	 *
	 * @return void
	 */
	public function test_all_on_a_missing_term_returns_nothing(): void {
		$this->assertSame( array(), $this->parts->all( 0 ) );
		$this->assertSame( array(), $this->parts->all( -1 ) );
	}
}
