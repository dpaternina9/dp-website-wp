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
	 * `$today` is where an **ongoing** entry's bar ends. `dp_end` is optional on
	 * both post types and its registered description says so in the editor: a
	 * role that has not finished, or a project still being worked on, is left
	 * blank and runs to today. Nothing implemented that until 2026-09-02, so
	 * David left it blank as instructed and his current role had no bar at all.
	 *
	 * It is a `Year` rather than an `int` because `Year` encodes months as
	 * twelfths: an ongoing role that ended at January of the current year would
	 * be drawn up to eleven months short, which is precisely the class of error
	 * ADR-0014 was written about.
	 *
	 * Null means "read the site's clock", which is what the plugin does. It is
	 * injectable for the reason `Geometry::through()`'s year is: a test that
	 * wants a month boundary passes the boundary, and the merge queue already
	 * records that Brain Monkey cannot stand in for `time()`.
	 *
	 * @param Geometry  $geometry Where every bar goes.
	 * @param Year|null $today    The point in time an unfinished entry runs to, or null to read the clock.
	 */
	public function __construct(
		private readonly Geometry $geometry,
		private readonly ?Year $today = null
	) {}

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
	 * The two ends are not symmetrical, and the asymmetry is the decision. A
	 * blank **start** is no bar: a role with no beginning has nowhere on the
	 * track to begin, and guessing one would be inventing a date. A blank
	 * **end** is "still going", which is what both post types' `dp_end`
	 * description already tells the author in the editor — so this is a
	 * derivation filling a blank, announced where the blank is left, rather than
	 * a hidden render-time rewrite (ADR-0018 rule 3).
	 *
	 * @param int     $post_id The post.
	 * @param BarKind $kind    Role lane or shipped thing.
	 * @return Bar|null
	 */
	private function bar( int $post_id, BarKind $kind ): ?Bar {
		$start = Year::try_from_float( $this->decimal( $post_id, 'dp_start' ) );
		$end   = $this->ends( $post_id );

		if ( null === $start || null === $end ) {
			return null;
		}

		return $this->geometry->bar( $start, $end, $kind );
	}

	/**
	 * When one entry ended: what it says, or today when it has not.
	 *
	 * `0.0` is this project's own sentinel for "no date yet" — `Meta`'s year
	 * fields declare it as their default, sanitise a blank to it and carry an
	 * `anyOf` schema that admits it beside the real range — so reading it as
	 * "unfinished" here is reading the value the content model already stores
	 * rather than inventing a second meaning for it.
	 *
	 * A value that is present but outside what a `Year` will hold still means no
	 * bar. That can only arrive from an import or a direct write, and it is a
	 * date somebody typed wrong rather than a date they left out; treating it as
	 * "today" would draw a bar the record does not support.
	 *
	 * @param int $post_id The post.
	 * @return Year|null
	 */
	private function ends( int $post_id ): ?Year {
		$stored = $this->decimal( $post_id, 'dp_end' );

		return 0.0 === $stored ? $this->now() : Year::try_from_float( $stored );
	}

	/**
	 * The point in time an unfinished entry runs to.
	 *
	 * The site's timezone, through `wp_date()`, for the reason ADR-0014 gives
	 * for the axis: on 31 December a site in Bogotá is five hours short of the
	 * UTC one, and `date()` would read the container's clock, which is a third
	 * answer nobody chose. Both halves are clamped into what `Year` accepts so
	 * that a clock this class cannot control can never raise on a public page.
	 *
	 * @return Year
	 */
	private function now(): Year {
		if ( null !== $this->today ) {
			return $this->today;
		}

		$year  = wp_date( 'Y' );
		$month = wp_date( 'n' );

		return Year::from_year_month(
			min( Year::MAX_YEAR, max( Year::MIN_YEAR, is_string( $year ) && is_numeric( $year ) ? (int) $year : (int) gmdate( 'Y' ) ) ),
			min( 12, max( 1, is_string( $month ) && is_numeric( $month ) ? (int) $month : (int) gmdate( 'n' ) ) )
		);
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
