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
use DP\Core\Editor\FieldForm;
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
	 * Put the URL shape back for whatever runs next.
	 *
	 * The seeder now sets `permalink_structure`, and `$wp_rewrite` keeps its copy
	 * in memory for the whole process — so a run that left it set would hand the
	 * next test in the file a different site from the one it asked for. The
	 * transaction rolls the option back; nothing rolls the object back.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->set_permalink_structure( '' );

		parent::tear_down();
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

				/*
				 * Eight, not the design's six. One is the live entry, which is
				 * never in the archive, and the featured panel takes the newest
				 * archived video while the channel is off — so six entries draw
				 * four cards in a three-column grid. Seven archived entries fill
				 * two whole rows instead.
				 */
				'videos'        => 8,

				/*
				 * Seven from the design, twenty-two of filler that says so.
				 * `posts_per_page` is ten and the index holds one post back, so
				 * this is what makes three pages, a middle page, and an
				 * end-of-archive panel reachable at all.
				 */
				'posts'         => 29,
				'planned_parts' => 5,

				/*
				 * Eleven, where the design's `PAGES` has three. Seven are the
				 * views it draws from data rather than from a page — the front
				 * page, the writing index, Work, Watch, About, the resume and
				 * Contact — and WordPress needs a page behind each of them or
				 * the theme's custom templates are assigned to nothing and
				 * cannot be reached at all. The eleventh is the series index,
				 * which the design does not draw and which is the only way
				 * `/series/` is anything but a 404.
				 */
				'pages'         => 11,

				/*
				 * The permalink structure, `page_on_front`, `page_for_posts` and
				 * the privacy page. The theme ships both a `front-page` and a
				 * `home` template, which is the design's shape, and neither is
				 * reachable until Reading says so; and under the empty structure
				 * a fresh install starts with, none of the design's paths exists
				 * at all.
				 */
				'settings'      => 4,

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
			8,
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
	 * The Watch page starts from the theme's gear pattern, through the seam.
	 *
	 * Digest section 3.6: the gear list is editor content David owns, "a
	 * block, not a post type". The plugin may not know the theme's markup, so
	 * the body arrives through `dp_seed_watch_body` — with this theme active,
	 * that is the `dpaternina/watch-gear` pattern, and the fixture's own
	 * "gear list missing" callout is what a themeless seed would have left.
	 *
	 * @return void
	 */
	public function test_the_watch_page_starts_from_the_gear_pattern(): void {
		$this->seeder->seed();

		$page = $this->seeded_page( 'watch' );

		$this->assertSame( 'dp-watch', get_page_template_slug( $page->ID ) );

		$deck = get_post_meta( $page->ID, 'dp_lead', true );

		$this->assertIsString( $deck );
		$this->assertStringContainsString( 'Not live at the moment.', $deck );
		$this->assertStringContainsString( 'What the stream runs on', $page->post_content );
		$this->assertStringContainsString( 'dp-gear-group', $page->post_content );
		$this->assertStringNotContainsString( 'GEAR LIST MISSING', $page->post_content, 'The theme answered the seam, so the placeholder callout must not seed.' );
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
	 * A seeded site serves the paths the design draws, not query strings.
	 *
	 * This is the assertion the first version of this work did not have, and the
	 * gap it left was invisible from the inside: the sweep that checked every
	 * page drove itself from `get_permalink()`, so it was asking WordPress what
	 * URL it would generate and then checking that WordPress could resolve it.
	 * Under an empty `permalink_structure` that is `?page_id=47`, which returns
	 * 200 and proves nothing, while every path in the design 404s.
	 *
	 * So this asserts the shape as well as the resolution, and it resolves
	 * through `url_to_postid()`, which runs the URL back through the rewrite
	 * rules rather than through the function that produced it.
	 *
	 * @return void
	 */
	public function test_a_seeded_site_serves_paths_rather_than_query_strings(): void {
		$this->seeder->seed();

		$this->assertSame( $this->fixture->permalink_structure(), get_option( 'permalink_structure' ) );

		foreach ( $this->fixture->pages() as $page ) {
			$post = $this->seeded_page( $page['slug'] );
			$url  = get_permalink( $post );

			$this->assertIsString( $url );
			$this->assertStringNotContainsString( '?', $url, $page['slug'] . ' has a path, not a query string.' );
			$this->assertSame(
				'front' === $page['role'] ? home_url( '/' ) : home_url( '/' . $page['slug'] . '/' ),
				$url
			);

			/*
			 * `go_to()` parses the URL the way a request does, through the
			 * rewrite rules, so this is the half `get_permalink()` cannot
			 * vouch for. It is uniform across all three roles because the
			 * queried object of a static front page and of the posts page is
			 * the page itself.
			 */
			$this->go_to( $url );

			$this->assertSame(
				$post->ID,
				get_queried_object_id(),
				$page['slug'] . ' is what its own path resolves to.'
			);
		}
	}

	/**
	 * `dp_series`' rewrite exists, which under a plain structure it does not.
	 *
	 * CLAUDE.md section 5.1 names it as one of the only two registered rewrites
	 * in the project. A rewrite is a rewrite rule, and a site with an empty
	 * `permalink_structure` generates no rewrite rules at all — so the one route
	 * this project owns was, on every freshly seeded site, absent.
	 *
	 * @return void
	 */
	public function test_the_series_archive_lives_under_its_own_path(): void {
		$this->seeder->seed();

		$term = get_term_by( 'slug', $this->fixture->series()['slug'], Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Term::class, $term );

		$link = get_term_link( $term );

		$this->assertIsString( $link );
		$this->assertSame(
			home_url( '/' . ( new Taxonomies() )->rewrite_slug() . '/' . $term->slug . '/' ),
			$link
		);

		$rules = get_option( 'rewrite_rules' );

		$this->assertIsArray( $rules );
		$this->assertNotEmpty( $rules, 'A structure with no rules behind it is a structure that 404s.' );

		$matched = array_filter(
			$rules,
			static fn ( $target ): bool => is_string( $target ) && str_contains( $target, 'dp_series=' )
		);

		$this->assertNotEmpty( $matched, 'The series taxonomy has rewrite rules.' );

		/*
		 * Core's own taxonomies are in the same boat and for the same reason:
		 * `register_taxonomy()` adds no permastruct on a site with no permalink
		 * structure, so a flush that ran before they were re-registered would
		 * write a rule set with no category archives in it and
		 * `wp_rewrite_rules()` would serve that from the option forever.
		 */
		$categories = array_filter(
			$rules,
			static fn ( $target ): bool => is_string( $target ) && str_contains( $target, 'category_name=' )
		);

		$this->assertNotEmpty( $categories, 'Core\'s category archives survived the flush too.' );
	}

	/**
	 * A structure David already chose is not replaced.
	 *
	 * Any non-empty structure gives the routes their rules, so there is nothing
	 * to gain by overwriting one — and everything to lose, since it would
	 * invalidate every URL on the site.
	 *
	 * @return void
	 */
	public function test_a_permalink_structure_already_set_is_left_alone(): void {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$report = $this->seeder->seed();

		$this->assertSame( '/%year%/%postname%/', get_option( 'permalink_structure' ) );
		$this->assertSame( 3, $report->count( 'settings' ), 'Three settings, not four: the structure was already there.' );
	}

	/**
	 * A fresh run gives the structure back, and leaves one it did not set.
	 *
	 * The third case is the one that matters and the one a value comparison gets
	 * wrong: `.wp-env.json` sets *this exact structure* at `wp-env start`, so
	 * "the option matches the fixture" is not evidence that this script wrote it.
	 * The index records the write, and only a recorded write is undone.
	 *
	 * @return void
	 */
	public function test_a_fresh_run_releases_only_the_structure_it_set(): void {
		$this->seeder->seed();

		Seeder::create()->wipe();

		$this->assertSame( '', get_option( 'permalink_structure' ), 'What it set, it gives back.' );

		$this->set_permalink_structure( '/%year%/%postname%/' );

		Seeder::create()->seed();
		Seeder::create()->wipe();

		$this->assertSame( '/%year%/%postname%/', get_option( 'permalink_structure' ), 'A different structure is left alone.' );

		$this->set_permalink_structure( $this->fixture->permalink_structure() );

		Seeder::create()->seed();
		Seeder::create()->wipe();

		$this->assertSame(
			$this->fixture->permalink_structure(),
			get_option( 'permalink_structure' ),
			'And so is the same structure, when somebody else put it there.'
		);
	}

	/**
	 * The environment and the fixture agree on the URL shape.
	 *
	 * The value is written twice on purpose and the halves do different jobs, so
	 * the risk is that one moves and the other does not.
	 *
	 * `.wp-env.json` sets the structure at `wp-env start`, through WP-CLI's own
	 * `rewrite structure --hard`. That is the only thing here that can write the
	 * `.htaccess` Apache needs before a request ever reaches PHP: `got_mod_rewrite()`
	 * is false under WP-CLI, and the way past it is the `apache_modules` key in
	 * the `wp-cli.yml` wp-env already ships — a piece of environment
	 * configuration, not something a plugin should assert on a guess.
	 *
	 * The seeder sets the same structure when it finds none, which is what makes
	 * `wp dp seed` correct on a site wp-env did not build, and what makes
	 * `get_term_link()` return `/series/life-story/` at the moment the chrome
	 * links are saved rather than `?dp_series=life-story`.
	 *
	 * @return void
	 */
	public function test_the_environment_and_the_fixture_want_the_same_urls(): void {
		$path = dirname( __DIR__, 2 ) . '/.wp-env.json';

		$this->assertFileIsReadable( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
		$json = file_get_contents( $path );

		$this->assertIsString( $json );
		$this->assertStringContainsString(
			"wp rewrite structure '" . $this->fixture->permalink_structure() . "' --hard",
			$json,
			'.wp-env.json sets the structure the fixture names, or a reset gives a site whose paths 404.'
		);
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
	 * The Privacy page is still the design's placeholder, minus one false claim.
	 *
	 * Digest section 7 flags it as the one page where placeholder copy shipping
	 * as-is would actively mislead, and the answer to that has always been to
	 * ship it anyway so the problem is visible on the site rather than hidden in
	 * a document. That is still the rule, and this test still holds it: the
	 * placeholder is here, unrewritten, and rewriting it before launch is a
	 * decision rather than a bug fix.
	 *
	 * One sentence is the exception. The page used to promise that no
	 * third-party script loads on any page, and since ADR-0023 the contact page
	 * can load Cloudflare Turnstile. Seeding a sentence the same release made
	 * false is not surfacing a problem, it is publishing an untruth, so the
	 * claim is narrowed and the challenge is described. This test is where
	 * somebody finds out, on the day they rewrite the rest, that the rewrite
	 * has to happen in `Fixture` too.
	 *
	 * @return void
	 */
	public function test_the_privacy_page_is_still_the_designs_placeholder(): void {
		$this->seeder->seed();

		$privacy = get_page_by_path( 'privacy', OBJECT, 'page' );

		$this->assertInstanceOf( WP_Post::class, $privacy );

		$this->assertStringContainsString(
			'No Google Analytics, no Meta pixel, no session recording.',
			$privacy->post_content,
			'Placeholder copy, carried through unchanged. Rewriting it before launch is a decision, not a bug fix.'
		);

		$this->assertStringNotContainsString(
			'No third-party analytics, advertising, or social scripts load on any page.',
			$privacy->post_content,
			'ADR-0023 made this false on the contact page. A seed may ship placeholder copy; it may not ship a claim this release disproved.'
		);

		$this->assertStringContainsString(
			'Cloudflare Turnstile',
			$privacy->post_content,
			'The one third-party script this site loads has to be named on the page that describes what loads.'
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

	/**
	 * Every timeline post the seeder writes opens with its editing form.
	 *
	 * The editor applies a post type's `template` only while the post is an
	 * `auto-draft` — `setupEditor()` checks exactly that — and everything here is
	 * published the moment it exists. So a seeded Role whose content were empty
	 * would open on a blank locked canvas with no way to reach any of its seven
	 * fields, which is the defect this phase set out to remove and would have
	 * reintroduced on the only site anybody looks at.
	 *
	 * @return void
	 */
	public function test_every_seeded_timeline_post_opens_with_its_form(): void {
		$this->seeder->seed();

		$form = new FieldForm();

		foreach ( PostTypes::all() as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'any',
				)
			);

			$this->assertNotEmpty( $posts, $post_type . ' seeded nothing.' );

			foreach ( $posts as $post ) {
				$this->assertInstanceOf( WP_Post::class, $post );
				$this->assertSame(
					$form->markup( $post_type ),
					$post->post_content,
					$post->post_title . ' would open on an empty locked canvas.'
				);
			}
		}
	}
}
