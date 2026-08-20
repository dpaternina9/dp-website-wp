<?php
/**
 * Fixture body copy, as block markup.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * Turns a fixture `body` array into `post_content` the block editor will open.
 *
 * The mapping is digest section 5.1's, and nothing here invents a block: `p` is
 * `core/paragraph`, `h2..h4` are `core/heading`, `ul`/`ol` are `core/list`,
 * `quote` is `core/quote`, `code` is `core/code`, `image` is `core/image`,
 * `table` is `core/table`, `rule` is `core/separator`, and `note` is the custom
 * `dp/callout`.
 *
 * The markup is written to be **byte-identical to what the block editor's own
 * serialiser produces**, because anything else opens as an invalid block and
 * greets David with a recovery prompt on the reference post. Two consequences
 * that are easy to get wrong and are pinned by tests:
 *
 * - An attribute equal to its declared default is **omitted** from the block
 *   comment, which is why a `SHELL` code label and a `NOTE` callout label are
 *   not written out but `WP-CLI` and `FOUND A MISTAKE?` are.
 * - Attributes with an HTML `source` — a caption, a callout body, table cells —
 *   live in the markup and never in the comment.
 *
 * Two blocks are seeded that Phase 3 does not own:
 *
 * - **`dp/callout`** is registered by Phase 4, whose `block.json` and `save.js`
 *   this mirrors. If that block's saved shape changes, re-run `wp dp seed`
 *   rather than editing content by hand.
 * - **`core/image` is seeded with no source.** The design ships no media; the
 *   figure slots are placeholders. A caption with no file is exactly that, and it
 *   is visibly unfinished in the editor, which is what CLAUDE.md asks placeholder
 *   content to be.
 */
final class BlockMarkup {

	/**
	 * The label a code block carries when nobody has set one.
	 *
	 * Declared as the attribute's default in `src/Blocks/js/house-style/code-label.js`,
	 * which is what makes the serialiser leave it out of the comment. Repeated
	 * here rather than imported so this file does not depend on a class the block
	 * layer may still be moving around.
	 *
	 * @var string
	 */
	private const DEFAULT_CODE_LABEL = 'SHELL';

	/**
	 * The label a callout carries when nobody has set one.
	 *
	 * Declared as the attribute's default in `src/Blocks/js/callout/block.json`.
	 *
	 * @var string
	 */
	private const DEFAULT_CALLOUT_LABEL = 'NOTE';

	/**
	 * Render a whole body.
	 *
	 * @param array<int, FixtureBlock> $blocks The body.
	 * @return string Block markup.
	 */
	public function render( array $blocks ): string {
		return implode( "\n\n", array_map( $this->block( ... ), $blocks ) );
	}

	/**
	 * Render one block.
	 *
	 * @param FixtureBlock $block The block.
	 * @return string
	 */
	private function block( FixtureBlock $block ): string {
		return match ( $block->kind ) {
			FixtureBlockKind::Paragraph  => $this->paragraph( $block->text ),
			FixtureBlockKind::Heading2   => $this->heading( 2, $block->text ),
			FixtureBlockKind::Heading3   => $this->heading( 3, $block->text ),
			FixtureBlockKind::Heading4   => $this->heading( 4, $block->text ),
			FixtureBlockKind::Quote      => $this->quote( $block->text, $block->cite ),
			FixtureBlockKind::BulletList => $this->list_block( $block->items, false ),
			FixtureBlockKind::NumberList => $this->list_block( $block->items, true ),
			FixtureBlockKind::Code       => $this->code( $block->text, $block->label ),
			FixtureBlockKind::Note       => $this->note( $block->text, $block->label ),
			FixtureBlockKind::Image      => $this->image( $block->label ),
			FixtureBlockKind::Table      => $this->table( $block->head, $block->rows ),
			FixtureBlockKind::Rule       => $this->rule(),
		};
	}

	/**
	 * `core/paragraph`.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private function paragraph( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>" . $this->text( $text ) . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * `core/heading`.
	 *
	 * @param int    $level The heading level, 2 to 4.
	 * @param string $text  The text.
	 * @return string
	 */
	private function heading( int $level, string $text ): string {
		$attributes = 2 === $level ? '' : ' ' . $this->attributes( array( 'level' => $level ) );

		return sprintf(
			"<!-- wp:heading%s -->\n<h%d class=\"wp-block-heading\">%s</h%d>\n<!-- /wp:heading -->",
			$attributes,
			$level,
			$this->text( $text ),
			$level
		);
	}

