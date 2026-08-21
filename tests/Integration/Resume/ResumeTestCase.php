<?php
/**
 * The shared harness for the résumé's integration tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Resume;

use DP\Core\Content\ContentModel;
use DP\Core\Content\PostTypes;
use DP\Core\Resume\PdfCache;
use DP\Core\Resume\ResumePdf;
use WP_UnitTestCase;

/**
 * A résumé page, the two post types behind it, and a cache directory that is
 * emptied between tests.
 *
 * Three things here are load-bearing.
 *
 * **The model is re-registered.** `WP_UnitTestCase::tear_down()` calls
 * `unregister_all_meta_keys()`, so from the second test in a run onwards
 * everything `dp-core` registered on `init` is gone (ADR-0003). Without this the
 * ledger reads an empty record and every assertion about it passes.
 *
 * **The cache directory is real and is swept.** `PdfCache` writes to
 * `uploads/dp-resume/`, and the point of most of these tests is which file is
 * there afterwards. A double would move the assertion off the thing under test;
 * leaving the files behind would make each test depend on the last one.
 *
 * **Roles are created with an old `post_date`.** `wp_insert_post()` sets a new
 * post's `post_modified` from its `post_date` and an updated post's to right
 * now, so a fixture dated 2020 is the only way a test can update a role and see
 * the modified date actually move. Two updates in the same second are otherwise
 * indistinguishable, and the cache key is built out of exactly that timestamp.
 */
abstract class ResumeTestCase extends WP_UnitTestCase {

	/**
	 * The page carrying the résumé template.
	 *
	 * @var int
	 */
	protected int $resume_page = 0;

	/**
	 * The cache under test.
	 *
	 * @var PdfCache
	 */
	protected PdfCache $cache;

	/**
	 * Register the content model and start from an empty cache.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();

		$this->cache = new PdfCache();

		$this->empty_the_cache();
	}

	/**
	 * Leave nothing behind for the next test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->empty_the_cache();

		parent::tear_down();
	}

	/**
	 * Create the page David assigned the résumé template to.
	 *
	 * The slug is deliberately not `resume`: CLAUDE.md section 5.1 says nothing
	 * may assume one, and a fixture using the expected name would never notice a
	 * slug creeping in.
	 *
	 * @param string $template The template as `_wp_page_template` holds it.
	 * @param string $status   The post status.
	 * @return int
	 */
	protected function seed_resume_page( string $template = ResumePdf::TEMPLATE, string $status = 'publish' ): int {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'The record, on one page',
				'post_name'    => 'the-record-on-one-page',
				'post_status'  => $status,
				'post_date'    => '2020-01-01 00:00:00',
				'post_content' => '<!-- wp:dp/resume-ledger /-->',
			)
		);

		$this->assertIsInt( $page_id );

		if ( '' !== $template ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
		}

		$this->resume_page = $page_id;

		return $page_id;
	}

	/**
	 * A published role, dated in the past so an update can be seen to move it.
	 *
	 * @param string $org  The organisation, which is the post title.
	 * @param float  $start The decimal year it began.
	 * @param string $date  The publication date, which is also its modified date.
	 * @return int
	 */
	protected function seed_role( string $org, float $start = 2020.0, string $date = '2020-01-01 00:00:00' ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => PostTypes::ROLE,
				'post_title' => $org,
				'post_date'  => $date,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_role_title', 'Placeholder title' );
		update_post_meta( $post_id, 'dp_start', $start );
		update_post_meta( $post_id, 'dp_end', $start + 2.0 );
		update_post_meta( $post_id, 'dp_range', sprintf( '%d — %d', (int) $start, (int) $start + 2 ) );
		update_post_meta( $post_id, 'dp_detail', 'Placeholder role description.' );
		update_post_meta( $post_id, 'dp_stack', 'PHP · JS' );

		return $post_id;
	}

	/**
	 * A published shipped thing, hung off a role.
	 *
	 * The link is `dp_role_id` and not `post_parent`: that is what `Chart` groups
	 * on, and a ship whose `dp_role_id` points at nothing is dropped rather than
	 * shown loose.
	 *
	 * @param string $name  The thing's name, which is the post title.
	 * @param int    $role  The role it came out of.
	 * @param string $range The range exactly as it is printed.
	 * @param string $date  The publication date, which is also its modified date.
	 * @return int
	 */
	protected function seed_ship( string $name, int $role, string $range = '2021', string $date = '2020-06-01 00:00:00' ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => PostTypes::SHIP,
				'post_title' => $name,
				'post_date'  => $date,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_role_id', $role );
		update_post_meta( $post_id, 'dp_start', 2020.5 );
		update_post_meta( $post_id, 'dp_end', 2021.0 );
		update_post_meta( $post_id, 'dp_range', $range );
		update_post_meta( $post_id, 'dp_detail', 'Placeholder description.' );

		return $post_id;
	}

	/**
	 * Delete every file the cache is holding.
	 *
	 * @return void
	 */
	protected function empty_the_cache(): void {
		$uploads = wp_upload_dir();

		if ( ! is_string( $uploads['basedir'] ?? null ) ) {
			return;
		}

		$found = glob( $uploads['basedir'] . '/' . PdfCache::DIRECTORY . '/*.pdf' );

		foreach ( is_array( $found ) ? $found : array() as $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * How many rendered files the cache is holding.
	 *
	 * @return int
	 */
	protected function cached_files(): int {
		$uploads = wp_upload_dir();
		$found   = is_string( $uploads['basedir'] ?? null )
			? glob( $uploads['basedir'] . '/' . PdfCache::DIRECTORY . '/*.pdf' )
			: false;

		return is_array( $found ) ? count( $found ) : 0;
	}

	/**
	 * Move a post's modified date, the way an edit an hour later would.
	 *
	 * `wp_update_post()` always writes "now", so two edits inside one second are
	 * the same timestamp and the key cannot tell them apart. Writing the column
	 * directly is what makes "the content behind it changed" a thing a test can
	 * state precisely.
	 *
	 * @param int    $post_id  The post.
	 * @param string $modified The new modified date, in GMT.
	 * @return void
	 */
	protected function touch_post( int $post_id, string $modified ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the column under test; wp_update_post() cannot set it.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $modified,
				'post_modified_gmt' => $modified,
			),
			array( 'ID' => $post_id )
		);

		clean_post_cache( $post_id );
	}
}
