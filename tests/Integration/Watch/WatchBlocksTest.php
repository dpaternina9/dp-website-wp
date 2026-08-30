<?php
/**
 * Integration tests for `dp/watch-featured` and `dp/video-grid`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Watch\LiveStatus;
use DP\Core\Watch\LiveStream;
use DP\Core\Watch\Settings;
use DP\Core\Watch\VideoGrid;
use DP\Core\Watch\WatchFeatured;
use WP_Block_Type_Registry;

/**
 * The two Watch blocks, rendered, live and not.
 *
 * The handoff is the thing under test: the design's `featuredVideo` /
 * `vods` split means the two blocks must agree about the live state, and the
 * one entry up top must never also be in the grid. The live state itself is
 * put into `LiveStatus`'s own transient rather than mocked at the class,
 * because that transient is the mechanism the two blocks agree through.
 *
 * The other standing assertion is the click-to-play contract's server half:
 * **no render ever contains an iframe.** The player is the script's to add,
 * after a press; a server that shipped one would break the design's privacy
 * and layout promise before the page finished loading.
 */
final class WatchBlocksTest extends WatchTestCase {

	/**
	 * Render both blocks the way the template does, panel first.
	 *
	 * @return string
	 */
	private function render_page(): string {
		return do_blocks( '<!-- wp:dp/watch-featured /--><!-- wp:dp/video-grid /-->' );
	}

	/**
	 * Put a live stream in the cache the two blocks agree through.
	 *
	 * Written into `LiveStatus`'s own transient rather than mocked at the class,
	 * because that transient *is* the mechanism the panel and the grid agree
	 * through — and because filling it means no test here needs a stubbed HTTP
	 * conversation to be about something else. `LiveCardTest` covers the call
	 * that fills it for real.
	 *
	 * @param string $title The stream title Twitch is reporting.
	 * @return void
	 */
	private function cache_live( string $title = 'A stream Twitch is reporting' ): void {
		update_option( Settings::LOGIN, 'patsypatz' );

		set_transient(
			LiveStatus::TRANSIENT,
			( new LiveStream( $title, time() - 4320, 'Software and Game Development' ) )->to_cache()
		);
	}

	/**
	 * Seed the design's shape: one live entry, three archived videos.
	 *
	 * @return void
	 */
	private function seed_fixture(): void {
		$this->seed_video( 'Building the Kiveo reading-stats screen, live', 'twitch', '', 'pink', 1, true );
		$this->seed_video( 'Provisioning a client site from one command', 'twitch', '2280918841', 'purple', 2 );
		$this->seed_video( 'Why your analytics plugin is slowing the site down', 'youtube', 'dp-fixture-yt', 'teal', 3 );
		$this->seed_video( 'Rewriting the query parser, badly, twice', 'twitch', '', 'purple', 4 );
	}

