<?php
/**
 * Integration tests for the thing a release actually ships: the ZIP.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Update;

/*
 * This test's whole job is to run a shell script and read what it wrote to a
 * temporary directory. `exec()` is the subject, not an accident; WP_Filesystem
 * has no business in `sys_get_temp_dir()`; and the files being read are local
 * build output, never a remote URL.
 */
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
// phpcs:disable WordPress.WP.AlternativeFunctions

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Runs `bin/dp-build.sh` for real and opens what comes out.
 *
 * A release pipeline can be green in every other respect and still produce an
 * archive WordPress unpacks to `wp-content/plugins/dp-core-1.2.3/`, beside the
 * copy that is running, updating nothing. There is no way to catch that except
 * by building an archive and looking inside it, so that is what this does.
 *
 * These are integration tests by nature rather than by location: they shell out
 * to Composer and touch the filesystem. They skip rather than fail where the
 * toolchain is absent.
 */
final class ReleaseArtifactTest extends WP_UnitTestCase {

	/**
	 * Repository root.
	 *
	 * @var string
	 */
	private string $root = '';

	/**
	 * Temporary output directory for this test's build.
	 *
	 * @var string
	 */
	private string $out = '';

	/**
	 * Locate the repository and make somewhere to build into.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->root = dirname( __DIR__, 3 );

		if ( ! is_readable( $this->root . '/bin/dp-build.sh' ) ) {
			$this->markTestSkipped( 'The build script is not reachable from this checkout.' );
		}

		if ( ! class_exists( ZipArchive::class ) ) {
			$this->markTestSkipped( 'ext-zip is required to inspect a release artefact.' );
		}

		if ( '' === $this->which( 'composer' ) || '' === $this->which( 'bash' ) ) {
			$this->markTestSkipped( 'The build needs bash and Composer on PATH.' );
		}

		$this->out = sys_get_temp_dir() . '/dp-release-' . wp_generate_password( 8, false, false );
	}

	/**
	 * Remove the build output.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( '' !== $this->out && is_dir( $this->out ) ) {
			$this->remove_tree( $this->out );
		}

		parent::tear_down();
	}

	/**
	 * The plugin ZIP has the structure WordPress needs to replace the plugin in place.
	 *
	 * @return void
	 */
	public function test_the_plugin_zip_is_shaped_for_the_upgrader(): void {
		$archive = $this->build( 'core', '9.9.9' );
		$names   = $this->entries( $archive );

		$this->assertSame(
			array( 'dp-core' ),
			$this->top_level_directories( $names ),
			'Exactly one top-level directory, named for the slug the plugin is installed under.'
		);

		$this->assertContains( 'dp-core/dp-core.php', $names );
		$this->assertContains(
			'dp-core/vendor/autoload.php',
			$names,
			'docs/adr/0001 §1: the plugin carries its own autoloader, so the release must run Composer inside it.'
		);

		$bootstrap = $this->read( $archive, 'dp-core/dp-core.php' );

		$this->assertStringContainsString( '* Version:           9.9.9', $bootstrap, 'The header is stamped from the tag.' );
		$this->assertStringContainsString( "const VERSION = '9.9.9';", $bootstrap, 'And so is the constant beside it.' );
		$this->assertStringContainsString( 'Update URI:        https://updates.dpaternina.com/core', $bootstrap );

		$this->assert_no_development_files( $names );

		$archive->close();
	}

	/**
	 * The theme ZIP likewise.
	 *
	 * @return void
	 */
	public function test_the_theme_zip_is_shaped_for_the_upgrader(): void {
		$archive = $this->build( 'theme', '9.9.9' );
		$names   = $this->entries( $archive );

		$this->assertSame( array( 'dpaternina' ), $this->top_level_directories( $names ) );
		$this->assertContains( 'dpaternina/style.css', $names );
		$this->assertContains( 'dpaternina/theme.json', $names );
		$this->assertContains( 'dpaternina/templates/index.html', $names );

		$stylesheet = $this->read( $archive, 'dpaternina/style.css' );

		$this->assertStringContainsString( "\nVersion: 9.9.9\n", $stylesheet );
		$this->assertStringContainsString( 'Update URI: https://updates.dpaternina.com/theme', $stylesheet );

		$this->assert_no_development_files( $names );

		$archive->close();
	}

