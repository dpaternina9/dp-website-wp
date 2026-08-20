<?php
/**
 * The `house-style` fixture post, as block markup.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Blocks;

/**
 * The reference post from the design, in the form WordPress stores it.
 *
 * The design builds the same post in dpaternina.dc.html out of a `body` array
 * of plain objects. This is that array, block by block, in the same order, with
 * the copy carried across verbatim — it is placeholder copy and CLAUDE.md §6
 * says to keep it visibly so.
 *
 * It exists because "every block in the vocabulary, in one post" is exactly
 * what the render tests need, and because a fixture built from the design is
 * worth more than one built from what the code happens to emit.
 */
final class HouseStyleFixture {

	/**
	 * The post's block markup.
	 *
	 * @return string
	 */
	public static function content(): string {
		return implode(
			"\n\n",
			array(
				self::paragraph( 'The rule is that a post is text first. Everything below exists because a paragraph could not do the job — a quote carries someone else&#8217;s voice, a list carries a set, code carries an exact string. If a block is decoration, it is not in the system.' ),
				self::heading( 2, 'Headings run three deep' ),
				self::paragraph( 'Level two splits a post into parts. It is the only heading most posts need, and it sits far enough from body copy that you can scan for it.' ),
				self::heading( 3, 'Level three groups within a part' ),
				self::paragraph( 'Same face, smaller, tighter to the paragraph it introduces. Use it when a section has two or three distinct moves.' ),
				self::heading( 4, 'Level four is for reference material' ),
				self::paragraph( 'Specs, options, parameters. If a post needs a fourth level in prose, the post wants splitting instead.' ),
				self::heading( 2, 'Lists' ),
				self::paragraph( 'Unordered for sets where order carries no meaning:' ),
				self::list_block(
					false,
					array(
						'One idea per item, no trailing punctuation.',
						'Sentence case, same as everything else.',
						'Three to six items — longer than that is a table.',
					)
				),
				self::paragraph( 'Numbered only for genuine sequences:' ),
				self::list_block(
					true,
					array(
						'Write the thing badly and completely.',
						'Cut every sentence that repeats the one before it.',
						'Read it aloud once; fix whatever makes you stumble.',
					)
				),
				self::heading( 2, 'Quotes' ),
				self::paragraph( 'A pull quote is for a line worth stopping on — someone else&#8217;s words, or my own if they earn the space. Attribution is optional and stays quiet.' ),
				self::quote(
					'&#8220;It&#8217;s better done than perfect.&#8221; I&#8217;ll be damned if he wasn&#8217;t right.',
					'A lead I had in 2013'
				),
				self::heading( 2, 'Code' ),
				self::paragraph( 'Monospaced, labelled, and short enough to read in place. Anything longer than about fifteen lines goes in a repo and gets a link.' ),
				self::code(
					'SHELL',
					"$ npm run build --silent\n\n  bundle   142 kB → 61 kB gzip\n  css       18 kB →  4 kB gzip\n  done in 3.1s"
				),
				self::heading( 2, 'Callouts' ),
				self::paragraph( 'One callout per post, maximum. It is for a caveat the reader will hit in practice, not for emphasis I failed to write into the sentence.' ),
				self::callout( 'NOTE', 'Numbers in these posts are from my own projects unless a source is linked. When I do not have the figure, I say so instead of estimating.' ),
				self::heading( 2, 'Images' ),
				self::paragraph( 'Inline images sit full column width, with a mono caption underneath. Photographs get room to breathe; screenshots get cropped to the part that matters.' ),
				self::image( 'AN INLINE FIGURE — SAME WIDTH AS THE COLUMN' ),
				self::heading( 2, 'Tables' ),
				self::paragraph( 'For comparisons where the reader wants to look something up rather than read a paragraph about it.' ),
				self::table(),
				self::separator(),
				self::paragraph( 'That is the whole kit. If a post wants something that is not here, the something gets designed properly and added to this page — not improvised once and forgotten.' ),
			)
		);
	}

