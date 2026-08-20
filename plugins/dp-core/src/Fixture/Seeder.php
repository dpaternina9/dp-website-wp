<?php
/**
 * Writes the fixture into a site.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

use DP\Core\Content\PostTypes;
use DP\Core\Content\Taxonomies;
use RuntimeException;
use WP_Post;
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
	 * The index, as loaded and then updated.
	 *
	 * @var array{posts: array<string, int>, terms: array<string, int>}
	 */
	private array $index = array(
		'posts' => array(),
		'terms' => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param Fixture     $fixture The design's data.
	 * @param BlockMarkup $markup  Converts fixture bodies into block markup.
	 */
	public function __construct(
		private readonly Fixture $fixture,
		private readonly BlockMarkup $markup
	) {}

	/**
	 * Build the seeder with its default collaborators.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self( new Fixture(), new BlockMarkup() );
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
			$roles      = $this->seed_roles();
			$posts      = $this->seed_posts( $categories, $series );
			$planned    = $this->seed_planned_parts( $series );
			$pages      = $this->seed_pages();
			$ships      = $this->seed_ships( $roles, $posts );
			$videos     = $this->seed_videos();
		} finally {
			kses_init();
			$this->save_index();
		}

		return new SeedReport(
			array(
				'categories'    => count( $categories ),
				'series'        => $series > 0 ? 1 : 0,
				'roles'         => count( $roles ),
				'shipped'       => count( $ships ),
				'videos'        => count( $videos ),
				'posts'         => count( $posts ),
				'planned_parts' => count( $planned ),
				'pages'         => count( $pages ),
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
			'posts' => array(),
			'terms' => array(),
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
	 * @return int Term ID.
	 */
	private function seed_series(): int {
		$series  = $this->fixture->series();
		$term_id = $this->upsert_term(
			'series:' . $series['slug'],
			Taxonomies::SERIES,
			$series['title'],
			$series['slug'],
			''
		);

		update_term_meta( $term_id, 'dp_series_deck', $series['deck'] );

		return $term_id;
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
	 * The seven sample posts.
	 *
	 * @param array<string, int> $categories Slug to term ID.
	 * @param int                $series     The series term ID.
	 * @return array<string, int> Post slug to post ID.
	 */
	private function seed_posts( array $categories, int $series ): array {
		$ids = array();

		foreach ( $this->fixture->posts() as $post ) {
			$post_id = $this->upsert_post(
				'post:' . $post['slug'],
				'post',
				$post['title'],
				'publish',
				$post['part'],
				array(
					'dp_lead'         => $post['lead'],
					'dp_read_time'    => $post['read_time'],
					'dp_hero_caption' => $post['caption'],
					'dp_tone'         => $post['tone'],
					'dp_series_part'  => $post['part'],
				),
				slug: $post['slug'],
				excerpt: $post['excerpt'],
				content: $this->markup->render( $post['body'] ),
				date: $post['date']
			);

			$category_id = $categories[ $post['category'] ] ?? 0;

			if ( $category_id > 0 ) {
				wp_set_post_terms( $post_id, array( $category_id ), 'category', false );
			}

			if ( $post['part'] > 0 && $series > 0 ) {
				wp_set_post_terms( $post_id, array( $series ), Taxonomies::SERIES, false );
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
	 * @param int $series The series term ID.
	 * @return array<string, int> Fixture key to post ID.
	 */
	private function seed_planned_parts( int $series ): array {
		$ids = array();

		foreach ( $this->fixture->planned_parts() as $part ) {
			$post_id = $this->upsert_post(
				'planned:' . $part['key'],
				'post',
				$part['title'],
				'draft',
				$part['part'],
				array(
					'dp_series_part'  => $part['part'],
					'dp_series_years' => $part['years'],
					'dp_series_note'  => $part['note'],
					'dp_tone'         => 'pink',
				)
			);

			if ( $series > 0 ) {
				wp_set_post_terms( $post_id, array( $series ), Taxonomies::SERIES, false );
			}

			$ids[ $part['key'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * Uses, Colophon and Privacy.
	 *
	 * They are seeded as ordinary pages with ordinary slugs and no template
	 * assigned. Which page is which, where it lives, and what template it uses is
	 * David's (CLAUDE.md section 5.1) — the seed supplies content, not routing.
	 *
	 * @return array<string, int> Slug to post ID.
	 */
	private function seed_pages(): array {
		$ids = array();

		foreach ( $this->fixture->pages() as $page ) {
			$ids[ $page['slug'] ] = $this->upsert_post(
				'page:' . $page['slug'],
				'page',
				$page['title'],
				'publish',
				0,
				array(
					'dp_lead'    => $page['deck'],
					'dp_updated' => $page['updated'],
				),
				slug: $page['slug'],
				content: $this->markup->render( $page['body'] )
			);
		}

		return $ids;
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
			'posts' => $this->int_map( $stored['posts'] ?? array() ),
			'terms' => $this->int_map( $stored['terms'] ?? array() ),
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
