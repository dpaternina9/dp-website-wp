<?php
/**
 * The design's computed style objects, read as source rather than as prose.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Evaluates one named style object out of a `design-source/*.logic.js` file.
 *
 * Half of every component's styling is not in its markup. The design computes
 * it — `orgStyle`, `rowStyle`, `legendStyle`, `headlineStyle` — and writes the
 * result into a `style="{{ … }}"` hole. Between 2026-08-19 and 2026-08-23 those
 * script blocks were missing from the import, `docs/adr/0012-…` recorded them as
 * unrecoverable, and three audits of the work page passed over values nothing
 * was asserting. The blocks are back, verbatim, as `*.logic.js`, and this class
 * is what stops them being hand-transcribed into a fixture a second time.
 *
 * **This is not a JavaScript engine and must not become one.** It reads the one
 * expression grammar the design's style objects are written in: literals,
 * identifiers bound by the caller, member access, `!`, the arithmetic and
 * comparison operators, `&&` / `||` / `??`, the conditional operator, object
 * literals, and object spread. A construct outside that grammar throws with the
 * file and the fragment in the message, which is the correct outcome: the design
 * has grown something this reader cannot see, and a reviewer needs to know that
 * rather than to receive a quietly shorter fixture.
 *
 * **Why the caller binds the environment.** `isStack` is not a value in the
 * file; it is which of the design's three modes is being asserted, and bars and
 * stack are two different assertions of the same object. `teal` is
 * `lane.accent || var(--dp-teal)`, and a lane's accent is content. So the map in
 * `DesignBaseline` says which mode and which accent — a judgement about role,
 * exactly like the theme selector beside it — and every value to the right of
 * that judgement is read out of the design.
 *
 * **Numbers become pixels the way the design tool makes them pixels.** A style
 * object assigned to a DOM `style` attribute follows React's rule: zero stays
 * `0`, a unitless property keeps its number, and anything else gains `px`. That
 * rule is emulated here rather than reimplemented per property, so `gap: 6`
 * reaches the fixture as `6px` and `fontWeight: 600` as `600`.
 */
final class DesignLogic {

	/**
	 * Properties whose numeric values carry no unit.
	 *
	 * The design's objects use exactly these; the list is short on purpose, so a
	 * new unitless property is a decision rather than a guess.
	 *
	 * @var list<string>
	 */
	private const UNITLESS = array(
		'font-weight',
		'line-height',
		'opacity',
		'order',
		'z-index',
		'flex-grow',
		'flex-shrink',
	);

	/**
	 * Source text, keyed by path relative to `design-source/`.
	 *
	 * @var array<string, string>
	 */
	private array $sources = array();

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
	 * Every property the design's object declares, in source order.
	 *
	 * Returned unevaluated, so a caller can say which subset it wants asserted
	 * and the generator can still write down the names it left out. That is the
	 * difference between a fixture with a hole in it and a fixture that documents
	 * its own hole.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding's name, e.g. `orgStyle`.
	 * @param int                  $ordinal     Which occurrence, when a file declares the name twice.
	 * @param array<string, mixed> $environment Identifiers the object spreads that the file does not fix.
	 * @return list<string> CSS property names, kebab-cased.
	 *
	 * @throws RuntimeException If the binding cannot be found or parsed.
	 */
	public function properties( string $file, string $name, int $ordinal = 1, array $environment = array() ): array {
		return array_keys( $this->fields( $file, $name, $ordinal, $environment ) );
	}

	/**
	 * Evaluate one named style object.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding's name, e.g. `orgStyle`.
	 * @param int                  $ordinal     Which occurrence, when a file declares the name twice.
	 * @param array<string, mixed> $environment Identifiers the object reads that the file does not fix.
	 * @param list<string>         $only        Which properties to evaluate; every one, when empty.
	 * @return array<string, string> CSS property to value, in source order.
	 *
	 * @throws RuntimeException If a requested property cannot be evaluated.
	 */
	public function declarations( string $file, string $name, int $ordinal = 1, array $environment = array(), array $only = array() ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- `list<string>` is PHPStan's spelling of `array`, and level 9 is what reads it.
		$fields       = $this->fields( $file, $name, $ordinal, $environment );
		$declarations = array();

		foreach ( $fields as $property => $expression ) {
			if ( array() !== $only && ! in_array( $property, $only, true ) ) {
				continue;
			}

			$value = $this->evaluate( $file, $name, $expression, $environment );

			if ( null === $value ) {
				continue;
			}

			$declarations[ $property ] = $this->css( $property, $value );
		}

		foreach ( $only as $property ) {
			if ( ! isset( $declarations[ $property ] ) ) {
				throw new RuntimeException(
					sprintf(
						'%s: "%s" does not declare "%s". The design has moved under the map; re-read the object.',
						$file,
						$name,
						$property
					)
				);
			}
		}

		return $declarations;
	}

