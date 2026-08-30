<?php
/**
 * The Watch archive, imported rather than typed.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\PostTypes;
use DP\Core\Content\VideoSource;
use WP_Post;
use WP_Query;

/**
 * Turns two platforms' archives into `dp_video` posts, and never takes anything
 * back from David.
 *
 * The Watch page's requirement is that it manages itself: a VOD appears because
 * it was streamed, an upload appears because it was uploaded, and David does not
 * open the editor to make either happen. This is the half of that which writes
 * posts. The live panel is `LiveStatus`'s, and is already automatic.
 *
 * ## What is synced
 *
 * Seven fields, and only these seven:
 *
 * | Field | From |
 * |---|---|
 * | `post_title` | The platform's title. |
 * | `post_status` | `publish`, or `draft` once the platform stops listing it. |
 * | `dp_video_source` | Which platform answered. |
 * | `dp_video_ref` | The platform's identifier, which is also the canonical URL — `Video::watch_url()` builds one from the other, so storing the URL as well would be storing it twice. |
 * | `dp_duration` | The runtime, through `Duration`. |
 * | `dp_when` | The publication month. |
 * | `dp_tone` | The design's per-platform hue. |
 *
 * **`dp_note` is not in that table, and that is a decision rather than an
 * omission.** No API text is imported into the note. The card renders without one
 * until David writes it, and once he has, the guard below protects it like any
 * other field he has touched. `dp_live` is not there either: the live entry is
 * the one `dp_video` still written by hand, because its copy is his.
 *
 * `post_date` is set once, at insert, from the platform's publication date, and
 * never touched again — a publication date does not change remotely, and a
 * correction David makes to one is his.
 *
 * ## The author-edit guard
 *
 * ADR-0018 rule 3: a derivation fills a blank, and where a value is present the
 * author's value wins. The decision itself is `AuthorEdits::decide()`, which
 * carries the long explanation; the bookkeeping is here, in two private meta
 * keys on each synced post:
 *
 * - **`_dp_sync_shadow`** — what this class last wrote, field by field. Before
 *   writing a field it compares the shadow with what is stored. Equal means
 *   nobody has been here since; different means David has, and the field is his.
 * - **`_dp_sync_locked`** — the fields that turned out to be his. Once a field is
 *   in this list it is never written again, even if its value later happens to
 *   match what the sync would have put there.
 *
 * A third key, **`_dp_sync_key`**, is the post's identity: `twitch:123456789` or
 * `youtube:dQw4w9WgXcQ`. It is what makes the upsert an upsert, and it is why a
 * `dp_video` David wrote by hand — the live entry, the seeded fixtures — can
 * never be adopted, updated or unpublished by a sync. No key, not ours.
 *
 * All three are underscore-prefixed and deliberately **not** registered through
 * `Content\Meta`. Registering them would put a control on the editing form for a
 * value that is not David's to edit, and leaving them unregistered is also what
 * keeps them out of REST: `WP_REST_Post_Meta_Fields` exposes registered meta and
 * nothing else.
 *
 * ## Failing soft
 *
 * Every remote call answers `null` for "the platform did not say", and a
 * platform that did not say is skipped entirely — not partially. Reconciliation
 * in particular runs **only** against a list that arrived whole, because a
 * truncated list is indistinguishable from a channel whose videos were deleted,
 * and acting on that would empty the Watch page during an outage.
 */
final class VideoSync {

	/**
	 * The meta key holding a post's platform identity, `platform:id`.
	 *
	 * @var string
	 */
	public const SYNC_KEY = '_dp_sync_key';

	/**
	 * The meta key holding what the sync last wrote, field by field.
	 *
	 * @var string
	 */
	public const SHADOW = '_dp_sync_shadow';

	/**
	 * The meta key listing the fields an author has taken over.
	 *
	 * @var string
	 */
	public const LOCKED = '_dp_sync_locked';

	/**
	 * The meta key holding a Twitch thumbnail URL template.
	 *
	 * Not an author-facing field and not under the guard: it is a cache of
	 * something the sync already had to ask Helix for, kept so the render path
	 * does not have to ask again.
	 *
	 * @var string
	 */
	public const THUMBNAIL = '_dp_sync_thumbnail';

	/**
	 * The option remembering the last run, for the line on Settings → General.
	 *
	 * @var string
	 */
	public const LAST_RUN = 'dp_watch_last_sync';

	/**
	 * How many synced posts one run will consider.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 500;

	/**
	 * Constructor.
	 *
	 * @param TwitchApi  $twitch  The Helix client, shared with the live check.
	 * @param YouTubeApi $youtube The Data API client.
	 */
	public function __construct(
		private readonly TwitchApi $twitch = new TwitchApi(),
		private readonly YouTubeApi $youtube = new YouTubeApi()
	) {}

