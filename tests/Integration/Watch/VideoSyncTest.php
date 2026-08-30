<?php
/**
 * Integration tests for the Watch import.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Watch;

use DP\Core\Content\PostTypes;
use DP\Core\Watch\Schedule;
use DP\Core\Watch\Settings;
use DP\Core\Watch\TwitchApi;
use DP\Core\Watch\VideoSync;
use DP\Core\Watch\Videos;
use DP\Core\Watch\YouTubeApi;
use WP_Post;

/**
 * The upsert, against stubbed platforms and a real database.
 *
 * Every remote call is intercepted by `WatchTestCase`, so nothing here touches
 * Twitch or YouTube. What is asserted is the four things the sync promises and
 * cannot be read off the code: that running it twice does nothing the second
 * time, that a field David has edited is never written again, that a video the
 * platform stops listing is unpublished rather than destroyed, and that a
 * platform which fails changes nothing at all.
 */
final class VideoSyncTest extends WatchTestCase {

	/**
	 * The sync under test.
	 *
	 * @var VideoSync
	 */
	private VideoSync $sync;

	/**
	 * Build the sync over the intercepted clients.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->sync = new VideoSync( new TwitchApi(), new YouTubeApi() );
	}

	/**
	 * The plugin attached the schedule. Nothing else in the suite proves it.
	 *
	 * @return void
	 */
	public function test_the_plugin_attaches_the_hourly_schedule(): void {
		$this->assertNotFalse(
			has_action( Schedule::HOOK ),
			'Plugin::register() no longer wires the Watch sync to its cron hook.'
		);
	}

	/**
	 * The event is scheduled once and not rescheduled on top of itself.
	 *
	 * @return void
	 */
	public function test_the_event_is_scheduled_once(): void {
		wp_clear_scheduled_hook( Schedule::HOOK );

		$schedule = new Schedule( $this->sync );

		$schedule->ensure_scheduled();

		$first = wp_next_scheduled( Schedule::HOOK );

		$this->assertNotFalse( $first );
		$this->assertSame( Schedule::RECURRENCE, wp_get_schedule( Schedule::HOOK ) );

		$schedule->ensure_scheduled();

		$this->assertSame( $first, wp_next_scheduled( Schedule::HOOK ), 'The event was rescheduled on top of itself.' );

		Schedule::unschedule();

		$this->assertFalse( wp_next_scheduled( Schedule::HOOK ), 'Deactivating leaves an hourly event behind.' );
	}

	/**
	 * With nothing configured the sync writes nothing and says why.
	 *
	 * This is also the state a seeded development site is in, so it is the state
	 * that must never damage the fixture: the assertion below is that the seeded
	 * entry is still there and still published afterwards.
	 *
	 * @return void
	 */
	public function test_with_no_credentials_nothing_is_synced_and_it_says_so(): void {
		$hand_written = $this->seed_video( 'A video David wrote by hand', 'twitch', '', 'purple', 1 );

		$report = $this->sync->run();

		$this->assertFalse( $report->configured );
		$this->assertFalse( $report->ok() );
		$this->assertSame( 0, $report->total() );
		$this->assertStringContainsString( 'no Twitch or YouTube credentials are configured', $report->summary() );
		$this->assertSame( array(), $this->http_requests, 'A sync with no credentials still called out.' );
		$this->assertSame( 'publish', get_post_status( $hand_written ) );
	}