	/**
	 * One entry of a named constant map, e.g. `TONES.teal`.
	 *
	 * The design keeps its tone and accent palettes as plain objects at the top
	 * of a script block and interpolates them into a `{{ }}` hole in the markup.
	 * A hole is not a value, so the entry that anchors on that element has to be
	 * told what filled it — and this is what makes the telling a read of the
	 * design rather than a literal typed into the map.
	 *
	 * @param string $file Path relative to `design-source/`.
	 * @param string $name The constant's name, e.g. `TONES`.
	 * @param string $key  Which entry.
	 * @return string
	 *
	 * @throws RuntimeException If the constant or the key is missing.
	 */
	public function constant( string $file, string $name, string $key ): string {
		$fields = $this->object_fields( $file, $name, $this->literal( $file, $name, 1 ), array() );
		$wanted = self::kebab( $key );

		if ( ! isset( $fields[ $wanted ] ) ) {
			throw new RuntimeException( sprintf( '%s: "%s" has no entry "%s".', $file, $name, $key ) );
		}

		$value = $this->evaluate( $file, $name, $fields[ $wanted ], array() );

		if ( ! is_string( $value ) ) {
			throw new RuntimeException( sprintf( '%s: "%s.%s" is not a string.', $file, $name, $key ) );
		}

		return $value;
	}

	/**
	 * Split one named object literal into property name to raw expression.
	 *
	 * Spreads are resolved in place, so `{ ...gridStyle, gap: 8 }` yields the
	 * spread object's fields followed by the override, which is the order the
	 * design's own evaluation produces.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding's name.
	 * @param int                  $ordinal     Which occurrence.
	 * @param array<string, mixed> $environment Bound identifiers, for spreads the file does not declare.
	 * @return array<string, string>
	 *
	 * @throws RuntimeException If the binding cannot be found or parsed.
	 */
	private function fields( string $file, string $name, int $ordinal, array $environment ): array {
		return $this->object_fields( $file, $name, $this->literal( $file, $name, $ordinal ), $environment );
	}

	/**
	 * Split an object literal's source into property name to raw expression.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding the literal belongs to, for error messages.
	 * @param string               $literal     The literal's source, braces included.
	 * @param array<string, mixed> $environment Bound identifiers, for spreads the file does not declare.
	 * @return array<string, string>
	 *
	 * @throws RuntimeException If the literal is malformed.
	 */
	private function object_fields( string $file, string $name, string $literal, array $environment ): array {
		$fields = array();

		foreach ( $this->split( $this->uncomment( substr( $literal, 1, -1 ) ) ) as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			if ( str_starts_with( $part, '...' ) ) {
				foreach ( $this->spread( $file, $name, trim( substr( $part, 3 ) ), $environment ) as $property => $expression ) {
					$fields[ $property ] = $expression;
				}

				continue;
			}

			$colon = $this->colon( $part );

			if ( null === $colon ) {
				// ES6 shorthand: `{ maxWidth, margin: '0 auto' }`. The key is the binding.
				if ( 1 !== preg_match( '/^[A-Za-z_$][\w$]*$/', $part ) ) {
					throw new RuntimeException( sprintf( '%s: cannot read "%s" inside "%s".', $file, $part, $name ) );
				}

				$fields[ self::kebab( $part ) ] = $part;
				continue;
			}

			$key = trim( substr( $part, 0, $colon ), " \t\n\r'\"" );

			$fields[ self::kebab( $key ) ] = trim( substr( $part, $colon + 1 ) );
		}

		if ( array() === $fields ) {
			throw new RuntimeException( sprintf( '%s: "%s" declares nothing.', $file, $name ) );
		}

		return $fields;
	}

