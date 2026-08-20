<?php
/**
 * The house style against the design that specifies it.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;

/**
 * Holds assets/css/blocks.css to design-source/components/PostBlocks.dc.html.
 *
 * The token-parity test (Phase 1) guards the values a token *has*. This guards
 * the values a block *is given*: the margins, the padding, the 28px marker
 * column, the 3/2 crop, the 420px table floor. None of those are tokens, so
 * nothing else was watching them.
 *
 * It checks both directions on purpose. Each row asserts that the design still
 * says what we transcribed **and** that the theme still carries it, so a
 * re-import that changes a margin fails here naming the block, rather than
 * being noticed on a page six weeks later.
 */
final class HouseStyleParityTest extends TestCase {

	/**
	 * Design declaration, and the rule in blocks.css that carries it.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const TRANSCRIBED = array(
		'h2 margin'           => array( 'margin: 48px 0 0', 'margin-block: 48px 0' ),
		'h3 margin'           => array( 'margin: 36px 0 0', 'margin-block: 36px 0' ),
		'h4 margin'           => array( 'margin: 32px 0 0', 'margin-block: 32px 0' ),
		'quote margin'        => array( 'margin: 32px 0', 'margin-block: 32px' ),
		'quote padding'       => array(
			'padding: clamp(20px, 4vw, 24px) clamp(20px, 5vw, 32px)',
			'padding: clamp(20px, 4vw, 24px) clamp(20px, 5vw, 32px)',
		),
		'quote rule'          => array(
			'border-left: var(--border-width-strong) solid var(--dp-teal)',
			'border-left: var(--border-width-strong) solid var(--dp-teal)',
		),
		'quote radius'        => array(
			'border-radius: 0 var(--radius-lg) var(--radius-lg) 0',
			'border-radius: 0 var(--radius-lg) var(--radius-lg) 0',
		),
		'quote surface'       => array( 'background: var(--bg-surface)', 'background: var(--bg-surface)' ),
		'cite treatment'      => array(
			'margin-top: 12px; font-family: var(--font-mono); font-size: var(--fs-xs); letter-spacing: var(--ls-caps); color: var(--text-muted)',
			'margin-top: 12px',
		),
		'list margin'         => array( 'margin: 20px 0 0', 'margin-block: 20px 0' ),
		'list marker column'  => array(
			'grid-template-columns: 28px minmax(0, 1fr); gap: 12px; align-items: baseline',
			'grid-template-columns: 28px minmax(0, 1fr)',
		),
		'list measure'        => array( 'max-width: var(--measure)', 'max-width: var(--measure)' ),
		'code margin'         => array( 'margin: 28px 0', 'margin-block: 28px' ),
		'code surface'        => array( 'background: var(--band)', 'background: var(--band)' ),
		'code radius'         => array( 'border-radius: var(--radius-lg)', 'border-radius: var(--radius-lg)' ),
		'code label bar'      => array( 'padding: 10px 20px', 'padding: 10px 20px' ),
		'code body'           => array( 'white-space: pre-wrap; overflow-wrap: anywhere', 'white-space: pre-wrap' ),
		'callout tint'        => array(
			'background: color-mix(in srgb, var(--dp-teal) 8%, var(--bg-surface))',
			'background: color-mix(in srgb, var(--dp-teal) 8%, var(--bg-surface))',
		),
		'callout border'      => array(
			'border: 1px solid color-mix(in srgb, var(--dp-teal) 30%, transparent)',
			'border: 1px solid color-mix(in srgb, var(--dp-teal) 30%, transparent)',
		),
		'callout gap'         => array( 'flex-direction: column; gap: 8px', 'gap: 8px' ),
		'image crop'          => array( 'aspect-ratio: 3 / 2', 'aspect-ratio: 3 / 2' ),
		'image radius'        => array( 'border-radius: var(--radius-md)', 'border-radius: var(--radius-md)' ),
		'table scroll floor'  => array( 'min-width: 420px', 'min-width: 420px' ),
		'table cell padding'  => array( 'padding: 14px 20px', 'padding: 14px 20px' ),
		'table row separator' => array(
			'border-top: 1px solid color-mix(in srgb, var(--border-subtle) 60%, transparent)',
			'border-top: 1px solid color-mix(in srgb, var(--border-subtle) 60%, transparent)',
		),
		'separator margin'    => array( 'margin: 44px 0', 'margin-block: 44px' ),
		'separator gradient'  => array(
			'background: var(--dp-gradient-spectrum); opacity: 0.6',
			'background: var(--dp-gradient-spectrum)',
		),
		'separator opacity'   => array( 'opacity: 0.6', 'opacity: 0.6' ),
		'paragraph measure'   => array( 'max-width: var(--measure)', 'max-width: var(--measure)' ),
		'paragraph wrap'      => array( 'text-wrap: pretty', 'text-wrap: pretty' ),
		'heading balanced'    => array( 'text-wrap: balance', 'text-wrap: balance' ),
	);

	/**
	 * Every transcribed value is still in the design and still in the theme.
	 *
	 * @return void
	 */
	public function test_every_transcribed_value_matches_the_design(): void {
		$design = $this->read( 'design-source/components/PostBlocks.dc.html' );
		$css    = $this->read( 'themes/dpaternina/assets/css/blocks.css' );

		$this->assertStringContainsString( 'BLOCK VOCABULARY', $design, 'PostBlocks.dc.html is not the file this test was written against.' );

		foreach ( self::TRANSCRIBED as $what => $pair ) {
			list( $declared, $carried ) = $pair;

			$this->assertStringContainsString(
				$declared,
				$design,
				sprintf( 'The design no longer declares the %s. Re-read PostBlocks.dc.html before changing this test.', $what )
			);

			$this->assertStringContainsString(
				$carried,
				$css,
				sprintf( 'The design declares the %s and blocks.css does not carry it.', $what )
			);
		}
	}

