<?php
/**
 * The shared harness for the template tests.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Content\ContentModel;
use DP\Core\Content\Taxonomies;
use DP\Theme\Chrome\Destinations;
use WP_Post;
use WP_Term;
use WP_UnitTestCase;

/**
 * Renders a template the way a request does, and builds the design's fixture.
 *
 * Two things here are load-bearing.
 *
 * **The template is resolved, not named.** `locate_block_template()` is the
 * function a real request goes through, so asking it for the template rather
 * than fetching `dpaternina//home` by hand is what makes the assertion "core
 * picks this file for this URL" mean anything. Every test that cares which
 * template answered asserts on `$_wp_current_template_id` afterwards, and that
 * is the only place the file name appears.
 *
 * **The model is re-registered in `set_up()`.** `WP_UnitTestCase::tear_down()`
 * calls `unregister_all_meta_keys()`, so from the second test in a run onwards
 * everything `dp-core` registered on `init` is gone. Without this a suite
 * asserts against an empty content model and passes, which is worse than
 * failing (ADR-0003).
 */
abstract class TemplateTestCase extends WP_UnitTestCase {

	/**
	 * Category term IDs, by slug.
	 *
	 * @var array<string, int>
	 */
	protected array $categories = array();

	/**
	 * Published post IDs, newest first.
	 *
	 * @var list<int>
	 */
	protected array $posts = array();

	/**
	 * The `dp_series` term.
	 *
	 * @var int
	 */
	protected int $series = 0;

	/**
	 * The page David chose as the posts index.
	 *
	 * @var int
	 */
	protected int $posts_page = 0;

	/**
	 * Register the content model and reset what the chrome caches.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();

		delete_transient( Destinations::CACHE_KEY );
	}

	/**
	 * Render the template WordPress would choose for a URL.
	 *
	 * @param string             $url       The URL to visit.
	 * @param string             $type      The template type, e.g. `home`, `single`.
	 * @param array<int, string> $hierarchy The PHP-theme hierarchy for that type, which
	 *                                      is what `locate_block_template()` maps
	 *                                      onto block templates.
	 * @return string The rendered document body.
	 */
	protected function render( string $url, string $type, array $hierarchy ): string {
		$this->go_to( $url );

		locate_block_template( '', $type, $hierarchy );

		return get_the_block_template_html();
	}

	/**
	 * The template that answered the last `render()`.
	 *
	 * @return string For example `dpaternina//home`.
	 */
	protected function resolved_template(): string {
		global $_wp_current_template_id;

		return is_string( $_wp_current_template_id ) ? $_wp_current_template_id : '';
	}

	/**
	 * Create the five categories the design names.
	 *
	 * @return void
	 */
	protected function seed_categories(): void {
		foreach ( array(
			'dev'           => 'Dev',
			'my-life-story' => 'My life story',
			'food'          => 'Food',
		) as $slug => $name ) {
			$term = self::factory()->category->create_and_get(
				array(
					'slug'        => $slug,
					'name'        => $name,
					'description' => $name . ' — what this archive collects.',
				)
			);

			$this->assertInstanceOf( WP_Term::class, $term );

			$this->categories[ $slug ] = $term->term_id;
		}
	}

