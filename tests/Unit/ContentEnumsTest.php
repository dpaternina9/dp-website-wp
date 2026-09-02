<?php
/**
 * Unit tests for the two closed vocabularies.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Core\Content\Timeline\BarKind;
use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * `docs/plan.md` Phase 3: "an Enum for video source and for tone, so tone is
 * never a loose string".
 *
 * The enums only earn that if the meta schemas are actually built from them, so
 * these tests assert the lists the schemas consume as well as the values.
 */
final class ContentEnumsTest extends TestCase {

	/**
	 * The five tones `SectionHead.dc.html` names, and no sixth.
	 *
	 * @return void
	 */
	public function test_the_tones_are_the_designs_five(): void {
		$this->assertSame(
			array( 'teal', 'pink', 'gold', 'purple', 'muted' ),
			Tone::values()
		);
	}

	/**
	 * A tone knows both of its custom properties, and they are not the same one.
	 *
	 * CLAUDE.md section 5: `--dp-*` hues are for fills, `--hue-*` for text,
	 * because a brand hue used directly as text fails AA. An enum that returned
	 * one colour would make that distinction impossible to keep.
	 *
	 * @return void
	 */
	public function test_a_tone_separates_text_from_fill(): void {
		$this->assertSame( 'var(--hue-teal)', Tone::Teal->text_variable() );
		$this->assertSame( 'var(--dp-teal)', Tone::Teal->fill_variable() );
		$this->assertSame( 'var(--hue-pink)', Tone::Pink->text_variable() );
		$this->assertSame( 'var(--dp-pink)', Tone::Pink->fill_variable() );

		$this->assertNotSame( Tone::Gold->text_variable(), Tone::Gold->fill_variable() );
	}

	/**
	 * Muted is a neutral, so it is the same property either way.
	 *
	 * @return void
	 */
	public function test_muted_is_the_same_property_either_way(): void {
		$this->assertSame( 'var(--text-muted)', Tone::Muted->text_variable() );
		$this->assertSame( 'var(--text-muted)', Tone::Muted->fill_variable() );
	}

	/**
	 * The stored vocabulary adds one value: "not set".
	 *
	 * @return void
	 */
	public function test_the_meta_vocabulary_allows_unset(): void {
		$this->assertSame( '', Tone::meta_values()[0] );
		$this->assertCount( count( Tone::values() ) + 1, Tone::meta_values() );
		$this->assertNull( Tone::try_from_meta( '' ) );
		$this->assertNull( Tone::try_from_meta( 'chartreuse' ) );
		$this->assertSame( Tone::Pink, Tone::try_from_meta( 'pink' ) );
	}

	/**
	 * The two platforms, stored lower case and printed in caps.
	 *
	 * @return void
	 */
	public function test_a_video_source_stores_data_and_prints_a_label(): void {
		$this->assertSame( array( 'twitch', 'youtube' ), VideoSource::values() );
		$this->assertSame( 'TWITCH', VideoSource::Twitch->label() );
		$this->assertSame( 'YOUTUBE', VideoSource::YouTube->label() );
	}

	/**
	 * The design's caps spelling is accepted on the way in.
	 *
	 * The fixture writes `source: 'TWITCH'`. A migration or a paste from the
	 * design should not have to know that we store it differently.
	 *
	 * @return void
	 */
	public function test_a_video_source_accepts_the_designs_spelling(): void {
		$this->assertSame( VideoSource::Twitch, VideoSource::try_from_meta( 'TWITCH' ) );
		$this->assertSame( VideoSource::YouTube, VideoSource::try_from_meta( 'YOUTUBE' ) );
		$this->assertSame( VideoSource::Twitch, VideoSource::try_from_meta( 'twitch' ) );
		$this->assertNull( VideoSource::try_from_meta( 'vimeo' ) );
		$this->assertNull( VideoSource::try_from_meta( '' ) );
	}

	/**
	 * A bar kind carries its floor and its colour.
	 *
	 * The floors are 10 and 8, not the design's 64 and 40: those were sized
	 * against a fixture with no sub-year role, and at the width the work page
	 * gives the track a 64px floor is about a year. See `BarKind::min_width()`.
	 *
	 * @return void
	 */
	public function test_a_bar_kind_carries_its_floor_and_its_colour(): void {
		$this->assertSame( 10, BarKind::Role->min_width() );
		$this->assertSame( 8, BarKind::Ship->min_width() );
		$this->assertSame( Tone::Teal, BarKind::Role->default_tone() );
		$this->assertSame( Tone::Gold, BarKind::Ship->default_tone() );
	}
}
