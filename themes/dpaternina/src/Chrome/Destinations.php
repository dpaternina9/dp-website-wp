<?php
/**
 * Where the chrome's links point, without ever naming a slug.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_Post;
use WP_Query;

/**
 * Resolves the two destinations the header and the footer need.
 *
 * CLAUDE.md section 5.1 is the whole reason this class exists. The theme may not
 * hardcode an href, may not look a page up by its path, and may not assume which
 * page is which. The two things it *is* allowed to read are named there:
 *
 * - **Settings to Reading**, for the posts index. That is where WordPress records
 *   which page David chose, and it is the only correct answer to "where is the
 *   blog" — whether he called it `/blog`, `/writing`, or never made one.
 * - **The assigned template**, for the design's own pages. Section 5.1 says
 *   branch on `get_page_template_slug()`, so a page carrying `dp-contact.html`
 *   *is* the contact page, by David's decision, under any slug he likes.
 *
 * Both lookups are cached in one option-backed transient rather than run per
 * link: the header alone asks for the contact page twice, and a `meta_key`
 * query on every page load to render a button is not a trade worth making. The
 * cache is dropped whenever a page is saved, deleted, or its status changes.
 */
final class Destinations {

	/**
	 * Transient holding the template-to-page map.
	 *
	 * The `_2` is a shape version, and it is here because of a real bug. The
	 * map used to be keyed by whatever `_wp_page_template` happened to hold —
	 * `dp-work.html` on a seeded site, `dp-work` on one assigned from the
	 * admin — and when `template_pages()` started normalising the two spellings
	 * to one, every install already holding the old map went on answering
	 * "no such page" for up to a day. Nothing on the read side could tell the
	 * two shapes apart, so nothing did.
	 *
	 * A change to what this transient holds now takes the suffix with it, and
	 * the old value is left to expire rather than being read as the new one.
	 * `only_int_map()` normalises on the way out as well: between them a map
	 * written by any past version of this class either resolves correctly or is
	 * ignored, and neither outcome is a silent wrong answer.
	 */
	public const CACHE_KEY = 'dpaternina_template_pages_2';

	/**
	 * The map, once resolved, for the rest of the request. Keyed by slug.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $template_pages = null;

	/**
	 * Attach the cache invalidation.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_page', $this->forget( ... ) );
		add_action( 'deleted_post', $this->forget( ... ) );
		add_action( 'switch_theme', $this->forget( ... ) );
	}

	/**
	 * The URL of the posts index.
	 *
	 * `page_for_posts` is a Reading setting, so this is right whatever David
	 * called the page. When he has not made one, the posts index is the site
	 * root, which is what WordPress itself falls back to — see the identical
	 * derivation inside `wp_list_categories()`.
	 *
	 * @return string
	 */
	public function posts_index(): string {
		$page = $this->posts_page();

		if ( null === $page ) {
			return home_url( '/' );
		}

		$permalink = get_permalink( $page );

		return is_string( $permalink ) ? $permalink : home_url( '/' );
	}

	/**
	 * The page David assigned to the posts index, if there is one.
	 *
	 * Null covers three cases that behave identically: no page chosen, a page
	 * chosen and then trashed, and a page chosen and then unpublished. In all
	 * three the posts index is the site root, and nothing may claim otherwise.
	 *
	 * @return WP_Post|null
	 */
	public function posts_page(): ?WP_Post {
		$stored = get_option( 'page_for_posts' );
		$id     = is_numeric( $stored ) ? (int) $stored : 0;

		if ( $id <= 0 ) {
			return null;
		}

		$page = get_post( $id );

		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
			return null;
		}

