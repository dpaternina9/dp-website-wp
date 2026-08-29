<?php
/**
 * CLAUDE.md §5.1, enforced.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WP_Rewrite;
use WP_UnitTestCase;

/**
 * Pages belong to David, not to the theme.
 *
 * Two halves, deliberately kept in one class because they are one rule:
 *
 * - a **runtime** check that WordPress ends up with no rewrite rule of ours
 *   (moved here from BootstrapTest, where it was the only §5.1 assertion);
 * - a **static** check over both packages, because a rewrite registered behind
 *   a condition, a `get_page_by_path()` lookup, or a template named after a slug
 *   would never show up in a rule set generated on a site with no pages.
 *
 * Neither half alone is enough. The runtime check cannot see code that did not
 * run; the static check cannot see a rule added by a filter.
 */
final class NoHardcodedRoutesTest extends WP_UnitTestCase {

	/**
	 * The two packages this project ships.
	 *
	 * `tests/` is not scanned: this file necessarily contains every pattern it
	 * looks for, and a scanner that had to exempt its own scanner would be one
	 * exemption away from exempting anything.
	 *
	 * @var list<string>
	 */
	private const PACKAGES = array(
		'themes/dpaternina',
		'plugins/dp-core',
	);

	/**
	 * Directories that hold generated or third-party code.
	 *
	 * @var list<string>
	 */
	private const SKIPPED = array( 'vendor', 'node_modules', 'build', 'dist' );

	/**
	 * What may never appear in shipped code, and why.
	 *
	 * `package` narrows a rule to one of `PACKAGES`, and only `page_on_front`
	 * uses it. Its own reason has always said *the theme*, and the distinction it
	 * was drawing is real: the theme renders whatever David chose and may never
	 * ask which page he chose, while writing a starting value is what the Reading
	 * screen does and what `dp-core`'s seeder does once, from the CLI, alongside
	 * the site logo and the privacy page. Everything else in this list is
	 * forbidden to both packages, `is_page()` and `get_page_by_path()` included,
	 * so the shapes that would actually couple code to a slug are still caught
	 * wherever they appear.
	 *
	 * @var array<string, array{pattern: string, reason: string, package?: string}>
	 */
	private const FORBIDDEN = array(
		'add_rewrite_rule' => array(
			'pattern' => '~\badd_rewrite_rule\s*\(~',
			'reason'  => 'The only registered rewrites in this project are the dp_series taxonomy slug '
				. 'and the resume format query var (Phase 3 / Phase 7). A third needs an ADR.',
		),
		'is_page'          => array(
			'pattern' => '~\bis_page\s*\(\s*[^)\s]~',
			'reason'  => 'Branch on the assigned template (get_page_template_slug()) or the queried '
				. 'object, never on a page slug or ID. is_page() with no argument is fine.',
		),
		'get_page_by_path' => array(
			'pattern' => '~\bget_page_by_path\s*\(~',
			'reason'  => 'Looking a page up by its path assumes a slug the theme does not own.',
		),
		'page_on_front'    => array(
			'pattern' => '~[\'"]page_on_front[\'"]~',
			'package' => 'themes/dpaternina',
			'reason'  => 'Which page is the front page is a Reading setting. The theme must render '
				. 'correctly whatever David chose, including nothing. Seeding a first value from '
				. 'dp-core is the Reading screen\'s own act and is not this rule.',
		),
	);

	/**
	 * Nothing in either package registers a rewrite rule at runtime.
	 *
	 * Pretty permalinks are switched on for the duration so the rule set is
	 * actually generated — asserting against an empty rule set would prove
	 * nothing.
	 *
	 * Two checks, because neither covers the other. The `dp_`/`dp-` scan over the
	 * generated rules catches a permastruct introduced by a post type or a
	 * taxonomy we registered. The three `WP_Rewrite` arrays below are where
	 * `add_rewrite_rule()` writes, and the assertion on them is that **no rule
	 * resolves to a page** — which catches a rewrite whose pattern gives no clue
	 * that it is ours, `^timeline` being the obvious one somebody would reach for.
	 *
	 * Core does use `add_rewrite_rule()` itself, for the REST prefix and the
	 * sitemaps, so those arrays are not empty on a stock install. None of core's
	 * nine rules resolves to a page, and a rule that does is by definition the
	 * coupling §5.1 forbids.
	 *
	 * @return void
	 */
	public function test_no_custom_rewrite_rules_are_registered(): void {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );

		$rules = get_option( 'rewrite_rules' );

		$this->assertIsArray( $rules );
		$this->assertNotEmpty( $rules, 'Core generated its own rules, so the check below is meaningful.' );

		$ours = array_values(
			array_filter(
				array_keys( $rules ),
				static fn ( string $pattern ): bool => str_contains( $pattern, 'dp_' )
					|| str_contains( $pattern, 'dp-' )
			)
		);

