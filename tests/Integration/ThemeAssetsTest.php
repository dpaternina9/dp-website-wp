<?php
/**
 * The theme's stylesheets, fonts, and the promise that nothing leaves the origin.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Theme\Assets;
use WP_UnitTestCase;

/**
 * Covers CLAUDE.md §5 (the editor must look like the front end) and §1.4
 * (nothing enqueues from somewhere else).
 */
final class ThemeAssetsTest extends WP_UnitTestCase {

	/**
	 * The editor is handed exactly the stylesheets the front end gets.
	 *
	 * Both lists come from one constant, so this asserts the wiring rather than
	 * the constant: that `add_editor_style()` really ran, and that
	 * `wp_enqueue_scripts` really enqueues the same files.
	 *
	 * @return void
	 */
	public function test_the_editor_and_the_front_end_load_the_same_stylesheets(): void {
		$expected = array_values( Assets::stylesheets() );

		$this->assertNotEmpty( $expected, 'The theme loads no stylesheets, so this test proves nothing.' );

		$this->assertTrue( current_theme_supports( 'editor-styles' ), 'Without editor-styles support the block editor ignores add_editor_style().' );

		$editor = array();

		foreach ( (array) ( $GLOBALS['editor_styles'] ?? array() ) as $style ) {
			if ( is_string( $style ) ) {
				$editor[] = ltrim( $style, '/' );
			}
		}

		foreach ( $expected as $relative ) {
			$this->assertContains(
				$relative,
				$editor,
				sprintf( '%s is loaded on the front end but not in the editor. CLAUDE.md §5.', $relative )
			);
		}

		do_action( 'wp_enqueue_scripts' );

		foreach ( array_keys( Assets::stylesheets() ) as $handle ) {
			$this->assertTrue(
				wp_style_is( $handle, 'enqueued' ),
				sprintf( 'The "%s" stylesheet is registered for the editor but never enqueued on the front end.', $handle )
			);
		}
	}

	/**
	 * Every font the theme declares is a file inside the theme.
	 *
	 * @return void
	 */
	public function test_every_declared_font_is_self_hosted(): void {
		$settings = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );

		$this->assertIsArray( $settings );

		$families = $settings['theme'] ?? null;

		$this->assertIsArray( $families );
		$this->assertCount( 3, $families, 'The design pairs Bricolage Grotesque, Manrope and JetBrains Mono.' );

		$checked = 0;

		foreach ( $families as $family ) {
			$this->assertIsArray( $family );

			$font_faces = $family['fontFace'] ?? null;

			$this->assertIsArray( $font_faces, 'Every family must declare its own @font-face; none may fall back to a system install.' );

			foreach ( $font_faces as $face ) {
				$this->assertIsArray( $face );
				$this->assertSame( 'swap', $face['fontDisplay'] ?? null );

				$sources = $face['src'] ?? null;

				$this->assertIsArray( $sources );

				foreach ( $sources as $src ) {
					$this->assertIsString( $src );
					$this->assertStringStartsWith(
						'file:./assets/fonts/',
						$src,
						'A font source points outside the theme. CLAUDE.md §5: no Google Fonts at runtime.'
					);
					$this->assertFileIsReadable( get_stylesheet_directory() . '/' . substr( $src, strlen( 'file:./' ) ) );

					++$checked;
				}
			}
		}

		$this->assertSame( 6, $checked, 'Three families, each with a latin and a latin-ext subset.' );
	}

	/**
	 * The preloaded fonts exist and are a strict subset of the declared ones.
	 *
	 * @return void
	 */
	public function test_the_preloaded_fonts_exist(): void {
		$preloaded = Assets::preloaded_fonts();

		$this->assertNotEmpty( $preloaded );

		foreach ( $preloaded as $relative ) {
			$this->assertFileIsReadable( get_stylesheet_directory() . '/' . $relative );
			$this->assertStringEndsWith( '-latin.woff2', $relative, 'Only latin subsets are worth preloading; unicode-range keeps latin-ext out of the critical path.' );
		}

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		foreach ( $preloaded as $relative ) {
			$this->assertStringContainsString( $relative, $head, 'The preload link is not printed.' );
		}
	}

	/**
	 * Nothing in `wp_head` reaches another origin.
	 *
	 * @return void
	 */
	public function test_wp_head_makes_no_off_origin_request(): void {
		$this->assertFalse( wp_style_is( 'wp-editor-font', 'registered' ), 'Core still registers a Google Fonts handle; the theme deregisters it.' );

		ob_start();
		do_action( 'wp_enqueue_scripts' );
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'fonts.googleapis.com', $head );
		$this->assertStringNotContainsString( 'fonts.gstatic.com', $head );
		$this->assertStringNotContainsString( 's.w.org', $head );
		$this->assertStringNotContainsString( 'wp-emoji-release', $head );
	}

	/**
	 * The resource-hint filter refuses anything off-origin.
	 *
	 * @return void
	 */
	public function test_resource_hints_are_limited_to_this_origin(): void {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		$this->assertIsString( $host );

		$hints = apply_filters(
			'wp_resource_hints',
			array(
				'//fonts.gstatic.com',
				'https://cdn.example.com/a.js',
				'https://' . $host . '/wp-content/x.css',
				array( 'href' => 'https://tracker.example/pixel.gif' ),
			)
		);

		$this->assertSame( array( 'https://' . $host . '/wp-content/x.css' ), $hints );
	}
}
