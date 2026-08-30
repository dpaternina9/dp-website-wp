<?php
/**
 * Integration tests for the automatic live card.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Watch\LiveStatus;
use DP\Core\Watch\Settings;
use DP\Core\Watch\TwitchApi;

/**
 * The live panel, drawn from Twitch, against a real database and a stubbed Helix.
 *
 * David's requirement was that being live is not something he manages. Detection
 * always satisfied that; the card's *copy* did not, because it came from a
 * `dp_video` post he had to write and keep true. These tests are the proof that
 * it no longer does, and the proof that closing that gap did not quietly take
 * the page away from him:
 *
 * 1. **No post at all** — the whole card is Twitch's.
 * 2. **A post he wrote** — every field he filled in is his.
 * 3. **A post he half-wrote** — the blanks are Twitch's, the rest is his.
 * 4. **Not live** — no panel claims to be, whatever posts exist.
 * 5. **No credentials** — the same, and nothing is even asked of Twitch.
 *
 * Nothing here calls Twitch: `WatchTestCase` intercepts every request, so the
 * `helix/streams` body is a fixture and an unstubbed URL is a failure rather
 * than a network call.
 */
final class LiveCardTest extends WatchTestCase {

	/**
	 * How long the fixture stream has been running, in seconds.
	 *
	 * Seventy-two minutes, which is the design's own "1H 12M IN".
	 *
	 * @var int
	 */
	private const RUNNING = 72 * 60;

	/**
	 * The instant the stubbed stream started.
	 *
	 * Recorded when the stub is built rather than recomputed at assertion time:
	 * a test that derives the same timestamp twice from `time()` fails whenever
	 * a second happens to tick between the two, which is a flake and not a bug.
	 *
	 * @var int
	 */
	private int $started = 0;

	/**
	 * Render both blocks the way the template does, panel first.
	 *
	 * @return string
	 */
	private function render_page(): string {
		return do_blocks( '<!-- wp:dp/watch-featured /--><!-- wp:dp/video-grid /-->' );
	}

	/**
	 * Two archived videos, so there is always something to fall back to.
	 *
	 * @return void
	 */
	private function seed_archive(): void {
		$this->seed_video( 'Provisioning a client site from one command', 'twitch', '2280918841', 'purple', 1 );
		$this->seed_video( 'Why your analytics plugin is slowing the site down', 'youtube', 'dp-fixture-yt', 'teal', 2 );
	}

	/**
	 * Configure the channel and answer Helix with a live stream.
	 *
	 * The token endpoint is stubbed as well, because the client will not make a
	 * Helix call without one — which is itself part of the failing-soft contract.
	 *
	 * @param string $title    The stream title Twitch reports.
	 * @param string $category The category Twitch reports.
	 * @return void
	 */
	private function stub_live( string $title = 'Building the Kiveo reading-stats screen, live', string $category = 'Software and Game Development' ): void {
		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->started = time() - self::RUNNING;

		$this->http_stubs[ TwitchApi::TOKEN_URL ] = self::response(
			200,
			'{"access_token":"tok","expires_in":5000000,"token_type":"bearer"}'
		);

		$this->http_stubs[ TwitchApi::STREAMS_URL ] = self::response(
			200,
			(string) wp_json_encode(
				array(
					'data' => array(
						array(
							'id'           => '41375541868',
							'user_login'   => 'patsypatz',
							'game_name'    => $category,
							'type'         => 'live',
							'title'        => $title,
							'viewer_count' => 41,
							'started_at'   => gmdate( 'Y-m-d\TH:i:s\Z', $this->started ),
						),
					),
				)
			)
		);
	}

	/**
	 * With no `dp_live` post anywhere, the card is entirely Twitch's — which is
	 * the requirement this whole change exists to satisfy.
	 *
	 * @return void
	 */
	public function test_with_no_post_the_card_is_built_from_the_stream(): void {
		$this->seed_archive();
		$this->stub_live();

		$html = $this->render_page();

		$this->assertStringContainsString( 'is-live', $html );
		$this->assertStringContainsString(
			'<h2 class="dp-watch-featured-title">Building the Kiveo reading-stats screen, live</h2>',
			$html,
			'The heading is the stream title Helix reported.'
		);
		$this->assertStringContainsString( 'Streaming now · 1H 12M in', $html );
		$this->assertStringContainsString( 'Software and Game Development', $html );
		$this->assertStringContainsString( 'Live now on Twitch', $html );
		$this->assertStringContainsString( 'data-dp-embed="twitch-live"', $html );
		$this->assertStringContainsString( 'href="https://www.twitch.tv/patsypatz"', $html );

		// Live, the grid keeps the whole archive; the composed card is not a post,
		// so there is nothing for it to double-count.
		$this->assertSame( 2, substr_count( $html, 'dp-vg-card' ) );
	}

	/**
	 * The strapline carries the stream's start instant and the sentence to
	 * re-fill, so a reader's browser can keep the number honest on a page that
	 * has been sitting in a cache.
	 *
	 * @return void
	 */
	public function test_a_derived_strapline_carries_what_the_clock_needs(): void {
		$this->seed_archive();
		$this->stub_live();

		$html = $this->render_page();

		$this->assertStringContainsString( 'data-dp-live-since="' . $this->started . '"', $html );
		$this->assertStringContainsString( 'data-dp-live-format="Streaming now · %s in"', $html );
	}