	/**
	 * Build with the default clients.
	 *
	 * Touches no WordPress function, so it is safe before `init`.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self( new TwitchApi(), new YouTubeApi() );
	}

	/**
	 * Sync every configured platform.
	 *
	 * @return SyncReport What happened, whether or not anything did.
	 */
	public function run(): SyncReport {
		$totals     = array(
			'added'       => 0,
			'updated'     => 0,
			'unchanged'   => 0,
			'unpublished' => 0,
			'locked'      => 0,
		);
		$failures   = array();
		$platforms  = array();
		$configured = Settings::has_twitch() || Settings::has_youtube();

		if ( Settings::has_twitch() ) {
			$videos = $this->twitch->archive_videos();

			if ( null === $videos ) {
				$failures[] = __( 'Twitch did not answer: check the login, the client ID and the client secret.', 'dp-core' );
			} else {
				$platforms[] = 'Twitch';
				$totals      = $this->add( $totals, $this->ingest( VideoSource::Twitch, $videos ) );
			}
		}

		if ( Settings::has_youtube() ) {
			$videos = $this->youtube->videos();

			if ( null === $videos ) {
				$failures[] = __( 'YouTube did not answer: check the channel and the API key.', 'dp-core' );
			} else {
				$platforms[] = 'YouTube';
				$totals      = $this->add( $totals, $this->ingest( VideoSource::YouTube, $videos ) );
			}
		}

		$report = new SyncReport(
			$totals['added'],
			$totals['updated'],
			$totals['unchanged'],
			$totals['unpublished'],
			$totals['locked'],
			$failures,
			$platforms,
			$configured
		);

		update_option(
			self::LAST_RUN,
			array(
				'time'    => time(),
				'message' => $report->summary(),
			),
			false
		);

		/**
		 * Fires after every Watch sync, successful or not.
		 *
		 * The only signal a scheduled run leaves besides the line on Settings →
		 * General, so it is the hook to hang an alert on.
		 *
		 * @since 0.1.0
		 *
		 * @param SyncReport $report What the run did.
		 */
		do_action( 'dp_core_watch_synced', $report );

		return $report;
	}

	/**
	 * Write one platform's whole archive, then reconcile what is missing from it.
	 *
	 * @param VideoSource             $source The platform.
	 * @param array<int, RemoteVideo> $videos Everything it listed. Never a partial list.
	 * @return array{added: int, updated: int, unchanged: int, unpublished: int, locked: int}
	 */
	private function ingest( VideoSource $source, array $videos ): array {
		$counts = array(
			'added'       => 0,
			'updated'     => 0,
			'unchanged'   => 0,
			'unpublished' => 0,
			'locked'      => 0,
		);

		$existing = $this->synced( $source );

		foreach ( $videos as $video ) {
			$key     = $video->key();
			$post_id = $existing[ $key ] ?? 0;
			$fresh   = 0 === $post_id;

			if ( $fresh ) {
				$post_id = $this->insert( $video );

				if ( 0 === $post_id ) {
					continue;
				}
			}

			unset( $existing[ $key ] );

			$applied           = $this->apply( $post_id, $this->fields( $video ) );
			$counts['locked'] += $applied['locked'];

			update_post_meta( $post_id, self::THUMBNAIL, $video->thumbnail );

			if ( $fresh ) {
				++$counts['added'];
			} elseif ( $applied['changed'] ) {
				++$counts['updated'];
			} else {
				++$counts['unchanged'];
			}
		}

		/*
		 * Whatever is left carried this platform's key and was not in the answer,
		 * so the platform no longer has it: deleted, made private, or expired off
		 * Twitch. It is drafted rather than deleted — see `unpublish()`.
		 */
		foreach ( $existing as $post_id ) {
			$applied           = $this->apply( $post_id, array( 'post_status' => 'draft' ) );
			$counts['locked'] += $applied['locked'];

			if ( $applied['changed'] ) {
				++$counts['unpublished'];
			}
		}

		return $counts;
	}

	/**
	 * The fields one remote video says the post should carry.
	 *
	 * @param RemoteVideo $video The video.
	 * @return array<string, string>
	 */
	private function fields( RemoteVideo $video ): array {
		return array(
			'post_title'      => $video->title,
			'post_status'     => 'publish',
			'dp_video_source' => $video->source->value,
			'dp_video_ref'    => $video->id,
			'dp_duration'     => Duration::format( $video->duration ),
			'dp_when'         => self::when( $video->published ),
			'dp_tone'         => self::tone( $video->source ),
		);
	}

