<?php
/**
 * PHPUnit bootstrap.
 *
 * One entry point, two harnesses. Which one loads is decided by the environment,
 * not by a flag, so a suite can never be run against the wrong harness:
 *
 * - `WP_TESTS_DIR` set  → the real WordPress test suite (the wp-env `tests`
 *   container exports it as /wordpress-phpunit). Integration suite.
 * - otherwise           → Composer autoloader only, with Brain Monkey available.
 *   Unit suite.
 *
 * Running the Integration suite outside the container therefore fails loudly
 * rather than reporting an empty pass, which is the point.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests;

$dp_site_root = dirname( __DIR__ );

require_once $dp_site_root . '/vendor/autoload.php';

$dp_site_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $dp_site_tests_dir || '' === $dp_site_tests_dir ) {
	// Unit harness. Brain Monkey is autoloaded; each test case starts and stops it.
	return;
}

$dp_site_tests_dir = rtrim( $dp_site_tests_dir, '/\\' );

if ( ! is_readable( $dp_site_tests_dir . '/includes/functions.php' ) ) {
	// WP_Filesystem does not exist yet: this runs before WordPress is loaded, and
	// the whole point of the branch is that WordPress could not be found.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite(
		STDERR,
		sprintf(
			'WP_TESTS_DIR is set to %s but the WordPress test suite is not there.%s'
			. 'Run the Integration suite with: composer test:integration%s',
			$dp_site_tests_dir,
			PHP_EOL,
			PHP_EOL
		)
	);
	exit( 1 );
}

require_once $dp_site_tests_dir . '/includes/functions.php';

/*
 * Load the packages under test the way WordPress would, but from the monorepo
 * checkout rather than from wp-content, so the code under test is the code in
 * the working tree.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $dp_site_root ): void {
		require_once $dp_site_root . '/plugins/dp-core/dp-core.php';
	}
);

require $dp_site_tests_dir . '/includes/bootstrap.php';
