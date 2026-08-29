<?php
/**
 * Where the blog is, according to WordPress rather than to this theme.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use WP_Post;

/**
 * Settings → Reading, read once and asked twice.
 *
 * This class used to resolve twelve destinations: a Reading setting, the privacy
 * page, the longest series, and the page carrying each of this theme's `dp-`
 * custom templates — the last of which it cached in a transient. ADR-0018
 * deletes all of that. A link to a page David made is a link he sets in the site
 * editor; the theme no longer decides which page is which, so there is no map to
 * build, nothing to cache, and no cache to go stale. (The stale one cost a day:
 * `Navigation`'s own docblock recorded it.)
 *
 * What is left is the one lookup that was never about a page this theme
 * nominated. **The posts index is a Reading setting.** It is where WordPress
 * itself records which page David chose, it is the only correct answer to "where
 * is the blog", and it is not something an author can usefully type into a link
 * — the answer changes with a setting rather than with an edit. Two callers
 * need it: `Navigation`, to mark the blog active and to answer `dp-core`'s
 * `dp_destination_url`, and `DP\Theme\Blocks\FilterPills`, for the All pill.
 *
 * Neither answer is memoised. Both are `get_option()` plus `get_post()`, which
 * are served from the options cache and the post cache, and the transient this
 * class used to keep existed for a `meta_key` query that is gone.
 */
final class Destinations {

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
}
