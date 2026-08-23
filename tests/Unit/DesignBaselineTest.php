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

		foreach ( $decoded['entries'] as $entry ) {
			$this->assertIsArray( $entry );
			$this->assertIsString( $entry['selector'] ?? null );
			$this->assertIsArray( $entry['declarations'] ?? null );
			$this->assertNotEmpty(
				$entry['declarations'],
				sprintf( 'The entry "%s" pins nothing.', is_string( $entry['id'] ?? null ) ? $entry['id'] : '?' )
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
