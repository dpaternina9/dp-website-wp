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
