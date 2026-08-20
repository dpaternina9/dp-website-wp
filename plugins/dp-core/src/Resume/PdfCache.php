<?php
/**
 * Where a rendered résumé is kept, and what makes it out of date.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

use DP\Core\Content\PostTypes;
use WP_Post;

/**
 * One file per version of the content behind the résumé.
 *
 * `docs/plan.md` section 7.1: cached to `uploads/`, "keyed on the résumé's
 * `post_modified` plus the modified time of the newest `dp_role`/`dp_ship`, so
 * it regenerates exactly when the content behind it changes and is a static
 * file every other time".
 *
 * Three details that are not obvious from that sentence.
 *
 * **The count is in the key as well as the newest date.** Deleting a role does
 * not change any remaining role's `post_modified`, so a key built from dates
 * alone would keep serving a PDF containing a job that is no longer on the
 * page. Counting the rows costs nothing in the same aggregate query and closes
 * that hole.
 *
 * **A stale file is kept on purpose.** The plan is explicit — "a stale cached
 * PDF is always preferred over no PDF" — so writing a new key does not delete
 * the one before it. `prune()` keeps the two newest files and drops the rest,
 * which bounds the directory without ever leaving nothing to fall back to.
 *
 * **The directory carries an index file and is not listable.** A `.pdf` under
 * `uploads/` is public by design — that is the point, the browser downloads it
 * — but the directory listing is not something we need to publish.
 */
final class PdfCache {

	/**
	 * Bumped by hand when the rendered document changes shape for a reason the
	 * content's modified dates cannot see — a stylesheet change, a new section.
	 *
	 * @var string
	 */
	public const SCHEMA = 'v1';

	/**
	 * The directory under `uploads/`.
	 *
	 * @var string
	 */
	public const DIRECTORY = 'dp-resume';

	/**
	 * How many rendered files to keep, newest first.
	 *
	 * @var int
	 */
	private const KEEP = 2;

	/**
	 * The cache key for one résumé page.
	 *
	 * @param int $page_id The page carrying the résumé template.
	 * @return string A 32-character hexadecimal key.
	 */
	public function key( int $page_id ): string {
		$page     = get_post( $page_id );
		$modified = $page instanceof WP_Post ? $page->post_modified_gmt : '';
		$sources  = $this->sources();

		return substr(
			md5( self::SCHEMA . '|' . $page_id . '|' . $modified . '|' . $sources['count'] . '|' . $sources['modified'] ),
			0,
			32
		);
	}

	/**
	 * Absolute path of the file one key would be stored at.
	 *
	 * @param string $key The cache key.
	 * @return string Empty when the uploads directory is unusable.
	 */
	public function path( string $key ): string {
		$directory = $this->directory();

		return '' === $directory ? '' : $directory . '/' . $key . '.pdf';
	}

	/**
	 * The stored file for one key, if it is there.
	 *
	 * @param string $key The cache key.
	 * @return string|null Absolute path, or null.
	 */
	public function fresh( string $key ): ?string {
		$path = $this->path( $key );

		return '' !== $path && is_readable( $path ) ? $path : null;
	}

	/**
	 * The most recently written file, whatever key it was written under.
	 *
	 * This is the "prefer a stale PDF over no PDF" half of the plan. It is only
	 * ever consulted after a renderer has failed.
	 *
	 * @return string|null Absolute path, or null when nothing has ever rendered.
	 */
	public function stale(): ?string {
		$files = $this->stored();

		return array() === $files ? null : $files[0];
	}

	/**
	 * Store one rendering.
	 *
	 * @param string $key The cache key.
	 * @param string $pdf The PDF bytes.
	 * @return string|null Absolute path written, or null when it could not be written.
	 */
	public function write( string $key, string $pdf ): ?string {
		$path = $this->path( $key );

		if ( '' === $path ) {
			return null;
		}

		/*
		 * Written directly rather than through WP_Filesystem. WP_Filesystem is
		 * built for the admin's credentials flow: on a host whose method is not
		 * `direct` it returns false without credentials, and this runs for an
		 * anonymous visitor who has none to give. A feature that silently stopped
		 * caching on exactly those hosts would be worse than the ignore below.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $path, $pdf, LOCK_EX );

		if ( false === $written ) {
			return null;
		}

		$this->prune();

		return $path;
	}

	/**
	 * Drop everything but the newest few files.
	 *
	 * @return void
	 */
	public function prune(): void {
		$files = $this->stored();

		foreach ( array_slice( $files, self::KEEP ) as $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Every stored file, newest first.
	 *
	 * @return list<string>
	 */
	private function stored(): array {
		$directory = $this->directory();

		if ( '' === $directory ) {
			return array();
		}

		$found = glob( $directory . '/*.pdf' );

		if ( ! is_array( $found ) ) {
			return array();
		}

		$files = array_values( array_filter( $found, 'is_readable' ) );

		usort(
			$files,
			static function ( string $one, string $two ): int {
				$a = filemtime( $one );
				$b = filemtime( $two );

				return ( false === $b ? 0 : $b ) <=> ( false === $a ? 0 : $a );
			}
		);

		return $files;
	}

	/**
	 * The cache directory, created if need be.
	 *
	 * @return string Absolute path without a trailing slash, or '' when unusable.
	 */
	private function directory(): string {
		$uploads = wp_upload_dir();

		if ( ! is_string( $uploads['basedir'] ?? null ) || ( $uploads['error'] ?? false ) ) {
			return '';
		}

		$directory = $uploads['basedir'] . '/' . self::DIRECTORY;

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return '';
		}

		$index = $directory . '/index.html';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, '' );
		}

		return $directory;
	}

	/**
	 * How many published roles and shipped things there are, and the newest date.
	 *
	 * An aggregate rather than a `WP_Query`: the answer is two scalars, and
	 * `WP_Query` would hydrate every row to compute them. `CLAUDE.md` section
	 * 1.4 allows `$wpdb` through `prepare()`, which this is.
	 *
	 * @return array{count: int, modified: string}
	 */
	private function sources(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- two scalars over two post types, read only when a PDF is asked for; caching the answer would cache the very thing the key exists to detect a change in.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE( MAX( post_modified_gmt ), '' ) AS newest
				 FROM {$wpdb->posts}
				 WHERE post_type IN ( %s, %s ) AND post_status = %s",
				PostTypes::ROLE,
				PostTypes::SHIP,
				'publish'
			),
			ARRAY_A
		);

		return array(
			'count'    => is_array( $row ) && is_numeric( $row['total'] ?? null ) ? (int) $row['total'] : 0,
			'modified' => is_array( $row ) && is_string( $row['newest'] ?? null ) ? $row['newest'] : '',
		);
	}
}