	/**
	 * The fields one `...spread` contributes.
	 *
	 * A spread of something the file declares — `...gridStyle` inside
	 * `shipGridStyle` — is read out of the file. A spread of something the file
	 * computes from the mode, like `...pinned`, is bound by the map instead: it
	 * is empty in the two modes this baseline measures, and saying so in the map
	 * is honest where teaching this reader `Math.max` would not be.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding being read, for error messages.
	 * @param string               $spread      The spread identifier.
	 * @param array<string, mixed> $environment Bound identifiers.
	 * @return array<string, string>
	 *
	 * @throws RuntimeException If the identifier is bound to something that is not an object.
	 */
	private function spread( string $file, string $name, string $spread, array $environment ): array {
		if ( ! array_key_exists( $spread, $environment ) ) {
			return $this->object_fields( $file, $name, $this->literal( $file, $spread, 1 ), $environment );
		}

		$bound = $environment[ $spread ];

		if ( ! is_array( $bound ) ) {
			throw new RuntimeException( sprintf( '%s: "...%s" is bound to something that is not an object.', $file, $spread ) );
		}

		$fields = array();

		foreach ( $bound as $property => $value ) {
			if ( ! is_string( $property ) || ! is_string( $value ) ) {
				throw new RuntimeException( sprintf( '%s: "...%s" must be bound to a map of strings.', $file, $spread ) );
			}

			$fields[ self::kebab( $property ) ] = "'" . $value . "'";
		}

		return $fields;
	}

	/**
	 * The source of the object literal a named binding evaluates to.
	 *
	 * Handles the four shapes the design writes: `const x = { … }`,
	 * `x: { … }`, `const x = (a, b) => ({ … })` and `const x = a => ({ … })`.
	 *
	 * @param string $file    Path relative to `design-source/`.
	 * @param string $name    The binding's name.
	 * @param int    $ordinal Which occurrence.
	 * @return string The literal, braces included.
	 *
	 * @throws RuntimeException If the binding cannot be found.
	 */
	private function literal( string $file, string $name, int $ordinal ): string {
		$source = $this->source( $file );
		$found  = 0;
		$offset = 0;

		while ( preg_match( '/(?<![\w$.])' . preg_quote( $name, '/' ) . '\s*[:=](?!=)/', $source, $matches, PREG_OFFSET_CAPTURE, $offset ) ) {
			$after  = (int) $matches[0][1] + strlen( (string) $matches[0][0] );
			$offset = $after;
			$cursor = $this->skip_arrow( $source, $after );

			if ( null === $cursor ) {
				continue;
			}

			++$found;

			if ( $found === $ordinal ) {
				return $this->balanced( $file, $name, $source, $cursor );
			}
		}

		throw new RuntimeException(
			sprintf(
				'%s: no object literal named "%s" (occurrence %d). The design has moved; re-read the component.',
				$file,
				$name,
				$ordinal
			)
		);
	}

	/**
	 * Advance past whitespace, an arrow-function head and a wrapping paren.
	 *
	 * @param string $source The file's source.
	 * @param int    $cursor Where the binding's `=` or `:` ended.
	 * @return int|null The offset of the opening brace, or null when there is none.
	 */
	private function skip_arrow( string $source, int $cursor ): ?int {
		$cursor = $this->skip_space( $source, $cursor );

		if ( isset( $source[ $cursor ] ) && '{' === $source[ $cursor ] ) {
			return $cursor;
		}

		/*
		 * An arrow function returning an object literal, in the two shapes the
		 * design writes: `(isOpen, isShip) => ({ … })` and `isOpen => ({ … })`.
		 * Anchored at the cursor, so `rowStyle: rowStyle(isOpen, false)` — which
		 * is a *call* to the binding, not the binding — does not scan forward and
		 * claim some unrelated brace forty lines later.
		 */
		if ( 1 === preg_match( '/(\([^()]*\)|[A-Za-z_$][\w$]*)\s*=>\s*\(?\s*\{/A', $source, $matches, 0, $cursor ) ) {
			return $cursor + strlen( $matches[0] ) - 1;
		}

		return null;
	}

