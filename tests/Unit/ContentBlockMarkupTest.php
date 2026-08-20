<?php
/**
 * Unit tests for the fixture-to-block-markup conversion.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Core\Fixture\BlockMarkup;
use DP\Core\Fixture\FixtureBlock;
use DP\Core\Fixture\FixtureBlockKind;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The seeded markup has to be what the block editor would have written.
 *
 * Anything else opens as an invalid block, and the first post David would see it
 * on is the reference post whose entire purpose is to show the house style
 * working. `ContentSeedTest` proves the blocks parse inside a real WordPress;
 * this proves the strings, cheaply, where a failure points at one method.
 */
final class ContentBlockMarkupTest extends TestCase {

	/**
	 * The converter under test.
	 *
	 * @var BlockMarkup
	 */
	private BlockMarkup $markup;

	/**
	 * Start Brain Monkey and stand up the one WordPress function this uses.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Functions\when( 'wp_json_encode' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- standing in for it.
			static fn ( mixed $data, int $flags = 0 ): string|false => json_encode( $data, $flags )
		);

		$this->markup = new BlockMarkup();
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * A paragraph is a paragraph.
	 *
	 * @return void
	 */
	public function test_it_renders_a_paragraph(): void {
		$this->assertSame(
			"<!-- wp:paragraph -->\n<p>Text first.</p>\n<!-- /wp:paragraph -->",
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Paragraph, text: 'Text first.' ) ) )
		);
	}

	/**
	 * Level two carries no attributes; deeper levels carry the level.
	 *
	 * @return void
	 */
	public function test_it_renders_headings(): void {
		$rendered = $this->markup->render(
			array(
				new FixtureBlock( FixtureBlockKind::Heading2, text: 'Two' ),
				new FixtureBlock( FixtureBlockKind::Heading4, text: 'Four' ),
			)
		);

		$this->assertStringContainsString(
			"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Two</h2>\n<!-- /wp:heading -->",
			$rendered
		);
		$this->assertStringContainsString(
			"<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">Four</h4>\n<!-- /wp:heading -->",
			$rendered
		);
	}

	/**
	 * A citation sits inside the blockquote, after the paragraph.
	 *
	 * @return void
	 */
	public function test_it_renders_a_quote_with_a_citation(): void {
		$this->assertSame(
			"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>A line.</p>\n"
			. "<!-- /wp:paragraph --><cite>A lead I had in 2013</cite></blockquote>\n<!-- /wp:quote -->",
			$this->markup->render(
				array( new FixtureBlock( FixtureBlockKind::Quote, text: 'A line.', cite: 'A lead I had in 2013' ) )
			)
		);
	}

	/**
	 * A quote with no attribution has no empty `cite` element.
	 *
	 * @return void
	 */
	public function test_a_quote_without_a_citation_has_no_cite_element(): void {
		$this->assertStringNotContainsString(
			'<cite>',
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Quote, text: 'A line.' ) ) )
		);
	}

	/**
	 * Lists are `core/list` with `core/list-item` children.
	 *
	 * @return void
	 */
	public function test_it_renders_both_kinds_of_list(): void {
		$unordered = $this->markup->render(
			array( new FixtureBlock( FixtureBlockKind::BulletList, items: array( 'One', 'Two' ) ) )
		);

		$this->assertStringStartsWith( "<!-- wp:list -->\n<ul class=\"wp-block-list\">", $unordered );
		$this->assertStringContainsString( "<!-- wp:list-item -->\n<li>One</li>\n<!-- /wp:list-item -->", $unordered );
		$this->assertStringEndsWith( "</ul>\n<!-- /wp:list -->", $unordered );

		$ordered = $this->markup->render(
			array( new FixtureBlock( FixtureBlockKind::NumberList, items: array( 'One' ) ) )
		);

		$this->assertStringStartsWith( "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">", $ordered );
	}

	/**
	 * A code label equal to the default is left out of the comment.
	 *
	 * The block editor omits an attribute that matches its declared default. If
	 * we wrote it anyway the markup would still parse, but it would no longer be
	 * what the editor round-trips to, and the next save would produce a diff on a
	 * post nobody edited.
	 *
	 * @return void
	 */
	public function test_a_default_code_label_is_not_serialised(): void {
		$this->assertSame(
			"<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>$ ls</code></pre>\n<!-- /wp:code -->",
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Code, text: '$ ls', label: 'SHELL' ) ) )
		);
	}

	/**
	 * Any other code label is serialised.
	 *
	 * @return void
	 */
	public function test_a_custom_code_label_is_serialised(): void {
		$this->assertStringStartsWith(
			'<!-- wp:code {"dpLabel":"DEPLOY"} -->',
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Code, text: '$ git push', label: 'DEPLOY' ) ) )
		);
	}

	/**
	 * A callout matches the shape Phase 4's `save.js` produces.
	 *
	 * @return void
	 */
	public function test_it_renders_a_callout(): void {
		$this->assertSame(
			"<!-- wp:dp/callout -->\n<div class=\"wp-block-dp-callout dp-callout\">"
			. '<span class="dp-callout-label">NOTE</span>'
			. '<p class="dp-callout-text">A caveat.</p>'
			. "</div>\n<!-- /wp:dp/callout -->",
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Note, text: 'A caveat.', label: 'NOTE' ) ) )
		);

		$this->assertStringStartsWith(
			'<!-- wp:dp/callout {"label":"FOUND A MISTAKE?"} -->',
			$this->markup->render(
				array( new FixtureBlock( FixtureBlockKind::Note, text: 'Tell me.', label: 'FOUND A MISTAKE?' ) )
			)
		);
	}

	/**
	 * A figure keeps its caption and admits it has no file.
	 *
	 * @return void
	 */
	public function test_it_renders_a_sourceless_figure(): void {
		$this->assertSame(
			"<!-- wp:image -->\n<figure class=\"wp-block-image\"><img alt=\"\"/>"
			. '<figcaption class="wp-element-caption">THE DESK, AUGUST 2026</figcaption>'
			. "</figure>\n<!-- /wp:image -->",
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Image, label: 'THE DESK, AUGUST 2026' ) ) )
		);
	}

	/**
	 * A table states its layout rather than relying on a default that has moved.
	 *
	 * @return void
	 */
	public function test_it_renders_a_table(): void {
		$rendered = $this->markup->render(
			array(
				new FixtureBlock(
					FixtureBlockKind::Table,
					head: array( 'Block', 'Limit' ),
					rows: array( array( 'Quote', 'Two per post' ) )
				),
			)
		);

		$this->assertStringStartsWith( '<!-- wp:table {"hasFixedLayout":true} -->', $rendered );
		$this->assertStringContainsString( '<table class="has-fixed-layout">', $rendered );
		$this->assertStringContainsString( '<thead><tr><th>Block</th><th>Limit</th></tr></thead>', $rendered );
		$this->assertStringContainsString( '<tbody><tr><td>Quote</td><td>Two per post</td></tr></tbody>', $rendered );
	}

	/**
	 * The rule is a separator.
	 *
	 * @return void
	 */
	public function test_it_renders_a_rule(): void {
		$this->assertSame(
			"<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->",
			$this->markup->render( array( new FixtureBlock( FixtureBlockKind::Rule ) ) )
		);
	}

	/**
	 * Only the three characters that would change the parse are escaped.
	 *
	 * `esc_html()` would turn every apostrophe in this fixture into `&#039;`, in
	 * content that is about to be stored and later edited by a person. The
	 * quotation marks in "It's better done than perfect" are the reason this is
	 * asserted rather than assumed.
	 *
	 * @return void
	 */
	public function test_it_escapes_markup_and_leaves_punctuation_alone(): void {
		$rendered = $this->markup->render(
			array(
				new FixtureBlock(
					FixtureBlockKind::Paragraph,
					text: '"It\'s better done than perfect." Fish & chips <script>alert(1)</script>'
				),
			)
		);

		$this->assertStringContainsString( '"It\'s better done than perfect."', $rendered );
		$this->assertStringContainsString( 'Fish &amp; chips', $rendered );
		$this->assertStringContainsString( '&lt;script&gt;', $rendered );
		$this->assertStringNotContainsString( '<script>', $rendered );
		$this->assertStringNotContainsString( '&#039;', $rendered );
	}

	/**
	 * Blocks are separated by a blank line, as the serialiser separates them.
	 *
	 * @return void
	 */
	public function test_blocks_are_separated_by_a_blank_line(): void {
		$rendered = $this->markup->render(
			array(
				new FixtureBlock( FixtureBlockKind::Paragraph, text: 'One.' ),
				new FixtureBlock( FixtureBlockKind::Paragraph, text: 'Two.' ),
			)
		);

		$this->assertStringContainsString( "<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->", $rendered );
	}
}
