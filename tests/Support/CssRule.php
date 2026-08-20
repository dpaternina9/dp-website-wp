<?php
/**
 * One top-level CSS rule, as parsed out of a stylesheet.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

/**
 * A selector, its declaration block, and the exact source text it came from.
 *
 * `verbatim` keeps the leading comment and the original whitespace so a rule can
 * be copied from the design system into the theme without being reformatted.
 * That is what lets the token bridge carry `.dp-light` and `.dp-dark` across
 * unchanged, and what lets the parity test prove it did.
 */
final class CssRule {

	/**
	 * Constructor.
	 *
	 * @param string $selector  The rule's selector, whitespace-normalised.
	 * @param string $body      The declaration block, without the braces.
	 * @param string $verbatim  The rule's source text, including any leading comment.
	 */
	public function __construct(
		public readonly string $selector,
		public readonly string $body,
		public readonly string $verbatim
	) {}

	/**
	 * The custom properties this rule declares, in source order.
	 *
	 * @return array<string, string> Property name (with the leading `--`) to raw value.
	 */
	public function custom_properties(): array {
		return CssParser::custom_properties( $this->body );
	}
}
