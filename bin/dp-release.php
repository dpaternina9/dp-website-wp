<?php
/**
 * Release tooling: keys, ZIPs and signed manifests.
 *
 * Usage:
 *   php bin/dp-release.php keygen [--write]
 *   php bin/dp-release.php zip --source=DIR --slug=NAME --out=FILE
 *   php bin/dp-release.php manifest --type=theme|plugin --slug=NAME --version=X.Y.Z \
 *                                   --package=URL [--url=URL] [--requires=6.6] \
 *                                   [--requires-php=8.4] [--tested=7.1] --out=FILE
 *   php bin/dp-release.php verify --manifest=FILE [--key=BASE64]
 *
 * `manifest` reads the base64 Ed25519 secret key from the DP_UPDATE_SIGNING_KEY
 * environment variable and never accepts it as an argument, because arguments
 * end up in `ps` output and in CI logs.
 *
 * This is build tooling, not plugin code: it runs on a developer's machine and
 * in GitHub Actions, never inside WordPress. It reuses the plugin's own
 * Verifier so that "the release we published" and "what the site will accept"
 * are checked by the same code rather than by two implementations that agree
 * until they don't.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Update\Build;

/*
 * WordPress is not loaded here and never will be, so `wp_json_encode()`,
 * `WP_Filesystem` and the rest of the alternatives WPCS wants do not exist to
 * call. And base64 is the transport for an Ed25519 key and signature, not a way
 * of hiding code — the thing the obfuscation sniff is looking for.
 */
// phpcs:disable WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

use DP\Core\Update\ManifestError;
use DP\Core\Update\PublicKey;
use DP\Core\Update\Verifier;
use Throwable;
use ZipArchive;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$dp_core_release_root = dirname( __DIR__ );

require_once $dp_core_release_root . '/vendor/autoload.php';

/**
 * Write a line to a stream. WordPress is not loaded, so neither is WP_Filesystem.
 *
 * @param resource $stream  Open stream.
 * @param string   $message Message to write.
 * @return void
 */
function say( $stream, string $message ): void {
	fwrite( $stream, $message . PHP_EOL );
}

/**
 * Abort with a message on stderr.
 *
 * @param string $message Why we are stopping.
 * @return never
 */
function fail( string $message ): never {
	say( STDERR, 'dp-release: ' . $message );
	exit( 1 );
}

/**
 * Parse `--key=value` and `--flag` arguments.
 *
 * @param string[] $argv Raw arguments, excluding the script name and subcommand.
 * @return array<string, string>
 */
function options( array $argv ): array {
	$options = array();

	foreach ( $argv as $argument ) {
		if ( ! str_starts_with( $argument, '--' ) ) {
			continue;
		}

		$argument = substr( $argument, 2 );
		$split    = strpos( $argument, '=' );

		if ( false === $split ) {
			$options[ $argument ] = '1';
			continue;
		}

		$options[ substr( $argument, 0, $split ) ] = substr( $argument, $split + 1 );
	}

	return $options;
}

/**
 * Read a required option, or abort.
 *
 * @param array<string, string> $options Parsed options.
 * @param string                $name    Option name, without dashes.
 * @return string
 */
function required( array $options, string $name ): string {
	$value = $options[ $name ] ?? '';

	if ( '' === trim( $value ) ) {
		fail( 'missing required option --' . $name );
	}

	return trim( $value );
}

/**
 * Generate an Ed25519 signing keypair.
 *
 * @param array<string, string> $options Parsed options.
 * @param string                $root    Repository root.
 * @return void
 */
function keygen( array $options, string $root ): void {
	$pair   = sodium_crypto_sign_keypair();
	$public = base64_encode( sodium_crypto_sign_publickey( $pair ) );
	$secret = base64_encode( sodium_crypto_sign_secretkey( $pair ) );

	$key_file = $root . '/plugins/dp-core/src/Update/PublicKey.php';

	if ( isset( $options['write'] ) ) {
		$source = file_get_contents( $key_file );

		if ( false === $source ) {
			fail( 'could not read ' . $key_file );
		}

		$patched = preg_replace(
			"/public const COMPILED = '[^']*';/",
			"public const COMPILED = '" . $public . "';",
			$source,
			1,
			$count
		);

		if ( ! is_string( $patched ) || 1 !== $count ) {
			fail( 'could not find the COMPILED constant in ' . $key_file );
		}

		file_put_contents( $key_file, $patched );
		say( STDOUT, 'Wrote the public key into plugins/dp-core/src/Update/PublicKey.php. Commit that change.' );
	} else {
		say( STDOUT, 'Public key (paste into PublicKey::COMPILED, or re-run with --write):' );
		say( STDOUT, '  ' . $public );
	}

	say( STDOUT, '' );
	say( STDOUT, 'Secret key — store as the GitHub Actions secret DP_UPDATE_SIGNING_KEY and then' );
	say( STDOUT, 'delete it from your scrollback. It is not written to disk and cannot be recovered:' );
	say( STDOUT, '  ' . $secret );

	sodium_memzero( $pair );
}

/**
 * Build a ZIP whose single top-level directory is the package slug.
 *
 * WordPress installs an update by unpacking the archive into wp-content and
 * expecting exactly one directory inside. Getting that wrong produces a
 * plugin installed at `wp-content/plugins/dp-core-1.2.3/`, silently beside the
 * one that is active — which is why this is a build step rather than a
 * `zip -r` in a workflow.
 *
 * @param array<string, string> $options Parsed options.
 * @return void
 */