	/**
	 * A `dp_live` post David wrote wins outright when he filled it in.
	 *
	 * And the strapline he typed carries no start instant: nothing in the markup
	 * invites the script to rewrite a value he set.
	 *
	 * @return void
	 */
	public function test_a_post_david_wrote_overrides_the_stream(): void {
		$this->seed_archive();
		$post_id = $this->seed_video( 'Building the Kiveo reading-stats screen, live', 'twitch', '', 'pink', 3, true );

		update_post_meta( $post_id, 'dp_note', 'The note he wrote himself.' );

		$this->stub_live( 'A title Twitch is reporting', 'Just Chatting' );

		$html = $this->render_page();

		$this->assertStringContainsString(
			'<h2 class="dp-watch-featured-title">Building the Kiveo reading-stats screen, live</h2>',
			$html
		);
		$this->assertStringContainsString( 'STREAMING NOW · 1H 12M IN', $html );
		$this->assertStringContainsString( 'The note he wrote himself.', $html );

		$this->assertStringNotContainsString( 'A title Twitch is reporting', $html );
		$this->assertStringNotContainsString( 'Just Chatting', $html );
		$this->assertStringNotContainsString( 'data-dp-live-since', $html, 'A strapline he typed must never tick.' );

		$this->assertSame( 2, substr_count( $html, 'dp-vg-card' ), 'His live post is not also a card in the grid.' );
	}

	/**
	 * A post he half-wrote: the note is his, and the fields he left blank are
	 * filled from the stream rather than rendered empty.
	 *
	 * @return void
	 */
	public function test_the_blanks_on_his_post_are_filled_from_the_stream(): void {
		$this->seed_archive();
		$post_id = $this->seed_video( 'His own headline', 'twitch', '', 'pink', 3, true );

		delete_post_meta( $post_id, 'dp_live_meta' );
		update_post_meta( $post_id, 'dp_note', 'SwiftUI charts, and my embarrassing reading data.' );

		$this->stub_live( 'A title Twitch is reporting', 'Software and Game Development' );

		$html = $this->render_page();

		$this->assertStringContainsString( '<h2 class="dp-watch-featured-title">His own headline</h2>', $html );
		$this->assertStringContainsString( 'SwiftUI charts, and my embarrassing reading data.', $html );
		$this->assertStringContainsString( 'Streaming now · 1H 12M in', $html, 'The blank strapline is the derivation\'s to fill.' );
		$this->assertStringContainsString( 'data-dp-live-since=', $html );
	}

	/**
	 * Offline: no panel claims to be live, whatever David has written.
	 *
	 * The `dp_live` post disappears entirely rather than heading the archive —
	 * the design's own rule — and the panel is the newest archived video.
	 *
	 * @return void
	 */
	public function test_offline_renders_no_live_panel_at_all(): void {
		$this->seed_archive();
		$this->seed_video( 'Building the Kiveo reading-stats screen, live', 'twitch', '', 'pink', 3, true );

		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->http_stubs[ TwitchApi::TOKEN_URL ]   = self::response( 200, '{"access_token":"tok","expires_in":5000000}' );
		$this->http_stubs[ TwitchApi::STREAMS_URL ] = self::response( 200, '{"data":[]}' );

		$html = $this->render_page();

		$this->assertStringNotContainsString( 'is-live', $html );
		$this->assertStringNotContainsString( 'Building the Kiveo reading-stats screen, live', $html );
		$this->assertStringContainsString( 'Latest on Twitch', $html );
		$this->assertSame( 1, substr_count( $html, 'dp-vg-card' ), 'Offline, the featured video leaves the grid.' );
	}

	/**
	 * Helix refusing the call is not live either, and it is cached as such so an
	 * outage costs one request every couple of minutes rather than one per view.
	 *
	 * @return void
	 */
	public function test_a_helix_failure_is_not_live_and_is_cached(): void {
		$this->seed_archive();

		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->http_stubs[ TwitchApi::TOKEN_URL ]   = self::response( 200, '{"access_token":"tok","expires_in":5000000}' );
		$this->http_stubs[ TwitchApi::STREAMS_URL ] = self::response( 500, 'upstream is having a day' );

		$html = $this->render_page();

		$this->assertStringNotContainsString( 'is-live', $html );
		$this->assertStringContainsString( 'Latest on Twitch', $html );
		$this->assertSame( array(), get_transient( LiveStatus::TRANSIENT ), '"Not live" must be cached, failure included.' );
	}

	/**
	 * With no credentials the panel never claims to be live, and nothing is
	 * asked of Twitch at all.
	 *
	 * @return void
	 */
	public function test_without_credentials_nothing_is_asked_and_nothing_is_live(): void {
		$this->seed_archive();
		$this->seed_video( 'Building the Kiveo reading-stats screen, live', 'twitch', '', 'pink', 3, true );

		update_option( Settings::LOGIN, 'patsypatz' );

		$html = $this->render_page();

		$this->assertStringNotContainsString( 'is-live', $html );
		$this->assertStringContainsString( 'Latest on Twitch', $html );

		foreach ( $this->http_requests as $url ) {
			$this->assertStringNotContainsString( 'twitch.tv', $url );
		}
	}

	/**
	 * The live check runs once per request, not once per block.
	 *
	 * Both blocks ask, and the second must read the first's transient — the
	 * mechanism they agree through, and the reason the panel and the grid can
	 * never disagree about who is up top.
	 *
	 * @return void
	 */
	public function test_the_two_blocks_share_one_helix_call(): void {
		$this->seed_archive();
		$this->stub_live();

		$this->render_page();

		$streams = 0;

		foreach ( $this->http_requests as $url ) {
			if ( str_starts_with( $url, TwitchApi::STREAMS_URL ) ) {
				++$streams;
			}
		}

		$this->assertSame( 1, $streams, 'The panel and the grid each called Helix.' );
	}
}
