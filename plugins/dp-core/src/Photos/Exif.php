<?php
/**
 * What the camera recorded.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * Reads the `image_meta` WordPress already extracted when the file was uploaded.
 *
 * `wp_read_image_metadata()` runs on every upload and stores what it found under
 * `image_meta` in the attachment's metadata: `camera` (the EXIF model),
 * `focal_length`, `aperture`, `shutter_speed` in seconds, `iso`, and
 * `created_timestamp`. Nothing here re-reads a file; this only turns those six
 * values into the two things the site prints — the mono camera line under a
 * photo, and the date the shutter fired.
 *
 * Pure: no WordPress call in the class, so the formatting is unit-tested
 * without a database (`tests/Unit/Photos/ExifTest.php`). The block, the editor's
 * Camera panel (through `CameraField`) and the bulk importer all read through
 * here, so the line David sees in the sidebar is the line the page prints.
 */
final class Exif {

	/**
	 * Constructor.
	 *
	 * @param array<array-key, mixed> $meta An attachment's `image_meta`.
	 */
	public function __construct( private readonly array $meta ) {}

	/**
	 * Read an attachment's `image_meta` out of its metadata array.
	 *
	 * @param mixed $metadata What `wp_get_attachment_metadata()` returned.
	 * @return self
	 */
	public static function from_metadata( mixed $metadata ): self {
		$meta = is_array( $metadata ) && is_array( $metadata['image_meta'] ?? null ) ? $metadata['image_meta'] : array();

		return new self( $meta );
	}

	/**
	 * The camera line: "X30 · 10.8mm · f/5 · 1/10s · ISO 800".
	 *
	 * Each part is left out when the camera did not record it, and a photo that
	 * recorded none of them has no line at all — the empty string, which the
	 * page takes to mean "print nothing".
	 *
	 * @return string
	 */
	public function line(): string {
		$parts = array_filter(
			array(
				$this->text( 'camera' ),
				$this->focal_length(),
				$this->aperture(),
				$this->shutter(),
				$this->iso(),
			),
			static fn ( string $part ): bool => '' !== $part
		);

		return implode( ' · ', $parts );
	}

	/**
	 * When the shutter fired, as a `Y-m-d H:i:s` wall-clock time, or null.
	 *
	 * The camera's clock has no time zone. `wp_read_image_metadata()` parses the
	 * EXIF `DateTimeOriginal` string as if it were UTC, so formatting the stamp
	 * back in UTC returns the time the camera displayed, which is what a publish
	 * date should say — `post_date` is the site's local wall clock, not UTC.
	 *
	 * @return string|null
	 */
	public function taken(): ?string {
		$stamp = $this->meta['created_timestamp'] ?? 0;

		if ( ! is_numeric( $stamp ) || (int) $stamp <= 0 ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', (int) $stamp );
	}

	/**
	 * The fields, as the editor's Camera panel lists them.
	 *
	 * @return array{camera: string, focal_length: string, aperture: string, shutter: string, iso: string}
	 */
	public function parts(): array {
		return array(
			'camera'       => $this->text( 'camera' ),
			'focal_length' => $this->focal_length(),
			'aperture'     => $this->aperture(),
			'shutter'      => $this->shutter(),
			'iso'          => $this->iso(),
		);
	}

	/**
	 * "10.8mm".
	 *
	 * @return string
	 */
	private function focal_length(): string {
		$value = $this->number( 'focal_length' );

		return $value > 0 ? self::trim_number( $value ) . 'mm' : '';
	}

	/**
	 * "f/5".
	 *
	 * @return string
	 */
	private function aperture(): string {
		$value = $this->number( 'aperture' );

		return $value > 0 ? 'f/' . self::trim_number( $value ) : '';
	}

	/**
	 * "1/400s" under a second, "2s" and "2.5s" over one.
	 *
	 * @return string
	 */
	private function shutter(): string {
		$value = $this->number( 'shutter_speed' );

		if ( $value <= 0 ) {
			return '';
		}

		if ( $value >= 1 ) {
			return self::trim_number( $value ) . 's';
		}

		return '1/' . (string) (int) round( 1 / $value ) . 's';
	}

	/**
	 * "ISO 800".
	 *
	 * @return string
	 */
	private function iso(): string {
		$value = $this->number( 'iso' );

		return $value > 0 ? 'ISO ' . (string) (int) round( $value ) : '';
	}

	/**
	 * One text field, trimmed.
	 *
	 * @param string $key The `image_meta` key.
	 * @return string
	 */
	private function text( string $key ): string {
		$value = $this->meta[ $key ] ?? '';

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * One numeric field, or zero.
	 *
	 * @param string $key The `image_meta` key.
	 * @return float
	 */
	private function number( string $key ): float {
		$value = $this->meta[ $key ] ?? 0;

		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * A number to one decimal place, without a trailing ".0".
	 *
	 * @param float $value The number.
	 * @return string
	 */
	private static function trim_number( float $value ): string {
		$fixed = number_format( $value, 1, '.', '' );

		return str_ends_with( $fixed, '.0' ) ? substr( $fixed, 0, -2 ) : $fixed;
	}
}
