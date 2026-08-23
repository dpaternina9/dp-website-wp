<?php
/**
 * The handful of facts about the site itself that the chrome prints.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

/**
 * A block bindings source for values the site knows and a template cannot.
 *
 * There is exactly one of them today, and it is the year in the footer's
 * copyright line. `SiteFooter.logic.js` computes `new Date().getFullYear()` and
 * the design prints "© 2026 DAVID PATERNINA"; ADR-0006 recorded dropping the
 * year as a deviation, on the reasoning that a template part is static markup
 * and a year is not. That reasoning was sound and the conclusion was not: a
 * bindings source is precisely the mechanism for a value a template cannot
 * carry, and it costs one allowlisted key.
 *
 * Deliberately not a general "site data" reader. The allowlist below is the
 * whole of what this source will answer, an unlisted key returns null, and null
 * leaves the bound block's own content in place — so a template that asks for
 * something this source does not know prints what the editor typed rather than
 * an empty paragraph.
 *
 * The year is the site's, not the visitor's: `wp_date()` reads the timezone in
 * Settings → General, so the line turns over when David's year does.
 */
final class SiteFacts {

	/**
	 * The bindings source name.
	 */
	public const SOURCE = 'dpaternina/site';

	/**
	 * Every key this source will answer.
	 *
	 * @var list<string>
	 */
	private const KEYS = array( 'year' );

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_source( ... ) );
	}

	/**
	 * Register the bindings source.
	 *
	 * @return void
	 */
	public function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Site facts', 'dpaternina' ),
				'get_value_callback' => $this->value( ... ),
			)
		);
	}

	/**
	 * Resolve one bound value.
	 *
	 * @param array<string, mixed> $arguments The binding's arguments; `key` selects the fact.
	 * @param mixed                $block     The block being rendered. Unused.
	 * @return string|null Null leaves the block's own content in place.
	 */
	public function value( array $arguments, mixed $block = null ): ?string {
		unset( $block );

		$key = isset( $arguments['key'] ) && is_string( $arguments['key'] ) ? $arguments['key'] : '';

		if ( ! in_array( $key, self::KEYS, true ) ) {
			return null;
		}

		return $this->fact( $key, isset( $arguments['text'] ) && is_string( $arguments['text'] ) ? $arguments['text'] : '' );
	}

	/**
	 * One allowlisted fact, optionally wrapped in a template.
	 *
	 * `text` is a `sprintf` pattern so the copy around the value stays in the
	 * template — and stays translatable — instead of being concatenated here.
	 *
	 * @param string $key      Which fact.
	 * @param string $template A `sprintf` pattern with a single `%s`, or empty.
	 * @return string
	 */
	private function fact( string $key, string $template ): string {
		$value = match ( $key ) {
			default => (string) wp_date( 'Y' ),
		};

		if ( '' === $template || 1 !== substr_count( $template, '%s' ) ) {
			return $value;
		}

		return sprintf( $template, $value );
	}
}