	/**
	 * Skip whitespace and line comments.
	 *
	 * @param string $source The file's source.
	 * @param int    $cursor Where to start.
	 * @return int
	 */
	private function skip_space( string $source, int $cursor ): int {
		$length = strlen( $source );

		while ( $cursor < $length ) {
			if ( '' !== trim( $source[ $cursor ] ) ) {
				if ( '/' === $source[ $cursor ] && isset( $source[ $cursor + 1 ] ) && '/' === $source[ $cursor + 1 ] ) {
					$end    = strpos( $source, "\n", $cursor );
					$cursor = false === $end ? $length : $end + 1;
					continue;
				}

				break;
			}

			++$cursor;
		}

		return $cursor;
	}

	/**
	 * The brace-balanced slice starting at an opening brace.
	 *
	 * @param string $file   Path relative to `design-source/`, for the error message.
	 * @param string $name   The binding's name, for the error message.
	 * @param string $source The file's source.
	 * @param int    $start  Offset of the opening brace.
	 * @return string
	 *
	 * @throws RuntimeException If the braces never balance.
	 */
	private function balanced( string $file, string $name, string $source, int $start ): string {
		$depth  = 0;
		$length = strlen( $source );

		for ( $cursor = $start; $cursor < $length; $cursor++ ) {
			$char = $source[ $cursor ];

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$cursor = $this->end_of_string( $source, $cursor );
				continue;
			}

			if ( '{' === $char ) {
				++$depth;
				continue;
			}

			if ( '}' === $char ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $source, $start, $cursor - $start + 1 );
				}
			}
		}

		throw new RuntimeException( sprintf( '%s: the literal for "%s" is not brace-balanced.', $file, $name ) );
	}

	/**
	 * The offset of the closing quote of the string starting at `$start`.
	 *
	 * @param string $source The file's source.
	 * @param int    $start  Offset of the opening quote.
	 * @return int
	 */
	private function end_of_string( string $source, int $start ): int {
		$quote  = $source[ $start ];
		$length = strlen( $source );

		for ( $cursor = $start + 1; $cursor < $length; $cursor++ ) {
			if ( '\\' === $source[ $cursor ] ) {
				++$cursor;
				continue;
			}

			if ( $quote === $source[ $cursor ] ) {
				return $cursor;
			}
		}

		return $length - 1;
	}

	/**
	 * Strip line and block comments that sit outside a string.
	 *
	 * The design annotates its style objects heavily, and one of those comments
	 * sits between a property's comma and its name. Left in, it becomes part of
	 * the key and the property silently changes name.
	 *
	 * @param string $body The literal's contents, braces stripped.
	 * @return string
	 */
	private function uncomment( string $body ): string {
		$clean  = '';
		$length = strlen( $body );

		for ( $cursor = 0; $cursor < $length; $cursor++ ) {
			$char = $body[ $cursor ];

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$end    = $this->end_of_string( $body, $cursor );
				$clean .= substr( $body, $cursor, $end - $cursor + 1 );
				$cursor = $end;
				continue;
			}

			if ( '/' === $char && isset( $body[ $cursor + 1 ] ) && '/' === $body[ $cursor + 1 ] ) {
				$end    = strpos( $body, "\n", $cursor );
				$cursor = false === $end ? $length : $end;
				$clean .= "\n";
				continue;
			}

			if ( '/' === $char && isset( $body[ $cursor + 1 ] ) && '*' === $body[ $cursor + 1 ] ) {
				$end    = strpos( $body, '*/', $cursor + 2 );
				$cursor = false === $end ? $length : $end + 1;
				$clean .= ' ';
				continue;
			}

			$clean .= $char;
		}

		return $clean;
	}

	/**
	 * Split an object body on its top-level commas.
	 *
	 * @param string $body The literal's contents, braces stripped.
	 * @return list<string>
	 */
	private function split( string $body ): array {
		$parts  = array();
		$start  = 0;
		$depth  = 0;
		$length = strlen( $body );

		for ( $cursor = 0; $cursor < $length; $cursor++ ) {
			$char = $body[ $cursor ];

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$cursor = $this->end_of_string( $body, $cursor );
				continue;
			}

			if ( '/' === $char && isset( $body[ $cursor + 1 ] ) && '/' === $body[ $cursor + 1 ] ) {
				$end    = strpos( $body, "\n", $cursor );
				$cursor = false === $end ? $length - 1 : $end;
				continue;
			}

			if ( '{' === $char || '(' === $char || '[' === $char ) {
				++$depth;
				continue;
			}

			if ( '}' === $char || ')' === $char || ']' === $char ) {
				--$depth;
				continue;
			}

			if ( ',' === $char && 0 === $depth ) {
				$parts[] = substr( $body, $start, $cursor - $start );
				$start   = $cursor + 1;
			}
		}

		$parts[] = substr( $body, $start );

		return $parts;
	}

	/**
	 * The offset of the colon separating a property's key from its value.
	 *
	 * @param string $part One comma-separated member of an object literal.
	 * @return int|null
	 */
	private function colon( string $part ): ?int {
		$depth  = 0;
		$length = strlen( $part );

		for ( $cursor = 0; $cursor < $length; $cursor++ ) {
			$char = $part[ $cursor ];

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$cursor = $this->end_of_string( $part, $cursor );
				continue;
			}

			if ( '{' === $char || '(' === $char || '[' === $char ) {
				++$depth;
				continue;
			}

			if ( '}' === $char || ')' === $char || ']' === $char ) {
				--$depth;
				continue;
			}

			if ( ':' === $char && 0 === $depth ) {
				return $cursor;
			}
		}

		return null;
	}

	/**
	 * Evaluate one property's expression.
	 *
	 * @param string               $file        Path relative to `design-source/`.
	 * @param string               $name        The binding's name, for error messages.
	 * @param string               $expression  The raw expression.
	 * @param array<string, mixed> $environment Bound identifiers.
	 * @return mixed
	 *
	 * @throws RuntimeException If the expression is outside the reader's grammar.
	 */
	private function evaluate( string $file, string $name, string $expression, array $environment ): mixed {
		$reader = new JsExpression( $expression, $environment, sprintf( '%s: %s', $file, $name ) );

		return $reader->value();
	}

	/**
	 * Render an evaluated value the way a DOM style attribute renders it.
	 *
	 * @param string $property The CSS property, kebab-cased.
	 * @param mixed  $value    The evaluated value.
	 * @return string
	 *
	 * @throws RuntimeException If the value is not something a style attribute can carry.
	 */
	private function css( string $property, mixed $value ): string {
		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			throw new RuntimeException( sprintf( 'The design gives "%s" a value no style attribute can carry.', $property ) );
		}

		$number = is_float( $value ) && floor( $value ) === $value ? (string) (int) $value : (string) $value;

		if ( 0.0 === (float) $value || in_array( $property, self::UNITLESS, true ) ) {
			return $number;
		}

		return $number . 'px';
	}

	/**
	 * A JavaScript property name, as CSS spells it.
	 *
	 * @param string $property The object literal's key.
	 * @return string
	 */
	private static function kebab( string $property ): string {
		return strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '-$0', $property ) );
	}

	/**
	 * Read one design-source file, once.
	 *
	 * A `.dc.html` carries its logic inside a `<script type="text/x-dc">`; a
	 * `.logic.js` is that same block, extracted at import.
	 *
	 * @param string $file Path relative to `design-source/`.
	 * @return string
	 *
	 * @throws RuntimeException If the file is missing or carries no logic.
	 */
	private function source( string $file ): string {
		if ( isset( $this->sources[ $file ] ) ) {
			return $this->sources[ $file ];
		}

		$path = $this->directory . '/' . ltrim( $file, '/' );

		/*
		 * design-source/ is a plain directory on disk, read from the command line
		 * before WordPress exists, exactly as DesignMarkup and DesignTokens read it.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $contents ) {
			throw new RuntimeException( sprintf( 'Cannot read the design source at %s.', $path ) );
		}

		if ( str_ends_with( $file, '.dc.html' ) ) {
			if ( 1 !== preg_match( '/<script type="text\/x-dc"[^>]*>(.*)<\/script>/s', $contents, $matches ) ) {
				throw new RuntimeException(
					sprintf(
						'%s carries no <script type="text/x-dc"> block. If the import dropped it, re-fetch it — '
							. 'that is exactly what cost three audits of the work page. See design-source/README.md.',
						$file
					)
				);
			}

			$contents = $matches[1];
		}

		$this->sources[ $file ] = $contents;

		return $contents;
	}
}
