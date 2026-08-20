<?php
/**
 * Seed the design's fixture, from a file rather than from the command registry.
 *
 * `wp dp seed` is the command you want. It ships inside the plugin, so it is
 * available wherever the plugin is — including in production, and including in
 * the wp-env development container, where this repository is not mounted.
 *
 * This file is the same run, reachable when the command registry is not: an
 * older build of the plugin, a site where it has been deactivated, or a debug
 * session where you want to change the seeder and re-run without a release.
 * `.wp-env.json` maps the repository into the **tests** environment only, so:
 *
 *     npm run env:cli -- wp dp seed --fresh
 *     npx wp-env run tests-cli wp eval-file wp-content/dp-repo/bin/seed.php fresh
 *
 * `eval-file` consumes `--flags` itself, so freshness is a positional argument
 * here rather than the `--fresh` the command takes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Bin;

use DP\Core\Fixture\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! class_exists( Seeder::class ) ) {
	\WP_CLI::error( 'dp-core is not loaded, so there is no seeder to run. Activate the plugin first.' );
}

/**
 * Positional arguments from `wp eval-file`.
 *
 * @var list<string> $args
 */
$dp_core_seed_args  = isset( $args ) && is_array( $args ) ? $args : array();
$dp_core_seed_fresh = in_array( 'fresh', $dp_core_seed_args, true );

$dp_core_seed_report = Seeder::create()->seed( $dp_core_seed_fresh );

foreach ( $dp_core_seed_report->lines() as $dp_core_seed_line ) {
	\WP_CLI::log( $dp_core_seed_line );
}

\WP_CLI::success( $dp_core_seed_report->summary() );
