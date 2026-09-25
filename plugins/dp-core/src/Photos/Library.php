<?php
/**
 * Every published photo, indexed once per change.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Term;

/**
 * The published set, in the wall's order, with each photo's trip and topics.
 *
 * **What counts as a photo on the page:** a published `dp_photo` with a featured
 * image. A draft is not one, and neither is a published photo whose image was
 * removed — the wall has nothing to draw for it, so it is not counted in the
 * index either, and the counts beside a trip are always how many tiles clicking
 * it will show.
 *
 * **Order is the publish date, newest first**, with the post ID breaking ties so
 * two photos imported in the same second always come out the same way round.
 * The publish date is a core field David sets in the sidebar; the bulk importer
 * starts it at the date the camera recorded (`Exif::taken()`), and the Camera
 * panel offers the same date on request. There is no position field.
 *
 * Two queries build it — one for the rows, one for their terms — and the result
 * is memoised in the object cache under the `posts` and `terms` `last_changed`
 * stamps core already maintains, the same idiom `SeriesParts` uses (ADR-0016):
 * publishing, re-dating, re-filing or removing an image moves one of the stamps,
 * and the next read rebuilds, with no invalidation hook of ours.
 */
final class Library {

	/**
	 * The object-cache group.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'dp_photos';

	/**
	 * Every photo on the page, newest first.
	 *
	 * @return list<Entry>
	 */
	public function entries(): array {
		$key    = 'library:' . $this->version();
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return array_values( array_filter( $cached, static fn ( mixed $entry ): bool => $entry instanceof Entry ) );
		}

		$entries = $this->load();

		wp_cache_set( $key, $entries, self::CACHE_GROUP );

