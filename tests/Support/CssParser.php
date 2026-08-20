<?php
/**
 * A very small CSS reader, sufficient for token stylesheets.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Reads custom properties out of a stylesheet and resolves them to literals.
 *
 * This is deliberately not a general CSS parser. It handles exactly what the
 * design system's token files and WordPress's generated global stylesheet
 * contain: top-level rules, at-rules it can skip, and declarations whose values
 * may nest `var()`, `clamp()`, `color-mix()` and `linear-gradient()`.
 */
final class CssParser {

	/**
	 * Guard against a `var()` cycle in a malformed token file.
	 */
	private const MAX_RESOLUTION_DEPTH = 32;

	/**
	 * Split a stylesheet into its top-level rules.
	 *
	 * At-rules (`@media`, `@font-face`) are returned whole, with their prelude as
	 * the selector, and are never descended into: a declaration nested inside one
	 * belongs to that at-rule's scope, not to the sheet's. Callers that want token
	 * scopes match on an exact selector, so at-rules simply never match.
	 *
	 * @param string $css Stylesheet source.
	 * @return list<CssRule>
	 */
	public static function rules( string $css ): array {
		$rules   = array();
		$length  = strlen( $css );
		$cursor  = 0;
		$start   = 0;
		$depth   = 0;
		$prelude = 0;

		while ( $cursor < $length ) {
			$char = $css[ $cursor ];

			if ( '/' === $char && $cursor + 1 < $length && '*' === $css[ $cursor + 1 ] && 0 === $depth ) {
				$end    = strpos( $css, '*/', $cursor + 2 );
				$cursor = false === $end ? $length : $end + 2;
				continue;
			}

			if ( '{' === $char ) {
				if ( 0 === $depth ) {
					$prelude = $cursor;
				}
				++$depth;
				++$cursor;
				continue;
			}

			if ( '}' === $char ) {
				--$depth;
				++$cursor;

				if ( 0 === $depth ) {
					$selector = trim( substr( $css, $start, $prelude - $start ) );
					$selector = (string) preg_replace( '~/\*.*?\*/~s', '', $selector );
					$selector = trim( (string) preg_replace( '~\s+~', ' ', $selector ) );

					if ( '' !== $selector ) {
						$rules[] = new CssRule(
							$selector,
							substr( $css, $prelude + 1, $cursor - $prelude - 2 ),
							trim( substr( $css, $start, $cursor - $start ) )
						);
					}

					$start = $cursor;
				}

				continue;
			}

			++$cursor;
		}

		return $rules;
	}

	/**
	 * Read the custom-property declarations out of a declaration block.
	 *
	 * Later declarations of the same property win, exactly as the cascade would
	 * resolve them inside one rule.
	 *
	 * @param string $body A declaration block, without the braces.
	 * @return array<string, string> Property name (with the leading `--`) to raw value.
	 */
	public static function custom_properties( string $body ): array {
		$body  = (string) preg_replace( '~/\*.*?\*/~s', '', $body );
		$found = array();

		foreach ( self::split_declarations( $body ) as $declaration ) {
			$colon = strpos( $declaration, ':' );

			if ( false === $colon ) {
				continue;
			}

			$name = trim( substr( $declaration, 0, $colon ) );

			if ( ! str_starts_with( $name, '--' ) ) {
				continue;
			}

			$found[ $name ] = trim( substr( $declaration, $colon + 1 ) );
		}

		return $found;
	}

