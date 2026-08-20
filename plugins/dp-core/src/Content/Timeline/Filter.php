<?php
/**
 * The timeline's three-way filter.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

/**
 * Everything / Roles / Shipped, as a closed set.
 *
 * The rule is quoted verbatim from the FILTER block at the bottom of
 * `design-source/components/TimelineChart.dc.html`:
 *
 * ```
 * Everything -> all lanes, all ships
 * Roles      -> all lanes, ships hidden
 * Shipped    -> only lanes with ships
 * ```
 *
 * Three sentences, and every one of them is a decision the renderer, the
 * query-arg links and the front-end script all have to agree on. They agree by
 * asking this enum, which is pure PHP with no WordPress in it and is therefore
 * unit-tested on the host rather than discovered in a browser.
 *
 * `Roles` hides ships rather than dropping the lanes that only exist because of
 * them, and `Shipped` drops lanes rather than hiding their rows. That asymmetry
 * is the design's, not a simplification: "all lanes, ships hidden" and "only
 * lanes with ships" are different sentences about different things.
 */
enum Filter: string {

	case Everything = 'everything';
	case Roles      = 'roles';
	case Shipped    = 'shipped';

	/**
	 * The filter a request asked for, defaulting to Everything.
	 *
	 * Anything unrecognised is the default rather than an error: the value
	 * arrives in a query string, where a stale bookmark or a truncated link is
	 * ordinary, and a 400 for it would be theatre.
	 *
	 * @param string $value The raw, already-sanitised query-arg value.
	 * @return self
	 */
	public static function from_request( string $value ): self {
		return self::tryFrom( strtolower( trim( $value ) ) ) ?? self::Everything;
	}

	/**
	 * The default, which is also what an absent query arg means.
	 *
	 * @return self
	 */
	public static function default_filter(): self {
		return self::Everything;
	}

	/**
	 * Whether this is the filter a bare URL shows.
	 *
	 * @return bool
	 */
	public function is_default(): bool {
		return self::Everything === $this;
	}

	/**
	 * Whether a lane appears at all under this filter.
	 *
	 * @param bool $has_ships Whether the lane carries at least one shipped thing.
	 * @return bool
	 */
	public function shows_lane( bool $has_ships ): bool {
		return match ( $this ) {
			self::Shipped => $has_ships,
			default       => true,
		};
	}

	/**
	 * Whether the shipped things hanging off a lane appear under this filter.
	 *
	 * @return bool
	 */
	public function shows_ships(): bool {
		return self::Roles !== $this;
	}

	/**
	 * Every filter, in the order the design's pill row draws them.
	 *
	 * @return list<self>
	 */
	public static function pills(): array {
		return array( self::Everything, self::Roles, self::Shipped );
	}
}
