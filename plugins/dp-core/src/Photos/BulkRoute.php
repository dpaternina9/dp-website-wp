<?php
/**
 * The REST half of "Add photos in bulk".
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

/**
 * `POST /dp/v1/photos/bulk` — create one photo per image.
 *
 * The dialog on the Photos screen calls this through `wp.apiFetch`, which sends
 * the REST nonce; the route checks capabilities itself rather than trusting the
 * screen it was called from:
 *
 * - `upload_files`, because the dialog is the media modal and choosing from the
 *   library is an upload-capability action in core;
 * - the post type's `create_posts`, because it creates posts;
 * - its `publish_posts` as well, when publish is what was asked for.
 *
 * A trip or topic that does not exist is a 400, not a silent drop: the dialog
 * only offers real terms, so an unknown one means the list was stale, and the
 * import should not run with half of what was chosen.
 */
final class BulkRoute {

	/**
	 * The route's namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'dp/v1';

	/**
	 * The route.
	 *
	 * @var string
	 */
	public const ROUTE = '/photos/bulk';

	/**
	 * Constructor.
	 *
	 * @param BulkCreate $create Does the work.
	 */
	public function __construct( private readonly BulkCreate $create ) {}

	/**
	 * Attach the route.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', $this->register_route( ... ) );
	}

	/**
	 * Register it.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => $this->handle( ... ),
				'permission_callback' => $this->permitted( ... ),
				'args'                => array(
					'attachments' => array(
						'type'     => 'array',
						'required' => true,
						'minItems' => 1,
						'maxItems' => 500,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'trip'        => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'topics'      => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'status'      => array(
						'type'    => 'string',
						'default' => 'draft',
						'enum'    => BulkCreate::STATUSES,
					),
				),
			)
		);
	}

	/**
	 * Whether the current user may import.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function permitted( WP_REST_Request $request ): bool|WP_Error {
		$type = get_post_type_object( PostType::NAME );

		if ( null === $type ) {
			return false;
		}

		$caps = array( 'upload_files', (string) $type->cap->create_posts );

		if ( 'publish' === $request->get_param( 'status' ) ) {
			$caps[] = (string) $type->cap->publish_posts;
		}

		foreach ( $caps as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'You are not allowed to add photos in bulk.', 'dp-core' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}
		}

		return true;
	}

	/**
	 * Run the import.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$trip   = self::integer( $request->get_param( 'trip' ) );
		$topics = self::integers( $request->get_param( 'topics' ) );

		if ( $trip > 0 && ! get_term( $trip, Taxonomies::TRIP ) instanceof WP_Term ) {
			return new WP_Error( 'dp_photos_unknown_trip', __( 'That trip no longer exists. Reload the page and choose again.', 'dp-core' ), array( 'status' => 400 ) );
		}

		foreach ( $topics as $topic ) {
			if ( ! get_term( $topic, Taxonomies::TOPIC ) instanceof WP_Term ) {
				return new WP_Error( 'dp_photos_unknown_topic', __( 'One of those topics no longer exists. Reload the page and choose again.', 'dp-core' ), array( 'status' => 400 ) );
			}
		}

		$status = $request->get_param( 'status' );

		$report = $this->create->run(
			self::integers( $request->get_param( 'attachments' ) ),
			$trip,
			$topics,
			is_string( $status ) ? $status : 'draft',
			get_current_user_id()
		);

		return new WP_REST_Response( $report->to_array(), 200 );
	}

	/**
	 * A scalar as a non-negative integer.
	 *
	 * @param mixed $value The value.
	 * @return int
	 */
	private static function integer( mixed $value ): int {
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	/**
	 * A list as positive integers.
	 *
	 * @param mixed $value The value.
	 * @return list<int>
	 */
	private static function integers( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( self::integer( ... ), $value ), static fn ( int $id ): bool => $id > 0 ) );
	}
}
