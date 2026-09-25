<?php
/**
 * The `wp dp photos import` command.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

use DP\Core\Photos\BulkCreate;
use DP\Core\Photos\BulkReport;
use DP\Core\Photos\Taxonomies;
use WP_Term;

/**
 * "Add photos in bulk", from a terminal.
 *
 * `WatchSyncCommand`'s shape: nothing here knows WP-CLI, output goes through an
 * `Output`, and an integration test can call it with a `NullOutput`. The import
 * itself is `BulkCreate` — the same code the Photos screen's dialog runs — so
 * the two doors cannot disagree about what an import does.
 *
 * A directory is uploaded into the Media Library first. Each file's SHA-1 is
 * kept on the attachment it became, so pointing the command at the same folder
 * twice finds the images it already uploaded instead of uploading them again —
 * and they are then skipped as already being photos. Running it twice makes
 * nothing twice.
 */
final class PhotosImportCommand {

	/**
	 * Where a directory import records which file an attachment came from.
	 *
	 * @var string
	 */
	public const SOURCE_HASH = '_dp_photo_source_sha1';

	/**
	 * The file extensions a directory import picks up.
	 *
	 * @var list<string>
	 */
	private const EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'heic' );

	/**
	 * Constructor.
	 *
	 * @param BulkCreate $create Makes the photos.
	 * @param Output     $output Where the run reports to.
	 */
	public function __construct(
		private readonly BulkCreate $create,
		private readonly Output $output
	) {}

	/**
	 * Make one photo for each image — Media Library IDs, a folder, or both.
	 *
	 * Each photo gets the image as its featured image and no title. Its publish
	 * date is the date the camera recorded, when the file has one. Images that
	 * already back a photo are skipped, so running this twice makes nothing
	 * twice.
	 *
	 * ## OPTIONS
	 *
	 * [<attachment-id>...]
	 * : Media Library image IDs to make photos from.
	 *
	 * [--dir=<path>]
	 * : A folder of image files. Each is uploaded to the Media Library first,
	 * unless an earlier import already uploaded the same file.
	 *
	 * [--trip=<slug>]
	 * : The trip every photo is filed under. It must already exist.
	 *
	 * [--topic=<slugs>]
	 * : Comma-separated topics every photo gets. Each must already exist.
	 *
	 * [--status=<status>]
	 * : draft or publish.
	 * ---
	 * default: draft
	 * options:
	 *   - draft
	 *   - publish
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Three images already in the Media Library, as drafts.
	 *     $ wp dp photos import 101 102 103
	 *
	 *     # A folder from a trip, published, with a topic.
	 *     $ wp dp photos import --dir=./putumayo --trip=putumayo --topic=water --status=publish --user=david
	 *
	 * @param array<int, string>         $args       Attachment IDs.
	 * @param array<string, string|bool> $assoc_args `dir`, `trip`, `topic`, `status`.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$trip = $this->term( $assoc_args['trip'] ?? '', Taxonomies::TRIP );

		if ( null === $trip ) {
			return;
		}

		$topics = array();

		foreach ( $this->slugs( $assoc_args['topic'] ?? '' ) as $slug ) {
			$topic = $this->term( $slug, Taxonomies::TOPIC );

			if ( null === $topic ) {
				return;
			}

			$topics[] = $topic;
		}

		$ids = array();

		foreach ( $args as $arg ) {
			if ( ctype_digit( $arg ) ) {
				$ids[] = (int) $arg;
			} else {
				$this->output->warning( sprintf( '"%s" is not an attachment ID; ignored.', $arg ) );
			}
		}

		$dir = $assoc_args['dir'] ?? '';

		if ( is_string( $dir ) && '' !== $dir ) {
			$ids = array_merge( $ids, $this->upload_directory( $dir ) );
		}

		if ( array() === $ids ) {
			$this->output->warning( 'Nothing to import: give attachment IDs, --dir, or both.' );

			return;
		}

		$status = $assoc_args['status'] ?? 'draft';
		$report = $this->create->run( $ids, $trip, $topics, is_string( $status ) ? $status : 'draft', $this->author() );

		foreach ( $report->created() as $attachment => $photo ) {
			$this->output->line( sprintf( 'Image %d → photo %d.', $attachment, $photo ) );
		}

		foreach ( $report->skipped() as $attachment => $reason ) {
			$this->output->line( sprintf( 'Image %d skipped: %s.', $attachment, self::reason( $reason ) ) );
		}

		$this->output->success( $report->summary() );
	}

	/**
	 * Resolve a term slug, or report that it does not exist.
	 *
	 * @param mixed  $slug     The slug given, or '' for none.
	 * @param string $taxonomy The taxonomy.
	 * @return int|null The term ID, 0 for none given, or null when it does not exist.
	 */
	private function term( mixed $slug, string $taxonomy ): ?int {
		$slug = is_string( $slug ) ? trim( $slug ) : '';

		if ( '' === $slug ) {
			return 0;
		}

		$term = get_term_by( 'slug', sanitize_title( $slug ), $taxonomy );

		if ( $term instanceof WP_Term ) {
			return $term->term_id;
		}

		$this->output->warning(
			sprintf(
				'There is no %1$s "%2$s". Add it on its screen in wp-admin, or with `wp term create %3$s`, and run this again. Nothing was imported.',
				Taxonomies::TRIP === $taxonomy ? 'trip' : 'topic',
				$slug,
				$taxonomy
			)
		);

		return null;
	}

	/**
	 * A comma-separated list, split.
	 *
	 * @param mixed $value The option's value.
	 * @return list<string>
	 */
	private function slugs( mixed $value ): array {
		if ( ! is_string( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), static fn ( string $slug ): bool => '' !== $slug ) );
	}

	/**
	 * Upload every image in a folder, reusing any an earlier import uploaded.
	 *
	 * @param string $dir The folder.
	 * @return list<int> Attachment IDs, in file-name order.
	 */
	private function upload_directory( string $dir ): array {
		$real = realpath( $dir );

		if ( false === $real || ! is_dir( $real ) ) {
			$this->output->warning( sprintf( '"%s" is not a folder.', $dir ) );

			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = glob( $real . '/*' );
		$files = is_array( $files ) ? $files : array();

		sort( $files );

		$ids = array();

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) || ! in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), self::EXTENSIONS, true ) ) {
				continue;
			}

			$hash     = sha1_file( $file );
			$existing = false === $hash ? 0 : $this->uploaded( $hash );

			if ( $existing > 0 ) {
				$ids[] = $existing;
				continue;
			}

			$id = $this->upload( $file );

			if ( $id > 0 ) {
				if ( false !== $hash ) {
					update_post_meta( $id, self::SOURCE_HASH, $hash );
				}

				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The attachment an earlier import made from a file with this hash.
	 *
	 * @param string $hash A SHA-1.
	 * @return int The attachment ID, or 0.
	 */
	private function uploaded( string $hash ): int {
		$found = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- a CLI import, never on a page view.
				'meta_key'    => self::SOURCE_HASH,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				'meta_value'  => $hash,
			)
		);

		$first = reset( $found );

		return is_numeric( $first ) ? (int) $first : 0;
	}

	/**
	 * Copy one file into the Media Library.
	 *
	 * `media_handle_sideload()` moves the file it is given, so it is handed a
	 * temporary copy and the folder is left as it was.
	 *
	 * @param string $file Absolute path.
	 * @return int The attachment ID, or 0.
	 */
	private function upload( string $file ): int {
		$copy = wp_tempnam( basename( $file ) );

		if ( '' === $copy || ! copy( $file, $copy ) ) {
			$this->output->warning( sprintf( 'Could not read "%s".', $file ) );

			return 0;
		}

		$id = media_handle_sideload(
			array(
				'name'     => basename( $file ),
				'tmp_name' => $copy,
			)
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $copy );
			$this->output->warning( sprintf( 'Could not upload "%1$s": %2$s', basename( $file ), $id->get_error_message() ) );

			return 0;
		}

		return $id;
	}

	/**
	 * Who the photos belong to: `--user`, else the first administrator.
	 *
	 * @return int
	 */
	private function author(): int {
		$current = get_current_user_id();

		if ( $current > 0 ) {
			return $current;
		}

		$administrators = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		$first = reset( $administrators );

		return is_numeric( $first ) ? (int) $first : 1;
	}

	/**
	 * A skip reason, in words.
	 *
	 * @param string $reason One of `BulkReport`'s constants.
	 * @return string
	 */
	private static function reason( string $reason ): string {
		return match ( $reason ) {
			BulkReport::ALREADY_A_PHOTO => 'it is already a photo',
			BulkReport::NOT_AN_IMAGE    => 'it is not an image in the Media Library',
			default                     => 'WordPress refused to save it',
		};
	}
}
