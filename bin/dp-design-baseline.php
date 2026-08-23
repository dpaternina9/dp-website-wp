<?php
/**
 * Regenerate (or verify) the work template's design-parity baseline.
 *
 * Usage:
 *   composer design:baseline   # write tests/e2e/fixtures/work-design-baseline.json
 *   composer design:check      # exit 1 if the committed fixture is not what we would write
 *
 * The same shape as `bin/dp-tokens.php`, and for the same reason: a value that
 * came from `design-source/` is only trustworthy while something re-reads
 * `design-source/` and fails when the two have drifted apart.
 * See docs/adr/0012-design-parity-harness.md.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use Throwable;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$dp_site_root = dirname( __DIR__ );

require_once $dp_site_root . '/vendor/autoload.php';

$dp_site_check = in_array( '--check', array_slice( $argv ?? array(), 1 ), true );
$dp_site_path  = $dp_site_root . '/' . DesignBaseline::OUTPUT;

/**
 * Write a line to a stream. This runs before WordPress exists, so neither
 * WP_Filesystem nor the WordPress escaping helpers are available.
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
	$dp_site_rendered = DesignBaseline::for_repository( $dp_site_root )->render();
} catch ( Throwable $dp_site_error ) {
	$dp_site_say( STDERR, 'Design baseline generation failed:' );
	$dp_site_say( STDERR, '  ' . $dp_site_error->getMessage() );
	exit( 1 );
}

if ( $dp_site_check ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$dp_site_current = is_readable( $dp_site_path ) ? file_get_contents( $dp_site_path ) : false;

	if ( $dp_site_current === $dp_site_rendered ) {
		$dp_site_say( STDOUT, DesignBaseline::OUTPUT . ' is up to date.' );
		exit( 0 );
	}

	$dp_site_say( STDERR, DesignBaseline::OUTPUT . ' is stale.' );
	$dp_site_say( STDERR, 'The design source has moved and the committed baseline has not. Run: composer design:baseline' );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$dp_site_written = file_put_contents( $dp_site_path, $dp_site_rendered );

if ( false === $dp_site_written ) {
	$dp_site_say( STDERR, 'Cannot write ' . DesignBaseline::OUTPUT );
	exit( 1 );
}

$dp_site_say( STDOUT, sprintf( 'Wrote %s (%d bytes).', DesignBaseline::OUTPUT, $dp_site_written ) );
exit( 0 );
