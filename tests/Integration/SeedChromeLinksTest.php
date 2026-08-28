<?php
/**
 * Integration tests for the chrome links a seed run puts in.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Content\ContentModel;
use DP\Core\Content\Taxonomies;
use DP\Core\Fixture\ChromeLinks;
use DP\Core\Fixture\Seeder;
use WP_Block_Template;
use WP_Post;
use WP_Term;
use WP_UnitTestCase;

/**
 * `dp_seed_chrome_links`: the plugin asks, the theme answers, the plugin saves.
 *
 * ADR-0018 deleted the system that wrote a chrome button's `href` at render
 * time, and said in as many words what the cost would be: "Fresh installs ship
 * with blank links … `dp-core`'s seeder sets them on a seeded site, so `npm run
 * env:reset` still produces a working site." Nothing did that, which is what
 * these tests are about.
 *
 * Four claims, and each of them is a way the mechanism could go wrong quietly.
 *
 * **It writes what the site editor writes.** A saved `wp_template` or
 * `wp_template_part` post carrying the theme's term — not a filter, not a class,
 * nothing at render time. So the front end and the editor draw the same thing,
 * which is what ADR-0008 wanted and ADR-0018 finally got.
 *
 * **A stale override cannot survive a run.** A stored override beats the theme's
 * file for as long as it exists, so one kept across releases freezes that
 * template silently. Every run deletes the ones it marked before writing any, and
 * the theme regenerates each from `get_block_file_template()` — the file, never
 * the stored copy.
 *
 * **It only ever touches its own.** Deletion is scoped by a meta mark, so an
 * override David saved is left alone by a normal run and by `--fresh`.
 *
 * **Neither package learns the other's vocabulary.** `dp-core` hands over
 * destination keys and URLs and saves opaque markup; the block names, labels and
 * file names stay in the theme, and there is a test below that greps for them.
 */
final class SeedChromeLinksTest extends WP_UnitTestCase {

	/**
	 * The five files the chrome-link seam covers, and what each one is.
	 *
	 * The header and the footer because they are on every page; the front page,
	 * the writing index and the 404 because each is a view whose whole job is to
	 * send you somewhere else. Deliberately not every template: a saved override
	 * is a template frozen until the next seed run, so the set is the smallest one
	 * that makes a seeded site navigable (ADR-0018 leaves the rest to David).
	 *
	 * A list of pairs rather than a slug-keyed map, because `404` is a slug and
	 * PHP would silently turn that key into the integer 404.
	 */
	private const COVERED = array(
		array( 'header', 'wp_template_part' ),
		array( 'footer', 'wp_template_part' ),
		array( 'front-page', 'wp_template' ),
		array( 'home', 'wp_template' ),
		array( '404', 'wp_template' ),
	);

	/**
	 * Register the content model, which `tear_down()` unregisters.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();
	}

	/*
	 * ------------------------------------------------------------ What is saved
	 */

	/**
	 * One override per covered file, carrying the theme and, for a part, its area.
	 *
	 * The area is not decoration: core turns a template part's
	 * `wp_template_part_area` term into the wrapping `<header>` or `<footer>`, so
	 * a part saved without it renders inside a `<div>` and the landmark is gone.
	 *
	 * @return void
	 */
	public function test_it_saves_one_override_for_each_covered_file(): void {
		Seeder::create()->seed();

		$this->assertSame( count( self::COVERED ), $this->saved_count() );

		foreach ( self::COVERED as list( $slug, $type ) ) {
			$post = $this->override( $slug, $type );

			$this->assertSame( '1', get_post_meta( $post->ID, Seeder::CHROME_MARK, true ), $slug . ' is marked as the seeder\'s.' );
			$this->assertSame( array( get_stylesheet() ), wp_get_post_terms( $post->ID, 'wp_theme', array( 'fields' => 'names' ) ) );

			if ( 'wp_template_part' === $type ) {
				$this->assertSame(
					array( $slug ),
					wp_get_post_terms( $post->ID, 'wp_template_part_area', array( 'fields' => 'names' ) ),
					$slug . ' carries its area, so core still wraps it in a landmark.'
				);
			}
		}
	}

