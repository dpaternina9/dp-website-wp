<?php
/**
 * Unit tests for the live card's precedence rule.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;
use DP\Core\Watch\LiveEntry;
use DP\Core\Watch\LiveStream;
use DP\Core\Watch\Video;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Whose words end up on the live card.
 *
 * The rule under test is one sentence — a derivation fills a blank, and where a
 * value is present the author's value wins — applied field by field rather than
 * post by post, so a `dp_live` post carrying only a note keeps that note and
 * takes everything else from Twitch. It is asserted here rather than only
 * through a rendered block because the branch that matters is small, exhaustive,
 * and would otherwise be checked through several hundred characters of HTML.
 *
 * The second thing asserted is the tick contract: a strapline David wrote is
 * never marked for the client-side clock to rewrite.
 */
final class LiveEntryTest extends TestCase {

	/**
	 * A start instant the elapsed arithmetic is measured from.
	 *
	 * @var int
	 */
	private const STARTED = 1756468800;

	/**
	 * Start Brain Monkey and stand up the one WordPress function this needs.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		Monkey\setUp();

		Functions\when( '__' )->returnArg();
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
	 * What Twitch is reporting.
	 *
	 * @return LiveStream
	 */
	private function stream(): LiveStream {
		return new LiveStream(
			'Building the Kiveo reading-stats screen, live',
			self::STARTED,
			'Software and Game Development'
		);
	}

	/**
	 * A `dp_live` post with the fields named filled in and the rest blank.
	 *
	 * @param string $title The post title.
	 * @param string $note  `dp_note`.
	 * @param string $meta  `dp_live_meta`.
	 * @return Video
	 */
	private function authored( string $title = '', string $note = '', string $meta = '' ): Video {
		return new Video( 7, $title, VideoSource::Twitch, '', Tone::Gold, '', '', $note, true, $meta );
	}

	/**
	 * With no post at all, every word on the card is Twitch's.
	 *
	 * @return void
	 */
	public function test_with_no_post_the_whole_card_comes_from_twitch(): void {
		$live = LiveEntry::compose( null, $this->stream(), self::STARTED + ( 72 * 60 ) );

		$this->assertSame( 'Building the Kiveo reading-stats screen, live', $live->entry->title );
		$this->assertSame( 'Software and Game Development', $live->entry->note );
		$this->assertSame( 'Streaming now · 1H 12M in', $live->entry->live_meta );
		$this->assertTrue( $live->entry->live );
		$this->assertSame( 0, $live->entry->id, 'The composed entry is not a post.' );
		$this->assertSame( Tone::Pink, $live->entry->tone, 'Pink is the design\'s live hue.' );
	}

	/**
	 * A post David wrote wins, field by field: what he filled in is his, what
	 * he left blank is still Twitch's.
	 *
	 * @return void
	 */
	public function test_the_author_wins_field_by_field(): void {
		$live = LiveEntry::compose(
			$this->authored( note: 'SwiftUI charts, and my embarrassing reading data.' ),
			$this->stream(),
			self::STARTED + ( 72 * 60 )
		);

		$this->assertSame( 'SwiftUI charts, and my embarrassing reading data.', $live->entry->note );
		$this->assertSame(
			'Building the Kiveo reading-stats screen, live',
			$live->entry->title,
			'A blank he did not fill is the derivation\'s to fill.'
		);
		$this->assertSame( 'Streaming now · 1H 12M in', $live->entry->live_meta );
		$this->assertSame( Tone::Gold, $live->entry->tone, 'A hue he chose is his.' );
	}

	/**
	 * A derived strapline is marked for the client clock; one David wrote is not.
	 *
	 * @return void
	 */
	public function test_only_a_derived_strapline_ticks(): void {
		$derived = LiveEntry::compose( null, $this->stream(), self::STARTED + 60 );

		$this->assertSame( self::STARTED, $derived->since );
		$this->assertSame( 'Streaming now · %s in', $derived->format );

		$his = LiveEntry::compose(
			$this->authored( meta: 'STREAMING NOW · 1H 12M IN' ),
			$this->stream(),
			self::STARTED + 60
		);

		$this->assertSame( 'STREAMING NOW · 1H 12M IN', $his->entry->live_meta );
		$this->assertSame( 0, $his->since, 'Nothing may invite the script to rewrite what he typed.' );
		$this->assertSame( '', $his->format );
	}

	/**
	 * A stream Twitch will not date reads "Streaming now" with no elapsed
	 * clause, and carries nothing for the clock to count from.
	 *
	 * @return void
	 */
	public function test_an_undated_stream_says_only_that_it_is_live(): void {
		$live = LiveEntry::compose( null, new LiveStream( 'Untitled but on air', 0, '' ), self::STARTED );

		$this->assertSame( 'Streaming now', $live->entry->live_meta );
		$this->assertSame( 0, $live->since );
		$this->assertSame( '', $live->entry->note );
	}

	/**
	 * A live stream with no title still renders, under a label rather than
	 * under invented prose about what David is doing.
	 *
	 * @return void
	 */
	public function test_a_titleless_stream_falls_back_to_a_label(): void {
		$live = LiveEntry::compose( null, new LiveStream( '', self::STARTED, '' ), self::STARTED + 60 );

		$this->assertSame( 'Live now', $live->entry->title );
	}
}