	/**
	 * Create the post one remote video gets.
	 *
	 * Only the identity and the date are written here. Every other field goes
	 * through `apply()` immediately afterwards, so a new post and an existing one
	 * take exactly the same path and the shadow is written in exactly one place.
	 *
	 * @param RemoteVideo $video The video.
	 * @return int The new post ID, or 0 when it could not be created.
	 */
	private function insert( RemoteVideo $video ): int {
		if ( '' === $video->title ) {
			/*
			 * `wp_insert_post()` refuses a post with no title, no content and no
			 * excerpt, and inventing a title would put words on the card that
			 * nobody wrote. The video is skipped and picked up by the next run,
			 * by which time the platform has almost certainly named it.
			 */
			return 0;
		}

		$postarr = array(
			'post_type'    => PostTypes::VIDEO,
			'post_title'   => $video->title,
			'post_status'  => 'publish',
			'post_content' => '',
		);

		if ( $video->published > 0 ) {
			$local = wp_date( 'Y-m-d H:i:s', $video->published );

			if ( is_string( $local ) ) {
				$postarr['post_date']     = $local;
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $video->published );
			}
		}

		$result = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $result ) || 0 === $result ) {
			return 0;
		}

		update_post_meta( $result, self::SYNC_KEY, $video->key() );

		/*
		 * The two columns `wp_insert_post()` just filled are shadowed here, before
		 * `apply()` ever looks at them. Without this the guard would open the post
		 * and find a title and a status it has no record of writing — which is
		 * precisely the shape of "somebody else put this here" — and lock both on
		 * the post's first second of life, so the title would never sync again.
		 *
		 * They are read back rather than assumed: `wp_insert_post()` runs the row
		 * through `sanitize_post()` and, on a cron run with no user, through kses,
		 * so what is stored is not necessarily what was sent. The shadow has to
		 * record what is stored, or the very next run reads the difference as an
		 * edit.
		 */
		$stored = get_post( $result );

		update_post_meta(
			$result,
			self::SHADOW,
			array(
				'post_title'  => $stored instanceof WP_Post ? $stored->post_title : $video->title,
				'post_status' => $stored instanceof WP_Post ? $stored->post_status : 'publish',
			)
		);

		return $result;
	}

	/**
	 * Write the fields the sync still owns, and record the ones it no longer does.
	 *
	 * @param int                   $post_id  The post.
	 * @param array<string, string> $incoming What the platform says each field should be.
	 * @return array{changed: bool, locked: int} Whether anything was written, and how
	 *                                           many fields became the author's on this run.
	 */
	private function apply( int $post_id, array $incoming ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array(
				'changed' => false,
				'locked'  => 0,
			);
		}

		$shadow  = $this->shadow( $post_id );
		$locked  = $this->locked( $post_id );
		$title   = null;
		$status  = null;
		$changed = false;
		$newly   = 0;
		$owned   = array();

		foreach ( $incoming as $field => $value ) {
			$decision = AuthorEdits::decide(
				$this->current( $post, $field ),
				$value,
				$shadow[ $field ] ?? null,
				in_array( $field, $locked, true )
			);

			if ( FieldDecision::Locked === $decision ) {
				if ( ! in_array( $field, $locked, true ) ) {
					$locked[] = $field;
					++$newly;
				}

				continue;
			}

			$owned[] = $field;

			if ( FieldDecision::Unchanged === $decision ) {
				continue;
			}

			if ( 'post_title' === $field ) {
				$title = $value;
			} elseif ( 'post_status' === $field ) {
				$status = $value;
			} else {
				update_post_meta( $post_id, $field, $value );
			}

			$changed = true;
		}

		$this->write_columns( $post_id, $title, $status );

		/*
		 * The shadow records what is *stored*, not what was sent. A title goes
		 * through `sanitize_post()` and, on a cron run with no logged-in user,
		 * through kses; a meta value goes through its registered sanitizer. If the
		 * shadow held the value the sync tried to write, the next run would read
		 * the difference as an edit and hand the field to David for good — a lock
		 * nobody asked for, on a field nobody touched.
		 */
		$stored = get_post( $post_id );

		foreach ( $owned as $field ) {
			$shadow[ $field ] = $stored instanceof WP_Post
				? $this->current( $stored, $field )
				: $incoming[ $field ];
		}

		update_post_meta( $post_id, self::SHADOW, $shadow );
		update_post_meta( $post_id, self::LOCKED, array_values( $locked ) );

		return array(
			'changed' => $changed,
			'locked'  => $newly,
		);
	}

	/**
	 * Write whichever of the post's own columns changed, in one update.
	 *
	 * The array is assembled out of literals rather than out of the loop above,
	 * because `wp_update_post()` declares a shape and a dynamically-keyed array
	 * cannot satisfy it. Two columns is also the whole of what the sync ever
	 * writes, so the branches are exhaustive rather than merely current.
	 *
	 * @param int         $post_id The post.
	 * @param string|null $title   The new title, or null to leave it.
	 * @param string|null $status  The new status, or null to leave it.
	 * @return void
	 */
	private function write_columns( int $post_id, ?string $title, ?string $status ): void {
		$update = array( 'ID' => $post_id );

		if ( null !== $title ) {
			$update['post_title'] = self::slashed( $title );
		}

		if ( null !== $status ) {
			$update['post_status'] = $status;
		}

		if ( 1 === count( $update ) ) {
			return;
		}

		wp_update_post( $update );
	}

	/**
	 * One value, slashed the way `wp_update_post()` expects to be handed it.
	 *
	 * `wp_slash()` is declared as taking and returning anything, so its answer is
	 * narrowed back to a string here rather than at three call sites.
	 *
	 * @param string $value The value.
	 * @return string
	 */
	private static function slashed( string $value ): string {
		$slashed = wp_slash( $value );

		return is_string( $slashed ) ? $slashed : $value;
	}

	/**
	 * What one field holds right now.
	 *
	 * @param WP_Post $post  The post.
	 * @param string  $field The field name: a `post_` column, or a meta key.
	 * @return string
	 */
	private function current( WP_Post $post, string $field ): string {
		if ( 'post_title' === $field ) {
			return $post->post_title;
		}

		if ( 'post_status' === $field ) {
			return $post->post_status;
		}

		$value = get_post_meta( $post->ID, $field, true );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * The shadow copy of what the sync last wrote to one post.
	 *
	 * @param int $post_id The post.
	 * @return array<string, string>
	 */
	private function shadow( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::SHADOW, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$shadow = array();

		foreach ( $stored as $field => $value ) {
			if ( is_string( $field ) && is_scalar( $value ) ) {
				$shadow[ $field ] = (string) $value;
			}
		}

		return $shadow;
	}

	/**
	 * The fields an author has taken over on one post.
	 *
	 * @param int $post_id The post.
	 * @return list<string>
	 */
	private function locked( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::LOCKED, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$locked = array();

		foreach ( $stored as $field ) {
			if ( is_string( $field ) && '' !== $field && ! in_array( $field, $locked, true ) ) {
				$locked[] = $field;
			}
		}

		return $locked;
	}

	/**
	 * Every post this sync has created for one platform, by its key.
	 *
	 * @param VideoSource $source The platform.
	 * @return array<string, int> Sync key to post ID.
	 */
	private function synced( VideoSource $source ): array {
		$query = new WP_Query(
			array(
				'post_type'              => PostTypes::VIDEO,
				'post_status'            => 'any',
				'posts_per_page'         => self::MAX_ROWS,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an EXISTS check on an indexed meta_key, run by a background job over a few hundred rows. The alternative is one query per remote video.
				'meta_query'             => array(
					array(
						'key'     => self::SYNC_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$prefix = RemoteVideo::key_prefix( $source );
		$map    = array();

		foreach ( $query->posts as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				continue;
			}

			$key = get_post_meta( $post_id, self::SYNC_KEY, true );

			if ( ! is_string( $key ) || ! str_starts_with( $key, $prefix ) || isset( $map[ $key ] ) ) {
				continue;
			}

			$map[ $key ] = $post_id;
		}

		return $map;
	}

	/**
	 * Add one platform's counts to the running totals.
	 *
	 * @param array{added: int, updated: int, unchanged: int, unpublished: int, locked: int} $totals The running totals.
	 * @param array{added: int, updated: int, unchanged: int, unpublished: int, locked: int} $counts One platform's.
	 * @return array{added: int, updated: int, unchanged: int, unpublished: int, locked: int}
	 */
	private function add( array $totals, array $counts ): array {
		foreach ( $counts as $name => $count ) {
			$totals[ $name ] += $count;
		}

		return $totals;
	}

	/**
	 * The design's "when it went out" stamp for a publication date.
	 *
	 * @param int $timestamp Unix timestamp, or 0 when the platform did not say.
	 * @return string e.g. `AUG 2026`, or '' when there is no date.
	 */
	private static function when( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		$formatted = wp_date( 'M Y', $timestamp );

		/*
		 * `strtoupper()` rather than a multibyte upper: it maps a–z and leaves
		 * every byte above 0x7F alone, so a translated month name in a locale
		 * this project does not ship survives unchanged instead of being
		 * corrupted by a byte-wise fold.
		 */
		return is_string( $formatted ) ? strtoupper( $formatted ) : '';
	}

	/**
	 * The hue the design gives each platform's cards.
	 *
	 * @param VideoSource $source The platform.
	 * @return string A `Content\Tone` value.
	 */
	private static function tone( VideoSource $source ): string {
		return match ( $source ) {
			VideoSource::Twitch  => 'purple',
			VideoSource::YouTube => 'teal',
		};
	}
}
