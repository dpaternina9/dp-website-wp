<?php
/**
 * The guard that stops the design and the theme drifting apart.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Tests\Support\CssParser;
use DP\Tests\Support\DesignTokens;
use DP\Tests\Support\ThemeTokens;
use DP\Tests\Support\TokenBridge;
use RuntimeException;
use WP_UnitTestCase;

/**
 * Asserts that every token in `design-source/` survives into the theme intact.
 *
 * The comparison is made against the CSS **WordPress actually generates**, not
 * against `theme.json` read as a file. That matters: WordPress renames slugs on
 * their way into custom properties, and a test that compared two JSON documents
 * would agree with itself while the browser saw something else. Resolving
 * `--dp-teal` through `wp_get_global_stylesheet()` plus the generated bridge is
 * the same journey a stylesheet in this theme makes.
 *
 * If this test fails, the design source and the implementation disagree. Per
 * CLAUDE.md §5 the design wins — change the theme, or change the design in
 * Claude Design and re-import.
 */
final class TokenParityTest extends WP_UnitTestCase {

	/**
	 * The design system's tokens.
	 *
	 * @var DesignTokens
	 */
	private DesignTokens $design;

	/**
	 * Load the design source.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->design = DesignTokens::from_repository( $this->repository_root() );
	}

	/**
	 * Every `:root` token in the design resolves, in the theme, to the same value.
	 *
	 * @return void
	 */
	public function test_every_design_token_has_the_same_value_in_the_theme(): void {
		$expected = $this->design->root_values();

		$this->assertGreaterThan(
			100,
			count( $expected ),
			'Only ' . count( $expected ) . ' tokens were parsed out of design-source/. '
			. 'The design system declares well over a hundred, so the parser found almost '
			. 'nothing and every assertion below would pass vacuously.'
		);

		$theme    = $this->theme_variables();
		$report   = array();
		$excluded = $this->not_carried();

		foreach ( $expected as $token => $design_value ) {
			if ( in_array( $token, $excluded, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $token, $theme ) ) {
				$report[] = sprintf(
					"  %s\n    design-source: %s\n    theme:         (not declared anywhere in the theme)",
					$token,
					$design_value
				);
				continue;
			}

			try {
				$theme_value = CssParser::normalize( CssParser::resolve( $theme[ $token ], $theme ) );
			} catch ( RuntimeException $error ) {
				/*
				 * One unresolvable token must not hide the others. Report it and
				 * carry on, so a run that breaks five tokens names five tokens.
				 */
				$report[] = sprintf(
					"  %s\n    design-source: %s\n    theme:         %s",
					$token,
					$design_value,
					$error->getMessage()
				);
				continue;
			}

			if ( $theme_value !== $design_value ) {
				$report[] = sprintf(
					"  %s\n    design-source: %s\n    theme:         %s",
					$token,
					$design_value,
					$theme_value
				);
			}
		}

		$this->assertSame(
			'',
			implode( "\n", $report ),
			sprintf(
				"%d design token(s) do not match design-source/:\n\n%s\n\n"
				. "design-source/ is the contract (CLAUDE.md §5). Fix theme.json — or change\n"
				. 'the design in Claude Design, re-import, and run: composer tokens:build',
				count( $report ),
				implode( "\n", $report )
			)
		);
	}

	/**
	 * The `.dp-light` and `.dp-dark` scopes are carried across unchanged.
	 *
	 * WordPress has no scoped presets, so these two blocks have no `theme.json`
	 * equivalent and are copied as CSS. Light mode is ruled out (CLAUDE.md §5);
	 * `.dp-light` is carried, not wired to anything.
	 *
	 * @return void
	 */
	public function test_the_theme_scopes_are_carried_over_verbatim(): void {
		$bridge = $this->read( $this->repository_root() . '/' . TokenBridge::OUTPUT );
		$report = array();

		foreach ( array( '.dp-light', '.dp-dark' ) as $selector ) {
			$rules = $this->design->scope_rules( $selector );

			$this->assertNotEmpty(
				$rules,
				sprintf( 'design-source/ declares no %s scope, so this test proves nothing.', $selector )
			);

			foreach ( $rules as $index => $rule ) {
				if ( ! str_contains( $bridge, $rule->verbatim ) ) {
					$report[] = sprintf( '  %s (block %d) is missing or has been reformatted', $selector, $index + 1 );
				}
			}
		}

		$this->assertSame(
			'',
			implode( "\n", $report ),
			sprintf(
				"The theme scopes no longer match design-source/theme.css:\n\n%s\n\n"
				. 'Run: composer tokens:build',
				implode( "\n", $report )
			)
		);
	}

	/**
	 * The committed bridge is exactly what the design source implies.
	 *
	 * @return void
	 */
	public function test_the_generated_bridge_is_up_to_date(): void {
		$root = $this->repository_root();

		$this->assertSame(
			TokenBridge::for_repository( $root )->render(),
			$this->read( $root . '/' . TokenBridge::OUTPUT ),
			TokenBridge::OUTPUT . ' is stale. It is generated, never hand-edited. Run: composer tokens:build'
		);
	}

