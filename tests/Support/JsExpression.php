<?php
/**
 * The one expression grammar the design's style objects are written in.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Evaluates one JavaScript expression out of `design-source/*.logic.js`.
 *
 * Deliberately tiny, and deliberately incapable of growing by accident. It
 * reads literals, bound identifiers, member access, `!`, unary minus, `+ - * /
 * %`, the comparisons, `&& || ??`, the conditional operator and parentheses —
 * which is the whole of what the design writes inside a style object. A call, a
 * template literal, an arrow function or anything else throws, naming the file
 * and the fragment, because the alternative is a fixture that silently stops
 * asserting a property the moment the design gains a construct.
 *
 * JavaScript's own semantics are honoured where they change the answer: `+` is
 * concatenation when either side is a string, `||` and `??` differ on the empty
 * string, and truthiness is JavaScript's rather than PHP's — `'0'` is true in
 * JavaScript and false in PHP, and `--dp-*` values are strings.
 *
 * See `DesignLogic` for why any of this exists.
 */
final class JsExpression {

	/**
	 * The tokenised source.
	 *
	 * @var list<array{type: string, value: string}>
	 */
	private array $tokens;

	/**
	 * How far through the token list the parser is.
	 *
	 * @var int
	 */
	private int $index = 0;

	/**
	 * Constructor.
	 *
	 * @param string               $source      The expression.
	 * @param array<string, mixed> $environment Identifiers the expression may read.
	 * @param string               $where       File and binding, for error messages.
	 *
	 * @throws RuntimeException If the source cannot be tokenised.
	 */
	public function __construct(
		private readonly string $source,
		private readonly array $environment,
		private readonly string $where
	) {
		$this->tokens = $this->tokenise( $source );
	}

	/**
	 * Evaluate the whole expression.
	 *
	 * @return mixed
	 *
	 * @throws RuntimeException If anything is left over, which means the grammar was exceeded.
	 */
	public function value(): mixed {
		$value = $this->conditional();

		if ( $this->index < count( $this->tokens ) ) {
			throw $this->fail( sprintf( 'unread input at "%s"', $this->tokens[ $this->index ]['value'] ) );
		}

		return $value;
	}

	/**
	 * `a ? b : c`, right-associative.
	 *
	 * @return mixed
	 */
	private function conditional(): mixed {
		$test = $this->either();

		if ( ! $this->at( '?' ) ) {
			return $test;
		}

		$this->take();
		$consequent = $this->conditional();
		$this->expect( ':' );

		$alternate = $this->conditional();

		return self::truthy( $test ) ? $consequent : $alternate;
	}

	/**
	 * `a || b` and `a ?? b`.
	 *
	 * @return mixed
	 */
	private function either(): mixed {
		$left = $this->both();

		while ( $this->at( '||' ) || $this->at( '??' ) ) {
			$operator = $this->take();
			$right    = $this->both();

			$left = '??' === $operator
				? ( null === $left ? $right : $left )
				: ( self::truthy( $left ) ? $left : $right );
		}

		return $left;
	}

	/**
	 * `a && b`.
	 *
	 * @return mixed
	 */
	private function both(): mixed {
		$left = $this->equality();

		while ( $this->at( '&&' ) ) {
			$this->take();
			$right = $this->equality();
			$left  = self::truthy( $left ) ? $right : $left;
		}

		return $left;
	}

	/**
	 * `a === b` and friends.
	 *
	 * @return mixed
	 */
	private function equality(): mixed {
		$left = $this->relational();

		while ( $this->at( '===' ) || $this->at( '!==' ) || $this->at( '==' ) || $this->at( '!=' ) ) {
			$operator = $this->take();
			$right    = $this->relational();
			$same     = $left === $right;

			$left = str_starts_with( $operator, '!' ) ? ! $same : $same;
		}

		return $left;
	}

	/**
	 * `a < b` and friends.
	 *
	 * @return mixed
	 */
	private function relational(): mixed {
		$left = $this->additive();

		while ( $this->at( '<=' ) || $this->at( '>=' ) || $this->at( '<' ) || $this->at( '>' ) ) {
			$operator = $this->take();
			$right    = $this->additive();
			$a        = self::number( $left );
			$b        = self::number( $right );

			$left = match ( $operator ) {
				'<'     => $a < $b,
				'>'     => $a > $b,
				'<='    => $a <= $b,
				default => $a >= $b,
			};
		}

		return $left;
	}

