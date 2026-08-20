<?php
/**
 * The verified contents of an update manifest.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update;

/*
 * Every `throw` below constructs a ManifestError, which has exactly one
 * consumer: ManifestSource::open(), which catches it and hands the message to
 * Log. None of these strings is ever echoed, and escaping a rejected version
 * number for a log file would make an incident harder to read while protecting
 * nothing. This is the same call `phpcs.xml.dist` already makes for `tests/`
 * and `bin/`.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * One release, described.
 *
 * Deliberately free of WordPress: no filters, no options, no HTTP, not even
 * `wp_parse_url()`. Everything this class rejects, it rejects on the shape of
 * the data alone. That is what lets `bin/dp-release.php verify` check a
 * manifest before publishing it using *this* class rather than a second
 * implementation that agrees with it until the day it doesn't — and it is why
 * the Unit suite can exercise every rule without a bootstrap. Whether the bytes
 * it was parsed from were *signed* is somebody else's question; see Verifier.
 */
final readonly class Manifest {

	/**
	 * A version we are willing to install.
	 *
	 * Semver with an optional pre-release or build suffix. Anything else is a
	 * typo or an attempt to confuse `version_compare()`, and both are refused.
	 */
	private const VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.\-]+)?$/';

	/**
	 * Hosts we will accept a package ZIP from unless told otherwise.
	 *
	 * A valid signature already proves the manifest is ours, so this is defence
	 * in depth rather than the primary control: it means a signing key used
	 * carelessly (a manifest generated from an untrusted template, say) still
	 * cannot point the WordPress upgrader at an arbitrary origin.
	 *
	 * @var string[]
	 */
	public const DEFAULT_PACKAGE_HOSTS = array( 'github.com', 'updates.dpaternina.com' );

	/**
	 * Constructor. Private: the only way in is `from_array()`.
	 *
	 * @param PackageType $type         Theme or plugin.
	 * @param string      $slug         Stylesheet directory, or plugin directory name.
	 * @param string      $version      The version being offered.
	 * @param string      $package      HTTPS URL of the release ZIP.
	 * @param string      $url          Human-readable release page.
	 * @param string      $requires     Minimum WordPress version, or ''.
	 * @param string      $requires_php Minimum PHP version, or ''.
	 * @param string      $tested       WordPress version tested against, or ''.
	 */
	private function __construct(
		public PackageType $type,
		public string $slug,
		public string $version,
		public string $package,
		public string $url,
		public string $requires,
		public string $requires_php,
		public string $tested
	) {}

	/**
	 * Parse a decoded manifest payload.
	 *
	 * @param mixed         $data          Decoded JSON. Anything but an array is a rejection.
	 * @param string[]|null $package_hosts Hosts the package URL may point at. Null uses the default.
	 * @return self
	 *
	 * @throws ManifestError If the payload is missing a field, or a field is not usable.
	 */
	public static function from_array( mixed $data, ?array $package_hosts = null ): self {
		if ( ! is_array( $data ) ) {
			throw new ManifestError( 'Manifest payload is not a JSON object.' );
		}

		$type = PackageType::tryFrom( self::text( $data, 'type' ) );

		if ( null === $type ) {
			throw new ManifestError(
				'Manifest declares an unknown package type.',
				array( 'type' => self::text( $data, 'type' ) )
			);
		}

		$slug = self::text( $data, 'slug' );

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]{1,62}$/', $slug ) ) {
			throw new ManifestError( 'Manifest slug is not a plausible directory name.', array( 'slug' => $slug ) );
		}

		$version = self::text( $data, 'version' );

		if ( 1 !== preg_match( self::VERSION_PATTERN, $version ) ) {
			throw new ManifestError( 'Manifest version is not semver.', array( 'version' => $version ) );
		}

		$package = self::text( $data, 'package' );

		/*
		 * parse_url(), not wp_parse_url(). This class is the one piece of the
		 * update path that runs both inside WordPress and inside the release
		 * tool, so it may not call WordPress. wp_parse_url() is parse_url() plus
		 * workarounds for PHP versions this project does not support.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$host = parse_url( $package, PHP_URL_HOST );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$scheme = parse_url( $package, PHP_URL_SCHEME );

		if ( 'https' !== $scheme || ! is_string( $host ) ) {
			throw new ManifestError( 'Manifest package URL is not an HTTPS URL.', array( 'package' => $package ) );
		}

		if ( ! in_array( strtolower( $host ), $package_hosts ?? self::DEFAULT_PACKAGE_HOSTS, true ) ) {
			throw new ManifestError( 'Manifest package URL points at an unexpected host.', array( 'host' => $host ) );
		}

		return new self(
			$type,
			$slug,
			$version,
			$package,
			self::text( $data, 'url' ),
			self::text( $data, 'requires' ),
			self::text( $data, 'requires_php' ),
			self::text( $data, 'tested' )
		);
	}

	/**
	 * Is this release newer than what is installed?
	 *
	 * Core makes this comparison itself before deciding between `response` and
	 * `no_update` (WordPress 7.1, `wp-includes/update.php`). We make it too, so
	 * that the answer is testable without a transient and so that a manifest
	 * offering an older build can be recognised as such in the log.
	 *
	 * @param string $installed The currently installed version.
	 * @return bool
	 */
	public function is_newer_than( string $installed ): bool {
		return version_compare( $this->version, $installed, '>' );
	}

	/**
	 * The array core expects back from `update_themes_*` / `update_plugins_*`.
	 *
	 * `new_version` is set explicitly rather than left to core's fallback, and
	 * the identity field is always present — core fills in `plugin` for us but
	 * never fills in `theme`.
	 *
	 * @param string $identity Plugin file (`dp-core/dp-core.php`) or stylesheet (`dpaternina`).
	 * @param string $update_uri The package's `Update URI` header, which core uses as the offer id.
	 * @return array<string, string>
	 */
	public function to_offer( string $identity, string $update_uri ): array {
		$offer = array(
			'id'                          => $update_uri,
			'slug'                        => $this->slug,
			$this->type->identity_field() => $identity,
			'version'                     => $this->version,
			'new_version'                 => $this->version,
			'url'                         => $this->url,
			'package'                     => $this->package,
		);

		$optional = array(
			'requires'     => $this->requires,
			'requires_php' => $this->requires_php,
			'tested'       => $this->tested,
		);

		foreach ( $optional as $key => $value ) {
			if ( '' !== $value ) {
				$offer[ $key ] = $value;
			}
		}

		return $offer;
	}

	/**
	 * Read a string field, defaulting to '' and never returning anything else.
	 *
	 * @param array<array-key, mixed> $data  Decoded payload.
	 * @param string                  $key   Field name.
	 * @return string
	 */
	private static function text( array $data, string $key ): string {
		$value = $data[ $key ] ?? '';

		return is_string( $value ) ? trim( $value ) : '';
	}
}
