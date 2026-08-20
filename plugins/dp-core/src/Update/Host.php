<?php
/**
 * The one origin this site will take an update from.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/**
 * The update host, in one place.
 *
 * This value appears in three places that must agree, which is exactly why it
 * is not written out three times:
 *
 * 1. the `Update URI:` header in `style.css` and `dp-core.php`, from which core
 *    derives the hostname it builds the filter name out of;
 * 2. the filter names we hook, `update_themes_{$host}` / `update_plugins_{$host}`;
 * 3. the origin check on the manifest URL before we make a request.
 *
 * Because (1) lives in a file header rather than in PHP, the filter below can
 * only ever be *half* an override: pointing a staging site at another host also
 * means changing the headers in that build, or core will never call us. That is
 * a deliberate limitation and not a bug — see `docs/adr/0004`.
 */
final class Host {

	/**
	 * Where releases are published.
	 */
	public const DEFAULT_HOST = 'updates.dpaternina.com';

	/**
	 * The host in force for this request.
	 *
	 * @return string A bare hostname, lower-cased, with nothing else in it.
	 */
	public static function current(): string {
		/**
		 * Filters the hostname the update client trusts.
		 *
		 * Changing this without also changing the `Update URI` headers in the
		 * build has no effect: core derives the filter name from the header.
		 *
		 * @since 0.1.0
		 *
		 * @param string $host Hostname releases are published under.
		 */
		$host = apply_filters( 'dp_core_update_host', self::DEFAULT_HOST );

		if ( ! is_string( $host ) ) {
			return self::DEFAULT_HOST;
		}

		$host = strtolower( trim( $host ) );

		// A hostname and nothing else: no scheme, no path, no port, no credentials.
		if ( 1 !== preg_match( '/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $host ) ) {
			return self::DEFAULT_HOST;
		}

		return $host;
	}

	/**
	 * Hosts a release package may be downloaded from.
	 *
	 * @return string[]
	 */
	public static function package_hosts(): array {
		/**
		 * Filters the hosts a signed manifest may point the upgrader at.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $hosts Allowed package hostnames.
		 */
		$hosts = apply_filters( 'dp_core_update_package_hosts', Manifest::DEFAULT_PACKAGE_HOSTS );

		if ( ! is_array( $hosts ) ) {
			return Manifest::DEFAULT_PACKAGE_HOSTS;
		}

		$clean = array();

		foreach ( $hosts as $host ) {
			if ( is_string( $host ) && '' !== trim( $host ) ) {
				$clean[] = strtolower( trim( $host ) );
			}
		}

		return array() === $clean ? Manifest::DEFAULT_PACKAGE_HOSTS : $clean;
	}
}
