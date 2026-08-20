<?php
/**
 * The block vocabulary, held against the digest.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Blocks;

use DP\Theme\Blocks\Vocabulary;
use PHPUnit\Framework\TestCase;

/**
 * Guards docs/design-digest.md §5.1.
 *
 * The expected list is read out of the digest rather than copied into this
 * file. A second copy would be a second thing to keep in step, and the point of
 * the test is that there is only one list — the digest's, which is itself a
 * reading of design-source/components/PostBlocks.dc.html.
 */
final class VocabularyTest extends TestCase {

	/**
	 * The core blocks §5.1 names, read from the digest.
	 *
	 * @return list<string> Sorted, deduplicated block names.
	 */
	private function digest_vocabulary(): array {
		$path = dirname( __DIR__, 3 ) . '/docs/design-digest.md';

		$this->assertFileIsReadable( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$digest = (string) file_get_contents( $path );

		$start = strpos( $digest, '### 5.1' );
		$end   = strpos( $digest, '### 5.2' );

		$this->assertIsInt( $start, 'The digest has no §5.1 any more.' );
		$this->assertIsInt( $end, 'The digest has no §5.2 any more.' );

		$section = substr( $digest, $start, $end - $start );

		$this->assertSame( 1, preg_match( '/Mapping:/', $section ), 'The section read is not the block vocabulary.' );

		preg_match_all( '#\bcore/[a-z-]+#', $section, $matches );

		$names = array_values( array_unique( $matches[0] ) );

		sort( $names );

		return $names;
	}

	/**
	 * The vocabulary is the digest's, exactly.
	 *
	 * `core/list-item` is the one addition, and it is not a choice: `core/list`
	 * cannot hold anything else, so leaving it off would not shorten the list,
	 * it would break lists.
	 *
	 * @return void
	 */
	public function test_the_vocabulary_is_the_digest_s_list(): void {
		$implemented = Vocabulary::CORE_BLOCKS;

		sort( $implemented );

		$this->assertSame(
			$this->digest_vocabulary(),
			array_values( array_diff( $implemented, array( 'core/list-item' ) ) ),
			'The house style gained or lost a block. If that is deliberate, docs/design-digest.md §5.1 changes first.'
		);

		$this->assertContains( 'core/list-item', Vocabulary::CORE_BLOCKS );
	}

	/**
	 * `note` is not a core block, so it arrives by prefix.
	 *
	 * The theme never names `dp/callout`: naming it would be the theme asserting
	 * that a plugin is installed.
	 *
	 * @return void
	 */
	public function test_the_callout_is_admitted_by_prefix_not_by_name(): void {
		$this->assertNotContains( 'dp/callout', Vocabulary::CORE_BLOCKS );
		$this->assertTrue( Vocabulary::is_prefixed( 'dp/callout' ) );
		$this->assertTrue( Vocabulary::is_prefixed( 'dp/timeline' ) );
	}

	/**
	 * Stackable is admitted wholesale, and nothing else is.
	 *
	 * @return void
	 */
	public function test_only_our_own_blocks_and_stackable_are_admitted_by_prefix(): void {
		$this->assertCount( 2, Vocabulary::PREFIXES );

		$this->assertTrue( Vocabulary::is_prefixed( 'stackable/columns' ) );
		$this->assertFalse( Vocabulary::is_prefixed( 'core/buttons' ) );
		$this->assertFalse( Vocabulary::is_prefixed( 'jetpack/contact-form' ) );
		$this->assertFalse( Vocabulary::is_prefixed( 'notdp/callout' ) );
	}

	/**
	 * Nothing outside the vocabulary has its core styles taken away.
	 *
	 * @return void
	 */
	public function test_core_styles_are_only_stripped_from_blocks_the_house_styles(): void {
		foreach ( Vocabulary::STYLED_BY_THE_HOUSE as $name ) {
			$this->assertContains(
				$name,
				Vocabulary::CORE_BLOCKS,
				sprintf( '%s is not in the vocabulary, so the theme has no business removing its style variations.', $name )
			);
		}
	}
}
