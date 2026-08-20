<?php
/**
 * One computed timeline bar.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

/**
 * The four numbers a bar needs, already clamped.
 *
 * Percentages are relative to the year track; `min_width` is CSS pixels. The
 * two units genuinely coexist in the design — the position is proportional and
 * the floor is absolute — so the object keeps them apart rather than pretending
 * to a single scale.
 *
 * Immutable and arithmetic-free: everything was decided in `Geometry`. Phase 6
 * renders one of these; it does not recompute one.
 */
final class Bar {

	/**
	 * How many decimal places a percentage keeps in generated CSS.
	 *
	 * Four is well below a subpixel at any plausible track width, and it stops
	 * the markup churning on floating-point noise, which would defeat caching
	 * and make rendered-markup assertions brittle.
	 *
	 * @var int
	 */
	private const PRECISION = 4;

	/**
	 * Constructor.
	 *
	 * @param float   $left      Distance from the left of the track, in percent.
	 * @param float   $width     Bar width, in percent, already clamped.
	 * @param float   $max_width The largest width that still fits, in percent.
	 * @param BarKind $kind      Whether this is a role lane or a shipped thing.
	 */
	public function __construct(
		private readonly float $left,
		private readonly float $width,
		private readonly float $max_width,
		private readonly BarKind $kind
	) {}

	/**
	 * Distance from the left of the track, in percent.
	 *
	 * @return float
	 */
	public function left(): float {
		return $this->left;
	}

	/**
	 * Bar width, in percent.
	 *
	 * @return float
	 */
	public function width(): float {
		return $this->width;
	}

	/**
	 * The largest width that still fits inside the track, in percent.
	 *
	 * Carried separately from `width()` because it is the CSS `max-width` that
	 * has to survive `min_width()` pushing a short bar wider.
	 *
	 * @return float
	 */
	public function max_width(): float {
		return $this->max_width;
	}

	/**
	 * The floor this bar may not render below, in CSS pixels.
	 *
	 * @return int
	 */
	public function min_width(): int {
		return $this->kind->min_width();
	}

	/**
	 * What this bar represents.
	 *
	 * @return BarKind
	 */
	public function kind(): BarKind {
		return $this->kind;
	}

	/**
	 * The four numbers as CSS declarations, in the order the design writes them.
	 *
	 * Geometry is the one thing in this project that legitimately reaches the
	 * page as an inline style: the values are per-row data, not house style, and
	 * no stylesheet can hold thirteen years of arbitrary start dates. Everything
	 * else about a bar — colour, radius, transition — stays in the stylesheet.
	 *
	 * @return string A `style` attribute value, unescaped.
	 */
	public function style(): string {
		return sprintf(
			'left:%s%%;width:%s%%;max-width:%s%%;min-width:%dpx',
			$this->format( $this->left ),
			$this->format( $this->width ),
			$this->format( $this->max_width ),
			$this->min_width()
		);
	}

	/**
	 * Round a percentage and drop trailing zeroes.
	 *
	 * @param float $percentage The value to format.
	 * @return string
	 */
	private function format( float $percentage ): string {
		$fixed = number_format( round( $percentage, self::PRECISION ), self::PRECISION, '.', '' );

		return rtrim( rtrim( $fixed, '0' ), '.' );
	}
}
