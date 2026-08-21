<?php
/**
 * Integration tests for the résumé PDF's cache key.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Resume;

use DP\Core\Resume\PdfCache;

/**
 * "Regenerates exactly when the content behind it changes, and is a static file
 * every other time" — `docs/plan.md` section 7.1, asserted from both directions.
 *
 * Only one of those two halves is obvious. A key that changes too often costs an
 * API call and a browser somewhere; a key that changes too rarely serves a
 * résumé listing a job that is no longer on the page, and nothing ever says so.
 * So every test here is a pair: something changes and the key moves, or
 * something changes and it deliberately does not.
 *
 * The deletion case is the one that is easy to get wrong and is the reason the
 * key counts rows as well as reading the newest date. Deleting a role does not
 * change any remaining role's `post_modified`, so a key built from dates alone
 * would go on serving the old PDF for ever.
 */
final class PdfCacheTest extends ResumeTestCase {

	/**
	 * The same content gives the same key, every time it is asked.
	 *
	 * @return void
	 */
	public function test_the_key_is_stable_while_nothing_changes(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );

		$key = $this->cache->key( $page );

		$this->assertMatchesRegularExpression( '~^[0-9a-f]{32}$~', $key );
		$this->assertSame( $key, $this->cache->key( $page ) );
		$this->assertSame( $key, ( new PdfCache() )->key( $page ) );
	}

	/**
	 * Editing a role regenerates it.
	 *
	 * @return void
	 */
	public function test_the_key_changes_when_a_role_is_modified(): void {
		$page = $this->seed_resume_page();
		$role = $this->seed_role( 'Backbone Technology' );

		$before = $this->cache->key( $page );

		$this->touch_post( $role, '2026-08-01 09:00:00' );

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Editing a shipped thing regenerates it too.
	 *
	 * @return void
	 */
	public function test_the_key_changes_when_a_ship_is_modified(): void {
		$page = $this->seed_resume_page();
		$role = $this->seed_role( 'Backbone Technology' );
		$ship = $this->seed_ship( 'Natural-language queries', $role );

		$before = $this->cache->key( $page );

		$this->touch_post( $ship, '2026-08-01 09:00:00' );

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Adding a role regenerates it.
	 *
	 * @return void
	 */
	public function test_the_key_changes_when_a_role_is_added(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );

		$before = $this->cache->key( $page );

		$this->seed_role( 'Fanxie Lab', 2024.4, '2024-05-01 00:00:00' );

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Deleting a role regenerates it, which dates alone could not manage.
	 *
	 * @return void
	 */
	public function test_the_key_changes_when_a_role_is_deleted(): void {
		$page   = $this->seed_resume_page();
		$oldest = $this->seed_role( 'Backbone Technology', 2014.0, '2014-01-01 00:00:00' );

		$this->seed_role( 'Fanxie Lab', 2024.4, '2024-05-01 00:00:00' );

		$before = $this->cache->key( $page );

		/*
		 * The one deleted is deliberately not the newest: removing the newest
		 * would move the MAX() and the key would change for the wrong reason,
		 * so the test would pass with the row count taken back out.
		 */
		wp_delete_post( $oldest, true );

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Editing the page the résumé is on regenerates it.
	 *
	 * @return void
	 */
	public function test_the_key_changes_when_the_page_itself_is_modified(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );

		$before = $this->cache->key( $page );

		$this->touch_post( $page, '2026-08-01 09:00:00' );

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Two different pages do not share a key.
	 *
	 * @return void
	 */
	public function test_two_pages_have_two_keys(): void {
		$page  = $this->seed_resume_page();
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertIsInt( $other );
		$this->assertNotSame( $this->cache->key( $page ), $this->cache->key( $other ) );
	}

	/**
	 * A post that is not part of the record does not regenerate it.
	 *
	 * @return void
	 */
	public function test_writing_a_blog_post_does_not_regenerate_it(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );

		$before = $this->cache->key( $page );

		self::factory()->post->create( array( 'post_title' => 'An unrelated post' ) );

		$this->assertSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Editing a different page does not regenerate it.
	 *
	 * @return void
	 */
	public function test_editing_another_page_does_not_regenerate_it(): void {
		$page  = $this->seed_resume_page();
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertIsInt( $other );

		$before = $this->cache->key( $page );

		$this->touch_post( $other, '2026-08-01 09:00:00' );

		$this->assertSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * A role that is not published is not on the page, so it is not in the key.
	 *
	 * @return void
	 */
	public function test_an_unpublished_role_does_not_regenerate_it(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );

		$before = $this->cache->key( $page );

		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'dp_role',
				'post_title'  => 'Somewhere I have not written up yet',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $draft );
		$this->assertSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * Publishing that draft does regenerate it.
	 *
	 * @return void
	 */
	public function test_publishing_a_role_regenerates_it(): void {
		$page = $this->seed_resume_page();
		$this->seed_role( 'Backbone Technology' );
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'dp_role',
				'post_title'  => 'Somewhere I have not written up yet',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $draft );

		$before = $this->cache->key( $page );

		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'publish',
			)
		);

		$this->assertNotSame( $before, $this->cache->key( $page ) );
	}

	/**
	 * A stored file is found again under the key that wrote it.
	 *
	 * @return void
	 */
	public function test_a_written_file_is_fresh_for_its_own_key(): void {
		$page = $this->seed_resume_page();
		$key  = $this->cache->key( $page );

		$this->assertNull( $this->cache->fresh( $key ) );

		$path = $this->cache->write( $key, '%PDF-1.7' );

		$this->assertIsString( $path );
		$this->assertSame( $path, $this->cache->fresh( $key ) );
		$this->assertStringEndsWith( $key . '.pdf', $path );
	}

	/**
	 * The previous rendering survives a new one, because stale beats nothing.
	 *
	 * @return void
	 */
	public function test_writing_a_new_key_keeps_the_one_before_it(): void {
		$page = $this->seed_resume_page();
		$role = $this->seed_role( 'Backbone Technology' );

		$first = $this->cache->key( $page );
		$this->cache->write( $first, '%PDF-first' );

		$this->touch_post( $role, '2026-08-01 09:00:00' );

		$second = $this->cache->key( $page );
		$this->cache->write( $second, '%PDF-second' );

		$this->assertNotSame( $first, $second );
		$this->assertIsString( $this->cache->fresh( $first ) );
		$this->assertIsString( $this->cache->fresh( $second ) );
	}

	/**
	 * The directory is bounded: two files, newest first, and no more.
	 *
	 * @return void
	 */
	public function test_pruning_keeps_the_two_newest_files(): void {
		foreach ( range( 1, 5 ) as $index ) {
			$path = $this->cache->write( str_pad( (string) $index, 32, '0', STR_PAD_LEFT ), '%PDF-' . $index );

			$this->assertIsString( $path );

			/*
			 * Distinct modification times, so "newest" is not the filesystem's
			 * guess. WP_Filesystem has no touch() with a timestamp and this is a
			 * test fixture on the test runner's own disk, not a site's uploads.
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
			touch( $path, time() + $index );
		}

		$this->cache->prune();

		$this->assertSame( 2, $this->cached_files() );
		$this->assertIsString( $this->cache->stale() );
	}

	/**
	 * With nothing ever rendered there is no stale copy to fall back to.
	 *
	 * @return void
	 */
	public function test_an_empty_cache_has_no_stale_copy(): void {
		$this->assertNull( $this->cache->stale() );
	}

	/**
	 * The stale copy is the most recently written file, whatever key wrote it.
	 *
	 * @return void
	 */
	public function test_the_stale_copy_is_the_newest_file(): void {
		$older = $this->cache->write( str_repeat( 'a', 32 ), '%PDF-older' );
		$newer = $this->cache->write( str_repeat( 'b', 32 ), '%PDF-newer' );

		$this->assertIsString( $older );
		$this->assertIsString( $newer );

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_touch -- see the note in the pruning test.
		touch( $older, time() - 600 );
		touch( $newer, time() );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_touch

		$this->assertSame( $newer, $this->cache->stale() );
	}
}