	/**
	 * `a + b` and `a - b`. JavaScript's `+` concatenates when either side is a string.
	 *
	 * @return mixed
	 */
	private function additive(): mixed {
		$left = $this->multiplicative();

		while ( $this->at( '+' ) || $this->at( '-' ) ) {
			$operator = $this->take();
			$right    = $this->multiplicative();

			if ( '+' === $operator && ( is_string( $left ) || is_string( $right ) ) ) {
				$left = self::text( $left ) . self::text( $right );
				continue;
			}

			$left = '+' === $operator
				? self::number( $left ) + self::number( $right )
				: self::number( $left ) - self::number( $right );
		}

		return $left;
	}

	/**
	 * `a * b`, `a / b`, `a % b`.
	 *
	 * @return mixed
	 *
	 * @throws RuntimeException On division by zero.
	 */
	private function multiplicative(): mixed {
		$left = $this->unary();

		while ( $this->at( '*' ) || $this->at( '/' ) || $this->at( '%' ) ) {
			$operator = $this->take();
			$right    = self::number( $this->unary() );
			$a        = self::number( $left );

			if ( '*' !== $operator && 0.0 === (float) $right ) {
				throw $this->fail( 'division by zero' );
			}

			$left = match ( $operator ) {
				'*'     => $a * $right,
				'/'     => $a / $right,
				default => fmod( $a, $right ),
			};
		}

		return $left;
	}

	/**
	 * `!a` and `-a`.
	 *
	 * @return mixed
	 */
	private function unary(): mixed {
		if ( $this->at( '!' ) ) {
			$this->take();

			return ! self::truthy( $this->unary() );
		}

		if ( $this->at( '-' ) ) {
			$this->take();

			return -self::number( $this->unary() );
		}

		return $this->primary();
	}

	/**
	 * A literal, a bound identifier with any member access, or a parenthesised expression.
	 *
	 * @return mixed
	 *
	 * @throws RuntimeException If the token is outside the grammar.
	 */
	private function primary(): mixed {
		$token = $this->tokens[ $this->index ] ?? null;

		if ( null === $token ) {
			throw $this->fail( 'the expression ended early' );
		}

		if ( 'punct' === $token['type'] && '(' === $token['value'] ) {
			$this->take();
			$value = $this->conditional();
			$this->expect( ')' );

			return $value;
		}

		if ( 'string' === $token['type'] ) {
			$this->take();

			return $token['value'];
		}

		if ( 'number' === $token['type'] ) {
			$this->take();

			return str_contains( $token['value'], '.' ) ? (float) $token['value'] : (int) $token['value'];
		}

		if ( 'ident' !== $token['type'] ) {
			throw $this->fail( sprintf( '"%s" is outside this reader\'s grammar', $token['value'] ) );
		}

		$this->take();
		$value = $this->identifier( $token['value'] );

		while ( $this->at( '.' ) ) {
			$this->take();
			$member = $this->tokens[ $this->index ] ?? null;

			if ( null === $member || 'ident' !== $member['type'] ) {
				throw $this->fail( 'a member name must follow "."' );
			}

			$this->take();

			if ( ! is_array( $value ) || ! array_key_exists( $member['value'], $value ) ) {
				throw $this->fail( sprintf( 'nothing bound for ".%s"', $member['value'] ) );
			}

			$value = $value[ $member['value'] ];
		}

		if ( $this->at( '(' ) ) {
			throw $this->fail( sprintf( '"%s(…)" is a call; bind its result instead', $token['value'] ) );
		}

		return $value;
	}

	/**
	 * Resolve one identifier.
	 *
	 * @param string $name The identifier.
	 * @return mixed
	 *
	 * @throws RuntimeException If nothing is bound for it.
	 */
	private function identifier( string $name ): mixed {
		$literal = match ( $name ) {
			'true'      => true,
			'false'     => false,
			'null'      => null,
			'undefined' => null,
			default     => 'unbound',
		};

		if ( 'unbound' !== $literal ) {
			return $literal;
		}

		if ( ! array_key_exists( $name, $this->environment ) ) {
			throw $this->fail(
				sprintf(
					'"%s" is not bound. The map has to say which mode or which accent it is asserting; '
						. 'see the environment note in DesignLogic',
					$name
				)
			);
		}

		return $this->environment[ $name ];
	}

	/**
	 * Whether the next token is this punctuation.
	 *
	 * @param string $punctuation The operator.
	 * @return bool
	 */
	private function at( string $punctuation ): bool {
		$token = $this->tokens[ $this->index ] ?? null;

		return null !== $token && 'punct' === $token['type'] && $punctuation === $token['value'];
	}