	/**
	 * Both blocks are registered and dynamic.
	 *
	 * @return void
	 */
	public function test_both_blocks_are_registered_and_dynamic(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( WatchFeatured::BLOCK_NAME, VideoGrid::BLOCK_NAME ) as $name ) {
			$type = $registry->get_registered( $name );

			$this->assertNotNull( $type, $name . ' is not registered. Plugin::register() is where the line goes.' );
			$this->assertTrue( $type->is_dynamic(), $name . ' has no render callback.' );
		}
	}

	/**
	 * With nothing published, both blocks render nothing at all.
	 *
	 * @return void
	 */
	public function test_with_no_videos_both_blocks_render_nothing(): void {
		$this->assertSame( '', $this->render_page() );
	}

	/**
	 * Not live: the newest archived video is the panel, and the grid starts
	 * from the second. The live entry appears nowhere.
	 *
	 * @return void
	 */
	public function test_not_live_features_the_latest_and_the_grid_takes_the_rest(): void {
		$this->seed_fixture();

		$html = $this->render_page();

		$this->assertStringContainsString( 'dp-watch-featured', $html );
		$this->assertStringContainsString( 'Provisioning a client site from one command', $html );
		$this->assertStringContainsString( 'Latest on Twitch', $html );
		$this->assertStringNotContainsString( 'is-live', $html );

		$this->assertStringNotContainsString(
			'Building the Kiveo reading-stats screen, live',
			$html,
			'The live entry is a separate thing, not the head of the archive; off-stream it disappears entirely.'
		);

		$this->assertSame( 2, substr_count( $html, 'dp-vg-card' ), 'The featured video is not repeated in the grid.' );
		$this->assertStringContainsString( 'Why your analytics plugin is slowing the site down', $html );
		$this->assertStringContainsString( 'Rewriting the query parser, badly, twice', $html );
	}

	/**
	 * Live: the panel is the live entry, badge and strapline included, and the
	 * whole archive stays in the grid.
	 *
	 * @return void
	 */
	public function test_live_features_the_stream_and_the_grid_keeps_the_whole_archive(): void {
		$this->seed_fixture();
		$this->cache_live();

		$html = $this->render_page();

		$this->assertStringContainsString( 'is-live', $html );
		$this->assertStringContainsString( 'Building the Kiveo reading-stats screen, live', $html );
		$this->assertStringContainsString( 'Live on Twitch', $html );
		$this->assertStringContainsString( 'STREAMING NOW · 1H 12M IN', $html );
		$this->assertStringContainsString( 'Watch the stream', $html );
		$this->assertStringContainsString( 'data-dp-embed="twitch-live"', $html );
		$this->assertStringContainsString( 'href="https://www.twitch.tv/patsypatz"', $html );

		$this->assertSame( 3, substr_count( $html, 'dp-vg-card' ), 'Live, the grid is the whole archive.' );
		$this->assertStringContainsString( 'Provisioning a client site from one command', $html );
	}

	/**
	 * Live with no `dp_live` post written: the panel is the stream anyway, and
	 * the grid keeps the whole archive. The two agree without a post existing.
	 *
	 * This is the case the page used to get wrong — it fell back to featuring
	 * the latest VOD while the channel was on air, because the card's copy had
	 * nowhere else to come from.
	 *
	 * @return void
	 */
	public function test_live_with_no_post_still_features_the_stream(): void {
		$this->seed_video( 'Provisioning a client site from one command', 'twitch', '2280918841', 'purple', 1 );
		$this->seed_video( 'Why your analytics plugin is slowing the site down', 'youtube', 'dp-fixture-yt', 'teal', 2 );

		$this->cache_live( 'Rewriting the query parser, badly, twice' );

		$html = $this->render_page();

		$this->assertStringContainsString( 'is-live', $html );
		$this->assertStringContainsString( 'Live now on Twitch', $html );
		$this->assertStringContainsString( 'Rewriting the query parser, badly, twice', $html );
		$this->assertSame( 2, substr_count( $html, 'dp-vg-card' ), 'Live, the grid is the whole archive.' );
	}

	/**
	 * No render contains a player. The iframe is the press's, never the server's.
	 *
	 * @return void
	 */
	public function test_no_render_ever_contains_an_iframe(): void {
		$this->seed_fixture();

		$this->assertStringNotContainsString( '<iframe', $this->render_page() );

		$this->cache_live();

		$this->assertStringNotContainsString( '<iframe', $this->render_page() );
	}

	/**
	 * Without JavaScript a card is a plain link to the video on its host,
	 * carrying exactly what the script needs to upgrade the press.
	 *
	 * @return void
	 */
	public function test_cards_are_plain_links_to_the_video_on_its_host(): void {
		$this->seed_fixture();

		$html = $this->render_page();

		$this->assertStringContainsString( 'href="https://www.youtube.com/watch?v=dp-fixture-yt"', $html );
		$this->assertStringContainsString( 'data-dp-embed="youtube"', $html );
		$this->assertStringContainsString( 'data-dp-ref="dp-fixture-yt"', $html );

		// The featured VOD's press-to-play link, on the panel.
		$this->assertStringContainsString( 'href="https://www.twitch.tv/videos/2280918841"', $html );
		$this->assertStringContainsString( 'data-dp-embed="twitch-vod"', $html );
	}

	/**
	 * A video with no identifier keeps its footer and loses the link (ADR-0008).
	 *
	 * @return void
	 */
	public function test_a_video_with_no_identifier_degrades_to_an_unlinked_card(): void {
		$this->seed_fixture();

		$html = $this->render_page();

		$this->assertStringContainsString( '<span class="dp-vg-link is-unlinked">Watch on Twitch</span>', $html );
	}

	/**
	 * Tone classes and the tile facts reach the markup.
	 *
	 * @return void
	 */
	public function test_cards_carry_their_tone_and_tile_facts(): void {
		$this->seed_fixture();

		$html = $this->render_page();

		$this->assertStringContainsString( 'dp-vg-card dp-tone-teal', $html );
		$this->assertStringContainsString( 'dp-vg-card dp-tone-purple', $html );
		$this->assertStringContainsString( '<span class="dp-vg-dur">2H 41M</span>', $html );
		$this->assertStringContainsString( '<span class="dp-vg-when">AUG 2026</span>', $html );
	}

	/**
	 * Everything David types is escaped at the point of output.
	 *
	 * The fixture strings are ones that survive the storage-side sanitizers
	 * unchanged — quotes and ampersands — so what is asserted is this render's
	 * own escaping rather than `sanitize_text_field()`'s.
	 *
	 * @return void
	 */
	public function test_titles_and_notes_are_escaped_on_output(): void {
		$post_id = $this->seed_video( 'Espresso & "guitars"', 'youtube', 'dp-fixture-yt', 'teal', 1 );

		update_post_meta( $post_id, 'dp_note', 'Fish & chips' );

		// A second entry so the first stays featured and both render paths run.
		$this->seed_video( 'Another & another', 'youtube', '', 'teal', 2 );

		$html = $this->render_page();

		$this->assertStringContainsString(
			'<h2 class="dp-watch-featured-title">Espresso &amp; &quot;guitars&quot;</h2>',
			$html,
			'The title is escaped into the heading; esc_html() encodes quotes too.'
		);
		$this->assertStringContainsString( 'data-dp-title="Espresso &amp; &quot;guitars&quot;"', $html );
		$this->assertStringContainsString( 'Fish &amp; chips', $html );
		$this->assertStringContainsString( '>Another &amp; another<', $html );
		$this->assertStringNotContainsString( 'Fish & chips', $html );
	}

	/**
	 * With no credentials configured, rendering never phones Twitch.
	 *
	 * The YouTube thumbnail fetch is the one remote call a bare render may
	 * make — it needs no key — and the interceptor refuses it; nothing in the
	 * attempt list may point anywhere else.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_render_never_calls_twitch(): void {
		$this->seed_fixture();

		$this->render_page();

		$this->assertNotEmpty( $this->http_requests, 'The YouTube thumbnail fetch should have been attempted — and refused by the interceptor.' );

		foreach ( $this->http_requests as $url ) {
			$this->assertStringNotContainsString( 'twitch.tv', $url );
			$this->assertStringContainsString( 'i.ytimg.com', $url );
		}
	}
}