	/**
	 * `core/quote`, with the citation the design allows.
	 *
	 * @param string $text The quotation.
	 * @param string $cite The attribution, which is optional and stays quiet.
	 * @return string
	 */
	private function quote( string $text, string $cite ): string {
		$citation = '' === $cite ? '' : '<cite>' . $this->text( $cite ) . '</cite>';

		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>"
			. $this->text( $text )
			. "</p>\n<!-- /wp:paragraph -->"
			. $citation
			. "</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * `core/list`.
	 *
	 * @param array<int, string> $items   The items.
	 * @param bool               $ordered Whether the list is numbered.
	 * @return string
	 */
	private function list_block( array $items, bool $ordered ): string {
		$tag        = $ordered ? 'ol' : 'ul';
		$attributes = $ordered ? ' ' . $this->attributes( array( 'ordered' => true ) ) : '';

		$rendered = array_map(
			fn ( string $item ): string => "<!-- wp:list-item -->\n<li>" . $this->text( $item ) . "</li>\n<!-- /wp:list-item -->",
			$items
		);

		return sprintf(
			"<!-- wp:list%s -->\n<%s class=\"wp-block-list\">%s</%s>\n<!-- /wp:list -->",
			$attributes,
			$tag,
			implode( "\n\n", $rendered ),
			$tag
		);
	}

	/**
	 * `core/code`, carrying the design's label.
	 *
	 * The label is an attribute Phase 4 adds to `core/code` through the block
	 * registration filter. Seeded here so the labelled dark code block has
	 * something to label; if Phase 4 names the attribute differently, re-run the
	 * seed rather than editing content by hand.
	 *
	 * @param string $code  The code.
	 * @param string $label The mono caps label above it. Defaults to SHELL in the design.
	 * @return string
	 */
	private function code( string $code, string $label ): string {
		$attributes = '' === $label || self::DEFAULT_CODE_LABEL === $label
			? ''
			: ' ' . $this->attributes( array( 'dpLabel' => $label ) );

		return sprintf(
			"<!-- wp:code%s -->\n<pre class=\"wp-block-code\"><code>%s</code></pre>\n<!-- /wp:code -->",
			$attributes,
			$this->text( $code )
		);
	}

	/**
	 * `dp/callout` — the design's `note`.
	 *
	 * @param string $note  The caveat.
	 * @param string $label The mono caps label. Defaults to NOTE in the design.
	 * @return string
	 */
	private function note( string $note, string $label ): string {
		$label      = '' === $label ? self::DEFAULT_CALLOUT_LABEL : $label;
		$attributes = self::DEFAULT_CALLOUT_LABEL === $label
			? ''
			: ' ' . $this->attributes( array( 'label' => $label ) );

		return sprintf(
			"<!-- wp:dp/callout%s -->\n<div class=\"wp-block-dp-callout dp-callout\"><span class=\"dp-callout-label\">%s</span><p class=\"dp-callout-text\">%s</p></div>\n<!-- /wp:dp/callout -->",
			$attributes,
			$this->text( $label ),
			$this->text( $note )
		);
	}

	/**
	 * `core/image`, with a caption and no file behind it yet.
	 *
	 * @param string $caption The mono caps caption.
	 * @return string
	 */
	private function image( string $caption ): string {
		return sprintf(
			"<!-- wp:image -->\n<figure class=\"wp-block-image\"><img alt=\"\"/><figcaption class=\"wp-element-caption\">%s</figcaption></figure>\n<!-- /wp:image -->",
			$this->text( $caption )
		);
	}

	/**
	 * `core/table`.
	 *
	 * @param array<int, string>             $head Header cells.
	 * @param array<int, array<int, string>> $rows Body rows.
	 * @return string
	 */
	private function table( array $head, array $rows ): string {
		$header = '' === implode( '', $head )
			? ''
			: '<thead><tr>' . implode( '', array_map( fn ( string $cell ): string => '<th>' . $this->text( $cell ) . '</th>', $head ) ) . '</tr></thead>';

		$body = implode(
			'',
			array_map(
				fn ( array $row ): string => '<tr>' . implode( '', array_map( fn ( string $cell ): string => '<td>' . $this->text( $cell ) . '</td>', $row ) ) . '</tr>',
				$rows
			)
		);

		/*
		 * `hasFixedLayout` is stated rather than left to the default because that
		 * default has moved in core: writing both the attribute and the class it
		 * implies keeps the markup valid whichever way round the running version
		 * has it.
		 */
		return '<!-- wp:table ' . $this->attributes( array( 'hasFixedLayout' => true ) ) . " -->\n"
			. '<figure class="wp-block-table"><table class="has-fixed-layout">'
			. $header
			. '<tbody>' . $body . '</tbody>'
			. "</table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * `core/separator` — the spectrum rule.
	 *
	 * @return string
	 */
	private function rule(): string {
		return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
	}

	/**
	 * Encode a block's attributes.
	 *
	 * @param array<string, string|int|bool> $attributes The attributes.
	 * @return string JSON, as the block comment carries it.
	 */
	private function attributes( array $attributes ): string {
		$json = wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return false === $json ? '{}' : $json;
	}

	/**
	 * Escape a text node.
	 *
	 * `ENT_NOQUOTES`, not `esc_html()`: this is content being **stored**, not
	 * output. Escaping the apostrophes and quotation marks that this fixture is
	 * full of would put `&#039;` into David's editor and into every future diff
	 * of the reference post. `<`, `>` and `&` still have to go, because those are
	 * the three that would change the parse of the markup around them.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private function text( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
