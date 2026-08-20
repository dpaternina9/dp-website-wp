<?php
/**
 * Integration tests for `wp dp seed`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Cli\NullOutput;
use DP\Core\Cli\SeedCommand;
use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Content\SeriesParts;
use DP\Core\Content\Taxonomies;
use DP\Core\Fixture\Fixture;
use DP\Core\Fixture\Seeder;
use WP_Post;
use WP_Term;
use WP_UnitTestCase;

/**
 * The seed has to reproduce the design's fixture, placeholders included.
 *
 * Two things are being proved. First that the counts and the wiring are right —
 * six lanes, four shipped things, the two published parts filed under the series.
 * Second, and more easily lost, that the copy is **still unfinished**: CLAUDE.md
 * is explicit that every word in the design is placeholder and that a seed which
 * quietly improved it would be a seed that had started inventing facts about
 * David. "Placeholder role description" appearing four times is an assertion, not
 * an embarrassment.
 */
final class ContentSeedTest extends WP_UnitTestCase {

	/**
	 * The seeder under test.
	 *
	 * @var Seeder
	 */
	private Seeder $seeder;

	/**
	 * The fixture the seeder reads.
	 *
	 * @var Fixture
	 */
	private Fixture $fixture;

	/**
	 * Build the collaborators.
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

		$this->seeder  = Seeder::create();
		$this->fixture = new Fixture();
	}

	/**
	 * The run produces the counts the design implies.
	 *
	 * @return void
	 */
	public function test_it_seeds_the_whole_fixture(): void {
		$report = $this->seeder->seed();

		$this->assertSame(
			array(
				'categories'    => 5,
				'series'        => 1,
				'roles'         => 6,
				'shipped'       => 4,
				'videos'        => 6,
				'posts'         => 7,
				'planned_parts' => 4,
				'pages'         => 3,
			),
			$report->counts()
		);

		$this->assertCount(
			6,
			get_posts(
				array(
					'post_type'   => PostTypes::ROLE,
					'numberposts' => -1,
				)
			)
		);
		$this->assertCount(
			4,
			get_posts(
				array(
					'post_type'   => PostTypes::SHIP,
					'numberposts' => -1,
				)
			)
		);
		$this->assertCount(
			6,
			get_posts(
				array(
					'post_type'   => PostTypes::VIDEO,
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * Running twice does not make two of anything.
	 *
	 * CLAUDE.md section 3: a reset gives a site that matches the fixture, and the
	 * fix for a wrong local database is the seed script. Both need it to be safe
	 * to run again.
	 *
	 * @return void
	 */
	public function test_it_is_idempotent(): void {
		$this->seeder->seed();
		$first = get_posts(
			array(
				'post_type'   => PostTypes::ROLE,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		Seeder::create()->seed();
		$second = get_posts(
			array(
				'post_type'   => PostTypes::ROLE,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		$this->assertCount( 6, $second );
		$this->assertSame( $first, $second, 'The same six posts, not six more.' );
	}

	/**
	 * A fresh run removes what a previous one made, and nothing else.
	 *
	 * @return void
	 */
	public function test_a_fresh_run_removes_only_its_own(): void {
		$mine = self::factory()->post->create(
			array(
				'post_title'  => 'Something David wrote himself',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $mine );

		$this->seeder->seed();

		$report = Seeder::create()->seed( true );

		$this->assertTrue( $report->was_fresh() );
		$this->assertCount(
			6,
			get_posts(
				array(
					'post_type'   => PostTypes::ROLE,
					'numberposts' => -1,
				)
			)
		);
		$this->assertInstanceOf( WP_Post::class, get_post( $mine ), 'A hand-written post survives --fresh.' );
	}

	/**
	 * The placeholders are still placeholders.
	 *
	 * @return void
	 */
	public function test_the_placeholders_survive(): void {
		$this->seeder->seed();

		$roles = get_posts(
			array(
				'post_type'   => PostTypes::ROLE,
				'numberposts' => -1,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);

		$placeholders = 0;

		foreach ( $roles as $role ) {
			if ( str_starts_with( $this->meta_text( $role->ID, 'dp_detail' ), 'Placeholder role description' ) ) {
				++$placeholders;
			}
		}

		$this->assertSame( 4, $placeholders, 'Four of the six roles are still unwritten, exactly as the design has them.' );

		$stats = array();

		foreach ( get_posts(
			array(
				'post_type'   => PostTypes::SHIP,
				'numberposts' => -1,
			)
		) as $ship ) {
			$stats[] = $this->meta_text( $ship->ID, 'dp_stat1' );
			$stats[] = $this->meta_text( $ship->ID, 'dp_stat2' );
		}

		$this->assertContains( '—', $stats, 'A statistic that is an em dash is a statistic nobody has yet.' );
		$this->assertGreaterThanOrEqual( 4, count( array_filter( $stats, static fn ( string $s ): bool => '—' === $s ) ) );
	}

	/**
	 * Kiveo's description still admits the copy is missing.
	 *
	 * @return void
	 */
	public function test_kiveo_still_says_copy_to_come(): void {
		$this->seeder->seed();

		$this->assertStringContainsString(
			'copy to come',
			$this->meta_text( $this->find( PostTypes::SHIP, 'Kiveo' ), 'dp_detail' )
		);
		$this->assertStringContainsString(
			'EXAMPLE',
			$this->meta_text( $this->find( PostTypes::SHIP, 'Agency platform & ops' ), 'dp_artifact' )
		);
	}

	/**
	 * The timeline's dates are the design's decimal years.
	 *
	 * @return void
	 */
	public function test_the_lanes_carry_their_decimal_years(): void {
		$this->seeder->seed();

		$monsterinsights = $this->find( PostTypes::ROLE, 'MonsterInsights' );

		$this->assertEqualsWithDelta( 2022.0, $this->meta_number( $monsterinsights, 'dp_start' ), 0.000001 );
		$this->assertEqualsWithDelta( 2026.4, $this->meta_number( $monsterinsights, 'dp_end' ), 0.000001 );
		$this->assertSame( '2022 — 2026', get_post_meta( $monsterinsights, 'dp_range', true ) );
		$this->assertSame( 'Developer team lead', get_post_meta( $monsterinsights, 'dp_role_title', true ) );

		$fanxie = $this->find( PostTypes::ROLE, 'Fanxie Lab' );

		$this->assertEqualsWithDelta( 2026.6, $this->meta_number( $fanxie, 'dp_end' ), 0.000001 );
		$this->assertSame( 'pink', get_post_meta( $fanxie, 'dp_accent', true ), 'Fanxie Lab is the one lane with its own accent.' );
		$this->assertSame( '', get_post_meta( $monsterinsights, 'dp_accent', true ) );
	}

	/**
	 * Every shipped thing hangs off a real role.
	 *
	 * @return void
	 */
	public function test_every_shipped_thing_hangs_off_a_role(): void {
		$this->seeder->seed();

		$featured = 0;

		foreach ( get_posts(
			array(
				'post_type'   => PostTypes::SHIP,
				'numberposts' => -1,
			)
		) as $ship ) {
			$role_id = $this->meta_number( $ship->ID, 'dp_role_id' );
			$role    = get_post( (int) $role_id );

			$this->assertInstanceOf( WP_Post::class, $role, $ship->post_title . ' points at a role.' );
			$this->assertSame( PostTypes::ROLE, $role->post_type );

			if ( get_post_meta( $ship->ID, 'dp_featured', true ) ) {
				++$featured;
			}
		}

		$this->assertSame( 3, $featured, 'The design puts three WorkCards above the timeline.' );
	}

	/**
	 * The two published parts are filed under the series, in order.
	 *
	 * @return void
	 */
	public function test_the_series_is_wired_up(): void {
		$this->seeder->seed();

		$term = get_term_by( 'slug', 'life-story', Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertStringContainsString(
			'The long version of how I got here',
			$this->term_text( $term->term_id, 'dp_series_deck' )
		);

		$parts     = new SeriesParts();
		$published = $parts->published( $term->term_id );
		$planned   = $parts->planned( $term->term_id );

		$this->assertCount( 2, $published );
		$this->assertCount( 4, $planned );

		$this->assertSame( 'The job that taught me what care looks like', get_the_title( $published[0] ) );
		$this->assertSame( 'The workaholic years, and why I stopped', get_the_title( $published[1] ) );

		$this->assertSame( 'Before any of it was a job', $planned[0]->title );
		$this->assertSame( '1995 — 2007', $planned[0]->years );
		$this->assertSame( 3, $planned[0]->part, 'Planned parts continue the numbering, they do not restart it.' );
		$this->assertSame( 'The exhausting year', $planned[3]->title );
	}

	/**
	 * Seeded post bodies parse as valid blocks.
	 *
	 * A post whose blocks do not validate greets David with a recovery prompt,
	 * and the first one he would see it on is the reference post whose entire job
	 * is to demonstrate the house style working.
	 *
	 * @return void
	 */
	public function test_seeded_bodies_are_valid_block_markup(): void {
		$this->seeder->seed();

		$reference = get_page_by_path( 'house-style', OBJECT, 'post' );

		$this->assertInstanceOf( WP_Post::class, $reference );

		$blocks = parse_blocks( $reference->post_content );
		$names  = array_values(
			array_filter(
				array_map(
					static fn ( array $block ): ?string => $block['blockName'],
					$blocks
				)
			)
		);

		$this->assertContains( 'core/paragraph', $names );
		$this->assertContains( 'core/heading', $names );
		$this->assertContains( 'core/quote', $names );
		$this->assertContains( 'core/list', $names );
		$this->assertContains( 'core/code', $names );
		$this->assertContains( 'core/table', $names );
		$this->assertContains( 'core/separator', $names );
		$this->assertContains( 'core/image', $names );
		$this->assertContains( 'dp/callout', $names, 'The house style has exactly one custom block.' );

		$this->assertCount(
			1,
			array_filter( $names, static fn ( string $name ): bool => 'dp/callout' === $name ),
			'One callout per post is the house limit.'
		);

		$this->assertSame(
			$reference->post_content,
			serialize_blocks( $blocks ),
			"The markup is what WordPress's own serialiser round-trips to."
		);
	}

	/**
	 * The labelled code block keeps its label, and the default one does not write it out.
	 *
	 * @return void
	 */
	public function test_code_labels_follow_the_serialisers_rules(): void {
		$this->seeder->seed();

		$reference = get_page_by_path( 'house-style', OBJECT, 'post' );
		$colophon  = get_page_by_path( 'colophon', OBJECT, 'page' );

		$this->assertInstanceOf( WP_Post::class, $reference );
		$this->assertInstanceOf( WP_Post::class, $colophon );

		$this->assertStringContainsString( '<!-- wp:code -->', $reference->post_content );
		$this->assertStringContainsString( '<!-- wp:code {"dpLabel":"DEPLOY"} -->', $colophon->post_content );
	}

	/**
	 * Pages are pages, with no template assigned and no opinion about their slug.
	 *
	 * CLAUDE.md section 5.1: David creates every page and picks its template. The
	 * seed supplies content, not routing.
	 *
	 * @return void
	 */
	public function test_pages_carry_content_and_no_routing(): void {
		$this->seeder->seed();

		foreach ( array( 'uses', 'colophon', 'privacy' ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );

			$this->assertInstanceOf( WP_Post::class, $page, $slug . ' was seeded.' );
			$this->assertSame( '', get_page_template_slug( $page->ID ), $slug . ' has no template assigned.' );
			$this->assertNotSame( '', get_post_meta( $page->ID, 'dp_lead', true ) );
			$this->assertSame( 'UPDATED AUG 2026', get_post_meta( $page->ID, 'dp_updated', true ) );
		}
	}

	/**
	 * The Privacy page still says the thing that will not be true.
	 *
	 * Digest section 7 flags it as the one page where placeholder copy shipping
	 * as-is would actively mislead. It is seeded verbatim anyway, so the problem
	 * is visible on the site rather than hidden in a document — and this test is
	 * where somebody will find out, on the day they rewrite it, that the rewrite
	 * has to happen here too.
	 *
	 * @return void
	 */
	public function test_the_privacy_page_is_still_the_designs_placeholder(): void {
		$this->seeder->seed();

		$privacy = get_page_by_path( 'privacy', OBJECT, 'page' );

		$this->assertInstanceOf( WP_Post::class, $privacy );
		$this->assertStringContainsString(
			'No third-party analytics, advertising, or social scripts load on any page.',
			$privacy->post_content,
			'Placeholder copy, carried through unchanged. Rewriting it before launch is a decision, not a bug fix.'
		);
	}

	/**
	 * The command itself runs and reports, without printing to the test run.
	 *
	 * @return void
	 */
	public function test_the_command_runs_and_reports(): void {
		$output  = new NullOutput();
		$command = new SeedCommand( Seeder::create(), $output );

		$command( array(), array() );

		$lines = $output->lines();

		$this->assertNotEmpty( $lines );
		$this->assertStringContainsString( 'Seeded', end( $lines ) );
		$this->assertStringContainsString( '6 roles', end( $lines ) );
		$this->assertCount(
			6,
			get_posts(
				array(
					'post_type'   => PostTypes::ROLE,
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * `--fresh` reaches the seeder.
	 *
	 * @return void
	 */
	public function test_the_command_passes_fresh_through(): void {
		Seeder::create()->seed();

		$output  = new NullOutput();
		$command = new SeedCommand( Seeder::create(), $output );

		$command( array(), array( 'fresh' => true ) );

		$this->assertStringContainsString( 'Removed everything a previous seed created.', $output->lines()[0] );
		$this->assertCount(
			6,
			get_posts(
				array(
					'post_type'   => PostTypes::ROLE,
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * The fixture and the seed agree on how many of each thing there are.
	 *
	 * Guards against the seeder skipping an entry silently — the counts above
	 * would still pass if both the fixture and the expectation were wrong
	 * together, and this is what stops them being wrong together.
	 *
	 * @return void
	 */
	public function test_the_fixture_and_the_seed_agree(): void {
		$report = $this->seeder->seed();

		$this->assertSame( count( $this->fixture->roles() ), $report->count( 'roles' ) );
		$this->assertSame( count( $this->fixture->ships() ), $report->count( 'shipped' ) );
		$this->assertSame( count( $this->fixture->videos() ), $report->count( 'videos' ) );
		$this->assertSame( count( $this->fixture->posts() ), $report->count( 'posts' ) );
		$this->assertSame( count( $this->fixture->pages() ), $report->count( 'pages' ) );
		$this->assertSame( count( $this->fixture->planned_parts() ), $report->count( 'planned_parts' ) );
		$this->assertSame( count( $this->fixture->categories() ), $report->count( 'categories' ) );
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
	 * Read a term meta value that should be text.
	 *
	 * @param int    $term_id  The term.
	 * @param string $meta_key The field.
	 * @return string
	 */
	private function term_text( int $term_id, string $meta_key ): string {
		$value = get_term_meta( $term_id, $meta_key, true );

		$this->assertIsString( $value, $meta_key . ' is text.' );

		return $value;
	}

	/**
	 * Find a seeded post of one type by its title.
	 *
	 * `get_page_by_title()` is deprecated, and `convertDeprecationsToExceptions`
	 * is on, so a helper it is.
	 *
	 * @param string $post_type The post type.
	 * @param string $title     The exact title.
	 * @return int The post ID.
	 */
	private function find( string $post_type, string $title ): int {
		$posts = get_posts(
			array(
				'post_type'   => $post_type,
				'numberposts' => -1,
			)
		);

		foreach ( $posts as $post ) {
			if ( $post->post_title === $title ) {
				return $post->ID;
			}
		}

		$this->fail( sprintf( 'No %s titled "%s" was seeded.', $post_type, $title ) );
	}
}
