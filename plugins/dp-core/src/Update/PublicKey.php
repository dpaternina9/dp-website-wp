<?php
/**
 * The Ed25519 public key releases are verified against.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/**
 * Where the trust anchor lives.
 *
 * The key is compiled into the plugin — a constant in a file that ships inside
 * the ZIP — rather than fetched, stored in an option, or exposed through a
 * filter. A key that can be replaced at runtime by any other plugin, or by
 * anyone who can write one row of `wp_options`, is not a trust anchor; it is
 * decoration. The only override is a PHP constant in `wp-config.php`, which
 * already implies filesystem access, and exists so a staging site can trust a
 * staging key without shipping a second build.
 *
 * `COMPILED` is empty in the repository on purpose. `bin/dp-release.php keygen`
 * writes the real value here and prints the secret half exactly once, for
 * David to paste into the GitHub secret. An empty key means every update is
 * refused and the refusal is logged, which is the correct failure mode for a
 * missing trust anchor — and `bin/dp-build.sh` refuses to produce a release ZIP
 * while it is empty, so a keyless build cannot reach a site in the first place.
 */
final class PublicKey {

	/**
	 * Base64 of the 32-byte Ed25519 public key. Written by `dp-release.php keygen`.
	 */
	public const COMPILED = '';

	/**
	 * Name of the optional `wp-config.php` override constant.
	 */
	public const OVERRIDE_CONSTANT = 'DP_CORE_UPDATE_PUBLIC_KEY';

	/**
	 * The key this installation verifies against, base64-encoded.
	 *
	 * @return string Empty string when no key is configured.
	 */
	public static function resolve(): string {
		if ( defined( self::OVERRIDE_CONSTANT ) ) {
			$override = constant( self::OVERRIDE_CONSTANT );

			if ( is_string( $override ) && '' !== trim( $override ) ) {
				return trim( $override );
			}
		}

		return self::COMPILED;
	}
}
