<?php
/**
 * Every href the wall prints.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * Builds the wall's links from the page's own URL and the active filter.
 *
 * Three shapes, all on the page David assigned the template to and none of them
 * a route: `?trip=` / `?topic=` for a filter, `?photo=` for an open photo — which
 * keeps the filter it was opened under, so stepping stays inside that set — and
 * `?photos-page=` for the next forty-eight.
 */
final class Links {

	/**
	 * Constructor.
	 *
	 * @param string $base   The page's URL, with none of the wall's args.
	 * @param Filter $filter The active filter.
	 */
	public function __construct(
		public readonly string $base,
		public readonly Filter $filter
	) {}

	/**
	 * The wall under a filter, from its first page.
	 *
	 * @param Filter $filter The filter to link to.
	 * @return string
	 */
	public function filtered( Filter $filter ): string {
		$args = $filter->args();

		return array() === $args ? $this->base : add_query_arg( $args, $this->base );
	}

	/**
	 * One photo, open, inside the active filter.
	 *
	 * @param string $slug The photo's slug.
	 * @return string
	 */
	public function photo( string $slug ): string {
		return add_query_arg( array_merge( $this->filter->args(), array( PhotoWall::PHOTO_ARG => $slug ) ), $this->base );
	}

	/**
	 * A later page of the active filter.
	 *
	 * @param int $page 1-based.
	 * @return string
	 */
	public function page( int $page ): string {
		return add_query_arg( array_merge( $this->filter->args(), array( PhotoWall::PAGE_ARG => (string) $page ) ), $this->base );
	}
}
