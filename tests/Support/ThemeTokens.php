<?php
/**
 * The theme's `theme.json`, read as a token table.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use RuntimeException;

/**
 * Answers, for a design token name, where `theme.json` put it.
 *
 * The lookup key is the design token's own name without its leading `--`, so
 * `--dp-teal` is found at the palette slug `dp-teal` and `--radius-xs` at the
 * `settings.custom` key `radius-xs`. One name, one home: a token found in two
 * sections, or in none, is an error rather than a guess.
 */
final class ThemeTokens {

	/**
	 * Where a preset lives, what its value is called, and the variable prefix
	 * WordPress generates for it.
	 *
	 * @var array<string, array{path: list<string>, value: string, prefix: string}>
	 */
	private const PRESETS = array(
		'color'       => array(
			'path'   => array( 'settings', 'color', 'palette' ),
			'value'  => 'color',
			'prefix' => '--wp--preset--color--',
		),
		'gradient'    => array(
			'path'   => array( 'settings', 'color', 'gradients' ),
			'value'  => 'gradient',
			'prefix' => '--wp--preset--gradient--',
		),
		'font-family' => array(
			'path'   => array( 'settings', 'typography', 'fontFamilies' ),
			'value'  => 'fontFamily',
			'prefix' => '--wp--preset--font-family--',
		),
		'font-size'   => array(
			'path'   => array( 'settings', 'typography', 'fontSizes' ),
			'value'  => 'size',
			'prefix' => '--wp--preset--font-size--',
		),
		'spacing'     => array(
			'path'   => array( 'settings', 'spacing', 'spacingSizes' ),
			'value'  => 'size',
			'prefix' => '--wp--preset--spacing--',
		),
	);

	/**
	 * Design token name (without `--`) to the WordPress variable that carries it.
	 *
	 * @var array<string, string>
	 */
	private array $variables = array();

	/**
	 * Design token name (without `--`) to the literal value in `theme.json`.
	 *
	 * @var array<string, string>
	 */
	private array $values = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $data Decoded `theme.json`.
	 *
	 * @throws RuntimeException If one name is claimed by two sections.
	 */
	private function __construct( private readonly array $data ) {
		foreach ( self::PRESETS as $kind => $preset ) {
			foreach ( $this->preset_entries( $preset['path'] ) as $entry ) {
				$slug = is_string( $entry['slug'] ?? null ) ? $entry['slug'] : '';
				$raw  = $entry[ $preset['value'] ] ?? null;

				if ( '' === $slug || ! is_string( $raw ) ) {
					throw new RuntimeException(
						sprintf(
							'theme.json has a %s preset with no usable slug or no "%s" value.',
							$kind,
							$preset['value']
						)
					);
				}

				$this->claim( $slug, $preset['prefix'] . $slug, $raw );
			}
		}

		$settings = $this->data['settings'] ?? null;
		$custom   = is_array( $settings ) ? ( $settings['custom'] ?? null ) : null;

		if ( is_array( $custom ) ) {
			foreach ( $custom as $key => $value ) {
				if ( is_string( $key ) && is_string( $value ) ) {
					$this->claim( $key, '--wp--custom--' . $key, $value );
				}
			}
		}
	}