	/**
	 * Consume the next token and return its text.
	 *
	 * @return string
	 *
	 * @throws RuntimeException If there is nothing left.
	 */
	private function take(): string {
		$token = $this->tokens[ $this->index ] ?? null;

		if ( null === $token ) {
			throw $this->fail( 'the expression ended early' );
		}

		++$this->index;

		return $token['value'];
	}

	/**
	 * Consume a required piece of punctuation.
	 *
	 * @param string $punctuation The operator.
	 * @return void
	 *
	 * @throws RuntimeException If it is not there.
	 */
	private function expect( string $punctuation ): void {
		if ( ! $this->at( $punctuation ) ) {
			throw $this->fail( sprintf( 'expected "%s"', $punctuation ) );
		}

		$this->take();
	}

	/**
	 * Split the source into tokens.
	 *
	 * @param string $source The expression.
	 * @return list<array{type: string, value: string}>
	 *
	 * @throws RuntimeException If a character cannot be read.
	 */
	private function tokenise( string $source ): array {
		$operators = array( '===', '!==', '...', '??', '&&', '||', '==', '!=', '<=', '>=', '?', ':', '!', '+', '-', '*', '/', '%', '<', '>', '(', ')', '[', ']', '{', '}', ',', '.' );
		$tokens    = array();
		$length    = strlen( $source );
		$cursor    = 0;

		while ( $cursor < $length ) {
			$char = $source[ $cursor ];

			if ( '' === trim( $char ) ) {
				++$cursor;
				continue;
			}

			if ( '/' === $char && isset( $source[ $cursor + 1 ] ) && '/' === $source[ $cursor + 1 ] ) {
				$end    = strpos( $source, "\n", $cursor );
				$cursor = false === $end ? $length : $end + 1;
				continue;
			}

			if ( "'" === $char || '"' === $char ) {
				$end = $cursor + 1;

				while ( $end < $length && $source[ $end ] !== $char ) {
					$end += '\\' === $source[ $end ] ? 2 : 1;
				}

				$tokens[] = array(
					'type'  => 'string',
					'value' => stripslashes( substr( $source, $cursor + 1, $end - $cursor - 1 ) ),
				);
				$cursor   = $end + 1;
				continue;
			}

			if ( 1 === preg_match( '/\d[\d.]*/A', $source, $matches, 0, $cursor ) ) {
				$tokens[] = array(
					'type'  => 'number',
					'value' => $matches[0],
				);
				$cursor  += strlen( $matches[0] );
				continue;
			}

			if ( 1 === preg_match( '/[A-Za-z_$][\w$]*/A', $source, $matches, 0, $cursor ) ) {
				$tokens[] = array(
					'type'  => 'ident',
					'value' => $matches[0],
				);
				$cursor  += strlen( $matches[0] );
				continue;
			}

			$operator = null;

			foreach ( $operators as $candidate ) {
				if ( str_starts_with( substr( $source, $cursor ), $candidate ) ) {
					$operator = $candidate;
					break;
				}
			}

			if ( null === $operator ) {
				throw $this->fail( sprintf( 'cannot read "%s"', substr( $source, $cursor, 12 ) ) );
			}

			$tokens[] = array(
				'type'  => 'punct',
				'value' => $operator,
			);
			$cursor  += strlen( $operator );
		}

		return $tokens;
	}

	/**
	 * A failure that names where it happened.
	 *
	 * @param string $problem What went wrong.
	 * @return RuntimeException
	 */
	private function fail( string $problem ): RuntimeException {
		return new RuntimeException(
			sprintf( '%s — %s, in: %s', $this->where, $problem, trim( preg_replace( '/\s+/', ' ', $this->source ) ?? $this->source ) )
		);
	}

	/**
	 * JavaScript truthiness, which differs from PHP's on `'0'` and on `'0.0'`.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private static function truthy( mixed $value ): bool {
		if ( is_string( $value ) ) {
			return '' !== $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0.0 !== (float) $value;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		return null !== $value;
	}

	/**
	 * A value as a number.
	 *
	 * @param mixed $value The value.
	 * @return float|int
	 *
	 * @throws RuntimeException If it is not arithmetic.
	 */
	private static function number( mixed $value ): float|int {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		throw new RuntimeException( sprintf( 'The design does arithmetic on "%s", which is not a number.', is_string( $value ) ? $value : gettype( $value ) ) );
	}

	/**
	 * A value as JavaScript would print it inside a concatenation.
	 *
	 * @param mixed $value The value.
	 * @return string
	 *
	 * @throws RuntimeException If it has no sensible text form.
	 */
	private static function text( mixed $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'null';
		}

		if ( is_float( $value ) && floor( $value ) === $value ) {
			return (string) (int) $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		throw new RuntimeException( 'The design concatenates something with no text form.' );
	}
}
