<?php
/**
 * Writes the fixture into a site.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Taxonomies;
use RuntimeException;
use WP_Filesystem_Base;
use WP_Post;
use WP_Rewrite;
use WP_Term;

/**
 * `wp dp seed`, minus the console.
 *
 * Idempotent by construction. Every object it creates is recorded against a
 * stable key in one option, so a second run updates the same objects rather than
 * making a second set of them. CLAUDE.md section 3 says a reset must give a site
 * that matches the design's fixtures and that the fix for a wrong local database
 * is the seed script, never the database — both of which need the script to be
 * safe to run twice.
 *
 * Order is a dependency graph, not a preference: terms before posts that carry
 * them, roles before the shipped things that hang off them, and posts before the
 * shipped thing that links to a write-up.
 */
final class Seeder {

	/**
	 * Where the key-to-object index lives.
	 *
	 * @var string
	 */
	public const INDEX_OPTION = 'dp_core_seed_index';

	/**
	 * The meta key that marks a template override as this script's.
	 *
	 * The index is the primary record, and this is the belt to its braces: an
	 * option can be deleted, and a `wp_template` post whose index entry has gone
	 * would then be an override nobody could find and nobody could refresh —
	 * which is exactly the stale-override failure the overrides exist to avoid.
	 * Every run deletes every override carrying this mark before writing new
	 * ones, so what a previous run left cannot survive whatever happened to the
	 * option. An override David saved from the site editor carries no mark and is
	 * never touched.
	 *
	 * @var string
	 */
	public const CHROME_MARK = '_dp_seed_chrome_link';

	/**
	 * The prefix template overrides are indexed under.
	 *
	 * @var string
	 */
	private const TEMPLATE_KEY = 'template:';