	/**
	 * Core resolves the saved post rather than the file, which is the whole point.
	 *
	 * @return void
	 */
	public function test_core_resolves_the_saved_override(): void {
		Seeder::create()->seed();

		$template = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );

		$this->assertInstanceOf( WP_Block_Template::class, $template );
		$this->assertSame( 'custom', $template->source, 'A saved override is what renders, not the theme file.' );
	}

	/*
	 * ------------------------------------------------------------- What is in it
	 */

	/**
	 * Every named button in a covered file comes out with a link on it.
	 *
	 * The names are read off the theme's own shipped markup rather than listed
	 * here, so a button renamed or added in the theme is covered by this the day
	 * it lands instead of the day somebody remembers to update a fixture.
	 *
	 * @return void
	 */
	public function test_every_named_button_in_the_chrome_is_linked(): void {
		Seeder::create()->seed();

		$destinations = $this->seeded_urls();
		$checked      = 0;

		foreach ( self::COVERED as list( $slug, $type ) ) {
			$shipped = $this->shipped_names( $slug, $type );

			if ( array() === $shipped ) {
				continue;
			}

			$saved = $this->buttons( $this->override( $slug, $type )->post_content );

			foreach ( $shipped as $name ) {
				$button = $saved[ $name ] ?? null;

				$this->assertIsArray( $button, sprintf( '"%s" survives into the saved %s.', $name, $slug ) );
				$this->assertNotSame( '', $button['url'], sprintf( '"%s" in %s carries a url attribute.', $name, $slug ) );
				$this->assertSame( $button['url'], $button['href'], sprintf( '"%s" in %s agrees with its own anchor.', $name, $slug ) );
				$this->assertContains(
					$button['url'],
					$destinations,
					sprintf( '"%s" in %s points at something this run created.', $name, $slug )
				);

				++$checked;
			}
		}

		$this->assertGreaterThan( 10, $checked, 'The chrome has named buttons, so this assertion is looking at something.' );
	}

	/**
	 * No button anywhere in a covered file is left without a link.
	 *
	 * The stronger form of the test above, and the acceptance criterion in plain
	 * terms: on a seeded site, every button on the header, the footer, the front
	 * page, the writing index and the 404 goes somewhere. Anchors and the mobile
	 * panel's own toggles count, because they ship with a link already.
	 *
	 * @return void
	 */
	public function test_no_button_on_a_covered_file_is_left_dead(): void {
		Seeder::create()->seed();

		foreach ( self::COVERED as list( $slug, $type ) ) {
			foreach ( $this->buttons( $this->override( $slug, $type )->post_content ) as $name => $button ) {
				$this->assertNotSame(
					'',
					$button['href'],
					sprintf( '%s ("%s") on the saved %s has somewhere to go.', $name, $button['label'], $slug )
				);
			}
		}
	}

	/**
	 * The closing band is inlined, because a pattern reference cannot carry a link.
	 *
	 * A `core/pattern` block is resolved from the theme's registry at render time,
	 * so a saved template that only references one cannot link the button inside
	 * it — which is why "Say hi" was the last dead button on a seeded home page.
	 * Expanding it is what the site editor does anyway on first save.
	 *
	 * The rest is left alone: a pattern whose expansion gains no link stays a
	 * reference, so the query loop and the pager are not frozen into a seeded copy.
	 *
	 * @return void
	 */
	public function test_a_pattern_is_inlined_only_where_it_buys_a_link(): void {
		Seeder::create()->seed();

		$front = $this->override( 'front-page', 'wp_template' )->post_content;

		$this->assertStringNotContainsString( 'dpaternina/cta-band', $front, 'The band is inlined, so its button could be linked.' );
		$this->assertStringContainsString( 'dp-cta-band', $front, 'And inlining it kept the markup.' );
		$this->assertStringContainsString(
			'<!-- wp:pattern {"slug":"dpaternina/post-row-compact"} /-->',
			$front,
			'A pattern with nothing to link is still a reference, so it cannot go stale.'
		);
	}

	/*
	 * ------------------------------------------------------------- Staleness
	 */

	/**
	 * A stale override cannot survive a re-seed.
	 *
	 * The failure this exists for is real and has happened: a saved `home`
	 * override went on rendering a block the theme had already replaced, because
	 * a stored override beats the file and nothing regenerated it.
	 *
	 * @return void
	 */
	public function test_a_stale_override_does_not_survive_a_re_seed(): void {
		Seeder::create()->seed();

		$before = $this->override( 'home', 'wp_template' );

		wp_update_post(
			array(
				'ID'           => $before->ID,
				'post_content' => '<!-- wp:paragraph --><p>A block the theme stopped shipping.</p><!-- /wp:paragraph -->',
			)
		);

		Seeder::create()->seed();

		$after = $this->override( 'home', 'wp_template' );

		$this->assertStringNotContainsString( 'A block the theme stopped shipping.', $after->post_content );
		$this->assertStringContainsString( 'dp-quicklink-series', $after->post_content, 'It was rebuilt from the file the theme ships now.' );
		$this->assertSame( 1, $this->saved_count( 'home' ), 'Rebuilding replaced it rather than making a second one.' );
	}

	/**
	 * Running twice leaves the same five, not ten.
	 *
	 * @return void
	 */
	public function test_two_runs_leave_one_set_of_overrides(): void {
		Seeder::create()->seed();
		Seeder::create()->seed();

		$this->assertSame( 5, $this->saved_count() );

		foreach ( self::COVERED as list( $slug, $type ) ) {
			$this->assertSame( 1, $this->saved_count( $slug ), $slug . ' exists once.' );
		}
	}

	/**
	 * A run finds its own overrides even with the index gone.
	 *
	 * The index is an option, and an option can be deleted — by hand, by a
	 * half-finished run, by a database restored from somewhere else. An override
	 * the index has forgotten would be one nothing could ever refresh, so the mark
	 * on the post is what the deletion actually keys on.
	 *
	 * @return void
	 */
	public function test_it_finds_its_own_overrides_without_the_index(): void {
		Seeder::create()->seed();

		delete_option( Seeder::INDEX_OPTION );

		Seeder::create()->seed();

		$this->assertSame( 5, $this->saved_count() );
	}

	/**
	 * `--fresh` takes them away, and a run after it puts them back.
	 *
	 * @return void
	 */
	public function test_fresh_removes_the_overrides_it_made(): void {
		Seeder::create()->seed();

		$this->assertSame( 5, $this->saved_count() );

		Seeder::create()->wipe();

		$this->assertSame( 0, $this->saved_count() );

		Seeder::create()->seed( true );

		$this->assertSame( 5, $this->saved_count() );
	}

	/*
	 * --------------------------------------------------------- Somebody else's
	 */

	/**
	 * An override David saved is not deleted, on a normal run or a fresh one.
	 *
	 * It carries no mark, which is the whole of the rule. Blanket-deleting
	 * `wp_template` posts would take his work with it.
	 *
	 * @return void
	 */
	public function test_an_override_it_did_not_write_is_left_alone(): void {
		$his = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'single',
				'post_title'   => 'single',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>David edited this in the site editor.</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertIsInt( $his );

		wp_set_post_terms( $his, array( get_stylesheet() ), 'wp_theme' );

		Seeder::create()->seed();
		Seeder::create()->seed( true );

		$still = get_post( $his );

		$this->assertInstanceOf( WP_Post::class, $still );
		$this->assertStringContainsString( 'David edited this in the site editor.', $still->post_content );
	}

	/**
	 * With nothing answering the seam, nothing is written and the seed still runs.
	 *
	 * A theme that does not answer is the ordinary case for any theme but this
	 * one, and the seeder may not depend on the answer arriving.
	 *
	 * @return void
	 */
	public function test_no_answer_means_no_overrides(): void {
		remove_all_filters( ChromeLinks::FILTER );

		$report = Seeder::create()->seed();

		$this->assertSame( 0, $report->count( 'chrome_links' ) );
		$this->assertSame( 0, $this->saved_count() );
		$this->assertSame( 9, $report->count( 'pages' ), 'Everything else was seeded regardless.' );
	}

	/**
	 * A nonsense answer is refused rather than written.
	 *
	 * @return void
	 */
	public function test_a_malformed_answer_is_refused(): void {
		remove_all_filters( ChromeLinks::FILTER );

		add_filter(
			ChromeLinks::FILTER,
			static fn (): array => array(
				'not an entry',
				array(
					'type'    => 'post',
					'slug'    => 'header',
					'content' => 'x',
				),
				array(
					'type'    => 'wp_template',
					'slug'    => '',
					'content' => 'x',
				),
				array(
					'type'    => 'wp_template',
					'slug'    => 'home',
					'content' => '   ',
				),
			)
		);

		$report = Seeder::create()->seed();

		$this->assertSame( 0, $report->count( 'chrome_links' ) );
		$this->assertSame( 0, $this->saved_count() );
	}

	/*
	 * ------------------------------------------------------------ The boundary
	 */

	/**
	 * `dp-core` does not know one word of the theme's link vocabulary.
	 *
	 * CLAUDE.md section 2.1. The names below are the seam's whole trigger and
	 * they live in the theme's markup and in one theme class; a copy of any of
	 * them inside the plugin would mean the plugin had started deciding what the
	 * chrome says.
	 *
	 * @return void
	 */
	public function test_the_plugin_names_none_of_the_themes_buttons(): void {
		$names = array();

		foreach ( self::COVERED as list( $slug, $type ) ) {
			$names = array_merge( $names, $this->shipped_names( $slug, $type ) );
		}

		$names = array_unique( $names );

		$this->assertNotEmpty( $names, 'The theme names its chrome buttons, so this is looking at something.' );

		$leaked = array();

		foreach ( $this->plugin_sources() as $relative => $source ) {
			foreach ( $names as $name ) {
				if ( str_contains( $source, $name ) ) {
					$leaked[] = $relative . ' names "' . $name . '"';
				}
			}

			foreach ( array( 'wp:button', 'core/button', 'wp-block-button', 'dp-button' ) as $markup ) {
				if ( str_contains( $source, $markup ) ) {
					$leaked[] = $relative . ' contains "' . $markup . '"';
				}
			}
		}

		$this->assertSame( array(), $leaked, 'dp-core has learned the theme\'s markup: ' . implode( ', ', $leaked ) );
	}

	/*
	 * ------------------------------------------------------------------ Helpers
	 */

	/**
	 * How many overrides the seeder has saved, in total or under one slug.
	 *
	 * @param string $slug One slug, or '' for all of them.
	 * @return int
	 */
	private function saved_count( string $slug = '' ): int {
		$args = array(
			'post_type'   => array( 'wp_template', 'wp_template_part' ),
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the mark is the thing under test.
			'meta_key'    => Seeder::CHROME_MARK,
		);

		if ( '' !== $slug ) {
			$args['name'] = $slug;
		}

		return count( get_posts( $args ) );
	}

	/**
	 * The saved override for one slug, asserted to be there.
	 *
	 * @param string $slug The template slug.
	 * @param string $type Either `wp_template` or `wp_template_part`.
	 * @return WP_Post
	 */
	private function override( string $slug, string $type ): WP_Post {
		$found = get_posts(
			array(
				'post_type'   => $type,
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);

		$post = reset( $found );

		$this->assertInstanceOf( WP_Post::class, $post, $slug . ' was saved as a ' . $type . '.' );

		return $post;
	}

	/**
	 * The permalinks a seeded site's chrome is allowed to point at.
	 *
	 * @return list<string>
	 */
	private function seeded_urls(): array {
		$urls = array();

		foreach ( get_posts(
			array(
				'post_type'   => 'page',
				'numberposts' => -1,
			)
		) as $page ) {
			$url = get_permalink( $page );

			if ( is_string( $url ) ) {
				$urls[] = $url;
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::SERIES,
				'hide_empty' => false,
			)
		);

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = $term instanceof WP_Term ? get_term_link( $term ) : '';

				if ( is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		return $urls;
	}

	/**
	 * The `metadata.name` of every named button in one shipped theme file.
	 *
	 * Read from the file rather than the saved post, so this is the theme's own
	 * statement of what it expects to be linked.
	 *
	 * @param string $slug The template slug.
	 * @param string $type Either `wp_template` or `wp_template_part`.
	 * @return list<string>
	 */
	private function shipped_names( string $slug, string $type ): array {
		$template = get_block_file_template(
			get_stylesheet() . '//' . $slug,
			'wp_template_part' === $type ? 'wp_template_part' : 'wp_template'
		);

		$this->assertInstanceOf( WP_Block_Template::class, $template, $slug . ' is a file the theme ships.' );
		$this->assertIsString( $template->content );

		$names = array();

		foreach ( $this->buttons( $template->content ) as $name => $button ) {
			if ( '' !== $name && '' === $button['href'] ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Every `core/button` in a piece of block markup, keyed by its name.
	 *
	 * A regular expression rather than the block parser, because the parser's
	 * tree is what the code under test walks and a test that walked it the same
	 * way would agree with a bug rather than catch one.
	 *
	 * @param string $markup Block markup.
	 * @return array<string, array{url: string, href: string, label: string}>
	 */
	private function buttons( string $markup ): array {
		$found = array();
		$count = preg_match_all(
			'~<!-- wp:button( \{.*?\})? -->\s*(.*?)\s*<!-- /wp:button -->~s',
			$markup,
			$matches,
			PREG_SET_ORDER
		);

		if ( false === $count || 0 === $count ) {
			return $found;
		}

		foreach ( $matches as $index => $match ) {
			$json       = trim( $match[1] );
			$attributes = json_decode( '' === $json ? '{}' : $json, true );
			$attributes = is_array( $attributes ) ? $attributes : array();
			$metadata   = $attributes['metadata'] ?? null;
			$name       = is_array( $metadata ) && is_string( $metadata['name'] ?? null ) ? $metadata['name'] : '';
			$inner      = $match[2];

			$found[ '' === $name ? 'unnamed-' . $index : $name ] = array(
				'url'   => is_string( $attributes['url'] ?? null ) ? $attributes['url'] : '',
				'href'  => 1 === preg_match( '~<a[^>]*\shref="([^"]*)"~', $inner, $anchor ) ? $anchor[1] : '',
				'label' => trim( wp_strip_all_tags( $inner ) ),
			);
		}

		return $found;
	}

	/**
	 * Every PHP source file `dp-core` ships.
	 *
	 * @return array<string, string> Path relative to the plugin root, to contents.
	 */
	private function plugin_sources(): array {
		$root  = dirname( __DIR__, 2 ) . '/plugins/dp-core/src';
		$paths = $this->php_files( $root );

		$this->assertNotEmpty( $paths, 'dp-core has source files, so this is looking at something.' );

		$found = array();

		foreach ( $paths as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
			$source = file_get_contents( $path );

			if ( is_string( $source ) ) {
				$found[ substr( $path, strlen( $root ) + 1 ) ] = $source;
			}
		}

		return $found;
	}

	/**
	 * Every `.php` file under a directory.
	 *
	 * @param string $directory Absolute path.
	 * @return list<string>
	 */
	private function php_files( string $directory ): array {
		$paths = glob( $directory . '/*.php' );
		$found = is_array( $paths ) ? $paths : array();

		$children = glob( $directory . '/*', GLOB_ONLYDIR );

		if ( is_array( $children ) ) {
			foreach ( $children as $child ) {
				$found = array_merge( $found, $this->php_files( $child ) );
			}
		}

		return $found;
	}
}
