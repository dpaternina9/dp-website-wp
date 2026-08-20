<?php
/**
 * Generator for the theme's token bridge stylesheet.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Builds `themes/dpaternina/assets/css/tokens.css` from the design source.
 *
 * `theme.json` holds the literal values, because that is what makes the editor's
 * swatches, size menus and spacing controls correct. It has to hold them under
 * WordPress's own names — `--wp--preset--color--dp-teal` — and CLAUDE.md §5 says
 * a token called `--dp-teal` stays `--dp-teal`. This file is the join: one
 * generated alias per design token, plus the two theme scopes copied across
 * unchanged. See docs/adr/0002-design-token-naming.md.
 *
 * The output is committed and is never hand-edited. `bin/dp-tokens.php --check`
 * and the token-parity test both regenerate it and compare.
 */
final class TokenBridge {

	/**
	 * Path of the generated stylesheet, relative to the repository root.
	 */
	public const OUTPUT = 'themes/dpaternina/assets/css/tokens.css';

	/**
	 * Path of the theme's `theme.json`, relative to the repository root.
	 */
	public const THEME_JSON = 'themes/dpaternina/theme.json';

	/**
	 * What separates the generated aliases from the copied scopes.
	 *
	 * Everything below this line is design-source text, reproduced byte for
	 * byte. Three stylelint rules disagree with how the design system formats
	 * it — `.dp-light` is declared once per token file, and its comments sit
	 * tight against the declarations they explain. Reformatting to satisfy them
	 * would break the verbatim guarantee that TokenParityTest exists to keep,
	 * and TokenParityTest is the stricter check of the two. The aliases above
	 * stay fully linted.
	 */
	private const SCOPE_PREAMBLE = "/* stylelint-disable comment-empty-line-before, no-duplicate-selectors, rule-empty-line-before -- verbatim from design-source/; see the header. */\n";

	/**
	 * Constructor.
	 *
	 * @param DesignTokens $design The design system's tokens.
	 * @param ThemeTokens  $theme  The theme's `theme.json`.
	 */
	public function __construct(
		private readonly DesignTokens $design,
		private readonly ThemeTokens $theme
	) {}

	/**
	 * Build a bridge for the repository at `$root`.
	 *
	 * @param string $root Absolute path to the repository root.
	 * @return self
	 */
	public static function for_repository( string $root ): self {
		$root = rtrim( $root, '/' );

		return new self(
			DesignTokens::from_repository( $root ),
			ThemeTokens::from_file( $root . '/' . self::THEME_JSON )
		);
	}

	/**
	 * Render the stylesheet.
	 *
	 * @return string
	 *
	 * @throws RuntimeException If a design token has no home in `theme.json`.
	 */
	public function render(): string {
		$missing = array();
		$aliases = array();

		foreach ( array_keys( $this->design->root_declarations() ) as $token ) {
			$variable = $this->theme->variable_for( $token );

			if ( null === $variable ) {
				$missing[] = $token;
				continue;
			}

			$aliases[] = sprintf( "\t%s: var(%s);", $token, $variable );
		}

		if ( array() !== $missing ) {
			throw new RuntimeException(
				sprintf(
					"theme.json does not carry %d design token(s), so no bridge can be generated for them:\n  %s\n"
					. 'Add each one to settings.color.palette, settings.color.gradients, '
					. 'settings.typography.fontFamilies, settings.typography.fontSizes, '
					. 'settings.spacing.spacingSizes or settings.custom, under its own name.',
					count( $missing ),
					implode( "\n  ", $missing )
				)
			);
		}

		$scopes = array();

		foreach ( $this->design->scope_rules( '.dp-light' ) as $rule ) {
			$scopes[] = $rule->verbatim;
		}

		foreach ( $this->design->theme_scope_rules() as $rule ) {
			if ( '.dp-light' === $rule->selector ) {
				continue;
			}

			$scopes[] = $rule->verbatim;
		}

		return $this->header()
			. ":root {\n"
			. implode( "\n", $aliases )
			. "\n}\n\n"
			. self::SCOPE_PREAMBLE
			. implode( "\n\n", $scopes )
			. "\n";
	}

	/**
	 * The generated file's header comment.
	 *
	 * @return string
	 */
	private function header(): string {
		return <<<'CSS'
			/*
			 * dP token bridge — GENERATED FILE, DO NOT EDIT.
			 *
			 * Regenerate with:  composer tokens:build
			 * Verify with:      composer tokens:check
			 *
			 * theme.json holds every token's literal value under WordPress's generated
			 * names. This file gives each of them back the name the design uses, so a
			 * stylesheet in this theme is written against --dp-teal, --space-5 and
			 * --radius-lg exactly as design-source/_ds/tokens/*.css declares them.
			 *
			 * Below the aliases, the .dp-light and .dp-dark scopes are copied across
			 * from the design source unchanged. WordPress has no scoped presets, so
			 * these have no theme.json equivalent and never will. Light mode is ruled
			 * out (CLAUDE.md §5) — .dp-light is carried, not wired to anything.
			 *
			 * Source of truth: design-source/_ds/tokens/*.css and design-source/theme.css.
			 * Decision record:  docs/adr/0002-design-token-naming.md.
			 */


			CSS;
	}
}
