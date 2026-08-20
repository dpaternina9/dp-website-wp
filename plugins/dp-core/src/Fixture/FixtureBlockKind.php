<?php
/**
 * The house block vocabulary.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * Every block a post or page in this design is allowed to contain.
 *
 * Taken verbatim from the vocabulary note at the bottom of
 * `design-source/components/PostBlocks.dc.html`, including its `h` alias, which
 * several fixture posts use where `h2` is meant.
 *
 * The set is closed on purpose: digest section 5.1 is the house style, and a
 * fixture that could express a block the theme has no style for would be a
 * fixture that could not be trusted as a reference.
 */
enum FixtureBlockKind: string {

	case Paragraph  = 'p';
	case Heading2   = 'h2';
	case Heading3   = 'h3';
	case Heading4   = 'h4';
	case Quote      = 'quote';
	case BulletList = 'ul';
	case NumberList = 'ol';
	case Code       = 'code';
	case Note       = 'note';
	case Image      = 'image';
	case Table      = 'table';
	case Rule       = 'rule';
}
