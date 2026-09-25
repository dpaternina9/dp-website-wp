<?php
/**
 * A photo was taken on one trip.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Error;
use WP_REST_Request;

/**
 * Refuses a save that files a photo under more than one trip.
 *
 * **Refused, never trimmed.** The tempting fix is to keep the first trip and
 * drop the rest, and it is exactly the hidden rewrite `CLAUDE.md` rule 2 forbids:
 * which of the two David meant is not knowable, so any guess would silently
 * overwrite something he chose. A refusal says so in the editor's own error
 * notice and changes nothing, and he picks.
 *
 * It sits on `rest_pre_insert_dp_photo` because the block editor saves over REST
 * and that filter runs *before* anything is written: the post row and its terms
 * are only touched once it returns. The editor's own trip control can only send
 * one (`photos/trip-select.js`), so in practice this answers the paths that are
 * not the editor — a REST client, a script, an old tab.
 */
final class OneTrip {

	/**
	 * The error code a refused save carries.
	 *
	 * @var string
	 */
	public const ERROR = 'dp_photo_one_trip';

	/**
	 * Attach the check.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rest_pre_insert_' . PostType::NAME, $this->check( ... ), 10, 2 );
	}

	/**
	 * Pass the prepared post through, or refuse it.
	 *
	 * @param mixed           $prepared The post about to be written.
	 * @param WP_REST_Request $request  The request carrying it.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return mixed The prepared post, or a WP_Error.
	 */
	public function check( mixed $prepared, WP_REST_Request $request ): mixed {
		$trips = $request->get_param( Taxonomies::TRIP );

		if ( ! is_array( $trips ) ) {
			return $prepared;
		}

		$distinct = array_unique(
			array_filter(
				array_map( static fn ( mixed $id ): int => is_numeric( $id ) ? (int) $id : 0, $trips ),
				static fn ( int $id ): bool => $id > 0
			)
		);

		if ( count( $distinct ) <= 1 ) {
			return $prepared;
		}

		return new WP_Error(
			self::ERROR,
			__( 'A photo belongs to one trip. Choose one of them and save again — nothing was changed.', 'dp-core' ),
			array( 'status' => 400 )
		);
	}
}
