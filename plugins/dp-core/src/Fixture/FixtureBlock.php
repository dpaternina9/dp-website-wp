<?php
/**
 * One block of fixture body copy.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * A single entry from a fixture `body` array.
 *
 * The design writes these as heterogeneous object literals — `{ p: '…' }`,
 * `{ ul: [ … ] }`, `{ table: { head, rows } }`. Transcribed as one typed shape
 * with a kind, the conversion in `BlockMarkup` becomes a total `match` rather
 * than a chain of `isset()` probes, and PHPStan can check every branch of it.
 */
final class FixtureBlock {

	/**
	 * Constructor.
	 *
	 * @param FixtureBlockKind               $kind  Which block this is.
	 * @param string                         $text  The block's text, where it has one.
	 * @param string                         $label The code label, the callout label, or an image caption.
	 * @param string                         $cite  Attribution under a quote.
	 * @param array<int, string>             $items List items.
	 * @param array<int, string>             $head  Table header cells.
	 * @param array<int, array<int, string>> $rows  Table body rows.
	 */
	public function __construct(
		public readonly FixtureBlockKind $kind,
		public readonly string $text = '',
		public readonly string $label = '',
		public readonly string $cite = '',
		public readonly array $items = array(),
		public readonly array $head = array(),
		public readonly array $rows = array()
	) {}
}