		return $entries;
	}

	/**
	 * The set's change stamp: different whenever anything the set is built
	 * from — posts, their meta, terms — has changed.
	 *
	 * The same two `last_changed` stamps the set is cached under, so "the
	 * library changed" and "this stamp changed" are one fact. The wall prints
	 * it for the script, which adds it to the one URL it fetches pages with,
	 * so a page cache or a CDN can never answer a new set with an old page.
	 *
	 * @return string
	 */
	public function version(): string {
		return substr( md5( wp_cache_get_last_changed( 'posts' ) . ':' . wp_cache_get_last_changed( 'terms' ) ), 0, 12 );
	}

	/**
	 * The photos a filter shows, newest first.
	 *
	 * @param Filter $filter The active filter.
	 * @return list<Entry>
	 */
	public function filtered( Filter $filter ): array {
		if ( ! $filter->active() ) {
			return $this->entries();
		}

		return array_values( array_filter( $this->entries(), static fn ( Entry $entry ): bool => $entry->in( $filter ) ) );
	}

	/**
	 * One photo by its slug, or null when it is not on the page.
	 *
	 * @param string $slug A `post_name`.
	 * @return Entry|null
	 */
	public function find( string $slug ): ?Entry {
		if ( '' === $slug ) {
			return null;
		}

		foreach ( $this->entries() as $entry ) {
			if ( $entry->slug === $slug ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * The trips with at least one photo, most recent trip first.
	 *
	 * @return list<Group>
	 */
	public function trips(): array {
		$groups = $this->groups( Taxonomies::TRIP );

		usort(
			$groups,
			static fn ( Group $a, Group $b ): int => array( $b->last, $a->term->name ) <=> array( $a->last, $b->term->name )
		);

		return $groups;
	}

	/**
	 * The topics with at least one photo, largest first.
	 *
	 * @return list<Group>
	 */
	public function topics(): array {
		$groups = $this->groups( Taxonomies::TOPIC );

		usort(
			$groups,
			static fn ( Group $a, Group $b ): int => array( $b->count, $a->term->name ) <=> array( $a->count, $b->term->name )
		);

		return $groups;
	}

	/**
	 * One trip's group, for the Trips screen, or null when it has no photos.
	 *
	 * @param int $term_id A `dp_trip` term ID.
	 * @return Group|null
	 */
	public function trip( int $term_id ): ?Group {
		foreach ( $this->groups( Taxonomies::TRIP ) as $group ) {
			if ( $group->term->term_id === $term_id ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Every group of one taxonomy that has photos, unordered.
	 *
	 * @param string $taxonomy `dp_trip` or `dp_topic`.
	 * @return list<Group>
	 */
	private function groups( string $taxonomy ): array {
		/**
		 * Term ID to its count and date span.
		 *
		 * @var array<int, array{count: int, first: string, last: string}> $tally
		 */
		$tally = array();

		foreach ( $this->entries() as $entry ) {
			$ids = Taxonomies::TRIP === $taxonomy ? array_filter( array( $entry->trip ) ) : $entry->topics;

			foreach ( $ids as $id ) {
				$row = $tally[ $id ] ?? array(
					'count' => 0,
					'first' => $entry->date,
					'last'  => $entry->date,
				);

				$tally[ $id ] = array(
					'count' => $row['count'] + 1,
					'first' => min( $row['first'], $entry->date ),
					'last'  => max( $row['last'], $entry->date ),
				);
			}
		}

		if ( array() === $tally ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'include'    => array_keys( $tally ),
				'hide_empty' => false,
			)
		);

		$groups = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof WP_Term && isset( $tally[ $term->term_id ] ) ) {
				$row      = $tally[ $term->term_id ];
				$groups[] = new Group( $term, $row['count'], $row['first'], $row['last'] );
			}
		}

		return $groups;
	}

	/**
	 * Read the set from the database.
	 *
	 * An aggregate read rather than a `WP_Query`: every row is three scalars, and
	 * hydrating hundreds of posts — content and all — to learn them is the cost
	 * this class exists to avoid. `CLAUDE.md` allows `$wpdb` through `prepare()`,
	 * which this is, and the answer is cached by the caller.
	 *
	 * @return list<Entry>
	 */
	private function load(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached by entries() under the posts and terms last_changed stamps.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_name AS slug, p.post_date AS date
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = %s AND CAST( m.meta_value AS UNSIGNED ) > 0
				 GROUP BY p.ID
				 ORDER BY p.post_date DESC, p.ID DESC",
				'_thumbnail_id',
				PostType::NAME,
				'publish'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || array() === $rows ) {
			return array();
		}

		$ids = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && is_numeric( $row['id'] ?? null ) ) {
				$ids[] = (int) $row['id'];
			}
		}

		$terms = $this->terms( $ids );
		$found = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! is_numeric( $row['id'] ?? null ) ) {
				continue;
			}

			$id      = (int) $row['id'];
			$found[] = new Entry(
				$id,
				is_string( $row['slug'] ?? null ) ? $row['slug'] : '',
				is_string( $row['date'] ?? null ) ? $row['date'] : '',
				$terms[ $id ]['trip'] ?? 0,
				$terms[ $id ]['topics'] ?? array()
			);
		}

		return $found;
	}

	/**
	 * Each photo's trip and topics, in one query.
	 *
	 * A photo carrying two trips — possible only by a route that is not the
	 * editor, since `OneTrip` refuses it there — is read as its lowest term ID, so
	 * the answer is at least the same on every render.
	 *
	 * @param array<int, int> $ids Photo post IDs.
	 * @return array<int, array{trip: int, topics: list<int>}>
	 */
	private function terms( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}

		$found = wp_get_object_terms(
			$ids,
			array( Taxonomies::TRIP, Taxonomies::TOPIC ),
			array(
				'fields'                 => 'all_with_object_id',
				'orderby'                => 'term_id',
				'update_term_meta_cache' => false,
			)
		);

		$map = array();

		foreach ( is_array( $found ) ? $found : array() as $term ) {
			if ( ! $term instanceof WP_Term || ! is_numeric( $term->object_id ?? null ) ) {
				continue;
			}

			$id           = (int) $term->object_id;
			$map[ $id ] ??= array(
				'trip'   => 0,
				'topics' => array(),
			);

			if ( Taxonomies::TRIP === $term->taxonomy && 0 === $map[ $id ]['trip'] ) {
				$map[ $id ]['trip'] = $term->term_id;
			} elseif ( Taxonomies::TOPIC === $term->taxonomy ) {
				$map[ $id ]['topics'][] = $term->term_id;
			}
		}

		return $map;
	}
}
