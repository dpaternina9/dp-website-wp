<?php
/**
 * Which photos the wall is showing.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Term;

/**
 * All photos, one trip, or one topic — read from `?trip=` or `?topic=`.
 *
 * The state is in the URL for the reason ADR-0007 gives for the timeline's: an
 * index entry is a link, so filtering works with the scripts off, a filtered
 * wall can be bookmarked and shared, and the script that makes it instant is an
 * upgrade rather than the mechanism.
 *
 * **Anything unrecognised is "all photos".** A slug that names no term, a term
 * in the wrong taxonomy, an array where a string should be — every one of them
 * degrades to the unfiltered page rather than to an error or an empty wall,
 * because a stale bookmark to a trip David has since renamed should still land
 * on his photos. A trip wins when a URL carries both, since a photo has at most
 * one and it is the narrower of the two.
 */
final class Filter {

	/**
	 * The query arg, and the kind, for a trip.
	 *
	 * @var string
	 */
	public const TRIP = 'trip';

	/**
	 * The query arg, and the kind, for a topic.
	 *
	 * @var string
	 */
	public const TOPIC = 'topic';

	/**
	 * Constructor.
	 *
	 * @param string       $kind `trip`, `topic`, or '' for all photos.
	 * @param WP_Term|null $term The term, when there is one.
	 */
	private function __construct(
		public readonly string $kind,
		public readonly ?WP_Term $term
	) {}

	/**
	 * No filter.
	 *
	 * @return self
	 */
	public static function none(): self {
		return new self( '', null );
	}

	/**
	 * One trip or one topic.
	 *
	 * @param WP_Term $term A `dp_trip` or `dp_topic` term.
	 * @return self
	 */
	public static function for_term( WP_Term $term ): self {
		return match ( $term->taxonomy ) {
			Taxonomies::TRIP  => new self( self::TRIP, $term ),
			Taxonomies::TOPIC => new self( self::TOPIC, $term ),
			default           => self::none(),
		};
	}

	/**
	 * The filter a request's query args describe.
	 *
	 * @param array<array-key, mixed> $query The query args, e.g. `$_GET`, unslashed.
	 * @return self
	 */
	public static function from_query( array $query ): self {
		foreach ( array(
			self::TRIP  => Taxonomies::TRIP,
			self::TOPIC => Taxonomies::TOPIC,
		) as $arg => $taxonomy ) {
			$slug = $query[ $arg ] ?? '';
			$slug = is_string( $slug ) ? sanitize_title( $slug ) : '';

			if ( '' === $slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( $term instanceof WP_Term ) {
				return self::for_term( $term );
			}
		}

		return self::none();
	}

	/**
	 * Whether anything is filtered at all.
	 *
	 * @return bool
	 */
	public function active(): bool {
		return null !== $this->term && '' !== $this->kind;
	}

	/**
	 * The term's ID, or 0.
	 *
	 * @return int
	 */
	public function term_id(): int {
		return null === $this->term ? 0 : $this->term->term_id;
	}

	/**
	 * The term's slug, or ''.
	 *
	 * @return string
	 */
	public function slug(): string {
		return null === $this->term ? '' : $this->term->slug;
	}

	/**
	 * The term's name, or ''.
	 *
	 * @return string
	 */
	public function name(): string {
		return null === $this->term ? '' : $this->term->name;
	}

	/**
	 * The query args that say this filter, for building a link.
	 *
	 * @return array<string, string>
	 */
	public function args(): array {
		return $this->active() ? array( $this->kind => $this->slug() ) : array();
	}

	/**
	 * Whether this is the same filter as another.
	 *
	 * @param Filter $other The other one.
	 * @return bool
	 */
	public function is( Filter $other ): bool {
		return $this->kind === $other->kind && $this->term_id() === $other->term_id();
	}
}