	/**
	 * The index, as loaded and then updated.
	 *
	 * `settings` records site options this script wrote, against the value it
	 * wrote, so `wipe()` can tell "I set this" from "it already said that". The
	 * other three settings need no such record: each holds the ID of a post in
	 * `posts`, which is proof enough. A permalink structure holds a string that
	 * anything could have put there — the development environment sets exactly
	 * this one at `wp-env start`, and clearing that on `--fresh` would be this
	 * script tidying away somebody else's work.
	 *
	 * @var array{posts: array<string, int>, terms: array<string, int>, settings: array<string, string>}
	 */
	private array $index = array(
		'posts'    => array(),
		'terms'    => array(),
		'settings' => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param Fixture     $fixture The design's data.
	 * @param BlockMarkup $markup  Converts fixture bodies into block markup.
	 * @param ChromeLinks $links   Asks the theme for its chrome's saved markup.
	 */
	public function __construct(
		private readonly Fixture $fixture,
		private readonly BlockMarkup $markup,
		private readonly ChromeLinks $links
	) {}

	/**
	 * Build the seeder with its default collaborators.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self( new Fixture(), new BlockMarkup(), new ChromeLinks() );
	}

	/**
	 * Write the whole fixture.
	 *
	 * @param bool $fresh Whether to delete what a previous run made first.
	 * @return SeedReport
	 *
	 * @throws RuntimeException When WordPress refuses to write something.
	 */
	public function seed( bool $fresh = false ): SeedReport {
		if ( $fresh ) {
			$this->wipe();
		}

		$this->load_index();

		/*
		 * Block markup is HTML comments carrying JSON. `wp_filter_post_kses`
		 * rewrites the inside of a comment, which is enough to break a block's
		 * attributes, and it is switched on whenever the acting user cannot
		 * `unfiltered_html` — which under WP-CLI is usually nobody at all.
		 *
		 * This is not a hole: what is being inserted is a fixture compiled into
		 * the plugin, with no path from a request to any of it. `kses_init()`
		 * restores whatever the filters were, by re-deciding rather than by
		 * assuming they were on.
		 */
		kses_remove_filters();

		try {
			$categories = $this->seed_categories();
			$series     = $this->seed_series();
			$extra      = $this->seed_extra_series();
			$roles      = $this->seed_roles();
			$lead       = $this->lead_image();
			$posts      = $this->seed_posts(
				$categories,
				array(
					'design' => $series,
					'extra'  => $extra,
				),
				$lead
			);
			$planned    = $this->seed_planned_parts( $series, $extra );
			$pages      = $this->seed_pages();
			$settings   = $this->seed_settings( $pages );
			$links      = $this->seed_chrome_links( $pages, $series );
			$ships      = $this->seed_ships( $roles, $posts );
			$videos     = $this->seed_videos();
			$brand      = $this->seed_brand();
		} finally {
			kses_init();
			$this->save_index();
		}

		return new SeedReport(
			array(
				'categories'    => count( $categories ),
				'series'        => ( $series > 0 ? 1 : 0 ) + ( $extra > 0 ? 1 : 0 ),
				'roles'         => count( $roles ),
				'shipped'       => count( $ships ),
				'videos'        => count( $videos ),
				'posts'         => count( $posts ),
				'planned_parts' => count( $planned ),
				'pages'         => count( $pages ),
				'settings'      => $settings,
				'chrome_links'  => $links,
				'brand'         => $brand,
			),
			$fresh
		);
	}

	/**
	 * Delete every object a previous run created, and forget them.
	 *
	 * Only what is in the index: a post David wrote himself is not this script's
	 * to delete, however much it looks like fixture content.
	 *
	 * @return void
	 */
	public function wipe(): void {
		$this->load_index();

		/*
		 * The brand mark is an attachment like any other in the index, and
		 * `wp_delete_post()` routes an attachment to `wp_delete_attachment()`
		 * for us. What it cannot know is that an option points at it — so the
		 * option is cleared first, and only when it points at *our* attachment.
		 */
		$mark   = $this->index['posts']['attachment:brand-mark'] ?? 0;
		$stored = get_option( 'site_logo' );

		if ( $mark > 0 && is_numeric( $stored ) && (int) $stored === $mark ) {
			delete_option( 'site_logo' );
		}

		/*
		 * Four settings point at a page by ID, and three of them are worse than
		 * useless once that page is gone: `show_on_front` set to `page` with a
		 * deleted `page_on_front` is a site whose front page is blank. Released
		 * first, and only where the ID is one this script wrote.
		 */
		if ( $this->release_setting( 'page_on_front', 'page:home' ) ) {
			update_option( 'show_on_front', 'posts' );
		}

		$this->release_setting( 'page_for_posts', 'page:posts' );
		$this->release_setting( 'wp_page_for_privacy_policy', 'page:privacy' );
		$this->release_permalinks();

		foreach ( $this->index['posts'] as $post_id ) {
			if ( get_post( $post_id ) instanceof WP_Post ) {
				wp_delete_post( $post_id, true );
			}
		}

		foreach ( $this->index['terms'] as $key => $term_id ) {
			$taxonomy = str_starts_with( $key, 'series:' ) ? Taxonomies::SERIES : 'category';

			if ( get_term( $term_id, $taxonomy ) instanceof WP_Term ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}

		$this->index = array(
			'posts'    => array(),
			'terms'    => array(),
			'settings' => array(),
		);

		delete_option( self::INDEX_OPTION );
	}

	/**
	 * The five categories.
	 *
	 * @return array<string, int> Slug to term ID.
	 */
	private function seed_categories(): array {
		$ids = array();

		foreach ( $this->fixture->categories() as $category ) {
			$ids[ $category['slug'] ] = $this->upsert_term(
				'category:' . $category['slug'],
				'category',
				$category['name'],
				$category['slug'],
				$category['description']
			);
		}

		return $ids;
	}

	/**
	 * The one series term, with its deck.
	 *
	 * The deck goes in the term's description, which is where a series' deck
	 * lives. The fixture still calls it a deck because the design does; only the
	 * column it lands in changed.
	 *
	 * @return int Term ID.
	 */
	private function seed_series(): int {
		$series = $this->fixture->series();

		return $this->upsert_term(
			'series:' . $series['slug'],
			Taxonomies::SERIES,
			$series['title'],
			$series['slug'],
			$series['deck']
		);
	}

	/**
	 * The placeholder second series.
	 *
	 * @return int Term ID.
	 */
	private function seed_extra_series(): int {
		$series = $this->fixture->extra_series();

		return $this->upsert_term(
			'series:' . $series['slug'],
			Taxonomies::SERIES,
			$series['title'],
			$series['slug'],
			$series['deck']
		);
	}

	/**
	 * The six timeline lanes.
	 *
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_roles(): array {
		$ids   = array();
		$order = 0;

		foreach ( $this->fixture->roles() as $role ) {
			++$order;

			$ids[ $role['key'] ] = $this->upsert_post(
				'role:' . $role['key'],
				PostTypes::ROLE,
				$role['org'],
				'publish',
				$order,
				array(
					'dp_role_title' => $role['title'],
					'dp_start'      => $role['start'],
					'dp_end'        => $role['end'],
					'dp_range'      => $role['range'],
					'dp_detail'     => $role['detail'],
					'dp_stack'      => $role['stack'],
					'dp_accent'     => $role['accent'],
				)
			);
		}

		return $ids;
	}

	/**
	 * The four shipped things.
	 *
	 * @param array<string, int> $roles Fixture key to role post ID.
	 * @param array<string, int> $posts Fixture slug to post ID.
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_ships( array $roles, array $posts ): array {
		$ids   = array();
		$order = 0;

		/*
		 * The one association the fixture leaves implicit. The design's default
		 * open state is the natural-language-queries panel, and that panel renders
		 * a WRITE-UP link; the only post in the fixture about that work is
		 * `ai-features-users`. Wiring it is reproducing a state the design shows,
		 * not inventing content — but it is an inference, so it is named here
		 * rather than buried in the data.
		 */
		$writeups = array( 'nlq' => 'ai-features-users' );

		foreach ( $this->fixture->ships() as $ship ) {
			++$order;

			$writeup_slug = $writeups[ $ship['key'] ] ?? '';

			$ids[ $ship['key'] ] = $this->upsert_post(
				'ship:' . $ship['key'],
				PostTypes::SHIP,
				$ship['name'],
				'publish',
				$order,
				array(
					'dp_role_id'        => $roles[ $ship['role'] ] ?? 0,
					'dp_start'          => $ship['start'],
					'dp_end'            => $ship['end'],
					'dp_range'          => $ship['range'],
					'dp_headline'       => $ship['headline'],
					'dp_detail'         => $ship['detail'],
					'dp_line'           => $ship['line'],
					'dp_bullets'        => $ship['bullets'],
					'dp_ship_role'      => $ship['ship_role'],
					'dp_stack'          => $ship['stack'],
					'dp_artifact_label' => $ship['artifact_label'],
					'dp_artifact'       => $ship['artifact'],
					'dp_stat1'          => $ship['stat1'],
					'dp_stat1_label'    => $ship['stat1_label'],
					'dp_stat2'          => $ship['stat2'],
					'dp_stat2_label'    => $ship['stat2_label'],
					'dp_featured'       => $ship['featured'],
					'dp_writeup_id'     => '' === $writeup_slug ? 0 : ( $posts[ $writeup_slug ] ?? 0 ),
				)
			);
		}

		return $ids;
	}

	/**
	 * The Watch grid, live panel included.
	 *
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_videos(): array {
		$ids   = array();
		$order = 0;

		foreach ( $this->fixture->videos() as $video ) {
			++$order;

			$ids[ $video['key'] ] = $this->upsert_post(
				'video:' . $video['key'],
				PostTypes::VIDEO,
				$video['title'],
				'publish',
				$order,
				array(
					'dp_video_source' => $video['source'],
					'dp_video_ref'    => $video['ref'],
					'dp_tone'         => $video['tone'],
					'dp_duration'     => $video['duration'],
					'dp_when'         => $video['when'],
					'dp_note'         => $video['note'],
					'dp_live'         => $video['live'],
					'dp_live_meta'    => $video['live_meta'],
				)
			);
		}

		return $ids;
	}

	/**
	 * The design's posts, and the filler that makes its states reachable.
	 *
	 * Both lists go through one loop because they are the same kind of object and
	 * the only difference is where the words came from — which the filler says
	 * about itself, in every field it has.
	 *
	 * A post carries no meta at all. Everything the design prints above and around
	 * one — the kicker, the tone, the read time, the standfirst, the lead image's
	 * caption and the part number — is derived from the content, the terms, the
	 * date or the attachment (ADR-0016), so the only thing to write here is the
	 * post.
	 *
	 * @param array<string, int> $categories Slug to term ID.
	 * @param array<string, int> $series     Fixture series key to term ID.
	 * @param int                $lead       Attachment ID for the lead image, or 0.
	 * @return array<string, int> Post slug to post ID.
	 */
	private function seed_posts( array $categories, array $series, int $lead ): array {
		$ids = array();

		foreach ( array_merge( $this->fixture->posts(), $this->fixture->filler_posts() ) as $post ) {
			$post_id = $this->upsert_post(
				'post:' . $post['slug'],
				'post',
				$post['title'],
				'publish',
				0,
				array(),
				slug: $post['slug'],
				excerpt: $post['excerpt'],
				content: $this->markup->render( $post['body'] ),
				date: $post['date']
			);

			$category_id = $categories[ $post['category'] ] ?? 0;

			if ( $category_id > 0 ) {
				wp_set_post_terms( $post_id, array( $category_id ), 'category', false );
			}

			$series_id = '' === $post['series'] ? 0 : ( $series[ $post['series'] ] ?? 0 );

			if ( $series_id > 0 ) {
				wp_set_post_terms( $post_id, array( $series_id ), Taxonomies::SERIES, false );
			}

			if ( $lead > 0 ) {
				set_post_thumbnail( $post_id, $lead );
			}

			$ids[ $post['slug'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * The four planned parts, as drafts carrying the series term.
	 *
	 * Plan section 3.1: the term is the switch. These are drafts *and* filed under
	 * the series, which is what makes their titles public; a draft without the
	 * term stays invisible.
	 *
	 * The line under the title is the draft's own excerpt, which is a core field
	 * with a sidebar box, and the order they appear in is their date. Neither is
	 * meta any more (ADR-0016), which is why nothing is written here but the post.
	 *
	 * @param int $series The design's series term ID.
	 * @param int $extra  The placeholder series term ID.
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_planned_parts( int $series, int $extra ): array {
		$ids   = array();
		$lists = array(
			$series => $this->fixture->planned_parts(),
			$extra  => $this->fixture->extra_planned_parts(),
		);

		foreach ( $lists as $term_id => $parts ) {
			foreach ( $parts as $part ) {
				$post_id = $this->upsert_post(
					'planned:' . $part['key'],
					'post',
					$part['title'],
					'draft',
					0,
					array(),
					excerpt: $part['note'],
					date: $part['date']
				);

				if ( $term_id > 0 ) {
					wp_set_post_terms( $post_id, array( (int) $term_id ), Taxonomies::SERIES, false );
				}

				$ids[ $part['key'] ] = $post_id;
			}
		}

		return $ids;
	}

	/**
	 * Every page the design implies, each carrying its starting template.
	 *
	 * The seed picks a first slug and a first template assignment; both are
	 * David's from then on. That is not a contradiction of CLAUDE.md section 5.1
	 * — nothing here registers a route, branches on a slug, or reads a page back
	 * by name. `_wp_page_template` is a per-page value the Page Attributes panel
	 * writes, and writing it once is the same act as choosing it in the admin. He
	 * re-slugs, renames or re-assigns any of them and nothing in either package
	 * notices.
	 *
	 * It is written as meta rather than through `wp_update_post()` deliberately:
	 * that path validates against `wp_get_theme()->get_page_templates()`, which
	 * makes the assignment fail silently while a theme is being switched or a
	 * template renamed. A template that has gone simply falls back through the
	 * hierarchy, which is the visible failure and the better one.
	 *
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_pages(): array {
		$ids   = array();
		$order = 0;

		foreach ( $this->fixture->pages() as $page ) {
			++$order;

			$post_id = $this->upsert_post(
				'page:' . $page['key'],
				'page',
				$page['title'],
				'publish',
				$order,
				array(
					'dp_lead'    => $page['deck'],
					'dp_updated' => $page['updated'],
				),
				slug: $page['slug'],
				content: $this->markup->render( $page['body'] )
			);

			if ( '' === $page['template'] ) {
				delete_post_meta( $post_id, '_wp_page_template' );
			} else {
				update_post_meta( $post_id, '_wp_page_template', $page['template'] );
			}

			$ids[ $page['key'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * Settings: the URL shape, Reading, and the privacy page.
	 *
	 * The theme ships both a `front-page` template and a `home` one, which is the
	 * design's own shape: a landing page, and a separate index of the writing.
	 * Neither is reachable until Reading says so, and `page_for_posts` does
	 * nothing at all while `show_on_front` is `posts` — so the three move
	 * together or not at all.
	 *
	 * These do overwrite. `seed_brand()` deliberately never replaces a logo David
	 * chose, because a logo is a preference; a front page pointing at a page this
	 * run has just re-created is not a preference, it is the wiring that makes
	 * the run's own output reachable. `wipe()` puts all three back.
	 *
	 * **The permalink structure is the exception, and it is set only when there
	 * is none.** A fresh install has an empty `permalink_structure`, and under
	 * plain permalinks *no rewrite rule exists at all* — so every path the design
	 * draws is a 404 and `dp_series`' rewrite slug, the one registered
	 * page-facing route in this project, is simply not there. A seeded site is
	 * supposed to match the design's fixtures and the design's URLs are paths, so
	 * an empty structure is filled in. A structure David already chose is left
	 * alone whatever it is: any non-empty structure gives the rewrite rules the
	 * routes need, and replacing, say, a dated structure would invalidate every
	 * URL on the site to gain nothing.
	 *
	 * It has to happen before the chrome links are built, because those are
	 * `get_permalink()` calls and a permalink is whatever the structure says it
	 * is at the moment it is asked. That is the same mistake this method's own
	 * first version made in its verification: a sweep driven by `get_permalink()`
	 * measures the URL WordPress currently generates, not the URL the design
	 * specifies, and `?page_id=47` resolves perfectly well.
	 *
	 * @param array<string, int> $pages Fixture key to post ID.
	 * @return int How many settings this run is now responsible for.
	 */
	private function seed_settings( array $pages ): int {
		$front   = $pages['home'] ?? 0;
		$posts   = $pages['posts'] ?? 0;
		$privacy = $pages['privacy'] ?? 0;
		$pointed = $this->seed_permalinks();

		if ( $front > 0 && $posts > 0 ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front );
			update_option( 'page_for_posts', $posts );

			$pointed += 2;
		}

		if ( $privacy > 0 ) {
			update_option( 'wp_page_for_privacy_policy', $privacy );

			++$pointed;
		}

		return $pointed;
	}

	/**
	 * Give a site with no permalink structure the one the design's URLs imply.
	 *
	 * **Setting the option is not enough, and this is the part that is easy to
	 * get wrong.** `register_taxonomy()` adds a permastruct only when
	 * `is_admin()` or there is already a permalink structure:
	 *
	 *     if ( false !== $args['rewrite'] && ( is_admin() || get_option( 'permalink_structure' ) ) )
	 *
	 * Under WP-CLI neither is true on a fresh install, so every taxonomy
	 * registered on `init` — core's `category` and `post_tag`, and this plugin's
	 * `dp_series` — has **no permastruct at all** by the time this runs. Change
	 * the option now and two things go wrong at once: `get_term_link()` keeps
	 * returning `?dp_series=life-story`, which is what the chrome links a few
	 * steps later would be built from and saved with; and a flush at this moment
	 * would write a rule set with no taxonomy rules in it, which
	 * `WP_Rewrite::wp_rewrite_rules()` then serves from the option forever
	 * because it only regenerates when that option is *empty*. A site that 404s
	 * on every archive, cached.
	 *
	 * So the registrations are re-run first. `create_initial_taxonomies()` is
	 * core's own function for it and is what `init` calls at priority 0; running
	 * it again resets `category` and `post_tag` to their declared arguments,
	 * which is a side effect worth naming, and is the reason this happens only on
	 * a site that has no structure to begin with. `ContentModel::register()` does
	 * the same for ours and is idempotent by design.
	 *
	 * Then the flush, and **soft**. The rewrite rules are site data and this
	 * writes them; the `.htaccess` that tells Apache to send the request to
	 * WordPress at all is server configuration and this does not. It cannot
	 * honestly write one anyway — `got_mod_rewrite()` is false under WP-CLI
	 * because there is no server to ask, so a hard flush silently does nothing —
	 * and a plugin that asserted `got_rewrite` to get past that would be a plugin
	 * writing Apache config into a site root on a guess. `.wp-env.json` does that
	 * part for the development environment, through the `apache_modules` key
	 * WP-CLI reads and wp-env already ships for exactly this.
	 *
	 * @return int 1 if this run set the structure, 0 if there already was one.
	 */
	private function seed_permalinks(): int {
		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return 0;
		}

		$stored = get_option( 'permalink_structure' );

		if ( is_string( $stored ) && '' !== $stored ) {
			return 0;
		}

		$wp_rewrite->set_permalink_structure( $this->fixture->permalink_structure() );

		create_initial_taxonomies();
		ContentModel::create()->register();

		$wp_rewrite->flush_rules( false );

		$this->index['settings']['permalink_structure'] = $this->fixture->permalink_structure();

		return 1;
	}

	/**
	 * Ask the theme to link its own chrome, and save what comes back.
	 *
	 * Since ADR-0018 the shipped templates carry the design's words and no URLs,
	 * and a fresh install has buttons that go nowhere until David links them. On
	 * a real site that is a one-time setup pass. On a seeded one it means `wp dp
	 * seed` produces a site nobody can navigate, so this closes the gap the same
	 * way David would: by saving a `wp_template` or `wp_template_part` post with
	 * the hrefs in it. Nothing is computed at request time and no filter touches a
	 * rendered button.
	 *
	 * Two rules, and the second is the one that is easy to get wrong.
	 *
	 * **Regenerate, never patch.** Every override this script wrote is deleted
	 * before any is written, and the theme builds each one from the file it
	 * currently ships. A stored override beats the theme's file forever, so an
	 * override kept across releases silently freezes that template at whatever
	 * the theme looked like the day it was first seeded — a bug this project has
	 * had, where a stale `home` override went on rendering a block the theme had
	 * already replaced.
	 *
	 * **Only ever its own.** Deletion is scoped by the `CHROME_MARK` meta, so an
	 * override David saved from the site editor is not touched — not on a normal
	 * run, not on `--fresh`. It does mean a re-seed discards edits made to the
	 * five chrome templates, which is the right trade on a seeded development
	 * site and would not be on a real one.
	 *
	 * @param array<string, int> $pages  Fixture key to post ID.
	 * @param int                $series The design's series term, for the one
	 *                                   chrome link that points at an archive.
	 * @return int How many overrides were saved.
	 */
	private function seed_chrome_links( array $pages, int $series ): int {
		$this->clear_chrome_links();

		$destinations = array();

		foreach ( $pages as $key => $page_id ) {
			$url = get_permalink( $page_id );

			if ( is_string( $url ) && '' !== $url ) {
				$destinations[ $key ] = $url;
			}
		}

		if ( $series > 0 ) {
			$archive = get_term_link( $series, Taxonomies::SERIES );

			if ( is_string( $archive ) && '' !== $archive ) {
				$destinations['series'] = $archive;
			}
		}

		$saved = 0;

		foreach ( $this->links->collect( $destinations ) as $override ) {
			if ( $this->save_override( $override ) > 0 ) {
				++$saved;
			}
		}

		return $saved;
	}

	/**
	 * Delete every template override a previous run wrote, and forget them.
	 *
	 * Found by the mark rather than by the index, so an override survives neither
	 * a lost index nor a half-finished run. Nothing without the mark is looked at.
	 *
	 * @return void
	 */
	private function clear_chrome_links(): void {
		$found = get_posts(
			array(
				'post_type'   => array( 'wp_template', 'wp_template_part' ),
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',

				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one query, once, in a CLI seed; there is no request path to it.
				'meta_key'    => self::CHROME_MARK,
			)
		);

		foreach ( $found as $post_id ) {
			if ( is_numeric( $post_id ) ) {
				wp_delete_post( (int) $post_id, true );
			}
		}

		foreach ( array_keys( $this->index['posts'] ) as $key ) {
			if ( str_starts_with( $key, self::TEMPLATE_KEY ) ) {
				unset( $this->index['posts'][ $key ] );
			}
		}
	}

	/**
	 * Save one override exactly as the site editor would.
	 *
	 * The two terms are not decoration. `wp_theme` is what makes
	 * `get_block_templates()` prefer this post over the theme's file at all, and
	 * a template part's `wp_template_part_area` is what core turns into the
	 * wrapping `<header>` or `<footer>` — a part saved without it renders inside
	 * a `<div>`, which is a landmark lost without a word.
	 *
	 * If David has already saved an override under the same slug, WordPress
	 * suffixes this one's `post_name` and it is never resolved: his edit wins and
	 * nothing is destroyed, which is the failure this should have.
	 *
	 * @param array{type: string, slug: string, title: string, area: string, content: string} $override What the theme handed back.
	 * @return int The post ID, or 0 when WordPress refused the write.
	 */
	private function save_override( array $override ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $override['type'],
				'post_name'    => $override['slug'],
				'post_title'   => $override['title'],
				'post_status'  => 'publish',
				'post_author'  => $this->author(),
				'post_content' => $override['content'],
			),
			true
		);

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}

		wp_set_post_terms( $post_id, array( get_stylesheet() ), 'wp_theme' );

		if ( 'wp_template_part' === $override['type'] && '' !== $override['area'] ) {
			wp_set_post_terms( $post_id, array( $override['area'] ), 'wp_template_part_area' );
		}

		update_post_meta( $post_id, self::CHROME_MARK, '1' );

		$this->index['posts'][ self::TEMPLATE_KEY . $override['type'] . ':' . $override['slug'] ] = $post_id;

		return $post_id;
	}

