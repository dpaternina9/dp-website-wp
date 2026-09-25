<?php
/**
 * Integration tests for telling page caches the Photos page changed.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\CachePurge;
use DP\Core\Photos\Taxonomies;

/**
 * A photo changes; every Photos page gets core's "this post changed" signal.
 *
 * The signal is `clean_post_cache()`, which fires the `clean_post_cache`
 * action — what page-cache and CDN plugins listen to. The test listens to the
 * same action and records which posts it was fired for.
 */
final class CachePurgeTest extends PhotosTestCase {

	/**
	 * The purger under test, hooked as the plugin hooks it.
	 *
	 * @var CachePurge
	 */
	private CachePurge $purge;

	/**
	 * The page carrying the Photos template.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * A page that is not a Photos page.
	 *
	 * @var int
	 */
	private int $other;

	/**
	 * Posts `clean_post_cache` was fired for, in order.
	 *
	 * @var list<int>
	 */
	private array $cleaned = array();

	/**
	 * Two pages, one with the template; a fresh purger; a listener.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->page  = $this->ok( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$this->other = $this->ok( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_post_meta( $this->page, '_wp_page_template', CachePurge::TEMPLATE );

		$this->purge = new CachePurge();
		$this->purge->register();
		$this->purge->flush();

		add_action(
			'clean_post_cache',
			function ( mixed $post_id ): void {
				$this->cleaned[] = is_numeric( $post_id ) ? (int) $post_id : 0;
			}
		);
	}

	/**
	 * What one flush cleaned, then forget it.
	 *
	 * @return list<int>
	 */
	private function flushed(): array {
		$this->cleaned = array();
		$pages         = $this->purge->flush();

		$this->assertSame( $pages, array_values( array_unique( $this->cleaned ) ) );

		return $pages;
	}

	/**
	 * Publishing a photo cleans the Photos page and only it — once.
	 *
	 * @return void
	 */
	public function test_publishing_a_photo_purges_the_photos_page(): void {
		$this->photo();
		$this->photo();

		$this->assertTrue( $this->purge->pending() );
		$this->assertSame( array( $this->page ), $this->flushed() );
		$this->assertNotContains( $this->other, $this->cleaned );

		// Once per request: nothing further happened, so nothing more to do.
		$this->assertSame( array(), $this->flushed() );
	}

	/**
	 * Trashing a published photo purges it.
	 *
	 * @return void
	 */
	public function test_trashing_a_photo_purges_the_photos_page(): void {
		$photo = $this->photo();
		$this->flushed();

		wp_trash_post( $photo );

		$this->assertSame( array( $this->page ), $this->flushed() );
	}

	/**
	 * Re-filing a photo under a trip or a topic purges it.
	 *
	 * @return void
	 */
	public function test_refiling_a_photo_purges_the_photos_page(): void {
		$photo = $this->photo();
		$this->flushed();

		wp_set_object_terms( $photo, array( $this->term( Taxonomies::TOPIC, 'Night' )->term_id ), Taxonomies::TOPIC );

		$this->assertSame( array( $this->page ), $this->flushed() );
	}

	/**
	 * Replacing a photo's image purges it.
	 *
	 * @return void
	 */
	public function test_a_new_featured_image_purges_the_photos_page(): void {
		$photo = $this->photo();
		$this->flushed();

		set_post_thumbnail( $photo, $this->image() );

		$this->assertSame( array( $this->page ), $this->flushed() );
	}

	/**
	 * Drafts and ordinary posts change nothing on the page.
	 *
	 * @return void
	 */
	public function test_a_draft_or_a_post_does_not_purge(): void {
		$this->photo( array( 'status' => 'draft' ) );
		$this->ok( self::factory()->post->create() );

		$this->assertFalse( $this->purge->pending() );
		$this->assertSame( array(), $this->flushed() );
	}
}
