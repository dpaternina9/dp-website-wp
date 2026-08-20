<?php
/**
 * The house style's block vocabulary.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

/**
 * The complete set of blocks a post may be written with.
 *
 * Transcribed from docs/design-digest.md §5.1, which takes it from
 * design-source/components/PostBlocks.dc.html and the `house-style` fixture
 * post:
 *
 *     p · h2 · h3 · h4 · quote(+cite) · ul · ol · code(+label) · note(+label)
 *     · image(+caption) · table{head,rows} · rule
 *
 * Three of those are not core blocks in their own right. `h2`, `h3` and `h4`
 * are all `core/heading`; `ul` and `ol` are both `core/list`; `note` is
 * `dp/callout`, which arrives through the `dp/` prefix below rather than by
 * name, so the theme never asserts that a plugin is installed.
 *
 * `core/list-item` is on the list because `core/list` cannot hold anything
 * else — leaving it off does not simplify the list, it breaks lists.
 *
 * DP\Tests\Unit\Blocks\VocabularyTest holds this against the digest.
 */
final class Vocabulary {

	/**
	 * The core blocks in the house style, by name.
	 *
	 * @var list<string>
	 */
	public const CORE_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/code',
		'core/image',
		'core/table',
		'core/separator',
	);

	/**
	 * Name prefixes admitted wholesale, whatever is registered under them.
	 *
	 * `dp/` is ours. `stackable/` is the one third-party library in play
	 * (CLAUDE.md §1.2) and is strictly additive: nothing here requires it, and
	 * if it is deactivated these names simply stop existing.
	 *
	 * @var list<string>
	 */
	public const PREFIXES = array(
		'dp/',
		'stackable/',
	);

	/**
	 * The blocks whose core style variations the house style removes.
	 *
	 * @var list<string>
	 */
	public const STYLED_BY_THE_HOUSE = array(
		'core/quote',
		'core/separator',
		'core/image',
		'core/table',
		'core/code',
		'core/list',
	);

	/**
	 * Whether a block name belongs to one of the admitted prefixes.
	 *
	 * @param string $name A block name.
	 * @return bool
	 */
	public static function is_prefixed( string $name ): bool {
		foreach ( self::PREFIXES as $prefix ) {
			if ( str_starts_with( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
