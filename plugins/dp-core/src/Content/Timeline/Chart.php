<?php
/**
 * The timeline, read from the database.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

use DP\Core\Content\PostTypes;
use DP\Core\Content\Tone;
use DP\Core\Content\Year;
use WP_Post;
use WP_Query;

/**
 * Turns published `dp_role` and `dp_ship` posts into lanes with their geometry
 * already computed.
 *
 * Two queries, whatever the number of lanes. Every published ship is fetched
 * once and grouped by `dp_role_id`, because the alternative — a query per lane —
 * is the N+1 that makes a chart of six roles cost seven round trips and a chart
 * of twenty cost twenty-one.
 *
 * Nothing here does arithmetic. `Geometry` was written a phase early, with unit
 * tests, precisely so that the render path could be a transcription: this class
 * hands it two `Year`s and stores the `Bar` it gets back.
 *
 * Ordering is `menu_order`, then the post date. That is the same rule the series
 * archive uses and for the same reason — the sequence is a decision David makes
 * in the admin under Page Attributes, not a consequence of when a row was
 * typed. The seed writes the design's order into `menu_order`, so a seeded site
 * reproduces `LANES` exactly.
 */
final class Chart {

	/**
	 * How many lanes and how many shipped things a single chart will read.
	 *
	 * `-1` would be simpler and is what most themes write. It is also an
	 * unbounded query on a public page: a thousand roles would be a thousand
	 * rows, every one of them with meta, rendered into one document. The design
	 * has six. A hundred is far beyond any real record and still cannot hurt.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 100;

	/**
	 * Constructor.
	 *
	 * @param Geometry $geometry Where every bar goes.
	 */
	public function __construct( private readonly Geometry $geometry ) {}

	/**
	 * The track these lanes are drawn against.
	 *
	 * @return Geometry
	 */
	public function geometry(): Geometry {
		return $this->geometry;
	}

	/**
	 * Every published lane, in order, with its ships.
	 *
	 * @return list<Lane>
	 */
	public function lanes(): array {
		$ships = $this->ships_by_role();
		$lanes = array();

		foreach ( $this->published( PostTypes::ROLE ) as $role ) {
			$lanes[] = new Lane(
				$role->ID,
				$this->key( $role ),
				$role->post_title,
				$this->text( $role->ID, 'dp_role_title' ),
				$this->text( $role->ID, 'dp_range' ),
				$this->text( $role->ID, 'dp_detail' ),
				$this->text( $role->ID, 'dp_stack' ),
				Tone::try_from_meta( $this->text( $role->ID, 'dp_accent' ) ),
				$this->bar( $role->ID, BarKind::Role ),
				$ships[ $role->ID ] ?? array()
			);
		}

		return $lanes;
	}

	/**
	 * The stable identifier a URL carries for one entry.
	 *
	 * Public and static because the theme builds the same string when it turns a
	 * `WorkCard` into a link to the entry it belongs to. That is one seam between
	 * the two packages, named once, rather than a format string written out in
	 * two files that drift.
	 *
	 * The slug is preferred over the ID so a link survives an export and an
	 * import; the ID is the fallback, because a post with no slug is a post
	 * WordPress has not published yet and a link to it still has to be unique.
	 *
	 * @param string $post_type The post type, `dp_role` or `dp_ship`.
	 * @param string $slug      The post slug, which may be empty.
	 * @param int    $post_id   The post ID.
	 * @return string
	 */
	public static function entry_key( string $post_type, string $slug, int $post_id ): string {
		$kind = PostTypes::SHIP === $post_type ? 'ship' : 'role';
		$name = '' === $slug ? (string) $post_id : $slug;

		return 'dp-' . $kind . '-' . sanitize_title( $name );
	}

