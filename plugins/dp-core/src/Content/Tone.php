<?php
/**
 * The tone vocabulary.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- PHPCompatibility does not model enums, and reads `match ( $this )` in an enum method as
// `$this` outside an object. It is valid PHP 8.1, and this project targets 8.4.
/**
 * The five tones the design uses to colour a label, a chip or a bar.
 *
 * `SectionHead.dc.html` names them exactly: teal, pink, gold, purple, muted.
 * They exist as an enum so tone is never a loose string — every meta field that
 * carries one is registered with this list as its REST `enum`, so an unknown
 * tone is rejected at the edge instead of being discovered at render time.
 *
 * Two accessors, because the design system draws a hard line between the two
 * (CLAUDE.md section 5): `--dp-*` hues are for fills, `--hue-*` for text. A brand
 * hue used directly as text fails AA.
 */
enum Tone: string {

	case Teal   = 'teal';
	case Pink   = 'pink';
	case Gold   = 'gold';
	case Purple = 'purple';
	case Muted  = 'muted';

	/**
	 * The custom property to use when this tone colours **text**.
	 *
	 * @return string A CSS `var()` expression.
	 */
	public function text_variable(): string {
		return match ( $this ) {
			self::Muted => 'var(--text-muted)',
			default     => 'var(--hue-' . $this->value . ')',
		};
	}

	/**
	 * The custom property to use when this tone colours a **fill**.
	 *
	 * @return string A CSS `var()` expression.
	 */
	public function fill_variable(): string {
		return match ( $this ) {
			self::Muted => 'var(--text-muted)',
			default     => 'var(--dp-' . $this->value . ')',
		};
	}

	/**
	 * Every tone, as stored.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map( static fn ( self $tone ): string => $tone->value, self::cases() );
	}

	/**
	 * Every accepted meta value: the tones plus the empty string.
	 *
	 * The empty string means "not set — derive it", which is what the design
	 * does for a post kicker (tone follows series membership). Including it in
	 * the schema is what lets the field have a sane default without widening the
	 * type to a free string.
	 *
	 * @return list<string>
	 */
	public static function meta_values(): array {
		return array_merge( array( '' ), self::values() );
	}

	/**
	 * Resolve a stored meta value, treating anything unrecognised as unset.
	 *
	 * @param string $value Stored meta value.
	 * @return self|null
	 */
	public static function try_from_meta( string $value ): ?self {
		return self::tryFrom( $value );
	}
}