	/**
	 * A first sync creates a post per remote video, in the design's vocabulary.
	 *
	 * @return void
	 */
	public function test_a_first_sync_creates_a_post_for_every_remote_video(): void {
		$this->configure_twitch();
		$this->configure_youtube();
		$this->stub_twitch( array( $this->vod( '335921245', 'Provisioning a client site from one command', '2h41m0s', '2026-08-03T21:30:18Z' ) ) );
		$this->stub_youtube( array( $this->upload( 'dQw4w9WgXcQ', 'Why your analytics plugin is slowing the site down', 'PT18M4S', '2026-07-14T09:30:00Z' ) ) );

		$report = $this->sync->run();

		$this->assertTrue( $report->ok() );
		$this->assertSame( 2, $report->added );
		$this->assertSame( 0, $report->updated );

		$vod = $this->post_for( 'twitch:335921245' );

		$this->assertSame( 'Provisioning a client site from one command', $vod->post_title );
		$this->assertSame( 'publish', $vod->post_status );
		$this->assertSame( 'twitch', get_post_meta( $vod->ID, 'dp_video_source', true ) );
		$this->assertSame( '335921245', get_post_meta( $vod->ID, 'dp_video_ref', true ) );
		$this->assertSame( '2H 41M', get_post_meta( $vod->ID, 'dp_duration', true ) );
		$this->assertSame( 'AUG 2026', get_post_meta( $vod->ID, 'dp_when', true ) );
		$this->assertSame( 'purple', get_post_meta( $vod->ID, 'dp_tone', true ) );
		$this->assertSame(
			'https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg',
			get_post_meta( $vod->ID, VideoSync::THUMBNAIL, true ),
			'The thumbnail template was not kept, so the render path has to ask Helix for it again.'
		);

		$upload = $this->post_for( 'youtube:dQw4w9WgXcQ' );

		$this->assertSame( 'youtube', get_post_meta( $upload->ID, 'dp_video_source', true ) );
		$this->assertSame( '18 MIN', get_post_meta( $upload->ID, 'dp_duration', true ) );
		$this->assertSame( 'JUL 2026', get_post_meta( $upload->ID, 'dp_when', true ) );
		$this->assertSame( 'teal', get_post_meta( $upload->ID, 'dp_tone', true ) );
	}

	/**
	 * The note stays blank, and the entry is never a live one.
	 *
	 * David's decision: no API text is imported into the line under the title.
	 * The live panel is the one `dp_video` still written by hand, so nothing the
	 * sync creates may claim to be it.
	 *
	 * The imported post still opens on the field form — `Editor::form_content()`
	 * fills the blank body of any `dp_video`, however it was created — which is
	 * what makes the note something David can write afterwards rather than a
	 * field with no control. That is asserted here because a synced post is the
	 * only kind he will ever open.
	 *
	 * @return void
	 */
	public function test_no_api_text_reaches_the_note_and_nothing_imported_is_live(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'A stream with a long Twitch description', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$post = $this->post_for( 'twitch:1' );

		$this->assertSame( '', get_post_meta( $post->ID, 'dp_note', true ) );
		$this->assertEmpty( get_post_meta( $post->ID, 'dp_live', true ) );
		$this->assertStringContainsString(
			'"metaKey":"dp_note"',
			$post->post_content,
			'An imported video opens without a control for the note, so it can never be written.'
		);
	}

	/**
	 * Syncing twice changes nothing the second time.
	 *
	 * @return void
	 */
	public function test_syncing_twice_changes_nothing(): void {
		$this->configure_twitch();
		$this->stub_twitch(
			array(
				$this->vod( '1', 'One', '1h0m0s', '2026-08-03T21:30:18Z' ),
				$this->vod( '2', 'Two', '18m4s', '2026-07-03T21:30:18Z' ),
			)
		);

		$first = $this->sync->run();

		$this->assertSame( 2, $first->added );

		$before = $this->post_for( 'twitch:1' )->post_modified_gmt;

		$second = $this->sync->run();

		$this->assertSame( 0, $second->added );
		$this->assertSame( 0, $second->updated );
		$this->assertSame( 2, $second->unchanged );
		$this->assertSame( 0, $second->unpublished );
		$this->assertCount( 2, $this->all_videos() );
		$this->assertSame( $before, $this->post_for( 'twitch:1' )->post_modified_gmt, 'An unchanged post was written to anyway.' );
	}

