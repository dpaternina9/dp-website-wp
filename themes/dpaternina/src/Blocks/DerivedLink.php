<?php
/**
 * The markup a computed link renders as, resolved or not.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

/**
 * One button, drawn the way `core/buttons` draws one, around a URL nobody typed.
 *
 * Three blocks in this theme produce a link whose URL cannot be written into a
 * template: the archive of the series the post being read belongs to, the
 * résumé's PDF, and the feed. ADR-0018 keeps exactly those three and deletes
 * everything else that used to resolve an href at render time — a link to a
 * page David made is now a link he sets in the site editor, once, on a
 * `core/button` like any other.
 *
 * What survives with them is ADR-0008's failure mode, and only for them: a URL
 * that does not resolve keeps its element and loses its `href`. The element is
 * then not a link, is not focusable and cannot reach a 404, while still being
 * visibly there — which is what stops "the button is missing on the front end"
 * from being the symptom of both "not set up yet" and "the code is broken".
 * `data-dp-destination` earns its place for the same reason it did in ADR-0008:
 * it is the one thing in the rendered DOM that says this anchor's href was
 * computed, and which computation produced it. The class stays
 * `dp-destination-unset` because the stylesheet, the design's disabled opacity
 * and every diagnosis David has already done all use that name.
 *
 * The markup is `core/buttons`' own — wrapper, layout classes, button, link —
 * because these three sit where a `core/button` sat, inside the design's
 * `.dp-series-footer-action`, `.dp-resume-download` and footer meta rules, and
 * every one of those is written against core's class names.
 */
final class DerivedLink {

	/**
	 * The class an anchor gets when its URL does not resolve.
	 *
	 * The stylesheet dims it to the design system's own disabled opacity and
	 * takes the pointer away. It is also the hook for finding every one of them
	 * at once, and the thing the integration suite asserts on.
	 */
	public const UNRESOLVED_CLASS = 'dp-destination-unset';

	/**
	 * The layout classes `core/buttons` emits for its default flex layout.
	 *
	 * Written out rather than derived because these blocks render their own
	 * wrapper: `wp_get_layout_style()` is reachable only from inside a block
	 * that declares layout support, and declaring one here would offer David a
	 * layout control on a block that holds exactly one link.
	 */
	private const LAYOUT_CLASSES = 'is-layout-flex wp-block-buttons-is-layout-flex';

	/**
	 * Render the button.
	 *
	 * @param string      $wrapper_attributes The wrapper's attributes, from `get_block_wrapper_attributes()`.
	 * @param string      $button_class       The presentational class on the button, e.g. `dp-button-quiet`.
	 * @param string|null $url                Where it points, or null when nothing resolves.
	 * @param string      $label              The visible text.
	 * @param string      $name               What the anchor announces in `data-dp-destination`.
	 * @return string
	 */
	public function render( string $wrapper_attributes, string $button_class, ?string $url, string $label, string $name ): string {
		return sprintf(
			'<div %1$s><div class="wp-block-button %2$s">%3$s</div></div>',
			$wrapper_attributes,
			esc_attr( $button_class ),
			$this->anchor( $url, $label, $name )
		);
	}

	/**
	 * The wrapper class list a block passes to `get_block_wrapper_attributes()`.
	 *
	 * The design's own class is asked for here rather than written into the
	 * template as a `className`, and the reason is the editor. `ServerSideRender`
	 * draws this markup inside a wrapper of its own that `useBlockProps()` has
	 * already put the block's `className` on — so a class set in the template
	 * appears **twice** in the canvas and once on the page, and any rule that
	 * spaces it then measures differently in the two contexts. Asked for here it
	 * lands on the rendered element in both, and the site editor's parity sweep
	 * lines the two up. A `className` David adds is still merged in on top.
	 *
	 * @param string $design The design's class for this link's surroundings.
	 * @return string
	 */
	public function wrapper_class( string $design ): string {
		return 'wp-block-buttons ' . self::LAYOUT_CLASSES . ' ' . $design;
	}

	/**
	 * The anchor itself, linked or inert.
	 *
	 * An `<a>` with no `href` has no implicit role and is not focusable, so it
	 * is already inert to the keyboard. `role` and `aria-disabled` are what make
	 * it announce as an unavailable link rather than as a stray run of text.
	 *
	 * @param string|null $url   Where it points, or null.
	 * @param string      $label The visible text.
	 * @param string      $name  The value of `data-dp-destination`.
	 * @return string
	 */
	private function anchor( ?string $url, string $label, string $name ): string {
		if ( null === $url || '' === $url ) {
			return sprintf(
				'<a class="wp-block-button__link wp-element-button %1$s" role="link" aria-disabled="true" data-dp-destination="%2$s">%3$s</a>',
				esc_attr( self::UNRESOLVED_CLASS ),
				esc_attr( $name ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<a class="wp-block-button__link wp-element-button" href="%1$s" data-dp-destination="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr( $name ),
			esc_html( $label )
		);
	}
}
