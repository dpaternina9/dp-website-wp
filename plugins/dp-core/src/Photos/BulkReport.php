<?php
/**
 * What a bulk import did.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * The photos a bulk import created, and the images it passed over and why.
 *
 * Both doors report from this — the admin dialog prints `summary()`, WP-CLI
 * prints it and the per-image lines — so "created 12, skipped 3" means the same
 * thing wherever it is read.
 */
final class BulkReport {

	/**
	 * The image already backs a photo.
	 *
	 * @var string
	 */
	public const ALREADY_A_PHOTO = 'already-a-photo';

	/**
	 * The ID is not an image in the Media Library.
	 *
	 * @var string
	 */
	public const NOT_AN_IMAGE = 'not-an-image';

	/**
	 * WordPress refused the insert.
	 *
	 * @var string
	 */
	public const REFUSED = 'refused';

	/**
	 * Attachment ID to the photo created for it.
	 *
	 * @var array<int, int>
	 */
	private array $created = array();

	/**
	 * Attachment ID to the reason it was skipped.
	 *
	 * @var array<int, string>
	 */
	private array $skipped = array();

	/**
	 * Record a photo created.
	 *
	 * @param int $attachment The image.
	 * @param int $photo      The new `dp_photo`.
	 * @return void
	 */
	public function create( int $attachment, int $photo ): void {
		$this->created[ $attachment ] = $photo;
	}

	/**
	 * Record an image passed over.
	 *
	 * @param int    $attachment The image.
	 * @param string $reason     One of the constants.
	 * @return void
	 */
	public function skip( int $attachment, string $reason ): void {
		$this->skipped[ $attachment ] = $reason;
	}

	/**
	 * Attachment ID to new photo ID.
	 *
	 * @return array<int, int>
	 */
	public function created(): array {
		return $this->created;
	}

	/**
	 * Attachment ID to reason.
	 *
	 * @return array<int, string>
	 */
	public function skipped(): array {
		return $this->skipped;
	}

	/**
	 * One sentence: "Created 12 photos. Skipped 3 images that already had one."
	 *
	 * @return string
	 */
	public function summary(): string {
		$created = count( $this->created );
		$counts  = array_count_values( $this->skipped );

		$lines = array(
			/* translators: %s: how many photos were created. */
			sprintf( _n( 'Created %s photo.', 'Created %s photos.', $created, 'dp-core' ), number_format_i18n( $created ) ),
		);

		$backed = $counts[ self::ALREADY_A_PHOTO ] ?? 0;

		if ( $backed > 0 ) {
			/* translators: %s: how many images were skipped. */
			$lines[] = sprintf( _n( 'Skipped %s image that is already a photo.', 'Skipped %s images that are already photos.', $backed, 'dp-core' ), number_format_i18n( $backed ) );
		}

		$other = count( $this->skipped ) - $backed;

		if ( $other > 0 ) {
			/* translators: %s: how many items were skipped. */
			$lines[] = sprintf( _n( 'Skipped %s item that is not an image or could not be saved.', 'Skipped %s items that are not images or could not be saved.', $other, 'dp-core' ), number_format_i18n( $other ) );
		}

		return implode( ' ', $lines );
	}

	/**
	 * The report as the REST route returns it.
	 *
	 * @return array{created: list<array{attachment: int, photo: int}>, skipped: list<array{attachment: int, reason: string}>, summary: string}
	 */
	public function to_array(): array {
		$created = array();
		$skipped = array();

		foreach ( $this->created as $attachment => $photo ) {
			$created[] = array(
				'attachment' => $attachment,
				'photo'      => $photo,
			);
		}

		foreach ( $this->skipped as $attachment => $reason ) {
			$skipped[] = array(
				'attachment' => $attachment,
				'reason'     => $reason,
			);
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'summary' => $this->summary(),
		);
	}
}
