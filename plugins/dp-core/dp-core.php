<?php
/**
 * Plugin bootstrap for dP Core.
 *
 * @package DP\Core
 *
 * @wordpress-plugin
 * Plugin Name:       dP Core
 * Plugin URI:        https://dpaternina.com
 * Description:       Content model, dynamic blocks, REST routes and WP-CLI commands for dpaternina.com. Switching themes must never delete data, so it lives here.
 * Version:           1.0.2
 * Requires at least: 6.6
 * Requires PHP:      8.4
 * Author:            David Paternina
 * Author URI:        https://dpaternina.com
 * License:           Proprietary
 * License URI:       https://dpaternina.com
 * Update URI:        https://wp-updates.fanxie.cloud/dpaternina/plugin-dp-core
 * Text Domain:       dp-core
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace DP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version. Stamped from the git tag by the release workflow (Phase 2).
 */
const VERSION = '1.0.2';

require_once __DIR__ . '/vendor/autoload.php';

Plugin::boot( __FILE__, VERSION );

/*
 * The one thing this plugin leaves behind that must be cleared on deactivation:
 * an hourly WP-Cron entry pointing at a hook nothing answers any more would be
 * an hourly error in somebody's log. Content is deliberately left alone —
 * deactivating a plugin is not deleting a site's videos.
 */
register_deactivation_hook( __FILE__, array( Watch\Schedule::class, 'unschedule' ) );
