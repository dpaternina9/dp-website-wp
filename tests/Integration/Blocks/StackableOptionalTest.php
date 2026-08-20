<?php
/**
 * Stackable is additive. This is the test that says so.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Proves CLAUDE.md §1.2: "If Stackable is deactivated, every template must
 * still render."
 *
 * The integration suite is the strongest possible place to assert it, because
 * Stackable is not installed here at all: tests/bootstrap.php loads the theme
 * and dp-core and nothing else. Every template rendered below is therefore
 * rendered on a site where Stackable has never existed. The test asserts that
 * precondition first, so it can never pass for the wrong reason.
 */
final class StackableOptionalTest extends WP_UnitTestCase {

	/**
	 * Directories in the theme whose contents may not name Stackable.
	 *
	 * `src/` is deliberately not among them: DP\Theme\Blocks\Vocabulary names
	 * the `stackable/` prefix, which is the allowlist admitting it rather than
	 * the theme depending on it.
	 *
	 * @var list<string>
	 */
	private const THEME_SOURCES = array( 'templates', 'parts', 'patterns', 'assets/css' );

	/**
	 * Stackable really is absent in this environment.
	 *
	 * @return void
	 */
	public function test_stackable_is_not_registered_here(): void {
		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			$this->assertStringStartsNotWith(
				'stackable/',
				(string) $name,
				'Stackable is loaded in the integration suite, so the rest of this test proves nothing.'
			);
		}
	}

	/**
	 * Every template the theme ships renders, and renders to something.
	 *
	 * @return void
	 */
	public function test_every_template_renders_without_stackable(): void {
		$templates = get_block_templates( array(), 'wp_template' );

		$this->assertNotEmpty( $templates, 'The theme registered no templates, so nothing was rendered.' );

		$seen = 0;

		foreach ( $templates as $template ) {
			if ( 'dpaternina' !== $template->theme ) {
				continue;
			}

			$rendered = do_blocks( $template->content );

			$this->assertStringNotContainsString(
				'wp:stackable',
				$rendered,
				sprintf( 'Template "%s" carries a Stackable block.', $template->slug )
			);

			$this->assertNotSame(
				'',
				trim( $rendered ),
				sprintf( 'Template "%s" rendered to nothing.', $template->slug )
			);

			++$seen;
		}

		$this->assertGreaterThanOrEqual( 5, $seen, 'The theme ships index plus four custom templates.' );
	}

	/**
	 * The fixture post renders in full with no third-party blocks present.
	 *
	 * @return void
	 */
	public function test_the_house_style_renders_without_stackable(): void {
		$html = do_blocks( HouseStyleFixture::content() );

		$this->assertStringContainsString( 'wp-block-quote', $html );
		$this->assertStringContainsString( 'wp-block-code', $html );
		$this->assertStringContainsString( 'wp-block-dp-callout', $html );
		$this->assertStringContainsString( 'wp-block-separator', $html );
	}

	/**
	 * Nothing the theme ships mentions Stackable at all.
	 *
	 * A template that renders today but carries `stackable/` markup would blank
	 * a section the day the plugin came off. The rendering test above cannot see
	 * a template that is added later; this can.
	 *
	 * @return void
	 */
	public function test_no_theme_file_names_stackable(): void {
		$root     = get_stylesheet_directory();
		$searched = 0;

		foreach ( self::THEME_SOURCES as $relative ) {
			$directory = $root . '/' . $relative;

			if ( ! is_dir( $directory ) ) {
				continue;
			}

			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ) );

			foreach ( $files as $file ) {
				if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
					continue;
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$contents = (string) file_get_contents( $file->getPathname() );

				$this->assertStringNotContainsStringIgnoringCase(
					'stackable',
					$contents,
					sprintf( '%s depends on Stackable. Nothing in the theme may.', $file->getPathname() )
				);

				++$searched;
			}
		}

		$this->assertGreaterThan( 5, $searched, 'No theme files were searched, so this proves nothing.' );
	}
}
