<?php
/**
 * Regenerate (or verify) the theme's token bridge stylesheet.
 *
 * Usage:
 *   composer tokens:build   # write themes/dpaternina/assets/css/tokens.css
 *   composer tokens:check   # exit 1 if the committed file is not what we would write
 *
 * The generator lives with the test that enforces it (tests/Support), because the
 * committed stylesheet and the parity assertion are two views of one guarantee:
 * design-source/ is the only place a token value is ever authored.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;
use Throwable;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$dp_site_root = dirname( __DIR__ );

require_once $dp_site_root . '/vendor/autoload.php';

$dp_site_check = in_array( '--check', array_slice( $argv ?? array(), 1 ), true );
$dp_site_path  = $dp_site_root . '/' . TokenBridge::OUTPUT;

/**
 * Write a line to a stream. The generator runs before WordPress exists, so
 * WP_Filesystem and the WordPress escaping helpers are both unavailable.
 *
 * @param resource $stream  Open stream.
 * @param string   $message Message to write.
 * @return void
 */
$dp_site_say = static function ( $stream, string $message ): void {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( $stream, $message . PHP_EOL );
};

try {
	$dp_site_rendered = TokenBridge::for_repository( $dp_site_root )->render();
} catch ( Throwable $dp_site_error ) {
	$dp_site_say( STDERR, 'Token bridge generation failed:' );
	$dp_site_say( STDERR, '  ' . $dp_site_error->getMessage() );
	exit( 1 );
}

if ( $dp_site_check ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$dp_site_current = is_readable( $dp_site_path ) ? file_get_contents( $dp_site_path ) : false;

	if ( $dp_site_current === $dp_site_rendered ) {
		$dp_site_say( STDOUT, TokenBridge::OUTPUT . ' is up to date.' );
		exit( 0 );
	}

	$dp_site_say( STDERR, TokenBridge::OUTPUT . ' is stale.' );
	$dp_site_say( STDERR, 'The design source has moved and the generated bridge has not. Run: composer tokens:build' );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$dp_site_written = file_put_contents( $dp_site_path, $dp_site_rendered );

if ( false === $dp_site_written ) {
	$dp_site_say( STDERR, 'Cannot write ' . TokenBridge::OUTPUT );
	exit( 1 );
}

$dp_site_say( STDOUT, sprintf( 'Wrote %s (%d bytes).', TokenBridge::OUTPUT, $dp_site_written ) );
exit( 0 );