function zip( array $options ): void {
	$source = rtrim( required( $options, 'source' ), '/' );
	$slug   = required( $options, 'slug' );
	$out    = required( $options, 'out' );

	if ( ! is_dir( $source ) ) {
		fail( 'source directory does not exist: ' . $source );
	}

	if ( file_exists( $out ) ) {
		unlink( $out );
	}

	$archive = new ZipArchive();

	if ( true !== $archive->open( $out, ZipArchive::CREATE ) ) {
		fail( 'could not create ' . $out );
	}

	$files = array();
	$walk  = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $walk as $item ) {
		if ( ! $item instanceof \SplFileInfo || ! $item->isFile() ) {
			continue;
		}

		$files[] = $item->getPathname();
	}

	// Deterministic order: two builds of the same tree should produce the same listing.
	sort( $files, SORT_STRING );

	foreach ( $files as $path ) {
		$archive->addFile( $path, $slug . '/' . ltrim( substr( $path, strlen( $source ) ), '/' ) );
	}

	$count = count( $files );

	if ( ! $archive->close() ) {
		fail( 'could not finish writing ' . $out );
	}

	say( STDOUT, sprintf( 'Wrote %s (%d files under %s/).', $out, $count, $slug ) );
}

/**
 * Emit a signed manifest envelope.
 *
 * @param array<string, string> $options Parsed options.
 * @return void
 */
function manifest( array $options ): void {
	$secret_b64 = getenv( 'DP_UPDATE_SIGNING_KEY' );

	if ( ! is_string( $secret_b64 ) || '' === trim( $secret_b64 ) ) {
		fail( 'DP_UPDATE_SIGNING_KEY is not set. It holds the base64 Ed25519 secret key.' );
	}

	$secret = base64_decode( trim( $secret_b64 ), true );

	if ( false === $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
		fail( 'DP_UPDATE_SIGNING_KEY is not a base64 Ed25519 secret key.' );
	}

	$payload = array(
		'type'         => required( $options, 'type' ),
		'slug'         => required( $options, 'slug' ),
		'version'      => required( $options, 'version' ),
		'package'      => required( $options, 'package' ),
		'url'          => $options['url'] ?? '',
		'requires'     => $options['requires'] ?? '',
		'requires_php' => $options['requires-php'] ?? '',
		'tested'       => $options['tested'] ?? '',
		'released'     => gmdate( 'c' ),
	);

	$payload_json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

	if ( false === $payload_json ) {
		fail( 'could not encode the manifest payload' );
	}

	$envelope = array(
		'schema'    => Verifier::SCHEMA,
		'payload'   => base64_encode( $payload_json ),
		'signature' => base64_encode( sodium_crypto_sign_detached( $payload_json, $secret ) ),
	);

	sodium_memzero( $secret );

	$envelope_json = json_encode( $envelope, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

	if ( false === $envelope_json ) {
		fail( 'could not encode the manifest envelope' );
	}

	$out = required( $options, 'out' );

	file_put_contents( $out, $envelope_json . PHP_EOL );

	say( STDOUT, 'Wrote ' . $out . '.' );
}

/**
 * Verify a manifest with the same code the site will use.
 *
 * @param array<string, string> $options Parsed options.
 * @return void
 */
function verify( array $options ): void {
	$path = required( $options, 'manifest' );

	if ( ! is_readable( $path ) ) {
		fail( 'cannot read ' . $path );
	}

	$body = file_get_contents( $path );

	if ( false === $body ) {
		fail( 'cannot read ' . $path );
	}

	$key = $options['key'] ?? PublicKey::COMPILED;

	if ( '' === $key ) {
		fail( 'no public key: pass --key=BASE64, or run `php bin/dp-release.php keygen --write` first.' );
	}

	try {
		$parsed = ( new Verifier( $key ) )->open( $body );
	} catch ( ManifestError $error ) {
		fail( 'manifest rejected: ' . $error->getMessage() );
	}

	say(
		STDOUT,
		sprintf(
			'Manifest verifies: %s %s %s -> %s',
			$parsed->type->value,
			$parsed->slug,
			$parsed->version,
			$parsed->package
		)
	);
}

$dp_core_release_argv = $argv ?? array();
$dp_core_release_verb = $dp_core_release_argv[1] ?? '';
$dp_core_release_opts = options( array_slice( $dp_core_release_argv, 2 ) );

try {
	switch ( $dp_core_release_verb ) {
		case 'keygen':
			keygen( $dp_core_release_opts, $dp_core_release_root );
			break;
		case 'zip':
			zip( $dp_core_release_opts );
			break;
		case 'manifest':
			manifest( $dp_core_release_opts );
			break;
		case 'verify':
			verify( $dp_core_release_opts );
			break;
		default:
			say( STDERR, 'Usage: php bin/dp-release.php <keygen|zip|manifest|verify> [options]' );
			say( STDERR, 'See the docblock at the top of this file.' );
			exit( 1 );
	}
} catch ( Throwable $dp_core_release_error ) {
	fail( $dp_core_release_error->getMessage() );
}
