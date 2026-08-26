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
 * decoration. The only override is the `DPATERNINA_UPDATE_PUBLIC_KEY` constant
 * in `wp-config.php` (the library derives that name from our `dpaternina` hook
 * prefix), which already implies filesystem access, and exists so a staging
 * site can trust a staging key without shipping a second build.
 *
 * `COMPILED` is empty in the repository on purpose. Running
 *
 *     php plugins/dp-core/vendor/fanxielab/wp-update-client/bin/release.php \
 *         keygen --write-to=plugins/dp-core/src/Update/UpdateKey.php
 *
 * writes the real value here and prints the secret half exactly once, for
 * David to paste into the `DPATERNINA_UPDATE_SIGNING_KEY` GitHub secret. An
 * empty key means every update is refused and the refusal is logged, which is
 * the correct failure mode for a missing trust anchor — and the library's
 * `bin/build.sh` refuses to produce a release ZIP while it is empty, so a
 * keyless build cannot reach a site in the first place.
 *
 * See docs/adr/0015-adopt-the-wp-update-client-library.md.
 */
final class UpdateKey {

	/**
	 * Base64 of the 32-byte Ed25519 public key. Written by the library's `release.php keygen`.
	 */
	public const COMPILED = '';
}