	/**
	 * Give the permalink structure back, if this script is the one that set it.
	 *
	 * The same rule as the three settings above and as the site logo: what was
	 * changed is undone, what somebody else chose is left. The difference is what
	 * counts as proof. Those three hold a post ID, and the index says whether the
	 * post is ours; this holds a string, and "it matches the fixture" is not the
	 * same claim as "we wrote it" — `.wp-env.json` sets this very value at
	 * `wp-env start`, so matching on the value alone had `--fresh` quietly
	 * clearing the environment's own work and putting it back a moment later.
	 *
	 * So the index records the write, and only a recorded write is undone.
	 *
	 * @return void
	 */
	private function release_permalinks(): void {
		global $wp_rewrite;

		$written = $this->index['settings']['permalink_structure'] ?? null;

		if ( ! $wp_rewrite instanceof WP_Rewrite || null === $written ) {
			return;
		}

		unset( $this->index['settings']['permalink_structure'] );

		if ( get_option( 'permalink_structure' ) !== $written ) {
			return;
		}

		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules( false );
	}

	/**
	 * Clear a setting, but only where it points at a page this script wrote.
	 *
	 * @param string $option The option name.
	 * @param string $key    The fixture key the page is indexed under.
	 * @return bool Whether the setting was pointing at one of ours.
	 */
	private function release_setting( string $option, string $key ): bool {
		$mine   = $this->index['posts'][ $key ] ?? 0;
		$stored = get_option( $option );

		if ( $mine <= 0 || ! is_numeric( $stored ) || (int) $stored !== $mine ) {
			return false;
		}

		update_option( $option, 0 );

		return true;
	}

