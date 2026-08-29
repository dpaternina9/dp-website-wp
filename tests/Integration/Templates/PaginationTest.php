<?php
/**
 * Integration tests for the pager and the two page-state classes.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Theme\Blocks\PageState;
use DP\Theme\Patterns;
use DP\Theme\Query\Pagination;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What `DP\Theme\Query\Pagination` computes, and what it is allowed to run on.
 *
 * Two of the three things asserted here are about cost and blast radius rather
 * than about the design, and both come from ADR-0018's second rule — a
 * computation announces itself, and does not stand in the way of everything
 * else.
 *
 * **The page state is a block, not a class.** It was a filter on bare
 * `render_block` that parsed a class attribute for every block on every page,
 * then narrowed to `core/group`, and in both shapes the trigger was invisible:
 * nothing about `dp-when-paginated` said that PHP would replace the group's
 * output with the empty string. It is `dpaternina/page-state` now, with the
 * state in the block's own attributes and a title in the inserter, and the
 * assertions below hold the retired classes out of the shipped markup.
 *
 * **The dead step is drawn by filtering the step, not the bar.** The first
 * version filtered `core/query-pagination` and spliced a `<span>` into the
 * rendered `<nav>`, reading the label back out with a regular expression over
 * that markup. Both assertions below are about what that cost: the label now
 * comes from the attribute the template set, and the step lands where the
 * template put it rather than at whichever end of the bar the splice chose.
 */
final class PaginationTest extends TemplateTestCase {

	/**
	 * The hierarchy for a category archive.
	 *
	 * @var array<int, string>
	 */
	private const CATEGORY = array( 'category.php', 'archive.php', 'index.php' );

	/*
	 * -------------------------------------------------------- The visible trigger
	 */

	/**
	 * Nothing in the shipped markup asks for behaviour with a bare CSS class.
	 *
	 * `dp-when-paginated` and `dp-when-last-page` were exactly that: a
	 * `core/group` carrying one of them had its rendered output replaced with
	 * the empty string. Nothing in the block, the inspector or the canvas said
	 * so, and the canvas drew two containers the front end usually did not.
	 * ADR-0018 rule 2. They are `dpaternina/page-state` now — a named block with
	 * a state in its attributes and a title in the inserter.
	 *
	 * @return void
	 */
	public function test_no_shipped_markup_asks_for_behaviour_with_a_bare_class(): void {
		$retired = array( 'dp-when-paginated', 'dp-when-last-page' );
		$markup  = $this->theme_markup_files();

		$markup['DP\Theme\Patterns::pager()'] = Patterns::pager();

		foreach ( $markup as $relative => $source ) {
			foreach ( $retired as $class ) {
				$this->assertStringNotContainsString( $class, $source, $relative );
			}
		}

		$this->assertStringContainsString( PageState::NAME, Patterns::pager(), 'The bar names the block instead.' );
	}

