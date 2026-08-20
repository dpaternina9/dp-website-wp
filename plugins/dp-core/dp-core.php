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
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.4
 * Author:            David Paternina
 * Author URI:        https://dpaternina.com
 * License:           Proprietary
 * License URI:       https://dpaternina.com
 * Update URI:        https://updates.dpaternina.com/core
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
const VERSION = '0.1.0';

require_once __DIR__ . '/vendor/autoload.php';

Plugin::boot( __FILE__, VERSION );
