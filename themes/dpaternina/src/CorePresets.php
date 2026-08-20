<?php
/**
 * Removes WordPress's own presets so only the design system's remain.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme;

use WP_Theme_JSON;
use WP_Theme_JSON_Data;

/**
 * Empties core's default palette, gradients, font sizes, spacing and shadows.
 *
 * `theme.json` already sets `defaultPalette`, `defaultGradients`,
 * `defaultFontSizes`, `defaultSpacingSizes` and `shadow.defaultPresets` to
 * false. Those flags stop the editor *offering* core's presets, but they do not
 * stop WordPress *emitting* them: `prevent_override` only decides whether a
 * theme preset may shadow a default one. Core's twelve colours, twelve
 * gradients, four font sizes, seven spacing steps and five shadows are still
 * declared as custom properties and as `.has-…` utility classes on every page —
 * about 7 KB of CSS for values this design will never use.
 *
 * Emptying them at the source is what "the editor offers this system or
 * nothing" actually means, and it removes the possibility of a pasted block or
 * an imported pattern carrying `has-vivid-red-color` and rendering a colour
 * that is not in the palette.
 *
 * `dimensions.aspectRatios` is deliberately left alone: core's own image and
 * cover block CSS resolves `--wp--preset--aspect-ratio--*`, so removing those
 * would break a core feature rather than a core opinion.
 *
 * Consequence for Phase 9: content migrated from the old site that carries a
 * core colour class loses that colour and falls back to the body text colour.
 * The migration maps old colours onto this palette, so that is the intended
 * outcome — but it is the reason to run the migration before the cutover rather
 * than after.
 */
final class CorePresets {

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_theme_json_data_default', $this->strip( ... ) );
	}

	/**
	 * Replace core's preset lists with empty ones.
	 *
	 * @param WP_Theme_JSON_Data $data Core's `theme.json` data.
	 * @return WP_Theme_JSON_Data
	 */
	public function strip( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
		return $data->update_with(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'palette'   => array(),
						'gradients' => array(),
						'duotone'   => array(),
					),
					'typography' => array(
						'fontSizes' => array(),
					),
					'spacing'    => array(
						'spacingSizes' => array(),
						'spacingScale' => array( 'steps' => 0 ),
					),
					'shadow'     => array(
						'presets' => array(),
					),
				),
			)
		);
	}
}