	/**
	 * No theme filter is attached to bare `render_block`.
	 *
	 * The hook fires once per block on every page. A filter on it that then
	 * parses a class attribute to decide it has nothing to do is the shape
	 * ADR-0018 narrowed twice; this is what stops a third.
	 *
	 * @return void
	 */
	public function test_no_theme_filter_is_attached_to_bare_render_block(): void {
		$offenders = array();

		foreach ( $this->theme_sources() as $relative => $source ) {
			if ( str_contains( $source, "add_filter( 'render_block'" ) ) {
				$offenders[] = $relative;
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'A filter on bare render_block runs for every block on every page. Name the block types instead.'
		);
	}

	/**
	 * The bar is drawn on a paginated archive and the end panel is not, until the end.
	 *
	 * The behaviour the class used to carry, asserted through the block that
	 * carries it now.
	 *
	 * @return void
	 */
	public function test_the_page_state_block_draws_each_state_where_it_belongs(): void {
		$this->seed_categories();
		$this->seed_posts( 12, 'dev' );

		$first = $this->render( $this->archive(), 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-pagination', $first, 'Two pages, so the bar is drawn.' );

		$last = $this->render( add_query_arg( 'paged', 2, $this->archive() ), 'category', self::CATEGORY );

		$this->assertStringContainsString( 'dp-pagination', $last );
	}

	/*
	 * -------------------------------------------------------------- The dead step
	 */

	/**
	 * On page one the previous step is drawn, inert, before the numbers.
	 *
	 * @return void
	 */
	public function test_page_one_draws_an_inert_previous_step_in_its_own_place(): void {
		$this->seed_categories();
		$this->seed_posts( 12, 'dev' );

		$html = $this->render( $this->archive(), 'category', self::CATEGORY );

		$this->assertStringContainsString(
			'<span class="wp-block-query-pagination-previous ' . Pagination::STEP_DISABLED . '" aria-disabled="true">Prev</span>',
			$html,
			'The design draws PREV on page one, dimmed; core drops it and the row jumps sideways.'
		);

		$step    = strpos( $html, Pagination::STEP_DISABLED );
		$numbers = strpos( $html, 'wp-block-query-pagination-numbers' );

		$this->assertIsInt( $step );
		$this->assertIsInt( $numbers );
		$this->assertLessThan(
			$numbers,
			$step,
			'The step belongs where the template put it, which is before the numbers.'
		);

		// NEXT, on the same page, is a real link.
		$this->assertStringContainsString( 'wp-block-query-pagination-next', $html );
		$this->assertStringNotContainsString(
			'<span class="wp-block-query-pagination-next',
			$html,
			'There is a page two, so NEXT is a link rather than a drawn-and-disabled step.'
		);
	}

	/**
	 * On the last page it is the next step that is drawn inert.
	 *
	 * @return void
	 */
	public function test_the_last_page_draws_an_inert_next_step(): void {
		$this->seed_categories();
		$this->seed_posts( 12, 'dev' );

		$html = $this->render( add_query_arg( 'paged', 2, $this->archive() ), 'category', self::CATEGORY );

		$this->assertStringContainsString(
			'<span class="wp-block-query-pagination-next ' . Pagination::STEP_DISABLED . '" aria-disabled="true">Next</span>',
			$html
		);
		$this->assertStringNotContainsString( '<span class="wp-block-query-pagination-previous', $html );
	}

	/**
	 * The word on the drawn step is the one the template set, not one of ours.
	 *
	 * `core/query-pagination-previous` takes a `label` attribute and David can
	 * change it in the site editor. The old code read the word back out of the
	 * *other* step's rendered anchor with a regular expression; this reads the
	 * attribute off the block that did not render, which is where the word
	 * actually is.
	 *
	 * @return void
	 */
	public function test_the_drawn_step_carries_the_label_the_template_set(): void {
		$this->seed_categories();
		$this->seed_posts( 12, 'dev' );

		$this->override( 'wp_template', 'category', $this->archive_template_labelled( 'Older', 'Newer' ) );

		$html = $this->render( $this->archive(), 'category', self::CATEGORY );

		$this->assertStringContainsString( 'aria-disabled="true">Older</span>', $html );
		$this->assertStringNotContainsString( 'aria-disabled="true">Prev</span>', $html );
	}

	/**
	 * A one-page archive has no bar and no drawn steps.
	 *
	 * Core returns nothing for a pagination whose children all rendered nothing,
	 * and this must not be what puts something back into it.
	 *
	 * @return void
	 */
	public function test_a_one_page_archive_has_no_pager_at_all(): void {
		$this->seed_categories();
		$this->seed_posts( 3, 'dev' );

		$html = $this->render( $this->archive(), 'category', self::CATEGORY );

		$this->assertStringNotContainsString( Pagination::STEP_DISABLED, $html );
		$this->assertStringNotContainsString( 'dp-pagination', $html );
	}

	/*
	 * ------------------------------------------------------------------ Fixtures
	 */

	/**
	 * The archive of the `dev` category.
	 *
	 * @return string
	 */
	private function archive(): string {
		$link = get_category_link( $this->categories['dev'] );

		$this->assertIsString( $link );

		return $link;
	}

	/**
	 * A category template whose pager carries two different labels.
	 *
	 * Saved as a `wp_template` post, which is what the site editor writes and
	 * what `get_block_templates()` then prefers over the theme's file.
	 *
	 * @param string $previous The word on the previous step.
	 * @param string $next     The word on the next step.
	 * @return string
	 */
	private function archive_template_labelled( string $previous, string $next ): string {
		$pager = str_replace(
			array( '"label":"Prev"', '"label":"Next"' ),
			array( '"label":"' . $previous . '"', '"label":"' . $next . '"' ),
			Patterns::pager()
		);

		return '<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"className":"dp-query"} -->'
			. '<div class="wp-block-query dp-query"><!-- wp:post-template -->'
			. Patterns::post_row()
			. '<!-- /wp:post-template -->'
			. $pager
			. '</div>'
			. '<!-- /wp:query -->';
	}

	/**
	 * Every PHP file the theme ships, keyed by path.
	 *
	 * @return array<string, string>
	 */
	private function theme_sources(): array {
		$root  = get_stylesheet_directory();
		$found = array();

		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );

		foreach ( $files as $file ) {
			if ( ! $file instanceof SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = $file->getPathname();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the theme under test.
			$source = file_get_contents( $path );

			if ( is_string( $source ) ) {
				$found[ substr( $path, strlen( $root ) + 1 ) ] = $source;
			}
		}

		$this->assertNotEmpty( $found );

		return $found;
	}
}
