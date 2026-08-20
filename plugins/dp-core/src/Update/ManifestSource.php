<?php
/**
 * Fetching, caching and re-verifying the update manifest.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/**
 * The network half of the update client.
 *
 * Two decisions worth stating out loud:
 *
 * **The cache stores the signed envelope, not the parsed manifest.** Every read
 * re-runs `sodium_crypto_sign_verify_detached()`. That costs tens of
 * microseconds and buys the property that a writable object cache — a shared
 * Redis, a persistent-cache plugin, a `wp_options` row — cannot inject an
 * update offer. Caching the *conclusion* would have made the signature check a
 * one-time formality.
 *
 * **A failure is cached too.** `wp_update_plugins()` runs on `admin_init`; an
 * update host that is down would otherwise mean a blocking HTTP request with
 * every admin page load. A short negative TTL keeps a soft failure soft.
 */
final class ManifestSource {

	/**
	 * How long a verified envelope is reused.
	 */
	public const SUCCESS_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long a failure suppresses another attempt.
	 */
	public const FAILURE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Seconds to wait for the manifest host.
	 */
	public const TIMEOUT = 8;

	/**
	 * Prefix for the site transient the envelope is cached in.
	 */
	public const TRANSIENT_PREFIX = 'dp_core_update_';

	/**
	 * Constructor.
	 *
	 * @param Verifier $verifier Signature verification and manifest parsing.
	 * @param Log      $log      Where refusals go.
	 */
	public function __construct(
		private readonly Verifier $verifier,
		private readonly Log $log
	) {}

	/**
	 * The manifest for one package, or null if there isn't a trustworthy one.
	 *
	 * Never throws. Every failure path returns null and logs, because this is
	 * called from inside a core filter during an admin request: an exception
	 * here would take down the update screen, and a warning would be printed
	 * into somebody's dashboard.
	 *
	 * @param PackageType $type       Theme or plugin.
	 * @param string      $update_uri The package's `Update URI` header value.
	 * @return Manifest|null
	 */
	public function manifest_for( PackageType $type, string $update_uri ): ?Manifest {
		$manifest_url = $this->manifest_url( $update_uri );

		if ( null === $manifest_url ) {
			$this->log->refused(
				'Update URI does not belong to the configured update host.',
				array(
					'update_uri' => $update_uri,
					'host'       => Host::current(),
				)
			);

			return null;
		}

		$transient = self::TRANSIENT_PREFIX . $type->value;
		$cached    = get_site_transient( $transient );

		if ( is_array( $cached ) && array_key_exists( 'body', $cached ) ) {
			$body = $cached['body'];

			if ( ! is_string( $body ) ) {
				// A recent attempt already failed and already logged. Stay quiet.
				return null;
			}

			$manifest = $this->open( $body, 'cache' );

			if ( null === $manifest ) {
				// The cache holds something that does not verify. Do not keep
				// reading it, and do not immediately re-fetch either: a poisoned
				// cache and a bad publish look identical from here.
				set_site_transient( $transient, array( 'body' => null ), self::FAILURE_TTL );
			}

			return $manifest;
		}

		$body = $this->request( $manifest_url );

		if ( null === $body ) {
			set_site_transient( $transient, array( 'body' => null ), self::FAILURE_TTL );

			return null;
		}

		$manifest = $this->open( $body, 'network' );

		set_site_transient(
			$transient,
			array( 'body' => null === $manifest ? null : $body ),
			null === $manifest ? self::FAILURE_TTL : self::SUCCESS_TTL
		);

		return $manifest;
	}

	/**
	 * Where the manifest for a given `Update URI` lives.
	 *
	 * `https://updates.dpaternina.com/theme` → `https://updates.dpaternina.com/theme.json`.
	 * The header is the single source of truth for the path; the host must match
	 * what this build is configured to trust, which is what stops a tampered
	 * header in an installed copy from redirecting the check somewhere else.
	 *
	 * @param string $update_uri Header value.
	 * @return string|null Null when the URI is not one we will talk to.
	 */
	private function manifest_url( string $update_uri ): ?string {
		$parts = wp_parse_url( $update_uri );

		if ( ! is_array( $parts ) ) {
			return null;
		}

		$host   = isset( $parts['host'] ) && is_string( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$scheme = isset( $parts['scheme'] ) && is_string( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		$path   = isset( $parts['path'] ) && is_string( $parts['path'] ) ? $parts['path'] : '';

		if ( 'https' !== $scheme || Host::current() !== $host ) {
			return null;
		}

		if ( 1 !== preg_match( '#^/[a-z0-9-]{1,64}$#', $path ) ) {
			return null;
		}

		return 'https://' . $host . $path . '.json';
	}

	/**
	 * Fetch the envelope. Returns null on any transport-level problem.
	 *
	 * @param string $url Absolute HTTPS URL of the manifest.
	 * @return string|null
	 */
	private function request( string $url ): ?string {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 2,
				'sslverify'   => true,
				'user-agent'  => 'dp-core-updater/1; ' . home_url( '/' ),
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log->refused(
				'Update manifest could not be fetched.',
				array(
					'url'   => $url,
					'error' => $response->get_error_message(),
				)
			);

			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$this->log->refused(
				'Update manifest request returned an unexpected status.',
				array(
					'url'    => $url,
					'status' => $code,
				)
			);

			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( '' === $body ) {
			$this->log->refused( 'Update manifest response was empty.', array( 'url' => $url ) );

			return null;
		}

		return $body;
	}

	/**
	 * Verify and parse, converting the one exception into null plus a log line.
	 *
	 * @param string $body   Raw envelope.
	 * @param string $source 'cache' or 'network', so a poisoned cache is distinguishable in the log.
	 * @return Manifest|null
	 */
	private function open( string $body, string $source ): ?Manifest {
		try {
			return $this->verifier->open( $body );
		} catch ( ManifestError $error ) {
			$this->log->refused(
				$error->getMessage(),
				array_merge( $error->context(), array( 'source' => $source ) )
			);

			return null;
		}
	}
}