	/**
	 * Create published posts, newest first, in the given category.
	 *
	 * @param int    $count    How many.
	 * @param string $category The category slug they all carry.
	 * @return list<int>
	 */
	protected function seed_posts( int $count, string $category = 'dev' ): array {
		$created = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_title'    => sprintf( 'Post number %d', $index + 1 ),
					'post_excerpt'  => sprintf( 'The standfirst of post number %d.', $index + 1 ),
					'post_content'  => '<!-- wp:paragraph --><p>Body copy.</p><!-- /wp:paragraph -->',
					'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( $index + 1 ) * DAY_IN_SECONDS ),
					'post_category' => array( $this->categories[ $category ] ),
				)
			);

			$this->assertIsInt( $post_id );

			$created[] = $post_id;
		}

		$this->posts = array_merge( $this->posts, $created );

		return $created;
	}

	/**
	 * Create the series term.
	 *
	 * @return int
	 */
	protected function seed_series(): int {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy'    => Taxonomies::SERIES,
				'name'        => 'My life story',
				'slug'        => 'life-story',
				'description' => 'The deck under the series title.',
			)
		);

		$this->assertInstanceOf( WP_Term::class, $term );

		$this->series = $term->term_id;

		return $this->series;
	}

	/**
	 * File a post under the series with a part number.
	 *
	 * @param int    $post_id The post.
	 * @param int    $part    Its part number, which is also its `menu_order`.
	 * @param string $years   The years a planned part covers.
	 * @param string $note    The line under a planned part.
	 * @return void
	 */
	protected function file_under_series( int $post_id, int $part, string $years = '', string $note = '' ): void {
		wp_set_post_terms( $post_id, array( $this->series ), Taxonomies::SERIES, false );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'menu_order' => $part,
			)
		);

		update_post_meta( $post_id, 'dp_series_part', $part );
		update_post_meta( $post_id, 'dp_series_years', $years );
		update_post_meta( $post_id, 'dp_series_note', $note );
	}

	/**
	 * Create a page, optionally carrying one of the theme's custom templates.
	 *
	 * @param string $title    The page's title.
	 * @param string $template The template file, e.g. `dp-contact.html`, or ''.
	 * @return int
	 */
	protected function seed_page( string $title, string $template = '' ): int {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Placeholder body.</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertIsInt( $page_id );

		if ( '' !== $template ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
		}

		delete_transient( Destinations::CACHE_KEY );

		return $page_id;
	}

	/**
	 * Point Settings to Reading at a page, whatever it is called.
	 *
	 * The slug is deliberately not `blog`: digest §2 says the template must
	 * render correctly whether David calls it `/blog`, `/writing`, or leaves it
	 * unset, and a fixture that used the expected name would never notice a slug
	 * creeping into the theme.
	 *
	 * @param string $title What David called it.
	 * @return int The page ID.
	 */
	protected function seed_posts_page( string $title = 'Field notes' ): int {
		$front = $this->seed_page( 'Welcome' );
		$posts = $this->seed_page( $title );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $posts );

		$this->posts_page = $posts;

		return $posts;
	}

	/**
	 * The permalink of a post, asserted to exist.
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	protected function permalink( int $post_id ): string {
		$url = get_permalink( $post_id );

		$this->assertIsString( $url );

		return $url;
	}

	/**
	 * A `dp_role` carrying the fields the record strip prints.
	 *
	 * @param string $org   The organisation, which is the post title.
	 * @param string $title The job title.
	 * @param float  $end   The decimal year it ended.
	 * @return int
	 */
	protected function seed_role( string $org, string $title, float $end ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'dp_role',
				'post_title' => $org,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_role_title', $title );
		update_post_meta( $post_id, 'dp_start', $end - 2.0 );
		update_post_meta( $post_id, 'dp_end', $end );
		update_post_meta( $post_id, 'dp_range', sprintf( '%d — %d', (int) $end - 2, (int) $end ) );

		return $post_id;
	}

	/**
	 * A `dp_ship`, featured or not.
	 *
	 * @param string $name     The thing's name, which is the post title.
	 * @param bool   $featured Whether it appears as a WorkCard.
	 * @param float  $end      The decimal year it shipped.
	 * @return int
	 */
	protected function seed_ship( string $name, bool $featured, float $end ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'dp_ship',
				'post_title' => $name,
			)
		);

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'dp_featured', $featured );
		update_post_meta( $post_id, 'dp_end', $end );
		update_post_meta( $post_id, 'dp_range', (string) (int) $end );
		update_post_meta( $post_id, 'dp_detail', $name . ' — one line on what it is.' );

		return $post_id;
	}

	/**
	 * Assert that a post's body never reaches rendered markup.
	 *
	 * @param string  $html The rendered template.
	 * @param WP_Post $post The post whose body must not be there.
	 * @return void
	 */
	protected function assertBodyAbsent( string $html, WP_Post $post ): void {
		$this->assertStringNotContainsString( $post->post_content, $html );
	}
}
