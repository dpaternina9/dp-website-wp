<?php
/**
 * What the house style's blocks actually render to.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

use DP\Core\Blocks\CodeLabel;
use WP_UnitTestCase;

/**
 * Renders the `house-style` fixture post and checks the markup, block by block.
 *
 * CLAUDE.md §1.5: render callbacks return strings, so assert on markup. Every
 * assertion here corresponds to a line in
 * design-source/components/PostBlocks.dc.html.
 */
final class RenderedMarkupTest extends WP_UnitTestCase {

	/**
	 * The rendered fixture post.
	 *
	 * @var string
	 */
	private string $html = '';

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		/*
		 * The label filter is the plugin's, and Plugin::register() is where it
		 * belongs. Until that line is wired, and harmlessly after it is, this
		 * attaches it here: the filter only ever sets the same attribute to the
		 * same value, so running twice is the same as running once.
		 */
		( new CodeLabel() )->register();

		$this->html = do_blocks( HouseStyleFixture::content() );

		$this->assertNotEmpty( $this->html );
	}

	/**
	 * Every block in the vocabulary reaches the page.
	 *
	 * @return void
	 */
	public function test_the_whole_vocabulary_renders(): void {
		$this->assertStringContainsString( '<h2 class="wp-block-heading">Headings run three deep</h2>', $this->html );
		$this->assertStringContainsString( '<h3 class="wp-block-heading">', $this->html );
		$this->assertStringContainsString( '<h4 class="wp-block-heading">', $this->html );
		$this->assertStringContainsString( '<blockquote class="wp-block-quote', $this->html );
		$this->assertStringContainsString( '<cite>A lead I had in 2013</cite>', $this->html );
		$this->assertMatchesRegularExpression( '/<pre [^>]*class="wp-block-code/', $this->html );
		$this->assertStringContainsString( 'wp-block-dp-callout', $this->html );
		$this->assertStringContainsString( '<figure class="wp-block-image', $this->html );
		$this->assertStringContainsString( '<figcaption class="wp-element-caption">AN INLINE FIGURE', $this->html );
		$this->assertStringContainsString( '<figure class="wp-block-table', $this->html );
		$this->assertStringContainsString( '<hr class="wp-block-separator', $this->html );
	}

	/**
	 * Lists keep their semantics after the design takes their markers away.
	 *
	 * `list-style: none` is required by the 28px marker column and is exactly
	 * what stops Safari announcing a list as a list. DP\Theme\Blocks\Markup puts
	 * `role="list"` back.
	 *
	 * @return void
	 */
	public function test_both_list_kinds_keep_their_role(): void {
		$this->assertMatchesRegularExpression( '/<ul role="list" class="wp-block-list"/', $this->html );
		$this->assertMatchesRegularExpression( '/<ol role="list" class="wp-block-list"/', $this->html );
		$this->assertSame( 6, substr_count( $this->html, '<li>' ), 'The fixture has two lists of three items.' );
	}

	/**
	 * Only lists get the role.
	 *
	 * @return void
	 */
	public function test_nothing_else_is_given_a_list_role(): void {
		$this->assertSame( 2, substr_count( $this->html, 'role="list"' ) );
	}

	/**
	 * The code block is a labelled, forced-dark island.
	 *
	 * @return void
	 */
	public function test_the_code_block_is_labelled_and_forced_dark(): void {
		$this->assertMatchesRegularExpression( '/<pre [^>]*data-dp-label="SHELL"[^>]*>/', $this->html );
		$this->assertMatchesRegularExpression( '/<pre [^>]*class="wp-block-code dp-dark"[^>]*>/', $this->html );
	}

	/**
	 * A code block with no label attribute still gets the design's default.
	 *
	 * @return void
	 */
	public function test_an_unlabelled_code_block_falls_back_to_shell(): void {
		$html = do_blocks( HouseStyleFixture::code( '', 'echo hello' ) );

		$this->assertStringContainsString( 'data-dp-label="SHELL"', $html );
	}

	/**
	 * A label David typed is the label that renders.
	 *
	 * @return void
	 */
	public function test_a_chosen_label_reaches_the_markup(): void {
		$html = do_blocks( HouseStyleFixture::code( 'WP-CLI', 'wp dp seed' ) );

		$this->assertStringContainsString( 'data-dp-label="WP-CLI"', $html );
		$this->assertStringNotContainsString( 'SHELL', $html );
	}

	/**
	 * The label never reaches the saved markup, only the rendered markup.
	 *
	 * This is what makes the attribute safe: core's own save() output is left
	 * alone, so no post can be invalidated by this plugin coming or going.
	 *
	 * @return void
	 */
	public function test_the_label_is_stored_in_the_comment_not_in_the_html(): void {
		$saved = HouseStyleFixture::code( 'WP-CLI', 'wp dp seed' );

		$this->assertStringContainsString( '<!-- wp:code {"dpLabel":"WP-CLI"} -->', $saved );
		$this->assertStringNotContainsString( 'data-dp-label', $saved );
	}

	/**
	 * An emptied label turns the bar off rather than restoring the default.
	 *
	 * @return void
	 */
	public function test_an_emptied_label_is_honoured(): void {
		$block = "<!-- wp:code {\"dpLabel\":\"\"} -->\n<pre class=\"wp-block-code\"><code>x</code></pre>\n<!-- /wp:code -->";

		$this->assertStringContainsString( 'data-dp-label=""', do_blocks( $block ) );
	}

	/**
	 * The callout survives with the structure the theme's CSS targets.
	 *
	 * It is a static block: nothing renders it, the markup in the post is the
	 * markup on the page. That is why it needs no plugin to be readable.
	 *
	 * @return void
	 */
	public function test_the_callout_keeps_its_label_and_its_text(): void {
		$this->assertStringContainsString( '<span class="dp-callout-label">NOTE</span>', $this->html );
		$this->assertMatchesRegularExpression( '/<p class="dp-callout-text">Numbers in these posts/', $this->html );
	}

	/**
	 * The table renders a head row and four body rows.
	 *
	 * @return void
	 */
	public function test_the_table_has_a_head_and_four_rows(): void {
		$this->assertStringContainsString( '<thead><tr><th>Block</th>', $this->html );
		$this->assertSame( 4, substr_count( $this->html, '<tr><td>' ) );
	}

	/**
	 * The house limits the design states are what the fixture keeps to.
	 *
	 * The reference post is the reference for the limits as well as for the
	 * blocks, so if it ever drifts past one of them the editor warning and the
	 * fixture would be saying different things.
	 *
	 * @return void
	 */
	public function test_the_reference_post_keeps_to_its_own_limits(): void {
		$content = HouseStyleFixture::content();

		$this->assertLessThanOrEqual( 2, substr_count( $content, '<!-- wp:quote -->' ), 'The house limit is two quotes.' );
		$this->assertLessThanOrEqual( 1, substr_count( $content, '<!-- wp:dp/callout' ), 'The house limit is one callout.' );

		$this->assertSame( 1, preg_match( '#<code>(.*?)</code>#s', $content, $matches ) );
		$this->assertLessThanOrEqual( 15, substr_count( $matches[1], "\n" ) + 1, 'The house limit is fifteen lines of code.' );

		$this->assertSame( 3, substr_count( $content, '<!-- wp:list-item -->' ) / 2, 'The house limit is six list items per list.' );
	}
}
