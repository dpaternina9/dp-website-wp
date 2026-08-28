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

				/*
				 * Six, not the design's five. The sixth is seeded empty so that
				 * the state a category archive draws for a term with nothing in
				 * it is reachable; the design fills all five of its own, so that
				 * state had never been looked at.
				 */
				'categories'    => 6,

				/*
				 * Two, not the design's one. The second is placeholder, and it
				 * exists because "the only series" is a special case the theme
				 * used to be able to rely on: with one term, "the series this
				 * post is in" and "the series" cannot be told apart.
				 */
				'series'        => 2,
				'roles'         => 6,
				'shipped'       => 4,
				'videos'        => 6,

				/*
				 * Seven from the design, twenty-two of filler that says so.
				 * `posts_per_page` is ten and the index holds one post back, so
				 * this is what makes three pages, a middle page, and an
				 * end-of-archive panel reachable at all.
				 */
				'posts'         => 29,
				'planned_parts' => 5,

				/*
				 * Nine, where the design's `PAGES` has three. The other six are
				 * the views it draws from data rather than from a page — the
				 * front page, the writing index, Work, About, the resume and
				 * Contact — and WordPress needs a page behind each of them or
				 * four of the theme's six custom templates are assigned to
				 * nothing and cannot be reached at all.
				 */
				'pages'         => 9,

				/*
				 * `page_on_front`, `page_for_posts` and the privacy page. The
				 * theme ships both a `front-page` and a `home` template, which
				 * is the design's shape, and neither is reachable until Reading
				 * says so.
				 */
				'settings'      => 3,

				/*
				 * The header, the footer, the front page, the blog index and
				 * the 404 — saved with their links in, because ADR-0018 leaves
				 * a shipped button with no href and a seeded site has nobody to
				 * set them.
				 */
				'chrome_links'  => 5,

				/*
				 * The site logo, which the seeder sets from the file the theme
				 * answers `dp_brand_logo_path` with. 1 because this site has
				 * none yet; a site where David already chose one reports 0 and
				 * keeps his (ADR-0011).
				 */
				'brand'         => 1,
			),
			$report->counts()
		);

		$logo = get_option( 'site_logo' );

		$this->assertIsNumeric( $logo, 'A fresh site should not be missing its brand mark.' );
		$this->assertGreaterThan( 0, (int) $logo );

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
	 * Each featured card gets the design's own sentence, not the panel's paragraph.
	 *
	 * Kiveo is why the two fields exist. Its `detail` opens "One line on what
	 * Kiveo does and who it's for — copy to come", which is a note to the author;
	 * `featuredWork` gives the card "Native iOS, built solo, with nothing leaving
	 * the device". Both are the design's verbatim copy and both ship, in the two
	 * different places the design puts them.
	 *
	 * "Performance work" is the fourth shipped thing and the one the design does
	 * not feature. It carries no line, because the fixture gives it none and
	 * writing one would be inventing copy (digest §8).
	 *
	 * @return void
	 */
	public function test_each_featured_card_carries_the_designs_own_line(): void {
		$this->seeder->seed();

		foreach ( array(
			'Kiveo'                    => 'Native iOS, built solo, with nothing leaving the device.',
			'Natural-language queries' => 'Ask your analytics a question instead of learning a reporting UI.',
			'Agency platform & ops'    => 'The plumbing a small agency runs on, kept deliberately boring.',
		) as $name => $line ) {
			$ship = $this->find( PostTypes::SHIP, $name );

			$this->assertSame( $line, $this->meta_text( $ship, 'dp_line' ) );
			$this->assertNotSame(
				$this->meta_text( $ship, 'dp_detail' ),
				$this->meta_text( $ship, 'dp_line' ),
				$name . ' has one string doing two jobs.'
			);
		}

		$this->assertSame(
			'',
			$this->meta_text( $this->find( PostTypes::SHIP, 'Performance work' ), 'dp_line' ),
			'The design never writes a card line for the one shipped thing it does not feature.'
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
	 * Order is the publish date, oldest first, so the numbering the design prints
	 * — part 1 then part 2 — falls out of the dates the fixture already had. The
	 * seed writes no part numbers because there are none to write (ADR-0016).
	 *
	 * @return void
	 */
	public function test_the_series_is_wired_up(): void {
		$this->seeder->seed();

		$term = get_term_by( 'slug', 'life-story', Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertStringContainsString(
			'The long version of how I got here',
			$term->description,
			"The fixture's deck is seeded into the term description, which is where a series' deck lives."
		);

		$parts     = new SeriesParts();
		$published = $parts->published( $term->term_id );
		$planned   = $parts->planned( $term->term_id );

		$this->assertCount( 2, $published );
		$this->assertCount( 4, $planned );

		$this->assertSame( 'The job that taught me what care looks like', get_the_title( $published[0] ) );
		$this->assertSame( 'The workaholic years, and why I stopped', get_the_title( $published[1] ) );

		$this->assertSame( 'Before any of it was a job', $planned[0]->title );
		$this->assertStringContainsString( 'A borrowed computer', $planned[0]->note, "The note is the draft's own excerpt." );
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
	 * The design's three block-kit pages still carry their deck and their date.
	 *
	 * @return void
	 */
	public function test_the_block_kit_pages_carry_their_content(): void {
		$this->seeder->seed();

		foreach ( array( 'uses', 'colophon', 'privacy' ) as $slug ) {
			$page = $this->seeded_page( $slug );

			$this->assertNotSame( '', get_post_meta( $page->ID, 'dp_lead', true ), $slug . ' has a deck.' );
			$this->assertSame( 'UPDATED AUG 2026', get_post_meta( $page->ID, 'dp_updated', true ) );
		}
	}

	/**
	 * Every page the fixture assigns a template to gets that template.
	 *
	 * The value is the custom template's slug, without the extension, because
	 * that is what the admin stores and what `wp_update_post()` would accept.
	 *
	 * @return void
	 */
	public function test_each_page_carries_the_template_the_fixture_names(): void {
		$this->seeder->seed();

		foreach ( $this->fixture->pages() as $page ) {
			$post = $this->seeded_page( $page['slug'] );

			$this->assertSame(
				$page['template'],
				get_page_template_slug( $post->ID ),
				$page['slug'] . ' carries the template the fixture names.'
			);
		}
	}

	/**
	 * Every template the theme offers has a page assigned to it.
	 *
	 * A `customTemplates` entry with nothing assigned is a view that renders
	 * nowhere, which is the state four of the six were in: the theme declared
	 * `dp-work`, `dp-about`, `dp-resume` and `dp-contact` and the seed created no
	 * page that could ever use them.
	 *
	 * @return void
	 */
	public function test_every_custom_template_the_theme_offers_is_assigned(): void {
		$this->seeder->seed();

		$offered = array_keys( wp_get_theme()->get_page_templates( null, 'page' ) );

		$this->assertNotEmpty( $offered, 'The active theme offers custom templates, so this is looking at something.' );

		$assigned = array();

		foreach ( $this->fixture->pages() as $page ) {
			if ( '' !== $page['template'] ) {
				$assigned[] = $page['template'];
			}
		}

		sort( $offered );
		sort( $assigned );

		$this->assertSame( $offered, $assigned, 'Every custom template has exactly one page, and no page names one the theme does not have.' );
	}

	/**
	 * Reading and Privacy point at the pages the run just made.
	 *
	 * `page_for_posts` does nothing at all while `show_on_front` is `posts`, so
	 * the three are asserted together or the assertion is worthless.
	 *
	 * @return void
	 */
	public function test_reading_and_privacy_point_at_the_seeded_pages(): void {
		$this->seeder->seed();

		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $this->seeded_page( 'home' )->ID, $this->setting( 'page_on_front' ) );
		$this->assertSame( $this->seeded_page( 'writing' )->ID, $this->setting( 'page_for_posts' ) );
		$this->assertSame( $this->seeded_page( 'privacy' )->ID, $this->setting( 'wp_page_for_privacy_policy' ) );
	}

	/**
	 * A fresh run gives the three settings back rather than leaving them dangling.
	 *
	 * `show_on_front` set to `page` with a deleted `page_on_front` is a site
	 * whose front page renders nothing, which is a worse state than the one the
	 * seed found.
	 *
	 * @return void
	 */
	public function test_a_fresh_run_releases_the_settings_it_set(): void {
		$this->seeder->seed();

		Seeder::create()->wipe();

		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( 0, $this->setting( 'page_on_front' ) );
		$this->assertSame( 0, $this->setting( 'page_for_posts' ) );
		$this->assertSame( 0, $this->setting( 'wp_page_for_privacy_policy' ) );
	}

	/**
	 * It leaves alone a Reading setting pointing at a page David chose.
	 *
	 * @return void
	 */
	public function test_a_fresh_run_leaves_a_front_page_it_did_not_set(): void {
		$this->seeder->seed();

		$mine = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'The one David picked',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $mine );

		update_option( 'page_on_front', $mine );

		Seeder::create()->wipe();

		$this->assertSame( $mine, $this->setting( 'page_on_front' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
	}

	/**
	 * A setting that holds a post ID.
	 *
	 * @param string $option The option name.
	 * @return int
	 */
	private function setting( string $option ): int {
		$stored = get_option( $option );

		$this->assertIsNumeric( $stored, $option . ' holds a post ID.' );

		return (int) $stored;
	}

	/**
	 * A page the run created, looked up the way a person would.
	 *
	 * @param string $slug The slug the fixture starts it with.
	 * @return WP_Post
	 */
	private function seeded_page( string $slug ): WP_Post {
		$page = get_page_by_path( $slug, OBJECT, 'page' );

		$this->assertInstanceOf( WP_Post::class, $page, $slug . ' was seeded.' );

		return $page;
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
		$this->assertSame(
			count( $this->fixture->posts() ) + count( $this->fixture->filler_posts() ),
			$report->count( 'posts' )
		);
		$this->assertSame( count( $this->fixture->pages() ), $report->count( 'pages' ) );
		$this->assertSame(
			count( $this->fixture->planned_parts() ) + count( $this->fixture->extra_planned_parts() ),
			$report->count( 'planned_parts' )
		);
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
