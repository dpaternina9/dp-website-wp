<?php
/**
 * The Photos page goes stale when a photo changes. Say so.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Post;

/**
 * Tells the site's page cache that every Photos page changed, whenever a photo did.
 *
 * The wall is drawn from `dp_photo` posts, but a page cache keys the Photos
 * *page* — and saving a photo is not an edit to that page, so nothing in core
 * tells a cache plugin the page is stale. The live site showed exactly that: a
 * week-long page cache, and visitors seeing one photo out of fifty.
 *
 * So this sends the signal core sends for an edited page: `clean_post_cache()`
 * on every page carrying the `dp-photos` template. Page-cache and CDN plugins
 * listen for it (it fires the `clean_post_cache` action) and purge that page's
 * URLs. Nothing here knows which host or which plugin — that is the point.
 *
 * **What counts as a change:** a photo's status moving to or from published
 * (so publishing, unpublishing and trashing), a save of a published photo, a
 * published one being deleted, a published photo's featured image being set,
 * replaced or removed, its trips or topics being re-filed, and a trip or topic
 * itself being renamed or deleted. A draft changes nothing on the page.
 *
 * **Once per request.** A bulk import of two hundred photos is hundreds of
 * those events; each would be a purge request to a CDN. The first one marks
 * the request and `flush()` runs once, on `shutdown` — at priority 1,
 * because purge plugins (Varnish, CDN) queue what `clean_post_cache` tells
 * them and send the queue on `shutdown` at the default priority; a signal
 * after that would be queued and never sent.
 */
final class CachePurge {

	/**
	 * The object-cache group the page list lives in.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'dp_photos';

	/**
	 * The template that makes a page a Photos page.
	 *
	 * @var string
	 */
	public const TEMPLATE = 'dp-photos';

	/**
	 * Whether this request has changed a photo.
	 *
	 * @var bool
	 */
	private bool $pending = false;

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'transition_post_status', $this->on_transition( ... ), 10, 3 );
		add_action( 'save_post_' . PostType::NAME, $this->on_save( ... ), 10, 2 );
		add_action( 'deleted_post', $this->on_deleted( ... ), 10, 2 );
		add_action( 'added_post_meta', $this->on_meta( ... ), 10, 3 );
		add_action( 'updated_post_meta', $this->on_meta( ... ), 10, 3 );
		add_action( 'deleted_post_meta', $this->on_meta( ... ), 10, 3 );
		add_action( 'set_object_terms', $this->on_terms( ... ), 10, 4 );
		add_action( 'edited_term', $this->on_term( ... ), 10, 3 );
		add_action( 'delete_term', $this->on_term( ... ), 10, 3 );
		add_action( 'shutdown', $this->on_shutdown( ... ), 1 );
	}

	/**
	 * A photo entered or left a status.
	 *
	 * @param string $new_status The status now.
	 * @param string $old_status The status before.
	 * @param mixed  $post       The post.
	 * @return void
	 */
	public function on_transition( string $new_status, string $old_status, mixed $post ): void {
		if ( $post instanceof WP_Post && PostType::NAME === $post->post_type && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			$this->pending = true;
		}
	}

	/**
	 * A published photo was saved — its title, excerpt or story may have changed.
	 *
	 * @param int   $post_id The photo.
	 * @param mixed $post    The post.
	 * @return void
	 */
	public function on_save( int $post_id, mixed $post ): void {
		unset( $post_id );

		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			$this->pending = true;
		}
	}

	/**
	 * A photo was deleted outright.
	 *
	 * @param int   $post_id The post.
	 * @param mixed $post    The post, as it was.
	 * @return void
	 */
	public function on_deleted( int $post_id, mixed $post = null ): void {
		unset( $post_id );

		if ( $post instanceof WP_Post && PostType::NAME === $post->post_type && 'publish' === $post->post_status ) {
			$this->pending = true;
		}
	}

	/**
	 * A photo's featured image was set, replaced or removed.
	 *
	 * @param mixed $meta_id   Unused.
	 * @param mixed $object_id The post.
	 * @param mixed $meta_key  The key.
	 * @return void
	 */
	public function on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
		unset( $meta_id );

		if ( '_thumbnail_id' === $meta_key && is_numeric( $object_id ) && self::is_published_photo( (int) $object_id ) ) {
			$this->pending = true;
		}
	}

	/**
	 * A photo was filed under different trips or topics.
	 *
	 * @param mixed $object_id The post.
	 * @param mixed $terms     Unused.
	 * @param mixed $tt_ids    Unused.
	 * @param mixed $taxonomy  The taxonomy.
	 * @return void
	 */
	public function on_terms( mixed $object_id, mixed $terms, mixed $tt_ids, mixed $taxonomy ): void {
		unset( $terms, $tt_ids );

		if ( in_array( $taxonomy, array( Taxonomies::TRIP, Taxonomies::TOPIC ), true ) && is_numeric( $object_id ) && self::is_published_photo( (int) $object_id ) ) {
			$this->pending = true;
		}
	}

	/**
	 * A trip or topic was renamed or deleted: the index prints its name.
	 *
	 * @param mixed $term_id  Unused.
	 * @param mixed $tt_id    Unused.
	 * @param mixed $taxonomy The taxonomy.
	 * @return void
	 */
	public function on_term( mixed $term_id, mixed $tt_id, mixed $taxonomy ): void {
		unset( $term_id, $tt_id );

		if ( in_array( $taxonomy, array( Taxonomies::TRIP, Taxonomies::TOPIC ), true ) ) {
			$this->pending = true;
		}
	}

	/**
	 * The request is over: purge, if anything changed.
	 *
	 * @return void
	 */
	public function on_shutdown(): void {
		$this->flush();
	}

	/**
	 * Whether a post is a photo on the wall. A draft's image or terms are not
	 * on any page yet; publishing it is its own event.
	 *
	 * @param int $post_id The post.
	 * @return bool
	 */
	private static function is_published_photo( int $post_id ): bool {
		return PostType::NAME === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
	}

	/**
	 * Whether this request has something to purge.
	 *
	 * @return bool
	 */
	public function pending(): bool {
		return $this->pending;
	}

	/**
	 * Clean every Photos page's post cache, once, if a photo changed.
	 *
	 * @return list<int> The pages cleaned.
	 */
	public function flush(): array {
		if ( ! $this->pending ) {
			return array();
		}

		$this->pending = false;
		$pages         = $this->pages();

		foreach ( $pages as $page_id ) {
			clean_post_cache( $page_id );
		}

		return $pages;
	}

	/**
	 * Every page carrying the Photos template, in any status.
	 *
	 * Memoised under the `posts` stamp, which moves when a page's template does.
	 *
	 * @return list<int>
	 */
	public function pages(): array {
		$key    = 'photo-pages:' . wp_cache_get_last_changed( 'posts' );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return array_values( array_filter( $cached, 'is_int' ) );
		}

		$found = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'numberposts'      => 20,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one small query, memoised, only on a request that changed a photo.
				'meta_key'         => '_wp_page_template',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				'meta_value'       => self::TEMPLATE,
			)
		);

		$pages = array();

		foreach ( $found as $id ) {
			if ( is_numeric( $id ) ) {
				$pages[] = (int) $id;
			}
		}

		wp_cache_set( $key, $pages, self::CACHE_GROUP );

		return $pages;
	}
}
