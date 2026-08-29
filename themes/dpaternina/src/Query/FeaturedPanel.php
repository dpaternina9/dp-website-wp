<?php
/**
 * The blog index's featured panel, and the one thing it costs the list below it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Query;

use WP_Block;
use WP_Block_Template;
use WP_Query;

/**
 * Holds back exactly the posts the featured panel is about to show.
 *
 * The design's blog index opens on a post in a panel of its own and then lists
 * everything else: `POSTS.filter(p => p.slug !== POSTS[0].slug)`. The panel is
 * its own query block, the list inherits the main query, and `core/post-template`
 * with `inherit` reads the global `WP_Query` directly — so the only place the
 * list can be narrowed is `pre_get_posts`, before anything has rendered.
 *
 * **This used to be a coincidence, and that is the whole of what changed.** The
 * first version ran its own `WP_Query` for "the newest published post" and set
 * `post__not_in` to whatever came back. Nothing connected that to the panel: the
 * two happened to agree because they had been written to the same sentence on
 * the same afternoon. Edit the panel in the site editor — a different `orderBy`,
 * `perPage: 2`, a category filter — and one post disappeared from the index
 * entirely while another rendered twice, with nothing in the markup, the editor
 * or the CSS saying why. It also fired on `is_home()` alone, so it ran on any
 * template answering the posts index, including `front-page.html`, which has no
 * panel to hold anything back for.
 *
 * So the panel **names itself** — `dpLoop: featured`, the same attribute every
 * other loop in this theme uses (`DP\Theme\Query\QueryLoops`) — and the
 * exclusion is derived from that block rather than from a second guess at what
 * it will contain. The template that is about to answer is parsed, the block
 * carrying the name is found, and its query vars are built by
 * **core's own `build_query_vars_from_query_block()`**, so `perPage`, `orderBy`,
 * `order`, `offset`, `taxQuery`, `author` and `sticky` all mean here exactly what
 * they mean when the panel renders a moment later. What the panel will select is
 * what the list holds back, by construction. Change one in the editor and the
 * other moves with it.
 *
 * Three things are deliberately narrow.
 *
 * **`post__not_in` rather than `offset`.** An offset on the main query is the
 * well-known way to break pagination — WordPress computes the page's offset
 * itself and the two do not compose — whereas excluding IDs leaves `found_posts`,
 * the page links and Settings → Reading all correct.
 *
 * **Feeds are left alone.** A reader subscribing to the blog should not silently
 * lose its most recent entry because a panel on a web page is showing it.
 *
 * **A loop that inherits the main query is never treated as the panel.** It would
 * be asking to exclude the archive from itself.
 *
 * The template is looked up rather than the panel being asked, because
 * `pre_get_posts` runs inside `wp()` and template resolution happens afterwards
 * in `template-loader.php`; there is no hook between the two. The candidates are
 * core's own home hierarchy and nothing else, and `get_block_template()` returns
 * David's saved edit in preference to the theme's file — which is what makes an
 * edit in the site editor the thing this reads.
 */
final class FeaturedPanel {

