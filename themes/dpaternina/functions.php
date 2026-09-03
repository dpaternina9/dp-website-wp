<?php
/**
 * Theme bootstrap for dPaternina.
 *
 * The theme carries its own Composer autoloader for the same reason `dp-core`
 * does (docs/adr/0001, §1): the release artefact is a zip of this directory and
 * nothing else, so the autoloader has to travel with it.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme version. Stamped from the git tag by the release workflow (Phase 2).
 */
const VERSION = '1.1.0';

require_once __DIR__ . '/vendor/autoload.php';

Theme::boot( __DIR__, VERSION );