	/**
	 * One attachment, captioned, used as every seeded post's lead image.
	 *
	 * The design's post view draws a `<figure>` with a 16/9 picture and a mono
	 * caps caption under it, and neither half of that renders on a post with no
	 * featured image — so the whole path went unreviewed on a seeded site.
	 *
	 * The picture is the theme's own monogram, which is the only image this
	 * repository ships and is obviously not a photograph. That is the point: a
	 * seeded lead image should look like a placeholder, and inventing photographs
	 * for David's posts is the same mistake as inventing his copy.
	 *
	 * The attachment carries a caption of its own, and after ADR-0016 that is the
	 * only place a lead image's caption comes from: the media library captions the
	 * file once, and every post using it prints the same line. On a seeded site
	 * that means one caption on every post, which is exactly what one attachment
	 * shared by every post should look like.
	 *
	 * The file arrives through `dp_seed_lead_image_path`, the same seam
	 * `dp_brand_logo_path` is: CLAUDE.md section 5.1's rule — the plugin does not
	 * reach into the theme — applies to assets as much as to routes. With no
	 * theme answering, posts get no lead image and everything else seeds
	 * normally.
	 *
	 * @return int The attachment ID, or 0 when there is nothing to attach.
	 */
	private function lead_image(): int {
		/**
		 * Filters the absolute path to the image seeded posts use as a lead image.
		 *
		 * @since 0.1.0
		 *
		 * @param string $path Absolute path to an image file, or '' for none.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$path = apply_filters( 'dp_seed_lead_image_path', '' );

		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return 0;
		}

		$attachment_id = $this->attachment( 'attachment:lead-image', $path );

		if ( $attachment_id > 0 ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => 'PLACEHOLDER — THE THEME\'S OWN MARK, STANDING IN FOR A LEAD IMAGE',
				)
			);
		}

		return $attachment_id;
	}

	/**
	 * The site logo, so a fresh site is not missing its brand mark.
	 *
	 * The mark used to be a `background: url()` in the theme's stylesheet, which
	 * meant it could only be changed by editing CSS and shipping a release. The
	 * chrome now renders `core/site-logo` in all three places, so David swaps it
	 * from the admin — and something has to put a first one there.
	 *
	 * Two rules govern what this does, and both are about not overreaching.
	 * **It never replaces a logo.** If `site_logo` already points at a real
	 * attachment, that is David's decision and this leaves it alone, on this run
	 * and on every run after it. **It does not know where the file is.** The
	 * mark ships with the theme and CLAUDE.md section 5.1's spirit — the plugin
	 * does not reach into the theme — applies to assets as much as to routes, so
	 * the path arrives through `dp_brand_logo_path`, which the theme answers.
	 * With the theme switched off nothing answers and nothing happens.
	 *
	 * @return int 1 if the logo was set by this run, 0 otherwise.
	 */
	private function seed_brand(): int {
		/**
		 * Filters the absolute path to the brand mark a fresh site starts with.
		 *
		 * The plugin may not reach into the theme's directory, and the mark is
		 * the theme's asset. This is the seam, and it names no class on either
		 * side. With no theme answering, nothing is seeded.
		 *
		 * @since 0.1.0
		 *
		 * @param string $path Absolute path to an image file, or '' for none.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$path = apply_filters( 'dp_brand_logo_path', '' );

		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return 0;
		}

		$stored = get_option( 'site_logo' );
		$chosen = is_numeric( $stored ) ? (int) $stored : 0;

		if ( $chosen > 0 && get_post( $chosen ) instanceof WP_Post ) {
			return 0;
		}

		$attachment_id = $this->attachment( 'attachment:brand-mark', $path );

		if ( 0 === $attachment_id ) {
			return 0;
		}

		update_option( 'site_logo', $attachment_id );

		return 1;
	}

	/**
	 * An attachment made from a file the theme ships, made once and reused after.
	 *
	 * @param string $key  The fixture key it is recorded against.
	 * @param string $path Absolute path to the image the theme ships.
	 * @return int The attachment ID, or 0 when WordPress refused to make one.
	 */
	private function attachment( string $key, string $path ): int {
		$existing = $this->index['posts'][ $key ] ?? 0;

		if ( $existing > 0 && get_post( $existing ) instanceof WP_Post ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return 0;
		}

		$contents = $wp_filesystem->get_contents( $path );

		if ( ! is_string( $contents ) || '' === $contents ) {
			return 0;
		}

		$name = basename( $path );

		if ( '' === $name ) {
			return 0;
		}

		$upload = wp_upload_bits( $name, null, $contents );

		if ( ! is_string( $upload['file'] ?? null ) || ( $upload['error'] ?? false ) ) {
			return 0;
		}

		$type          = wp_check_filetype( $upload['file'] );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => is_string( $type['type'] ) ? $type['type'] : 'image/png',
				'post_title'     => get_bloginfo( 'name' ),
				'post_status'    => 'inherit',
				'post_author'    => $this->author(),
			),
			$upload['file'],
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) || $attachment_id <= 0 ) {
			return 0;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);