	/**
	 * Replace every `var()` reference in a value with the literal it resolves to.
	 *
	 * A `var(--missing, fallback)` uses its fallback. A `var(--missing)` with no
	 * fallback is a genuine error in the stylesheet under test, so it throws
	 * rather than returning something plausible.
	 *
	 * @param string                $value Raw declaration value.
	 * @param array<string, string> $map   Property name to raw value.
	 * @param int                   $depth Internal recursion guard.
	 * @return string
	 *
	 * @throws RuntimeException If a referenced property does not exist, or the references cycle.
	 */
	public static function resolve( string $value, array $map, int $depth = 0 ): string {
		if ( $depth > self::MAX_RESOLUTION_DEPTH ) {
			throw new RuntimeException(
				sprintf( 'Cannot resolve "%s": custom properties reference each other in a cycle.', $value )
			);
		}

		$offset = strpos( $value, 'var(' );

		if ( false === $offset ) {
			return $value;
		}

		$inner    = self::balanced( $value, $offset + 3 );
		$argument = substr( $value, $offset + 4, $inner - $offset - 4 );
		$comma    = self::top_level_comma( $argument );
		$name     = trim( null === $comma ? $argument : substr( $argument, 0, $comma ) );
		$fallback = null === $comma ? null : trim( substr( $argument, $comma + 1 ) );

		if ( array_key_exists( $name, $map ) ) {
			$replacement = $map[ $name ];
		} elseif ( null !== $fallback ) {
			$replacement = $fallback;
		} else {
			throw new RuntimeException(
				sprintf( 'Cannot resolve "%s": it is referenced but never declared.', $name )
			);
		}

		$resolved = substr( $value, 0, $offset ) . $replacement . substr( $value, $inner + 1 );

		return self::resolve( $resolved, $map, $depth + 1 );
	}

	/**
	 * Resolve every entry of a custom-property map against itself.
	 *
	 * @param array<string, string> $map      Property name to raw value.
	 * @param array<string, string> $context  Extra properties available for resolution.
	 * @return array<string, string> Property name to normalised literal value.
	 */
	public static function resolve_all( array $map, array $context = array() ): array {
		$lookup   = array_merge( $context, $map );
		$resolved = array();

		foreach ( $map as $name => $value ) {
			$resolved[ $name ] = self::normalize( self::resolve( $value, $lookup ) );
		}

		return $resolved;
	}

	/**
	 * Reduce a CSS value to a form that can be compared for equality.
	 *
	 * Two values that differ only in spacing or hex case are the same value.
	 * Anything else — a different unit, a different stop, a different hue — is
	 * a real difference and survives normalisation.
	 *
	 * @param string $value Raw declaration value.
	 * @return string
	 */
	public static function normalize( string $value ): string {
		$value = (string) preg_replace( '~/\*.*?\*/~s', '', $value );
		$value = trim( (string) preg_replace( '~\s+~', ' ', $value ) );
		$value = (string) preg_replace( '~\s*([(),])\s*~', '$1', $value );

		return strtolower( $value );
	}

	/**
	 * Split a declaration block on semicolons that are not inside parentheses.
	 *
	 * @param string $body A declaration block, without the braces.
	 * @return list<string>
	 */
	private static function split_declarations( string $body ): array {
		$declarations = array();
		$buffer       = '';
		$depth        = 0;
		$length       = strlen( $body );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $body[ $i ];

			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;
			}

			if ( ';' === $char && 0 === $depth ) {
				$declarations[] = $buffer;
				$buffer         = '';
				continue;
			}

			$buffer .= $char;
		}

		$declarations[] = $buffer;

		return array_values( array_filter( array_map( 'trim', $declarations ), static fn ( string $d ): bool => '' !== $d ) );
	}

	/**
	 * Find the closing parenthesis that matches the one at `$open`.
	 *
	 * @param string $value Raw declaration value.
	 * @param int    $open  Offset of the opening parenthesis.
	 * @return int Offset of the matching closing parenthesis.
	 *
	 * @throws RuntimeException If the parentheses are unbalanced.
	 */
	private static function balanced( string $value, int $open ): int {
		$depth  = 0;
		$length = strlen( $value );

		for ( $i = $open; $i < $length; $i++ ) {
			if ( '(' === $value[ $i ] ) {
				++$depth;
			} elseif ( ')' === $value[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		throw new RuntimeException( sprintf( 'Unbalanced parentheses in "%s".', $value ) );
	}

	/**
	 * Offset of the first comma that is not inside nested parentheses.
	 *
	 * @param string $argument The contents of a `var()` call.
	 * @return int|null
	 */
	private static function top_level_comma( string $argument ): ?int {
		$depth  = 0;
		$length = strlen( $argument );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '(' === $argument[ $i ] ) {
				++$depth;
			} elseif ( ')' === $argument[ $i ] ) {
				--$depth;
			} elseif ( ',' === $argument[ $i ] && 0 === $depth ) {
				return $i;
			}
		}

		return null;
	}
}