	/**
	 * How deep the walk may go looking for the panel.
	 *
	 * A template is a handful of nested groups. The bound is here so a
	 * pathological saved template cannot turn a page load into a stack overflow.
	 */
	private const MAX_DEPTH = 12;

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_posts', $this->hold_back_what_the_panel_shows( ... ) );
	}

	/**
	 * Keep the posts the featured panel is about to draw out of the list below it.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return void
	 */
	public function hold_back_what_the_panel_shows( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() || $query->is_feed() ) {
			return;
		}

		$featured = $this->selection( $query );

		if ( array() === $featured ) {
			return;
		}

		$already  = $query->get( 'post__not_in' );
		$excluded = $featured;

		if ( is_array( $already ) ) {
			foreach ( $already as $id ) {
				if ( is_numeric( $id ) ) {
					$excluded[] = (int) $id;
				}
			}
		}

		$query->set( 'post__not_in', array_values( array_unique( $excluded ) ) );
	}

	/**
	 * The posts the panel on the answering template will show.
	 *
	 * @param WP_Query $query The main query.
	 * @return list<int> Empty when this request's template has no featured panel.
	 */
	private function selection( WP_Query $query ): array {
		$panel = $this->panel( $query );

		if ( null === $panel ) {
			return array();
		}

		$attributes = $panel['attrs'] ?? array();
		$context    = is_array( $attributes ) ? ( $attributes['query'] ?? array() ) : array();

		if ( ! is_array( $context ) ) {
			return array();
		}

		$query_id = is_array( $attributes ) && is_numeric( $attributes['queryId'] ?? null )
			? (int) $attributes['queryId']
			: 0;

		$arguments = build_query_vars_from_query_block(
			$this->as_instance( $context, $query_id ),
			$this->page( $query_id )
		);

		$arguments['fields']                 = 'ids';
		$arguments['no_found_rows']          = true;
		$arguments['update_post_meta_cache'] = false;
		$arguments['update_post_term_cache'] = false;

		$found = new WP_Query( $arguments );
		$ids   = array();

		foreach ( $found->posts as $id ) {
			if ( is_int( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The featured query block in the template about to answer this request.
	 *
	 * The **first** candidate that exists is the one WordPress will render, so
	 * the walk stops there whether or not it found a panel: a `front-page.html`
	 * with no panel must hold nothing back, not fall through to `home.html`'s.
	 *
	 * @param WP_Query $query The main query.
	 * @return array<mixed>|null The parsed block, or null.
	 */
	private function panel( WP_Query $query ): ?array {
		foreach ( $this->candidates( $query ) as $slug ) {
			$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );

			if ( ! $template instanceof WP_Block_Template ) {
				continue;
			}

			return $this->find( parse_blocks( (string) $template->content ), 0 );
		}

		return null;
	}

	/**
	 * The block templates WordPress will consider for a posts index, in its order.
	 *
	 * Core's `template-loader.php` asks `get_front_page_template()` first and
	 * only when the request is the front page, then `get_home_template()`, whose
	 * hierarchy is `home` then `index`. That is the whole list; a theme cannot
	 * add to it.
	 *
	 * @param WP_Query $query The main query.
	 * @return list<string>
	 */
	private function candidates( WP_Query $query ): array {
		$slugs = array( 'home', 'index' );

		if ( $query->is_front_page() ) {
			array_unshift( $slugs, 'front-page' );
		}

		return $slugs;
	}

	/**
	 * Walk parsed blocks for the one naming itself the featured loop.
	 *
	 * Inner blocks only. A `core/pattern` reference is not expanded, and
	 * `DP\Tests\Integration\Templates\HomeTest` holds the theme to declaring the
	 * panel in a template rather than in a pattern, so there is nothing to
	 * expand it for.
	 *
	 * @param array<mixed> $blocks The parsed blocks.
	 * @param int          $depth  How deep this call is.
	 * @return array<mixed>|null
	 */
	private function find( array $blocks, int $depth ): ?array {
		if ( $depth > self::MAX_DEPTH ) {
			return null;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( $this->is_the_panel( $block ) ) {
				return $block;
			}

			$inner = $block['innerBlocks'] ?? array();
			$found = is_array( $inner ) ? $this->find( $inner, $depth + 1 ) : null;

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Whether one parsed block is the featured panel's query.
	 *
	 * @param array<mixed> $block The parsed block.
	 * @return bool
	 */
	private function is_the_panel( array $block ): bool {
		if ( 'core/query' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		$attributes = $block['attrs'] ?? array();
		$context    = is_array( $attributes ) ? ( $attributes['query'] ?? array() ) : array();

		if ( ! is_array( $context ) || true === ( $context['inherit'] ?? false ) ) {
			return false;
		}

		return QueryLoops::FEATURED === ( $context[ QueryLoops::KEY ] ?? '' );
	}

	/**
	 * A block instance carrying the panel's context, for core to read.
	 *
	 * `build_query_vars_from_query_block()` reads `$block->context['query']`, so
	 * the cheapest honest way to ask it what the panel will select is to hand it
	 * a `core/post-template` holding the panel's own context — which is exactly
	 * what the real render will hand it. Nothing is rendered.
	 *
	 * @param array<mixed> $context  The panel's `query` attribute.
	 * @param int          $query_id The panel's `queryId` attribute.
	 * @return WP_Block
	 */
	private function as_instance( array $context, int $query_id ): WP_Block {
		return new WP_Block(
			array(
				'blockName'    => 'core/post-template',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'query'   => $context,
				'queryId' => $query_id,
			)
		);
	}

	/**
	 * Which page of the panel is being asked for.
	 *
	 * The same variable core reads in `render_block_core_post_template()`, read
	 * the same way, so a paginated panel excludes the page it is showing rather
	 * than the first one.
	 *
	 * @param int $query_id The panel's `queryId`.
	 * @return int
	 */
	private function page( int $query_id ): int {
		$key = 'query-' . $query_id . '-page';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a pagination variable, read exactly where core reads it, and cast to an integer below.
		$raw = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';

		return is_numeric( $raw ) ? max( 1, (int) $raw ) : 1;
	}
}