		$this->assertSame( array(), $ours, 'Pages belong to David. The only rewrites we may ever add arrive in Phase 3.' );

		$this->assertInstanceOf( WP_Rewrite::class, $wp_rewrite );

		$extra = array_merge(
			$wp_rewrite->extra_rules_top,
			$wp_rewrite->extra_rules,
			$wp_rewrite->non_wp_rules
		);

		$this->assertNotEmpty(
			$extra,
			"Core's own REST and sitemap rewrites are missing, so this check is looking at nothing."
		);

		$to_a_page = array();

		foreach ( $extra as $pattern => $target ) {
			if ( 1 === preg_match( '~\b(pagename|page_id)=|post_type=page\b~', (string) $target ) ) {
				$to_a_page[] = $pattern . ' => ' . $target;
			}
		}

		$this->assertSame(
			array(),
			$to_a_page,
			"A rewrite rule resolves to a page. CLAUDE.md §5.1: David creates every page and picks\n"
			. "its slug; a vanity URL such as /timeline is a row in the migration redirect map, not a\n"
			. 'rewrite. Found: ' . implode( ', ', $to_a_page )
		);

		$this->set_permalink_structure( '' );
	}

	/**
	 * No shipped source couples the theme or the plugin to a page.
	 *
	 * @return void
	 */
	public function test_no_source_file_couples_a_template_to_a_page(): void {
		$files = $this->source_files();

		$this->assertGreaterThan(
			5,
			count( $files ),
			'Only ' . count( $files ) . ' source files were found across both packages. '
			. 'The scan found nothing to scan, so it would pass vacuously.'
		);

		$allowed  = $this->allowed_findings();
		$findings = array();

		foreach ( $files as $relative => $path ) {
			$lines = file( $path, FILE_IGNORE_NEW_LINES );

			if ( false === $lines ) {
				continue;
			}

			foreach ( $lines as $number => $line ) {
				foreach ( self::FORBIDDEN as $name => $rule ) {
					if ( 1 !== preg_match( $rule['pattern'], $line ) ) {
						continue;
					}

					if ( isset( $rule['package'] ) && ! str_starts_with( $relative, $rule['package'] . '/' ) ) {
						continue;
					}

					if ( in_array( $relative . ':' . $name, $allowed, true ) ) {
						continue;
					}

					$findings[] = sprintf(
						"  %s:%d\n    matched: %s\n    %s\n    %s",
						$relative,
						$number + 1,
						$name,
						trim( $line ),
						$rule['reason']
					);
				}
			}
		}

		$this->assertSame(
			'',
			implode( "\n\n", $findings ),
			sprintf(
				"%d hardcoded page route(s) found. CLAUDE.md §5.1:\n\n%s",
				count( $findings ),
				implode( "\n\n", $findings )
			)
		);
	}

	/**
	 * No template file is named so the hierarchy could bind it to a slug.
	 *
	 * A file called `page-work.html` is a hierarchy match, so WordPress would
	 * auto-apply it to any page slugged `work`. Every custom template is
	 * prefixed `dp-` instead, which the core hierarchy never matches, so it can
	 * only ever be assigned deliberately from the admin. Digest §2.1.
	 *
	 * @return void
	 */
	public function test_no_template_is_named_after_a_page_slug(): void {
		$templates = glob( $this->repository_root() . '/themes/dpaternina/templates/*.html' );

		$this->assertIsArray( $templates );
		$this->assertNotEmpty( $templates, 'The theme has no templates, so this test proves nothing.' );

		$bound = array();

		foreach ( $templates as $template ) {
			$name = basename( $template, '.html' );

			if ( str_starts_with( $name, 'page-' ) || str_starts_with( $name, 'single-post-' ) ) {
				$bound[] = 'templates/' . basename( $template );
			}
		}

		$this->assertSame(
			array(),
			$bound,
			"These template file names are matched by the core hierarchy and would be applied to a\n"
			. "page by its slug, behind our backs. Rename them with the dp- prefix and declare them\n"
			. 'in theme.json customTemplates: ' . implode( ', ', $bound )
		);
	}

	/**
	 * Every declared custom template has a file behind it, and vice versa.
	 *
	 * A dropdown entry with no template is a broken admin; a `dp-` template with
	 * no declaration is unreachable.
	 *
	 * @return void
	 */
	public function test_every_custom_template_is_declared_and_present(): void {
		$declared = array_keys( wp_get_theme()->get_post_templates()['page'] ?? array() );
		$declared = array_map( static fn ( string $file ): string => basename( $file, '.html' ), $declared );

		sort( $declared );

		$this->assertSame(
			array( 'dp-about', 'dp-colophon', 'dp-contact', 'dp-resume', 'dp-series', 'dp-uses', 'dp-watch', 'dp-work' ),
			$declared,
			'theme.json customTemplates and templates/dp-*.html must agree.'
		);

		foreach ( $declared as $name ) {
			$this->assertFileIsReadable(
				$this->repository_root() . '/themes/dpaternina/templates/' . $name . '.html',
				sprintf( '"%s" is offered in the admin dropdown but has no template file.', $name )
			);
		}
	}

	/**
	 * No block markup the theme ships links a page on this site.
	 *
	 * The static scan above reads PHP, JS and TS. The theme's templates, parts
	 * and patterns are none of those, and they are where a link lives — so a
	 * `href="/contact"` typed into `parts/footer.html` would pass every other
	 * assertion in this file.
	 *
	 * It is deliberately narrower than the rule it replaces. ADR-0006 §2 asserted
	 * that no shipped markup contained an href *at all*, which is stronger than
	 * §5.1 asks for and had a real cost: with an author-set link defined out of
	 * existence, the destination filter could overwrite one without anybody
	 * noticing it was overwriting anything (ADR-0018). What §5.1 actually forbids
	 * is the theme deciding David's slugs, so a fragment, a `mailto:` and a link
	 * off this site all pass, and a path here does not.
	 *
	 * @return void
	 */
	public function test_no_shipped_block_markup_links_a_page_on_this_site(): void {
		$files = $this->markup_files();
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );

		$this->assertGreaterThan(
			10,
			count( $files ),
			'The scan found almost no markup, so it would pass vacuously.'
		);

		$findings = array();

		foreach ( $files as $relative => $markup ) {
			preg_match_all( '~href="([^"]*)"~', $markup, $hrefs );

			foreach ( $hrefs[1] as $href ) {
				if ( '' === $href || str_starts_with( $href, '#' ) ) {
					continue;
				}

				$scheme = wp_parse_url( $href, PHP_URL_SCHEME );

				if ( is_string( $scheme ) && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
					continue;
				}

				$found = wp_parse_url( $href, PHP_URL_HOST );

				if ( null === $found || false === $found || $found === $host ) {
					$findings[] = $relative . ' → ' . $href;
				}
			}
		}

		$this->assertSame(
			array(),
			$findings,
			"Shipped markup links a page on this site. CLAUDE.md §5.1: David creates every page and\n"
			. "picks its slug, so the theme ships the words and he sets the link once, in the site\n"
			. 'editor. Found: ' . implode( ', ', $findings )
		);
	}

	/**
	 * Every template, part and pattern the theme ships, keyed by path.
	 *
	 * @return array<string, string>
	 */
	private function markup_files(): array {
		$root  = $this->repository_root() . '/themes/dpaternina/';
		$found = array();

		foreach ( array( 'templates/*.html', 'parts/*.html', 'patterns/*.php' ) as $pattern ) {
			$paths = glob( $root . $pattern );

			if ( ! is_array( $paths ) ) {
				continue;
			}

			foreach ( $paths as $path ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file in the repository under test.
				$markup = file_get_contents( $path );

				if ( is_string( $markup ) ) {
					$found[ substr( $path, strlen( $this->repository_root() ) + 1 ) ] = $markup;
				}
			}
		}

		ksort( $found );

		return $found;
	}

	/**
	 * Findings that are permitted, as `relative/path.php:pattern-name`.
	 *
	 * Empty, and meant to stay that way. An entry here is a documented exception
	 * to CLAUDE.md §5.1 and needs the ADR that §5.1 asks for.
	 *
	 * @return list<string>
	 */
	private function allowed_findings(): array {
		return array();
	}

	/**
	 * Every PHP, JS and TS file in both packages.
	 *
	 * @return array<string, string> Path relative to the repository root, to absolute path.
	 */
	private function source_files(): array {
		$root  = $this->repository_root();
		$found = array();

		foreach ( self::PACKAGES as $package ) {
			$directory = $root . '/' . $package;

			if ( ! is_dir( $directory ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file instanceof SplFileInfo || ! $file->isFile() || ! in_array( $file->getExtension(), array( 'php', 'js', 'ts', 'tsx', 'jsx' ), true ) ) {
					continue;
				}

				$path     = $file->getPathname();
				$relative = substr( $path, strlen( $root ) + 1 );

				foreach ( self::SKIPPED as $skipped ) {
					if ( str_contains( $relative, '/' . $skipped . '/' ) ) {
						continue 2;
					}
				}

				$found[ $relative ] = $path;
			}
		}

		ksort( $found );

		return $found;
	}

	/**
	 * Absolute path to the repository root.
	 *
	 * @return string
	 */
	private function repository_root(): string {
		return dirname( __DIR__, 2 );
	}
}