	/**
	 * A paragraph block.
	 *
	 * @param string $text The paragraph's text.
	 * @return string
	 */
	public static function paragraph( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * A heading block.
	 *
	 * @param int    $level The heading level, 2 to 4.
	 * @param string $text  The heading's text.
	 * @return string
	 */
	public static function heading( int $level, string $text ): string {
		$attributes = 2 === $level ? '' : ' {"level":' . $level . '}';

		return "<!-- wp:heading{$attributes} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->";
	}

	/**
	 * A list block.
	 *
	 * @param bool     $ordered Whether the list is ordered.
	 * @param string[] $items   The item text.
	 * @return string
	 */
	public static function list_block( bool $ordered, array $items ): string {
		$tag        = $ordered ? 'ol' : 'ul';
		$attributes = $ordered ? ' {"ordered":true}' : '';
		$inner      = '';

		foreach ( $items as $item ) {
			$inner .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->\n";
		}

		return "<!-- wp:list{$attributes} -->\n<{$tag} class=\"wp-block-list\">{$inner}</{$tag}>\n<!-- /wp:list -->";
	}

	/**
	 * A quote block.
	 *
	 * @param string $text The quoted line.
	 * @param string $cite The attribution, or an empty string for none.
	 * @return string
	 */
	public static function quote( string $text, string $cite = '' ): string {
		$attribution = '' === $cite ? '' : "<cite>{$cite}</cite>";

		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->{$attribution}</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * A code block, with the label the design gives it.
	 *
	 * The label is in the block comment and not in the markup, which is the
	 * whole point of DP\Core\Blocks\CodeLabel.
	 *
	 * @param string $label The label, or an empty string to leave the attribute off entirely.
	 * @param string $code  The code.
	 * @return string
	 */
	public static function code( string $label, string $code ): string {
		$attributes = '' === $label ? '' : ' ' . (string) wp_json_encode( array( 'dpLabel' => $label ) );

		return "<!-- wp:code{$attributes} -->\n<pre class=\"wp-block-code\"><code>" . esc_html( $code ) . "</code></pre>\n<!-- /wp:code -->";
	}

	/**
	 * A callout block.
	 *
	 * @param string $label The label.
	 * @param string $text  The callout's text.
	 * @return string
	 */
	public static function callout( string $label, string $text ): string {
		$attributes = (string) wp_json_encode( array( 'label' => $label ) );

		return "<!-- wp:dp/callout {$attributes} -->\n"
			. '<div class="wp-block-dp-callout dp-callout">'
			. "<span class=\"dp-callout-label\">{$label}</span>"
			. "<p class=\"dp-callout-text\">{$text}</p>"
			. "</div>\n<!-- /wp:dp/callout -->";
	}

	/**
	 * An image block with a caption.
	 *
	 * @param string $caption The caption.
	 * @return string
	 */
	public static function image( string $caption ): string {
		$src = 'https://example.invalid/inline-figure.avif';

		return "<!-- wp:image -->\n"
			. '<figure class="wp-block-image size-large">'
			. '<img src="' . $src . '" alt=""/>'
			. "<figcaption class=\"wp-element-caption\">{$caption}</figcaption>"
			. "</figure>\n<!-- /wp:image -->";
	}

	/**
	 * The table from the fixture post.
	 *
	 * @return string
	 */
	public static function table(): string {
		$rows = array(
			array( 'Quote', 'A line in someone else&#8217;s voice', 'Two per post' ),
			array( 'List', 'A set or a sequence', 'Six items' ),
			array( 'Code', 'An exact command or snippet', 'Fifteen lines' ),
			array( 'Callout', 'A caveat you will actually hit', 'One per post' ),
		);

		$body = '';

		foreach ( $rows as $row ) {
			$body .= '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>';
		}

		return "<!-- wp:table -->\n"
			. '<figure class="wp-block-table"><table>'
			. '<thead><tr><th>Block</th><th>Use it for</th><th>Limit</th></tr></thead>'
			. "<tbody>{$body}</tbody>"
			. "</table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * A separator block.
	 *
	 * @return string
	 */
	public static function separator(): string {
		return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
	}
}