	/**
	 * The base layer is the design's base layer, verbatim.
	 *
	 * @return void
	 */
	public function test_the_base_layer_is_carried_over_verbatim(): void {
		$base = $this->read( $this->repository_root() . '/themes/dpaternina/assets/css/base.css' );

		$this->assertStringContainsString(
			$this->design->base_layer(),
			$base,
			'themes/dpaternina/assets/css/base.css no longer contains design-source/_ds/tokens/base.css '
			. 'verbatim. It carries the focus ring, the placeholder contrast, the media overflow rule '
			. 'and the prefers-reduced-motion backstop — every one of them an accessibility acceptance '
			. 'criterion (CLAUDE.md §1.7).'
		);
	}

	/**
	 * The layout widths are the design's container tokens, not new numbers.
	 *
	 * `--container-md` is the reading column the design uses for hero copy and
	 * body text; `--container-lg` is the page shell every band sits in. Mapping
	 * them onto `contentSize` and `wideSize` is what makes an unstyled block land
	 * where the design would have put it.
	 *
	 * @return void
	 */
	public function test_the_layout_widths_come_from_the_container_tokens(): void {
		$theme  = ThemeTokens::from_file( $this->repository_root() . '/' . TokenBridge::THEME_JSON );
		$design = $this->design->root_values();

		$this->assertSame(
			$design['--container-md'],
			CssParser::normalize( (string) $theme->at( 'settings', 'layout', 'contentSize' ) ),
			'settings.layout.contentSize must be --container-md, the design\'s reading column.'
		);

		$this->assertSame(
			$design['--container-lg'],
			CssParser::normalize( (string) $theme->at( 'settings', 'layout', 'wideSize' ) ),
			'settings.layout.wideSize must be --container-lg, the design\'s page shell.'
		);
	}

	/**
	 * Nothing sits on the allowlist that does not need to.
	 *
	 * A stale allowlist is worse than none: it silently exempts a token that is
	 * in fact carried, so a later drift in that token goes unreported.
	 *
	 * @return void
	 */
	public function test_the_allowlist_is_honest(): void {
		$stale = $this->stale_exemptions( $this->not_carried() );

		$this->assertSame(
			array(),
			$stale,
			"The token allowlist is out of date:\n  " . implode( "\n  ", $stale )
		);
	}

	/**
	 * The allowlist check itself works, while the allowlist is empty.
	 *
	 * An exemption mechanism nobody has exercised is a mechanism that will one
	 * day exempt everything. Two entries that must be rejected: a token the
	 * theme does carry, and a name the design never declared.
	 *
	 * @return void
	 */
	public function test_the_allowlist_check_rejects_a_bad_exemption(): void {
		$this->assertCount(
			1,
			$this->stale_exemptions( array( '--dp-teal' ) ),
			'--dp-teal is carried by the theme, so exempting it must be reported.'
		);

		$this->assertCount(
			1,
			$this->stale_exemptions( array( '--not-a-design-token' ) ),
			'A name the design never declared must be reported.'
		);
	}

	/**
	 * Tokens deliberately not carried into the theme.
	 *
	 * Empty, and meant to stay that way. A token belongs here only when there is
	 * a reason it cannot exist in the theme at all — not when it is merely
	 * inconvenient. Write the reason on the line above the entry, and expect
	 * test_the_allowlist_is_honest() to fail the moment the reason stops holding.
	 *
	 * @return list<string>
	 */
	private function not_carried(): array {
		return array();
	}

	/**
	 * Allowlist entries that no longer describe reality.
	 *
	 * @param array<int, string> $allowlist Tokens claimed to be deliberately absent.
	 * @return list<string> One explanation per bad entry.
	 */
	private function stale_exemptions( array $allowlist ): array {
		$design = $this->design->root_values();
		$theme  = $this->theme_variables();
		$stale  = array();

		foreach ( $allowlist as $token ) {
			if ( ! array_key_exists( $token, $design ) ) {
				$stale[] = $token . ' is on the allowlist but design-source/ does not declare it';
				continue;
			}

			if ( array_key_exists( $token, $theme ) ) {
				$stale[] = $token . ' is on the allowlist but the theme carries it after all';
			}
		}

		return $stale;
	}

	/**
	 * Every custom property the theme really declares, as the browser would see them.
	 *
	 * The generated global stylesheet supplies `--wp--preset--*` and
	 * `--wp--custom--*`; the token bridge supplies the design's own names on top.
	 *
	 * @return array<string, string> Property name to raw value.
	 */
	private function theme_variables(): array {
		$css = wp_get_global_stylesheet( array( 'variables' ) )
			. $this->read( $this->repository_root() . '/' . TokenBridge::OUTPUT );

		$variables = array();

		foreach ( CssParser::rules( $css ) as $rule ) {
			if ( ':root' !== $rule->selector ) {
				continue;
			}

			$variables = array_merge( $variables, $rule->custom_properties() );
		}

		return $variables;
	}

	/**
	 * Absolute path to the repository root.
	 *
	 * @return string
	 */
	private function repository_root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Read a file from the repository.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function read( string $path ): string {
		$this->assertFileIsReadable( $path );

		// Reading a source file from the checkout, not from wp-content.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) file_get_contents( $path );
	}
}
