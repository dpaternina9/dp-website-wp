<?php
/**
 * The type and colour theme.json gives the house style.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use WP_UnitTestCase;

/**
 * Asserts against the CSS WordPress actually emits, not against theme.json.
 *
 * Same reasoning as docs/adr/0002 §5: theme.json is an input, and reading it
 * back proves only that the file says what the file says. What matters is the
 * declaration that reaches the browser, at the specificity it reaches it, in
 * the order it reaches it — all three of which are core's decisions, not ours.
 */
final class HouseStyleTest extends WP_UnitTestCase {

	/**
	 * The theme's global styles, as CSS.
	 *
	 * @var string
	 */
	private string $css = '';

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->css = wp_get_global_stylesheet( array( 'styles' ) );

		$this->assertNotEmpty( $this->css, 'No global styles were generated, so nothing below proves anything.' );
	}

	/**
	 * Level four is mono caps in the accent colour, not the display face.
	 *
	 * The first of the two traps digest §5.1 names. `styles.elements.heading`
	 * sets the display face on every level, so this only holds because core
	 * emits the level-specific rule after the shared one — which is asserted
	 * separately below rather than assumed.
	 *
	 * @return void
	 */
	public function test_h4_is_mono_caps_in_the_accent_colour(): void {
		$rule = $this->rule_for( 'h4' );

		$this->assertStringContainsString( 'font-family: var(--wp--preset--font-family--font-mono)', $rule );
		$this->assertStringContainsString( 'font-size: var(--wp--preset--font-size--fs-xs)', $rule );
		$this->assertStringContainsString( 'letter-spacing: var(--wp--custom--ls-caps)', $rule );
		$this->assertStringContainsString( 'text-transform: uppercase', $rule );
		$this->assertStringContainsString( 'color: var(--wp--custom--accent-text)', $rule );
		$this->assertStringNotContainsString( 'font-display', $rule );
	}

	/**
	 * The level rule wins over the shared heading rule.
	 *
	 * Both are a single element of specificity, so this is entirely a question
	 * of source order. If core ever emits them the other way round, h4 silently
	 * becomes a display-face heading — which is the failure this catches.
	 *
	 * @return void
	 */
	public function test_the_level_rules_are_emitted_after_the_shared_heading_rule(): void {
		$shared = strpos( $this->css, 'h1, h2, h3, h4, h5, h6{' );
		$level  = strpos( $this->css, 'h4{' );

		$this->assertIsInt( $shared, 'The shared heading rule is not in the stylesheet at all.' );
		$this->assertIsInt( $level, 'The h4 rule is not in the stylesheet at all.' );
		$this->assertGreaterThan( $shared, $level, 'h4 is emitted before the rule it has to override.' );
	}

	/**
	 * Levels two and three keep the display face, at the design's sizes.
	 *
	 * @return void
	 */
	public function test_the_other_two_levels_are_the_display_face_at_the_design_s_sizes(): void {
		$this->assertStringContainsString( 'font-size: var(--wp--preset--font-size--fs-xl)', $this->rule_for( 'h2' ) );
		$this->assertStringContainsString( 'line-height: var(--wp--custom--lh-snug)', $this->rule_for( 'h2' ) );
		$this->assertStringContainsString( 'letter-spacing: var(--wp--custom--ls-display)', $this->rule_for( 'h2' ) );

		$this->assertStringContainsString( 'font-size: var(--wp--preset--font-size--fs-md)', $this->rule_for( 'h3' ) );
		$this->assertStringContainsString( 'letter-spacing: var(--wp--custom--ls-tight)', $this->rule_for( 'h3' ) );

		$shared = $this->rule_for( 'h1, h2, h3, h4, h5, h6' );

		$this->assertStringContainsString( 'font-family: var(--wp--preset--font-family--font-display)', $shared );
	}

	/**
	 * Body copy is 16/1.65 in the secondary text colour.
	 *
	 * @return void
	 */
	public function test_body_copy_carries_the_design_s_type(): void {
		$paragraph = $this->rule_for( ':root :where(p)' );

		$this->assertStringContainsString( 'font-size: var(--wp--preset--font-size--fs-base)', $paragraph );
		$this->assertStringContainsString( 'line-height: var(--wp--custom--lh-relaxed)', $paragraph );
		$this->assertStringContainsString( 'color: var(--wp--preset--color--text-secondary)', $paragraph );
	}

	/**
	 * Code is mono at 14/1.65 in the accent colour.
	 *
	 * @return void
	 */
	public function test_code_is_mono_in_the_accent_colour(): void {
		$code = $this->rule_for( ':root :where(.wp-block-code)' );

		$this->assertStringContainsString( 'font-family: var(--wp--preset--font-family--font-mono)', $code );
		$this->assertStringContainsString( 'font-size: var(--wp--preset--font-size--fs-sm)', $code );
		$this->assertStringContainsString( 'color: var(--wp--custom--accent-text)', $code );
	}

	/**
	 * Captions are mono caps, muted — the figure caption from the design.
	 *
	 * @return void
	 */
	public function test_captions_are_mono_caps(): void {
		$this->assertMatchesRegularExpression(
			'/:root :where\(\.wp-element-caption[^)]*\)\{[^}]*font-family: var\(--wp--preset--font-family--font-mono\)/',
			$this->css
		);
		$this->assertMatchesRegularExpression(
			'/:root :where\(\.wp-element-caption[^)]*\)\{[^}]*letter-spacing: var\(--wp--custom--ls-caps\)/',
			$this->css
		);
	}

	/**
	 * The rhythm is not in theme.json, and that is on purpose.
	 *
	 * Every margin in the design lives in assets/css/blocks.css instead, because
	 * `styles.blocks` output lands at the same specificity as core's flow-layout
	 * gap and would be decided by source order. Asserting the absence keeps the
	 * decision from being half-undone later. See docs/adr/0005.
	 *
	 * @return void
	 */
	public function test_the_block_gap_carries_the_paragraph_rhythm(): void {
		$this->assertSame(
			'var(--wp--preset--spacing--space-5)',
			wp_get_global_styles( array( 'spacing', 'blockGap' ) ),
			'The design puts 24px between paragraphs and --space-5 is 24px; if blockGap moves, every paragraph moves with it.'
		);

		$this->assertStringNotContainsString(
			'margin-top: 48px',
			$this->css,
			'A heading margin has been added to theme.json. The house rhythm belongs in blocks.css — docs/adr/0005.'
		);
	}

	/**
	 * The declarations of one rule, by its exact selector.
	 *
	 * @param string $selector The selector to look for.
	 * @return string The rule's declarations.
	 */
	private function rule_for( string $selector ): string {
		$position = strpos( $this->css, $selector . '{' );

		$this->assertIsInt( $position, sprintf( 'No rule for "%s" was emitted at all.', $selector ) );

		$start = $position + strlen( $selector ) + 1;
		$end   = strpos( $this->css, '}', $start );

		$this->assertIsInt( $end );

		return substr( $this->css, $start, $end - $start );
	}
}
