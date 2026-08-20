<?php
/**
 * Keeping hierarchy templates out of the page-template dropdown.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use WP_Block_Template;

/**
 * `taxonomy-dp_series.html` is not a page template, and must not be offered as one.
 *
 * WordPress decides whether a block template is "custom" — meaning a thing David
 * can assign to a page from the admin — by asking whether its slug is one of the
 * sixteen names `get_default_block_template_types()` knows. That list has
 * `taxonomy` in it but not `taxonomy-dp_series`, so the most specific template
 * in the hierarchy is classified as custom and turns up in the dropdown next to
 * Contact and Résumé.
 *
 * That is the coupling CLAUDE.md §5.1 is about, arriving from the other
 * direction: nobody wrote a route, but the admin now offers to bind a template
 * that queries a taxonomy term to a page that has none. Assigning it would
 * render "Start with these" against whatever the page's query returned.
 *
 * The rule below is the one core is missing, stated once: a slug of the form
 * `{known-type}-{qualifier}` is a hierarchy template, not a custom one. It is
 * described rather than listed so a `category-…` or `single-…` template a later
 * phase adds is covered without an edit here. Nothing is hidden — the template
 * still appears in the site editor, filed with the hierarchy where it belongs.
 */
final class TemplateHierarchy {

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'get_block_templates', $this->reclassify( ... ), 10, 3 );
	}

	/**
	 * Mark hierarchy templates as what they are.
	 *
	 * @param array<int, WP_Block_Template> $templates The templates found.
	 * @param array<string, mixed>          $query     The query they were found for. Unused.
	 * @param string                        $type      `wp_template` or `wp_template_part`.
	 * @return array<int, WP_Block_Template>
	 */
	public function reclassify( array $templates, array $query, string $type ): array {
		unset( $query );

		if ( 'wp_template' !== $type ) {
			return $templates;
		}

		$known = array_keys( get_default_block_template_types() );

		foreach ( $templates as $template ) {
			if ( ! $template instanceof WP_Block_Template || true !== $template->is_custom ) {
				continue;
			}

			if ( $this->is_hierarchy_slug( $template->slug, $known ) ) {
				$template->is_custom = false;
			}
		}

		return $templates;
	}

	/**
	 * Whether a slug qualifies one of core's own template types.
	 *
	 * @param string            $slug  The template's slug.
	 * @param array<int, mixed> $known The default template type names.
	 * @return bool
	 */
	private function is_hierarchy_slug( string $slug, array $known ): bool {
		foreach ( $known as $type ) {
			if ( is_string( $type ) && str_starts_with( $slug, $type . '-' ) ) {
				return true;
			}
		}

		return false;
	}
}