	/**
	 * Stamping happens in a staging copy: a build must never dirty the working tree.
	 *
	 * @return void
	 */
	public function test_building_does_not_modify_the_source_tree(): void {
		$source = $this->root . '/plugins/dp-core/dp-core.php';
		$before = file_get_contents( $source );

		$this->assertIsString( $before );

		$this->build( 'core', '9.9.9' )->close();

		$this->assertSame( $before, file_get_contents( $source ), 'The source bootstrap is untouched.' );
	}

	/**
	 * Run the build script and open the archive it names.
	 *
	 * @param string $package 'theme' or 'core'.
	 * @param string $version Version to stamp.
	 * @return ZipArchive
	 */
	private function build( string $package, string $version ): ZipArchive {
		$command = sprintf(
			'bash %s --package=%s --version=%s --out=%s --allow-unkeyed --skip-assets 2>&1',
			escapeshellarg( $this->root . '/bin/dp-build.sh' ),
			escapeshellarg( $package ),
			escapeshellarg( $version ),
			escapeshellarg( $this->out )
		);

		$output = array();
		$status = 1;

		exec( $command, $output, $status );

		$this->assertSame( 0, $status, "Build failed:\n" . implode( "\n", $output ) );

		$path = (string) end( $output );

		$this->assertFileExists( $path, "Build reported:\n" . implode( "\n", $output ) );

		$archive = new ZipArchive();

		$this->assertTrue( $archive->open( $path ), 'The artefact is a readable ZIP.' );

		return $archive;
	}

	/**
	 * Every entry name in an archive.
	 *
	 * @param ZipArchive $archive Open archive.
	 * @return list<string>
	 */
	private function entries( ZipArchive $archive ): array {
		$names = array();

		for ( $index = 0; $index < $archive->numFiles; $index++ ) {
			$name = $archive->getNameIndex( $index );

			if ( is_string( $name ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * The distinct first path segments in an archive.
	 *
	 * @param string[] $names Entry names.
	 * @return list<string>
	 */
	private function top_level_directories( array $names ): array {
		$roots = array();

		foreach ( $names as $name ) {
			$roots[] = (string) strtok( $name, '/' );
		}

		$roots = array_values( array_unique( $roots ) );
		sort( $roots );

		return $roots;
	}

	/**
	 * Read one file out of an archive.
	 *
	 * @param ZipArchive $archive Open archive.
	 * @param string     $name    Entry name.
	 * @return string
	 */
	private function read( ZipArchive $archive, string $name ): string {
		$contents = $archive->getFromName( $name );

		$this->assertIsString( $contents, $name . ' is in the archive and readable.' );

		return $contents;
	}

	/**
	 * Nothing a developer needs and a site does not may ship.
	 *
	 * @param string[] $names Entry names.
	 * @return void
	 */
	private function assert_no_development_files( array $names ): void {
		$forbidden = array( '.git', 'node_modules', 'package.json', 'package-lock.json', 'composer.lock', 'tests' );

		foreach ( $names as $name ) {
			foreach ( $forbidden as $needle ) {
				$this->assertStringNotContainsString(
					'/' . $needle,
					'/' . $name,
					$name . ' is development-only and must not ship.'
				);
			}

			$this->assertStringEndsNotWith( '.dist', $name );
			$this->assertStringEndsNotWith( '.map', $name );
		}
	}

	/**
	 * Locate an executable, or return ''.
	 *
	 * @param string $binary Program name.
	 * @return string
	 */
	private function which( string $binary ): string {
		$found  = array();
		$status = 1;

		exec( 'command -v ' . escapeshellarg( $binary ) . ' 2>/dev/null', $found, $status );

		return 0 === $status && isset( $found[0] ) ? (string) $found[0] : '';
	}

	/**
	 * Delete a directory tree.
	 *
	 * @param string $path Directory to remove.
	 * @return void
	 */
	private function remove_tree( string $path ): void {
		$walk = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $walk as $item ) {
			if ( ! $item instanceof SplFileInfo ) {
				continue;
			}

			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $path );
	}
}
