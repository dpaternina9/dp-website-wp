<?php
/**
 * The camera line, where the editor can read it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * Adds a read-only `dp_camera` property to an attachment's REST record.
 *
 * The Photos page prints a camera line nobody typed — it is worked out from the
 * featured image's EXIF — and ADR-0018 says anything computed has to be visible
 * in the editor. The Camera panel in a photo's sidebar is where it is visible,
 * and this is what the panel reads: the same `Exif` the block renders with, so
 * the sidebar and the page cannot format the same file two ways.
 *
 * It hangs off the **attachment**, not the photo, because the panel has to follow
 * the featured image David has picked but not yet saved. The editor already
 * fetches that attachment to draw the Featured Image panel; asking for it in
 * `edit` context — which is what `core-data` does for every post-type entity —
 * returns this property with it.
 *
 * `edit` context only: it is nothing a visitor needs, and a view-context
 * response is public.
 */
final class CameraField {

	/**
	 * The REST property.
	 *
	 * @var string
	 */
	public const FIELD = 'dp_camera';

	/**
	 * Attach the field.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', $this->register_field( ... ) );
	}

	/**
	 * Register it on `attachment`.
	 *
	 * @return void
	 */
	public function register_field(): void {
		register_rest_field(
			'attachment',
			self::FIELD,
			array(
				'get_callback' => $this->value( ... ),
				'schema'       => array(
					'description' => __( 'What the camera recorded, as the Photos page prints it. Read from the file when it was uploaded; not editable.', 'dp-core' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'line'  => array( 'type' => 'string' ),
						'taken' => array( 'type' => array( 'string', 'null' ) ),
						'parts' => array( 'type' => 'object' ),
					),
				),
			)
		);
	}

	/**
	 * The field's value for one attachment.
	 *
	 * @param mixed $record The response data so far, carrying `id`.
	 * @return array{line: string, taken: string|null, parts: array<string, string>}
	 */
	public function value( mixed $record ): array {
		$id   = is_array( $record ) && is_numeric( $record['id'] ?? null ) ? (int) $record['id'] : 0;
		$exif = Exif::from_metadata( $id > 0 ? wp_get_attachment_metadata( $id ) : array() );

		return array(
			'line'  => $exif->line(),
			'taken' => $exif->taken(),
			'parts' => $exif->parts(),
		);
	}
}
