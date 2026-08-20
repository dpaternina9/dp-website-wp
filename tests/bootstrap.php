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

/*
 * Make `dpaternina` the active theme for the whole run.
 *
 * The WordPress test suite installs with its own `default` theme and never
 * activates anything else, so without this every assertion about theme.json,
 * editor styles, fonts or custom templates would be made against a theme we did
 * not write — and would fail for the wrong reason, or worse, pass. This is the
 * same lesson the e2e global setup learned in Phase 0 (docs/adr/0001): a suite
 * establishes what it asserts on.
 *
 * `wp_tests_options` is the suite's own mechanism: each key becomes a
 * `pre_option_` filter, so the theme is active from the first query onwards
 * rather than being switched into place afterwards.
 */
$GLOBALS['wp_tests_options'] = array(
	'stylesheet' => 'dpaternina',
	'template'   => 'dpaternina',
);

/*
 * The suite resets `$wp_theme_directories` to its own fixture directory.
 * `wp-settings.php` adds `wp-content/themes` back on every load, which is where
 * wp-env mounts the theme; registering it here as well costs nothing and means
 * the bootstrap does not depend on that ordering staying true.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		register_theme_directory( WP_CONTENT_DIR . '/themes' );
	}
);

require $dp_site_tests_dir . '/includes/bootstrap.php';