		return $page;
	}

	/**
	 * The URL of the page carrying one of this theme's custom templates.
	 *
	 * @param string $template The template's slug, e.g. `dp-contact`. A `.html`
	 *                         extension is accepted and ignored.
	 * @return string|null Null when David has not assigned that template to anything.
	 */
	public function by_template( string $template ): ?string {
		$page_id = $this->id_by_template( $template );

		if ( null === $page_id ) {
			return null;
		}

		$permalink = get_permalink( $page_id );

		return is_string( $permalink ) ? $permalink : null;
	}

	/**
	 * The ID of the page carrying one of this theme's custom templates.
	 *
	 * The URL is what almost every caller wants, and `by_template()` is still
	 * the way to ask for it. This exists for the one caller that needs the page
	 * itself: the résumé's PDF link, whose URL `dp-core` builds from a page ID
	 * because the query variable behind it is the plugin's and its name is not
	 * something this theme should be writing out.
	 *
	 * @param string $template The template's slug, e.g. `dp-resume`. A `.html`
	 *                         extension is accepted and ignored.
	 * @return int|null Null when David has not assigned that template to anything.
	 */
	public function id_by_template( string $template ): ?int {
		$pages = $this->template_pages();
		$slug  = $this->slug( $template );

		return $pages[ $slug ] ?? null;
	}

	/**
	 * A template's slug, whichever of its two spellings arrived.
	 *
	 * WordPress offers a block theme's custom templates to the admin under their
	 * slugs — `dp-contact` — and that is what a page assigned from the dropdown
	 * stores. A page imported from a classic theme, or written by an older
	 * version of this code, carries the file name instead. Both mean the same
	 * template, so both resolve to the same key rather than to a silent miss.
	 *
	 * @param string $template Either spelling.
	 * @return string
	 */
	private function slug( string $template ): string {
		return str_ends_with( $template, '.html' )
			? substr( $template, 0, -5 )
			: $template;
	}

	/**
	 * Drop the cached map.
	 *
	 * @return void
	 */
	public function forget(): void {
		$this->template_pages = null;

		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Template slug to page ID, for every published page that has one.
	 *
	 * The last page to claim a template wins, deterministically: the query is
	 * ordered by ID so two pages carrying `dp-contact.html` always resolve to
	 * the same one rather than to whichever the database felt like returning.
	 *
	 * @return array<string, int>
	 */
	private function template_pages(): array {
		if ( null !== $this->template_pages ) {
			return $this->template_pages;
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			$this->template_pages = $this->only_int_map( $cached );

			return $this->template_pages;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => 100,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,

				/*
				 * The meta key alone is an EXISTS test, which is the whole
				 * filter: a page with no template assigned is not interesting.
				 * A LIKE on the value would be narrower and is deliberately not
				 * used — `WP_Meta_Query` escapes `%` inside a LIKE value, so a
				 * `dp-%` prefix pattern silently matches nothing.
				 */
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'               => '_wp_page_template',
			)
		);

		$map = array();

		foreach ( $query->posts as $id ) {
			if ( ! is_int( $id ) ) {
				continue;
			}

			$template = get_page_template_slug( $id );

			if ( is_string( $template ) && str_starts_with( $template, 'dp-' ) ) {
				$map[ $this->slug( $template ) ] = $id;
			}
		}

		set_transient( self::CACHE_KEY, $map, DAY_IN_SECONDS );

		$this->template_pages = $map;

		return $map;
	}

	/**
	 * Narrow an array read back out of the cache to the shape this class promises.
	 *
	 * The keys go through `slug()` on the way out for the same reason they go
	 * through it on the way in: a stored map is not necessarily one this
	 * version of the class wrote, and a key it cannot match is indistinguishable
	 * from a page that does not exist. Normalising here is what stops a cached
	 * `dp-work.html` from reading as "David has no work page".
	 *
	 * @param array<mixed> $stored What the transient held.
	 * @return array<string, int>
	 */
	private function only_int_map( array $stored ): array {
		$map = array();

		foreach ( $stored as $template => $id ) {
			if ( is_string( $template ) && is_int( $id ) ) {
				$map[ $this->slug( $template ) ] = $id;
			}
		}

		return $map;
	}
}
