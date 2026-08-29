<?php
/**
 * Unit tests for the Watch entry value object.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;
use DP\Core\Watch\Video;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The no-JavaScript half of click-to-play: the URLs a card is without a script.
 *
 * The value object is constructible without WordPress on purpose, so the URL
 * contract — host page per platform, channel page for the live entry, nothing
 * at all for an entry with no identifier — is held here, where a wrong URL
 * names the platform it is wrong for.
 */
final class VideoTest extends TestCase {

	/**
	 * Build one entry with defaults worth overriding.
	 *
	 * @param VideoSource|null $source Where it is hosted.
	 * @param string           $ref    The platform identifier.
	 * @param bool             $live   Whether it is the live entry.
	 * @return Video
	 */
	private function video( ?VideoSource $source, string $ref, bool $live = false ): Video {
		return new Video( 7, 'A title', $source, $ref, Tone::Purple, '2H 41M', 'AUG 2026', 'A note.', $live, '' );
	}

	/**
	 * Each platform links to the video on its own host.
	 *
	 * @return void
	 */
	public function test_watch_urls_point_at_the_video_on_its_host(): void {
		$this->assertSame(
			'https://www.twitch.tv/videos/335921245',
			$this->video( VideoSource::Twitch, '335921245' )->watch_url( 'patsypatz' )
		);
		$this->assertSame(
			'https://www.youtube.com/watch?v=abc-123_XYZ',
			$this->video( VideoSource::YouTube, 'abc-123_XYZ' )->watch_url( 'patsypatz' )
		);
	}

	/**
	 * The live entry links to the channel, and only when one is configured.
	 *
	 * @return void
	 */
	public function test_the_live_entry_links_to_the_channel(): void {
		$live = $this->video( VideoSource::Twitch, '', true );

		$this->assertSame( 'https://www.twitch.tv/patsypatz', $live->watch_url( 'patsypatz' ) );
		$this->assertSame( '', $live->watch_url( '' ), 'No login, no URL — the card degrades to an unlinked one.' );
	}

	/**
	 * No identifier, no URL and no embed. ADR-0008: the gap is visible, not clickable.
	 *
	 * @return void
	 */
	public function test_an_entry_with_no_identifier_resolves_to_nothing(): void {
		$blank = $this->video( VideoSource::Twitch, '' );

		$this->assertSame( '', $blank->watch_url( 'patsypatz' ) );
		$this->assertSame( '', $blank->embed_kind() );

		$unset = $this->video( null, '335921245' );

		$this->assertSame( '', $unset->watch_url( 'patsypatz' ) );
		$this->assertSame( '', $unset->embed_kind() );
	}

	/**
	 * The embed contract the theme's script reads: kind and ref agree with the URL.
	 *
	 * @return void
	 */
	public function test_the_embed_kind_and_ref_match_the_platform(): void {
		$vod = $this->video( VideoSource::Twitch, '335921245' );

		$this->assertSame( 'twitch-vod', $vod->embed_kind() );
		$this->assertSame( '335921245', $vod->embed_ref( 'patsypatz' ) );

		$upload = $this->video( VideoSource::YouTube, 'abc-123_XYZ' );

		$this->assertSame( 'youtube', $upload->embed_kind() );
		$this->assertSame( 'abc-123_XYZ', $upload->embed_ref( 'patsypatz' ) );

		$live = $this->video( VideoSource::Twitch, '', true );

		$this->assertSame( 'twitch-live', $live->embed_kind() );
		$this->assertSame( 'patsypatz', $live->embed_ref( 'patsypatz' ), 'The live embed plays the channel, not a VOD.' );
	}

	/**
	 * Identifiers are percent-encoded into the URLs, never interpolated raw.
	 *
	 * @return void
	 */
	public function test_identifiers_are_encoded_into_watch_urls(): void {
		$this->assertSame(
			'https://www.youtube.com/watch?v=a%26b%3Dc',
			$this->video( VideoSource::YouTube, 'a&b=c' )->watch_url( '' )
		);
	}
}
