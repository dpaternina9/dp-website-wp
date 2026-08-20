<?php
/**
 * The design system's token files, read as data.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Reads `design-source/` and answers what the design says a token is.
 *
 * `design-source/` is the contract (CLAUDE.md §5). Nothing here writes to it.
 * The load order mirrors the `@import` manifest in `_ds/styles.css`, with
 * `theme.css` last, because that is the order a browser would apply them in and
 * therefore the order that decides which declaration wins.
 */
final class DesignTokens {

	/**
	 * Token files in `_ds/styles.css` import order. `fonts.css` and `base.css`
	 * declare no custom properties, so neither contributes a token.
	 */
	private const TOKEN_FILES = array(
		'_ds/tokens/colors.css',
		'_ds/tokens/typography.css',
		'_ds/tokens/spacing.css',
		'_ds/tokens/effects.css',
	);

	/**
	 * The site-level scope file, loaded after the design system.
	 */
	private const THEME_FILE = 'theme.css';

	/**
	 * The base layer, which the theme carries over verbatim.
	 */
	private const BASE_FILE = '_ds/tokens/base.css';

	/**
	 * Parsed rules, keyed by the file they came from.
	 *
	 * @var array<string, list<CssRule>>
	 */
	private array $rules = array();

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute path to `design-source/`.
	 */
	private function __construct( private readonly string $directory ) {}

	/**
	 * Read the design source at the repository's canonical location.
	 *
	 * @param string $repository_root Absolute path to the repository root.
	 * @return self
	 */
	public static function from_repository( string $repository_root ): self {
		return new self( rtrim( $repository_root, '/' ) . '/design-source' );
	}

	/**
	 * Every custom property declared in a `:root` block, in cascade order.
	 *
	 * @return array<string, string> Property name (with the leading `--`) to raw value.
	 */
	public function root_declarations(): array {
		$declarations = array();

		foreach ( array_merge( self::TOKEN_FILES, array( self::THEME_FILE ) ) as $file ) {
			foreach ( $this->rules_in( $file ) as $rule ) {
				if ( ':root' === $rule->selector ) {
					$declarations = array_merge( $declarations, $rule->custom_properties() );
				}
			}
		}

		return $declarations;
	}

	/**
	 * Every `:root` token, resolved through its `var()` references to a literal.
	 *
	 * This is what makes a compound token comparable: `--dp-gradient-warm` is a
	 * `linear-gradient()` over three other tokens in the design and three hex
	 * literals in `theme.json`, and both sides only agree once resolved.
	 *
	 * @return array<string, string> Property name to normalised literal value.
	 */
	public function root_values(): array {
		return CssParser::resolve_all( $this->root_declarations() );
	}

	/**
	 * The rules that make up a scope, in the order a browser would apply them.
	 *
	 * @param string $selector Exact selector, e.g. `.dp-light`.
	 * @return list<CssRule>
	 */
	public function scope_rules( string $selector ): array {
		$matched = array();

		foreach ( array_merge( self::TOKEN_FILES, array( self::THEME_FILE ) ) as $file ) {
			foreach ( $this->rules_in( $file ) as $rule ) {
				if ( $selector === $rule->selector ) {
					$matched[] = $rule;
				}
			}
		}

		return $matched;
	}

	/**
	 * Every rule in `theme.css` other than its `:root` block.
	 *
	 * `theme.css` carries the two theme scopes and three global rules that have
	 * no `theme.json` equivalent — WordPress has no notion of a scoped preset —
	 * so the theme carries them across as CSS instead.
	 *
	 * @return list<CssRule>
	 */
	public function theme_scope_rules(): array {
		return array_values(
			array_filter(
				$this->rules_in( self::THEME_FILE ),
				static fn ( CssRule $rule ): bool => ':root' !== $rule->selector
			)
		);
	}

	/**
	 * The base layer's source, exactly as the design ships it.
	 *
	 * @return string
	 */
	public function base_layer(): string {
		return $this->read( self::BASE_FILE );
	}

	/**
	 * Absolute path to a file inside `design-source/`.
	 *
	 * @param string $relative Path relative to `design-source/`.
	 * @return string
	 */
	public function path( string $relative ): string {
		return $this->directory . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Parse one design-source file, once.
	 *
	 * @param string $relative Path relative to `design-source/`.
	 * @return list<CssRule>
	 */
	private function rules_in( string $relative ): array {
		if ( ! isset( $this->rules[ $relative ] ) ) {
			$this->rules[ $relative ] = CssParser::rules( $this->read( $relative ) );
		}

		return $this->rules[ $relative ];
	}

	/**
	 * Read a file from `design-source/`.
	 *
	 * @param string $relative Path relative to `design-source/`.
	 * @return string
	 *
	 * @throws RuntimeException If the file is missing or unreadable.
	 */
	private function read( string $relative ): string {
		$path = $this->path( $relative );

		// design-source/ is a plain directory on disk, read outside any request
		// context. WP_Filesystem is not available to the token generator, which
		// runs from the command line before WordPress exists.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $contents ) {
			throw new RuntimeException( sprintf( 'Cannot read the design source at %s.', $path ) );
		}

		return $contents;
	}
}
