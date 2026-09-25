<?php
/**
 * Unit tests for the camera line and the taken-at date.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Photos;

use DP\Core\Photos\Exif;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * `image_meta` in, the prototype's mono line out.
 *
 * The inputs are the strings `wp_read_image_metadata()` stores — every number
 * a string, zero for "not recorded" — and the expected lines are the
 * prototype's own ("Fujifilm X30 · 10.8mm · f/5 · 1/10s · ISO 800", with the
 * model alone, because that is what core keeps under `camera`).
 */
final class ExifTest extends TestCase {

	/**
	 * Everything recorded.
	 *
	 * @return void
	 */
	public function test_a_full_record_reads_as_the_prototype_prints_it(): void {
		$exif = new Exif(
			array(
				'camera'        => 'X30',
				'focal_length'  => '10.8',
				'aperture'      => '5',
				'shutter_speed' => '0.1',
				'iso'           => '800',
			)
		);

		$this->assertSame( 'X30 · 10.8mm · f/5 · 1/10s · ISO 800', $exif->line() );
	}

	/**
	 * Shutter speeds: fractions under a second, seconds over one.
	 *
	 * @return void
	 */
	public function test_shutter_speeds_read_the_way_cameras_say_them(): void {
		$line = static fn ( string $shutter ): string => ( new Exif( array( 'shutter_speed' => $shutter ) ) )->line();

		$this->assertSame( '1/400s', $line( '0.0025' ) );
		$this->assertSame( '1/3s', $line( '0.3333' ) );
		$this->assertSame( '2s', $line( '2' ) );
		$this->assertSame( '2.5s', $line( '2.5' ) );
	}

	/**
	 * What was not recorded is left out, and nothing at all is the empty string.
	 *
	 * @return void
	 */
	public function test_missing_parts_are_left_out(): void {
		$this->assertSame(
			'f/2.8 · ISO 100',
			( new Exif(
				array(
					'aperture'     => '2.8',
					'iso'          => '100',
					'focal_length' => '0',
				)
			) )->line()
		);
		$this->assertSame( '', ( new Exif( array() ) )->line() );
		$this->assertSame( '', Exif::from_metadata( false )->line() );
		$this->assertSame( '', Exif::from_metadata( array( 'width' => 10 ) )->line() );
	}

	/**
	 * The taken-at time is the camera's wall clock, or nothing.
	 *
	 * @return void
	 */
	public function test_the_taken_date_is_the_cameras_wall_clock(): void {
		$stamp = (string) gmmktime( 14, 30, 5, 12, 11, 2017 );

		$this->assertSame( '2017-12-11 14:30:05', ( new Exif( array( 'created_timestamp' => $stamp ) ) )->taken() );
		$this->assertNull( ( new Exif( array( 'created_timestamp' => '0' ) ) )->taken() );
		$this->assertNull( ( new Exif( array() ) )->taken() );
	}
}
