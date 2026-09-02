<?php
/**
 * The committed design-parity baseline still says what the design says.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Tests\Support\DesignBaseline;
use RuntimeException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Guards `tests/e2e/fixtures/work-design-baseline.json`.
 *
 * The e2e suite asserts the theme against that fixture. Nothing there can tell
 * whether the fixture still describes `design-source/` — a fixture that had
 * quietly drifted would go on passing, which is the failure mode this whole
 * harness exists to remove one level down. So the generator runs here, in the
 * fast gate, and the committed file has to be what it produces.
 *
 * A unit test rather than an integration one: none of this touches WordPress.
 * It reads `design-source/`, which is a directory of files, and compares the
 * result to a checked-in string.
 *
 * See docs/adr/0012-design-parity-harness.md.
 */
final class DesignBaselineTest extends TestCase {

	/**
	 * The generated fixture is exactly what the design source implies.
	 *
	 * @return void
	 */
	public function test_the_committed_baseline_is_up_to_date(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertSame(
			DesignBaseline::for_repository( $root )->render(),
			$this->read( $root . '/' . DesignBaseline::OUTPUT ),
			DesignBaseline::OUTPUT . ' is stale. It is generated, never hand-edited. '
				. 'Run: composer design:baseline'
		);
	}

	/**
	 * Every anchor in the map still selects exactly one element.
	 *
	 * `render()` throws when one does not, so this is the same guarantee said
	 * out loud: the failure a reviewer needs to see is "the design moved", not
	 * "a value silently stopped being asserted".
	 *
	 * @return void
	 */
	public function test_every_anchor_still_finds_its_element(): void {
		$rendered = '';

		try {
			$rendered = DesignBaseline::for_repository( dirname( __DIR__, 2 ) )->render();
		} catch ( RuntimeException $error ) {
			$this->fail(
				"An entry in the design-parity map no longer matches design-source/:\n  "
					. $error->getMessage()
			);
		}

		$decoded = json_decode( $rendered, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'entries', $decoded );
		$this->assertIsArray( $decoded['entries'] );
		$this->assertNotEmpty( $decoded['entries'], 'The map produced no entries at all.' );

		$this->assertArrayHasKey( 'sweeps', $decoded );
		$this->assertIsArray( $decoded['sweeps'] );

		foreach ( $decoded['entries'] as $entry ) {
			$this->assertIsArray( $entry );
			$this->assertIsString( $entry['selector'] ?? null );
			$this->assertIsArray( $entry['declarations'] ?? null );
			$this->assertNotEmpty(
				$entry['declarations'],
				sprintf( 'The entry "%s" pins nothing.', is_string( $entry['id'] ?? null ) ? $entry['id'] : '?' )
			);
			$this->assertArrayHasKey(
				is_string( $entry['sweep'] ?? null ) ? $entry['sweep'] : '?',
				$decoded['sweeps'],
				sprintf( 'The entry "%s" names a sweep that does not exist.', is_string( $entry['id'] ?? null ) ? $entry['id'] : '?' )
			);
		}
	}

	/**
	 * Nothing in the fixture is a value somebody typed from a screenshot.
	 *
	 * The amendment to ADR-0012 introduced entries marked **PINNED BY HAND** —
	 * three colours read off a picture, because the export was believed not to
	 * carry `orgStyle` and `kindLabelStyle`. It carried both, in a script block
	 * the import had dropped. The blocks are restored, every one of those values
	 * is read from `design-source/components/*.logic.js`, and this test is what
	 * stops the shortcut coming back: a hand-pinned entry cannot fail when the
	 * design moves, which is the one thing this harness is for.
	 *
	 * @return void
	 */
	public function test_no_value_was_pinned_by_hand(): void {
		$rendered = DesignBaseline::for_repository( dirname( __DIR__, 2 ) )->render();

		$this->assertStringNotContainsString(
			'PINNED BY HAND',
			$rendered,
			'An entry carries a value nothing in design-source/ states. Read the component\'s '
				. '*.logic.js — the styles ADR-0012 called unexportable are all in it.'
		);

		$this->assertStringNotContainsString(
			'NOT IN THE FILE',
			$rendered,
			'An entry names no design file. Every value in this fixture has one.'
		);
	}

