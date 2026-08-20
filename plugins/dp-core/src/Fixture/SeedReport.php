<?php
/**
 * What a seed run did.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * The counts from one run, and the lines a console would print.
 *
 * Returned rather than printed so the seeder can be run from an integration test
 * and asserted on. `phpunit.xml.dist` sets `beStrictAboutOutputDuringTests`, so a
 * seeder that wrote to stdout would fail the suite outright.
 */
final class SeedReport {

	/**
	 * Constructor.
	 *
	 * @param array<string, int> $counts How many of each thing exist after the run.
	 * @param bool               $fresh  Whether the run wiped what a previous one made.
	 */
	public function __construct(
		private readonly array $counts,
		private readonly bool $fresh
	) {}

	/**
	 * How many of each thing exist after the run.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		return $this->counts;
	}

	/**
	 * How many of one thing exist after the run.
	 *
	 * @param string $what One of the keys in `counts()`.
	 * @return int
	 */
	public function count( string $what ): int {
		return $this->counts[ $what ] ?? 0;
	}

	/**
	 * Whether the run started by deleting what a previous one made.
	 *
	 * @return bool
	 */
	public function was_fresh(): bool {
		return $this->fresh;
	}

	/**
	 * The run as console lines, one per kind of thing.
	 *
	 * @return list<string>
	 */
	public function lines(): array {
		$lines = array();

		if ( $this->fresh ) {
			$lines[] = 'Removed everything a previous seed created.';
		}

		foreach ( $this->counts as $what => $count ) {
			$lines[] = sprintf( '%-14s %d', str_replace( '_', ' ', $what ), $count );
		}

		return $lines;
	}

	/**
	 * One line naming the totals.
	 *
	 * @return string
	 */
	public function summary(): string {
		$pairs = array();

		foreach ( $this->counts as $what => $count ) {
			$pairs[] = $count . ' ' . str_replace( '_', ' ', $what );
		}

		return 'Seeded ' . implode( ', ', $pairs ) . '.';
	}
}