	/**
	 * The markers the design draws itself are drawn, not left to the browser.
	 *
	 * The digest, §5.1: an em dash for `ul`, a zero-padded index for `ol`, both
	 * mono at --fs-xs in --accent-text. `decimal-leading-zero` is what produces
	 * "01", "02" without a second list of literals.
	 *
	 * @return void
	 */
	public function test_list_markers_are_rendered_rather_than_native(): void {
		$css = $this->read( 'themes/dpaternina/assets/css/blocks.css' );

		$this->assertStringContainsString( 'list-style: none;', $css );
		$this->assertStringContainsString( 'content: "—";', $css );
		$this->assertStringContainsString( 'content: counter(dp-list-item, decimal-leading-zero);', $css );
		$this->assertStringContainsString( 'color: var(--accent-text);', $css );
	}

	/**
	 * The code block carries the design's default label with no plugin at all.
	 *
	 * @return void
	 */
	public function test_the_code_block_labels_itself_without_the_plugin(): void {
		$css = $this->read( 'themes/dpaternina/assets/css/blocks.css' );

		$this->assertStringContainsString( 'content: "SHELL";', $css );
		$this->assertStringContainsString( 'content: attr(data-dp-label);', $css );
	}

	/**
	 * Nothing in the theme's own CSS is written against WordPress's names.
	 *
	 * CLAUDE.md §5: "A --wp--preset--* name in a hand-written file is a smell."
	 *
	 * @return void
	 */
	public function test_the_house_stylesheet_uses_the_design_s_token_names(): void {
		$declarations = (string) preg_replace(
			'#/\*.*?\*/#s',
			'',
			$this->read( 'themes/dpaternina/assets/css/blocks.css' )
		);

		$this->assertStringNotContainsString( '--wp--preset--', $declarations );
		$this->assertStringNotContainsString( '--wp--custom--', $declarations );
		$this->assertStringContainsString( '--dp-teal', $declarations, 'Stripping the comments left nothing, so this proves nothing.' );
	}

	/**
	 * Read a file from the repository root.
	 *
	 * @param string $relative Path relative to the repository root.
	 * @return string
	 */
	private function read( string $relative ): string {
		$path = dirname( __DIR__, 3 ) . '/' . $relative;

		$this->assertFileIsReadable( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) file_get_contents( $path );
	}
}