	/**
	 * The one deliberate disagreement with the design is still written down.
	 *
	 * `.dp-tl-years` is the only element in this fixture the theme draws
	 * differently on purpose: the design spreads thirteen year labels with
	 * `justify-content: space-between`, which is 1/12 per label against the
	 * bars' 1/13, and the theme puts them on the bars' scale instead. David
	 * decided that on 2026-08-24 and ADR-0014 records it.
	 *
	 * What this test guards is not the decision — it is the shape of the record.
	 * Deleting the entry would make the sweep pass by measuring nothing, which is
	 * precisely the failure the second amendment to ADR-0012 spent four days on.
	 * So: the entry exists, it still carries the design's own declarations, both
	 * of them are named as diverged, and each names its ADR.
	 *
	 * @return void
	 */
	public function test_the_axis_divergence_is_recorded_rather_than_dropped(): void {
		$decoded = json_decode( DesignBaseline::for_repository( dirname( __DIR__, 2 ) )->render(), true );

		$this->assertIsArray( $decoded );
		$this->assertIsArray( $decoded['entries'] ?? null );

		$years   = null;
		$diverge = array();

		foreach ( $decoded['entries'] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( isset( $entry['divergence'] ) ) {
				$diverge[] = is_string( $entry['id'] ?? null ) ? $entry['id'] : '?';
			}

			if ( 'chart.years' === ( $entry['id'] ?? null ) ) {
				$years = $entry;
			}
		}

		$this->assertIsArray(
			$years,
			'The "chart.years" entry is gone. A divergence from design-source/ is recorded with '
				. 'its reason, never removed: an entry that is not there cannot fail, and a sweep that '
				. 'measures nothing passes. See docs/adr/0014-the-year-axis-and-the-bars-share-one-scale.md.'
		);

		$this->assertSame(
			array(
				'display'         => 'flex',
				'justify-content' => 'space-between',
			),
			$years['declarations'] ?? null,
			'The design has moved. Re-read ADR-0014 before re-running the generator.'
		);

		$this->assertIsArray( $years['divergence'] ?? null );
		$this->assertSame(
			array( 'display', 'justifyContent' ),
			array_keys( $years['divergence'] ),
			'Both of the design\'s declarations on this element are diverged from, and each says so.'
		);

		foreach ( $years['divergence'] as $property => $reason ) {
			$this->assertIsString( $reason );
			$this->assertMatchesRegularExpression(
				'/ADR-\d{4}/',
				$reason,
				sprintf( 'The divergence on "%s" names no ADR.', is_string( $property ) ? $property : '?' )
			);
		}

		$bars = array( 'row.role.bar.open', 'row.role.bar.closed', 'row.ship.bar.open', 'row.ship.bar.closed' );

		$this->assertSame(
			array_merge( array( 'chart.years' ), $bars ),
			$diverge,
			'A third entry diverges from the design. That needs an ADR and a line here before it '
				. 'needs a fixture.'
		);

		/*
		 * The second recorded divergence, ADR-0022: the design floors a role bar
		 * at 64px and a ship at 40, which on this page's track is about a year
		 * and about eight months. Every sub-year role was drawn a year long, so
		 * two consecutive three-month roles read as concurrent jobs. The floor
		 * is now 10px and 8px -- and it is one property on each of four entries,
		 * because everything else about a bar still matches the design exactly.
		 */
		foreach ( $bars as $id ) {
			$bar = null;

			foreach ( $decoded['entries'] as $entry ) {
				if ( is_array( $entry ) && ( $entry['id'] ?? null ) === $id ) {
					$bar = $entry;
				}
			}

			$this->assertIsArray( $bar, sprintf( 'The "%s" entry is gone.', $id ) );
			$this->assertIsArray( $bar['divergence'] ?? null );
			$this->assertSame(
				array( 'minWidth' ),
				array_keys( $bar['divergence'] ),
				sprintf( '"%s" diverges from the design on more than the floor.', $id )
			);

			$reason = $bar['divergence']['minWidth'];
			$this->assertIsString( $reason );
			$this->assertMatchesRegularExpression(
				'/ADR-\d{4}/',
				$reason,
				sprintf( 'The floor divergence on "%s" names no ADR.', $id )
			);
		}
	}

	/**
	 * Every sweep the fixture declares has something to measure.
	 *
	 * A sweep with no entries is a pass that navigates, waits, and asserts
	 * nothing — which is what the chart's closed states were until 2026-08-23,
	 * except that there was not even a pass.
	 *
	 * @return void
	 */
	public function test_every_sweep_measures_something(): void {
		$decoded = json_decode( DesignBaseline::for_repository( dirname( __DIR__, 2 ) )->render(), true );

		$this->assertIsArray( $decoded );
		$this->assertIsArray( $decoded['sweeps'] ?? null );
		$this->assertIsArray( $decoded['entries'] ?? null );

		$counted = array();

		foreach ( $decoded['entries'] as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['sweep'] ?? null ) ) {
				$counted[ $entry['sweep'] ] = ( $counted[ $entry['sweep'] ] ?? 0 ) + 1;
			}
		}

		foreach ( array_keys( $decoded['sweeps'] ) as $sweep ) {
			$this->assertArrayHasKey(
				$sweep,
				$counted,
				sprintf( 'The "%s" sweep measures nothing.', is_string( $sweep ) ? $sweep : '?' )
			);
		}
	}

	/**
	 * Read a file, or fail with the path rather than with a type error.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function read( string $path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a repository file, read outside any request.
		$contents = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $contents ) {
			$this->fail( sprintf( 'Cannot read %s.', $path ) );
		}

		return $contents;
	}
}