	/**
	 * A field David edited is never written again; the rest keep syncing.
	 *
	 * Both halves are asserted against the same run, because a guard that locks
	 * the whole post would pass the first assertion and be wrong.
	 *
	 * @return void
	 */
	public function test_an_edited_field_is_left_alone_for_good(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'The platform title', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$post_id = $this->post_for( 'twitch:1' )->ID;

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'The title David wrote',
			)
		);

		$this->stub_twitch( array( $this->vod( '1', 'A title the platform changed', '2h41m0s', '2026-08-03T21:30:18Z' ) ) );

		$second = $this->sync->run();

		$this->assertSame( 1, $second->locked, 'The edit was not recognised as the author\'s.' );
		$this->assertSame( 'The title David wrote', get_post_field( 'post_title', $post_id ) );
		$this->assertSame( '2H 41M', get_post_meta( $post_id, 'dp_duration', true ), 'A field nobody touched stopped syncing.' );

		$this->stub_twitch( array( $this->vod( '1', 'And changed again', '2h41m0s', '2026-08-03T21:30:18Z' ) ) );

		$third = $this->sync->run();

		$this->assertSame( 0, $third->locked, 'The lock was recorded again instead of being remembered.' );
		$this->assertSame( 'The title David wrote', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * A field edited back to the value the sync had stays the author's.
	 *
	 * The shadow alone would let it go: the stored value would agree with the
	 * shadow again. The permanent lock is what holds, and this is the assertion
	 * that would fail if it were dropped.
	 *
	 * @return void
	 */
	public function test_a_field_edited_back_to_the_synced_value_stays_locked(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'Original', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$post_id = $this->post_for( 'twitch:1' )->ID;

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Mine now',
			)
		);

		$this->sync->run();

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Original',
			)
		);

		$this->stub_twitch( array( $this->vod( '1', 'The platform moved on', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$this->assertSame( 'Original', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * A video the platform stops listing is drafted, not deleted.
	 *
	 * Deleting would destroy the note and the title David wrote about it, and a
	 * Twitch VOD leaves the archive on a timer rather than on a decision. A
	 * draft disappears from the Watch page — `Videos` reads published entries —
	 * and comes back whole if the video does.
	 *
	 * @return void
	 */
	public function test_a_video_the_platform_forgets_is_unpublished_and_not_deleted(): void {
		$this->configure_twitch();
		$this->stub_twitch(
			array(
				$this->vod( '1', 'Still there', '1h0m0s', '2026-08-03T21:30:18Z' ),
				$this->vod( '2', 'Gone tomorrow', '18m4s', '2026-07-03T21:30:18Z' ),
			)
		);

		$this->sync->run();

		$gone = $this->post_for( 'twitch:2' )->ID;

		update_post_meta( $gone, 'dp_note', 'A note David wrote about it.' );

		$this->stub_twitch( array( $this->vod( '1', 'Still there', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$second = $this->sync->run();

		$this->assertSame( 1, $second->unpublished );
		$this->assertInstanceOf( WP_Post::class, get_post( $gone ), 'The post was deleted rather than drafted.' );
		$this->assertSame( 'draft', get_post_status( $gone ) );
		$this->assertSame( 'A note David wrote about it.', get_post_meta( $gone, 'dp_note', true ) );
		$this->assertCount( 1, ( new Videos() )->archive(), 'A drafted video is still on the Watch page.' );

		$this->stub_twitch(
			array(
				$this->vod( '1', 'Still there', '1h0m0s', '2026-08-03T21:30:18Z' ),
				$this->vod( '2', 'Gone tomorrow', '18m4s', '2026-07-03T21:30:18Z' ),
			)
		);

		$this->sync->run();

		$this->assertSame( 'publish', get_post_status( $gone ), 'A video that came back stayed hidden.' );
		$this->assertSame( 'A note David wrote about it.', get_post_meta( $gone, 'dp_note', true ) );
	}

	/**
	 * A post David unpublished himself is not republished.
	 *
	 * The status is a synced field like any other, so hiding a video by hand is
	 * an author edit and the sync stops having an opinion about it.
	 *
	 * @return void
	 */
	public function test_a_video_the_author_unpublished_stays_unpublished(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'One', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$post_id = $this->post_for( 'twitch:1' )->ID;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->sync->run();

		$this->assertSame( 'draft', get_post_status( $post_id ), 'The sync overruled the author and republished it.' );
	}

	/**
	 * A `dp_video` written by hand is never adopted, updated or unpublished.
	 *
	 * It carries no sync key, and that is the whole rule: no key, not ours. The
	 * live-now entry and every seeded fixture depend on it.
	 *
	 * @return void
	 */
	public function test_a_hand_written_video_is_invisible_to_the_sync(): void {
		$live = $this->seed_video( 'The live entry David wrote', 'twitch', '', 'pink', 1, true );

		$this->configure_twitch();
		$this->stub_twitch( array() );

		$report = $this->sync->run();

		$this->assertSame( 0, $report->unpublished );
		$this->assertSame( 'publish', get_post_status( $live ) );
		$this->assertSame( 'The live entry David wrote', get_post_field( 'post_title', $live ) );
		$this->assertSame( '', get_post_meta( $live, VideoSync::SYNC_KEY, true ) );
	}

	/**
	 * A platform that answers with nothing says so rather than reporting success.
	 *
	 * @return void
	 */
	public function test_an_empty_answer_is_reported_as_an_empty_answer(): void {
		$this->configure_twitch();
		$this->stub_twitch( array() );

		$report = $this->sync->run();

		$this->assertTrue( $report->ok() );
		$this->assertSame( 0, $report->total() );
		$this->assertStringContainsString( 'listed no videos', $report->summary() );
	}

	/**
	 * A platform that fails leaves everything exactly as it was.
	 *
	 * The dangerous case: a truncated or refused listing must not read as "every
	 * video was deleted". Nothing is unpublished and the failure is named.
	 *
	 * @return void
	 */
	public function test_a_platform_that_fails_changes_nothing(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'One', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$this->sync->run();

		$post_id = $this->post_for( 'twitch:1' )->ID;

		$this->http_stubs[ TwitchApi::VIDEOS_URL ] = self::response( 500, '{"error":"Internal Server Error"}' );

		$report = $this->sync->run();

		$this->assertFalse( $report->ok() );
		$this->assertSame( 0, $report->unpublished );
		$this->assertSame( array(), $report->platforms, 'A platform that refused was counted as having answered.' );
		$this->assertStringContainsString( 'Twitch did not answer', $report->summary() );
		$this->assertSame( 'publish', get_post_status( $post_id ), 'An outage emptied the Watch page.' );
	}

	/**
	 * One platform failing does not stop the other.
	 *
	 * @return void
	 */
	public function test_one_platform_failing_does_not_stop_the_other(): void {
		$this->configure_twitch();
		$this->configure_youtube();
		$this->stub_youtube( array( $this->upload( 'dQw4w9WgXcQ', 'An upload', 'PT18M4S', '2026-07-14T09:30:00Z' ) ) );

		$this->http_stubs[ TwitchApi::TOKEN_URL ] = self::response( 401, '{"message":"invalid client"}' );

		$report = $this->sync->run();

		$this->assertFalse( $report->ok() );
		$this->assertSame( array( 'YouTube' ), $report->platforms );
		$this->assertSame( 1, $report->added );
		$this->assertInstanceOf( WP_Post::class, get_post( $this->post_for( 'youtube:dQw4w9WgXcQ' )->ID ) );
	}

	/**
	 * The last run is recorded where the settings screen can print it.
	 *
	 * A sync that quietly stops running looks exactly like a channel that
	 * quietly stops publishing, and this option is the only difference.
	 *
	 * @return void
	 */
	public function test_the_last_run_is_recorded(): void {
		$this->configure_twitch();
		$this->stub_twitch( array( $this->vod( '1', 'One', '1h0m0s', '2026-08-03T21:30:18Z' ) ) );

		$report = $this->sync->run();
		$stored = get_option( VideoSync::LAST_RUN );

		$this->assertIsArray( $stored );
		$this->assertSame( $report->summary(), $stored['message'] ?? null );
		$this->assertIsInt( $stored['time'] ?? null );
	}

	/**
	 * Imported videos come out newest first, because nothing gives them a position.
	 *
	 * `VideoSync` writes no `menu_order` — a position is David's to set — so the
	 * date tiebreak in `Videos` is the whole of an imported archive's order.
	 *
	 * @return void
	 */
	public function test_an_imported_archive_reads_newest_first(): void {
		$this->configure_twitch();
		$this->stub_twitch(
			array(
				$this->vod( '1', 'Older', '1h0m0s', '2026-06-03T21:30:18Z' ),
				$this->vod( '2', 'Newer', '1h0m0s', '2026-08-03T21:30:18Z' ),
			)
		);

		$this->sync->run();

		$titles = array_map(
			static fn ( $video ): string => $video->title,
			( new Videos() )->archive()
		);

		$this->assertSame( array( 'Newer', 'Older' ), $titles );
	}

	/**
	 * Give the site working Twitch credentials.
	 *
	 * @return void
	 */
	private function configure_twitch(): void {
		update_option( Settings::LOGIN, 'patsypatz' );
		update_option( Settings::CLIENT_ID, 'abcDEF123' );
		update_option( Settings::CLIENT_SECRET, 'secretXYZ' );

		$this->http_stubs[ TwitchApi::TOKEN_URL ] = self::response( 200, '{"access_token":"tok","expires_in":5000000,"token_type":"bearer"}' );
		$this->http_stubs[ TwitchApi::USERS_URL ] = self::response( 200, '{"data":[{"id":"141981764","login":"patsypatz"}]}' );
	}

	/**
	 * Give the site working YouTube credentials.
	 *
	 * @return void
	 */
	private function configure_youtube(): void {
		update_option( Settings::YOUTUBE_CHANNEL, 'UCabcdefghijklmnopqrstuv' );
		update_option( Settings::YOUTUBE_KEY, 'AIzaSy_test-key' );

		$this->http_stubs[ YouTubeApi::CHANNELS_URL ] = self::response(
			200,
			'{"items":[{"contentDetails":{"relatedPlaylists":{"uploads":"UUabcdefghijklmnopqrstuv"}}}]}'
		);
	}

	/**
	 * Answer Twitch's archive endpoint with these VODs and no next page.
	 *
	 * @param array<int, array<string, string>> $vods The VODs, from `vod()`.
	 * @return void
	 */
	private function stub_twitch( array $vods ): void {
		$this->http_stubs[ TwitchApi::VIDEOS_URL ] = self::response(
			200,
			(string) wp_json_encode(
				array(
					'data'       => $vods,
					'pagination' => array( 'cursor' => '' ),
				)
			)
		);
	}

	/**
	 * Answer YouTube's playlist and videos endpoints with these uploads.
	 *
	 * @param array<int, array<string, mixed>> $uploads The uploads, from `upload()`.
	 * @return void
	 */
	private function stub_youtube( array $uploads ): void {
		$items = array();

		foreach ( $uploads as $upload ) {
			$items[] = array( 'contentDetails' => array( 'videoId' => $upload['id'] ) );
		}

		$this->http_stubs[ YouTubeApi::PLAYLIST_ITEMS_URL ] = self::response(
			200,
			(string) wp_json_encode( array( 'items' => $items ) )
		);

		$this->http_stubs[ YouTubeApi::VIDEOS_URL ] = self::response(
			200,
			(string) wp_json_encode( array( 'items' => $uploads ) )
		);
	}

	/**
	 * One VOD, in Helix's shape.
	 *
	 * @param string $id        The VOD id.
	 * @param string $title     Its title.
	 * @param string $duration  Twitch's duration spelling, e.g. `2h41m0s`.
	 * @param string $published RFC 3339 publication date.
	 * @return array<string, string>
	 */
	private function vod( string $id, string $title, string $duration, string $published ): array {
		return array(
			'id'            => $id,
			'title'         => $title,
			'duration'      => $duration,
			'published_at'  => $published,
			'thumbnail_url' => 'https://static-cdn.jtvnw.net/cf_vods/x/thumb/index-%{width}x%{height}.jpg',
			'type'          => 'archive',
		);
	}

	/**
	 * One upload, in the Data API's shape.
	 *
	 * @param string $id        The video id.
	 * @param string $title     Its title.
	 * @param string $duration  An ISO 8601 duration, e.g. `PT18M4S`.
	 * @param string $published RFC 3339 publication date.
	 * @return array<string, mixed>
	 */
	private function upload( string $id, string $title, string $duration, string $published ): array {
		return array(
			'id'             => $id,
			'snippet'        => array(
				'title'       => $title,
				'publishedAt' => $published,
			),
			'contentDetails' => array( 'duration' => $duration ),
		);
	}

	/**
	 * The post carrying one sync key.
	 *
	 * @param string $key The key, e.g. `twitch:335921245`.
	 * @return WP_Post
	 */
	private function post_for( string $key ): WP_Post {
		foreach ( $this->all_videos() as $post ) {
			if ( get_post_meta( $post->ID, VideoSync::SYNC_KEY, true ) === $key ) {
				return $post;
			}
		}

		$this->fail( sprintf( 'No dp_video carries the sync key "%s".', $key ) );
	}

	/**
	 * Every `dp_video`, whatever its status.
	 *
	 * @return array<int, WP_Post>
	 */
	private function all_videos(): array {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::VIDEO,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_values( array_filter( $posts, static fn ( $post ): bool => $post instanceof WP_Post ) );
	}
}