	/**
	 * Every published shipped thing, grouped by the role it hangs off.
	 *
	 * A ship whose `dp_role_id` points at nothing is dropped rather than shown
	 * loose: the design's own sentence is "every project hangs off the job it
	 * came from", and a project with no job has nowhere on this chart to be.
	 * `dp_role_id`'s registered description says the same thing.
	 *
	 * @return array<int, list<Ship>>
	 */
	private function ships_by_role(): array {
		$grouped = array();

		foreach ( $this->published( PostTypes::SHIP ) as $ship ) {
			$role_id = $this->number( $ship->ID, 'dp_role_id' );

			if ( $role_id <= 0 ) {
				continue;
			}

			$grouped[ $role_id ][] = new Ship(
				$ship->ID,
				$this->key( $ship ),
				$ship->post_title,
				$this->text( $ship->ID, 'dp_range' ),
				$this->text( $ship->ID, 'dp_headline' ),
				$this->text( $ship->ID, 'dp_detail' ),
				$this->lines( $ship->ID, 'dp_bullets' ),
				$this->text( $ship->ID, 'dp_ship_role' ),
				$this->text( $ship->ID, 'dp_stack' ),
				$this->text( $ship->ID, 'dp_artifact_label' ),
				$this->text( $ship->ID, 'dp_artifact' ),
				$this->text( $ship->ID, 'dp_stat1' ),
				$this->text( $ship->ID, 'dp_stat1_label' ),
				$this->text( $ship->ID, 'dp_stat2' ),
				$this->text( $ship->ID, 'dp_stat2_label' ),
				$this->writeup( $ship->ID ),
				$this->bar( $ship->ID, BarKind::Ship )
			);
		}

		return $grouped;
	}

	/**
	 * Published posts of one type, in the order the admin put them.
	 *
	 * @param non-empty-string $post_type The post type to read.
	 * @return list<WP_Post>
	 */
	private function published( string $post_type ): array {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_ROWS,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
				),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$posts = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}

		return $posts;
	}

	/**
	 * The bar for one post, or null when its dates cannot be read.
	 *
	 * @param int     $post_id The post.
	 * @param BarKind $kind    Role lane or shipped thing.
	 * @return Bar|null
	 */
	private function bar( int $post_id, BarKind $kind ): ?Bar {
		$start = Year::try_from_float( $this->decimal( $post_id, 'dp_start' ) );
		$end   = Year::try_from_float( $this->decimal( $post_id, 'dp_end' ) );

		if ( null === $start || null === $end ) {
			return null;
		}

		return $this->geometry->bar( $start, $end, $kind );
	}

	/**
	 * The permalink of the post that writes a shipped thing up, or ''.
	 *
	 * A draft, a trashed post or a deleted one all resolve to no link rather
	 * than to a URL that 404s, which is the same promise ADR-0006 makes about
	 * every derived destination on this site.
	 *
	 * @param int $post_id The shipped thing.
	 * @return string
	 */
	private function writeup( int $post_id ): string {
		$writeup_id = $this->number( $post_id, 'dp_writeup_id' );

		if ( $writeup_id <= 0 || 'publish' !== get_post_status( $writeup_id ) ) {
			return '';
		}

		$url = get_permalink( $writeup_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * The entry key for one post.
	 *
	 * @param WP_Post $post The post.
	 * @return string
	 */
	private function key( WP_Post $post ): string {
		return self::entry_key( $post->post_type, $post->post_name, $post->ID );
	}

	/**
	 * One string meta value.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return string
	 */
	private function text( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * One list-of-strings meta value.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return list<string>
	 */
	private function lines( int $post_id, string $key ): array {
		$value = get_post_meta( $post_id, $key, true );

		if ( ! is_array( $value ) ) {
			return array();
		}

		$lines = array();

		foreach ( $value as $line ) {
			if ( is_string( $line ) && '' !== trim( $line ) ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * One integer meta value.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return int
	 */
	private function number( int $post_id, string $key ): int {
		$value = get_post_meta( $post_id, $key, true );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * One decimal-year meta value.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key.
	 * @return float
	 */
	private function decimal( int $post_id, string $key ): float {
		$value = get_post_meta( $post_id, $key, true );

		return is_numeric( $value ) ? (float) $value : 0.0;
	}
}