	/**
	 * Read the theme's `theme.json`.
	 *
	 * @param string $path Absolute path to `theme.json`.
	 * @return self
	 *
	 * @throws RuntimeException If the file is missing or is not valid JSON.
	 */
	public static function from_file( string $path ): self {
		// Read from disk before WordPress exists; see DesignTokens::read().
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $json ) {
			throw new RuntimeException( sprintf( 'Cannot read %s.', $path ) );
		}

		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( sprintf( '%s is not valid JSON.', $path ) );
		}

		$data = array();

		foreach ( $decoded as $key => $value ) {
			if ( is_string( $key ) ) {
				$data[ $key ] = $value;
			}
		}

		return new self( $data );
	}

	/**
	 * The WordPress custom property that carries a design token.
	 *
	 * @param string $token Design token name, with or without the leading `--`.
	 * @return string|null The generated variable name, or null if `theme.json` does not carry the token.
	 */
	public function variable_for( string $token ): ?string {
		return $this->variables[ $this->slug_for( $token ) ] ?? null;
	}

	/**
	 * The literal value `theme.json` records for a design token.
	 *
	 * @param string $token Design token name, with or without the leading `--`.
	 * @return string|null
	 */
	public function value_for( string $token ): ?string {
		return $this->values[ $this->slug_for( $token ) ] ?? null;
	}

	/**
	 * The `theme.json` slug that carries a design token name.
	 *
	 * Every slug in `theme.json` is written exactly as the design names its
	 * token, with one exception that WordPress forces. Core kebab-cases each
	 * slug on its way into a custom property, and its word splitter separates a
	 * run of digits from the letters that follow it: a slug of `fs-2xl` is
	 * emitted as `--wp--preset--font-size--fs-2-xl`. So `theme.json` writes the
	 * separated form — the file says what WordPress will actually emit — and
	 * this fallback closes the gap back to the design's `--fs-2xl`.
	 *
	 * Four tokens are affected and no others: `--fs-2xl` through `--fs-5xl`.
	 * `--space-10` and `--dp-teal-600` end in digits and are untouched.
	 *
	 * This is deliberately one rule rather than a copy of core's word splitter.
	 * TokenParityTest resolves every token through the stylesheet WordPress
	 * really generates, so if core's casing ever changes the test fails naming
	 * the token and both values, instead of a mirror silently drifting.
	 *
	 * @param string $token Design token name, with or without the leading `--`.
	 * @return string
	 */
	private function slug_for( string $token ): string {
		$name = ltrim( $token, '-' );

		if ( isset( $this->variables[ $name ] ) ) {
			return $name;
		}

		return (string) preg_replace( '~(\\d)(?=[a-z])~', '$1-', $name );
	}

	/**
	 * A value from anywhere in `theme.json`, by path.
	 *
	 * @param string ...$path Successive keys, e.g. `settings`, `layout`, `contentSize`.
	 * @return string|null The value if it is a string, otherwise null.
	 */
	public function at( string ...$path ): ?string {
		$node = $this->data;

		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return null;
			}

			$node = $node[ $key ];
		}

		return is_string( $node ) ? $node : null;
	}

	/**
	 * Record one token, refusing a second claim on the same name.
	 *
	 * @param string $slug     Design token name without the leading `--`.
	 * @param string $variable The WordPress custom property that will carry it.
	 * @param string $value    The literal value in `theme.json`.
	 * @return void
	 *
	 * @throws RuntimeException If the name is already claimed.
	 */
	private function claim( string $slug, string $variable, string $value ): void {
		if ( isset( $this->variables[ $slug ] ) ) {
			throw new RuntimeException(
				sprintf(
					'theme.json claims the name "%s" twice: as %s and as %s. A design token has exactly one home.',
					$slug,
					$this->variables[ $slug ],
					$variable
				)
			);
		}

		$this->variables[ $slug ] = $variable;
		$this->values[ $slug ]    = $value;
	}

	/**
	 * The entries of one preset list.
	 *
	 * @param array<int, string> $path Path into the decoded `theme.json`.
	 * @return list<array<string, mixed>>
	 */
	private function preset_entries( array $path ): array {
		$node = $this->data;

		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return array();
			}

			$node = $node[ $key ];
		}

		if ( ! is_array( $node ) ) {
			return array();
		}

		$entries = array();

		foreach ( $node as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$shape = array();

			foreach ( $entry as $key => $value ) {
				if ( is_string( $key ) ) {
					$shape[ $key ] = $value;
				}
			}

			$entries[] = $shape;
		}

		return $entries;
	}
}