		/*
		 * Core falls back to the site's name when an attachment has no alt
		 * text, so this is belt and braces — but the mark *is* the site's name
		 * in the header, and a nameless link there is the one accessibility
		 * failure this change could introduce.
		 */
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', get_bloginfo( 'name' ) );

		$this->index['posts'][ $key ] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Create or update the post behind a fixture key.
	 *
	 * Takes the fields rather than an array so the argument list handed to
	 * WordPress is built here, as a literal, and can be checked against the shape
	 * `wp_insert_post()` declares instead of against `array<string, mixed>`.
	 *
	 * @param string                                            $key        The fixture key.
	 * @param non-empty-string                                  $post_type  Post type.
	 * @param string                                            $title      Post title.
	 * @param string                                            $status     Post status.
	 * @param int                                               $menu_order Ordering.
	 * @param array<string, string|int|float|bool|list<string>> $meta       Meta to write.
	 * @param string                                            $slug       Post slug, or empty to derive one.
	 * @param string                                            $excerpt    Post excerpt.
	 * @param string                                            $content    Post content, as block markup.
	 * @param string                                            $date       Publication date, or empty for now.
	 * @return int The post ID.
	 *
	 * @throws RuntimeException When WordPress refuses the write.
	 */
	private function upsert_post(
		string $key,
		string $post_type,
		string $title,
		string $status,
		int $menu_order,
		array $meta,
		string $slug = '',
		string $excerpt = '',
		string $content = '',
		string $date = ''
	): int {
		$existing = $this->index['posts'][ $key ] ?? 0;

		$postarr = array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_status'  => $status,
			'post_author'  => $this->author(),
			'menu_order'   => $menu_order,
			'post_name'    => $slug,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
		);

		if ( '' !== $date ) {
			$postarr['post_date']     = $date;
			$postarr['post_date_gmt'] = $date;
		}

		/*
		 * `wp_insert_post()` and `wp_update_post()` both expect slashed data and
		 * both call `wp_unslash()` on the way to the database. This script was
		 * passing it unslashed, and nothing in today's fixture contains a
		 * backslash, so it round-tripped exactly and the omission cost nothing.
		 *
		 * It is here because the first value that *did* contain one lost it
		 * silently, with no error anywhere: a block comment's attributes are
		 * JSON, `wp_json_encode()` writes a quotation mark as `\u0022`, and every
		 * attribute holding a quoted example arrived a backslash short and read
		 * `u0022` in the editor. The value that found it is written elsewhere
		 * now; the contract is the same for everything written here.
		 */
		$postarr = wp_slash( $postarr );

		if ( $existing > 0 && get_post( $existing ) instanceof WP_Post ) {
			$postarr['ID'] = $existing;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				esc_html( sprintf( 'Could not write "%s": %s', $key, $result->get_error_message() ) )
			);
		}

		$this->index['posts'][ $key ] = $result;

		foreach ( $meta as $meta_key => $value ) {
			update_post_meta( $result, $meta_key, $value );
		}

		return $result;
	}

	/**
	 * Create or update the term behind a fixture key.
	 *
	 * @param string $key         The fixture key.
	 * @param string $taxonomy    The taxonomy.
	 * @param string $name        Term name.
	 * @param string $slug        Term slug.
	 * @param string $description Term description.
	 * @return int The term ID.
	 *
	 * @throws RuntimeException When WordPress refuses the write.
	 */
	private function upsert_term( string $key, string $taxonomy, string $name, string $slug, string $description ): int {
		$existing = $this->index['terms'][ $key ] ?? 0;

		if ( 0 === $existing || ! get_term( $existing, $taxonomy ) instanceof WP_Term ) {
			$found = get_term_by( 'slug', $slug, $taxonomy );

			if ( $found instanceof WP_Term ) {
				$existing = $found->term_id;
			}
		}

		if ( $existing > 0 ) {
			$result = wp_update_term(
				$existing,
				$taxonomy,
				array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $description,
				)
			);
		} else {
			$result = wp_insert_term(
				$name,
				$taxonomy,
				array(
					'slug'        => $slug,
					'description' => $description,
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				esc_html( sprintf( 'Could not write the "%s" term: %s', $key, $result->get_error_message() ) )
			);
		}

		$term_id                      = (int) $result['term_id'];
		$this->index['terms'][ $key ] = $term_id;

		return $term_id;
	}

	/**
	 * Who the seeded content belongs to.
	 *
	 * The acting user under WP-CLI is often nobody, and a post with author 0 is a
	 * post the admin list table cannot show properly. The first administrator is
	 * the only defensible guess, and 1 is the fallback the installer guarantees.
	 *
	 * @return int
	 */
	private function author(): int {
		$current = get_current_user_id();

		if ( $current > 0 ) {
			return $current;
		}

		$administrators = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		$first = reset( $administrators );

		return is_numeric( $first ) ? (int) $first : 1;
	}

	/**
	 * Read the index of what previous runs created.
	 *
	 * @return void
	 */
	private function load_index(): void {
		$stored = get_option( self::INDEX_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$this->index = array(
			'posts'    => $this->int_map( $stored['posts'] ?? array() ),
			'terms'    => $this->int_map( $stored['terms'] ?? array() ),
			'settings' => $this->string_map( $stored['settings'] ?? array() ),
		);
	}

	/**
	 * Write the index back.
	 *
	 * Not autoloaded: it is read by one command and by nothing on the front end.
	 *
	 * @return void
	 */
	private function save_index(): void {
		update_option( self::INDEX_OPTION, $this->index, false );
	}

	/**
	 * Coerce the settings half of a stored index into the shape assumed here.
	 *
	 * @param mixed $stored Whatever was in the option.
	 * @return array<string, string>
	 */
	private function string_map( mixed $stored ): array {
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$map = array();

		foreach ( $stored as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) ) {
				$map[ $key ] = $value;
			}
		}

		return $map;
	}

	/**
	 * Coerce a stored index half into the shape the rest of the class assumes.
	 *
	 * @param mixed $stored Whatever was in the option.
	 * @return array<string, int>
	 */
	private function int_map( mixed $stored ): array {
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$map = array();

		foreach ( $stored as $key => $value ) {
			if ( is_string( $key ) && is_numeric( $value ) ) {
				$map[ $key ] = (int) $value;
			}
		}

		return $map;
	}
}
