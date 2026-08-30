<?php
/**
 * The live card's copy: Twitch's, unless David wrote his own.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

use DP\Core\Content\Tone;
use DP\Core\Content\VideoSource;

/**
 * Composes the featured panel's live entry out of the stream and, if there is
 * one, the `dp_live` post.
 *
 * David's requirement for this page was "live on Twitch? shown automatically. I
 * should not have to manage any of that by hand." Detecting the stream was
 * already automatic (`LiveStatus`); its *copy* was not, because the card read a
 * `dp_video` post carrying a hand-typed title, note and strapline. This class
 * closes that: one `helix/streams` call already answers both "is he live" and
 * "what is he streaming", so the same cached answer fills the card.
 *
 * ## Precedence — field by field, the author wins
 *
 * The rule is `AuthorEdits`', and it is literally that function rather than a
 * restatement of it, so the two cannot drift: **a derivation fills a blank, and
 * where a value is present the author's value wins.** Applied per field, not per
 * post — a `dp_live` post with only a note written on it takes its title and its
 * strapline from Twitch and keeps the note.
 *
 * | Card | David's field | From Twitch when his is blank |
 * |---|---|---|
 * | Heading | the post title | `title` |
 * | Note | `dp_note` | nothing — the card renders without one |
 * | Strapline | `dp_live_meta` | "Streaming now · 1H 12M in", from `started_at` |
 * | Hue | `dp_tone` | pink, which is the design's live tone |
 *
 * With no `dp_live` post at all, every one of those is Twitch's and David
 * manages nothing. The post is now an override, not a requirement.
 *
 * ## The strapline, and why it carries a timestamp
 *
 * The design prints an elapsed time. Helix reports a start instant. The elapsed
 * value is therefore computed at render — which is right at render, and rots by
 * a minute a minute afterwards. A page held by a full-page cache would print a
 * number that goes on claiming to be live long after it stopped being true.
 *
 * So the derived strapline ships with the start instant beside it (`$since`) and
 * a template to re-fill (`$format`), and the theme's script recomputes the
 * number in the reader's browser. Server-rendered, client-corrected: with no
 * JavaScript the number is exactly as fresh as the page that carries it, and
 * with JavaScript it is exactly as fresh as the reader's clock.
 *
 * **A strapline David wrote never ticks.** `$since` is zero whenever
 * `dp_live_meta` is his, so nothing in the markup invites the script to rewrite
 * a value he set — the same rule that governs the sync.
 */
final class LiveEntry {

	/**
	 * Constructor.
	 *
	 * @param Video  $entry  The panel's entry, ready to render.
	 * @param int    $since  Unix start instant for the ticking strapline, or 0
	 *                       when the strapline must not tick — David wrote it,
	 *                       or Twitch gave no readable start.
	 * @param string $format The strapline template, `%s` where the elapsed time
	 *                       goes; '' whenever `$since` is 0.
	 */
	private function __construct(
		public readonly Video $entry,
		public readonly int $since,
		public readonly string $format
	) {}

	/**
	 * Build the live entry.
	 *
	 * @param Video|null $authored The published `dp_live` post, or null when
	 *                             David has not written one.
	 * @param LiveStream $stream   What Twitch says is on air.
	 * @param int        $now      The current Unix time.
	 * @return self
	 */
	public static function compose( ?Video $authored, LiveStream $stream, int $now ): self {
		$authored_title = null === $authored ? '' : $authored->title;
		$authored_note  = null === $authored ? '' : $authored->note;
		$authored_meta  = null === $authored ? '' : $authored->live_meta;

		/* translators: %s: how long the stream has been running so far, e.g. "1H 12M". */
		$frame = __( 'Streaming now · %s in', 'dp-core' );

		$elapsed = $stream->elapsed( $now );
		$derived = '' === $elapsed ? __( 'Streaming now', 'dp-core' ) : sprintf( $frame, $elapsed );

		/*
		 * A stream with no title is vanishingly rare and not a reason to hide a
		 * broadcast that is genuinely happening, so the heading falls back to a
		 * label rather than to invented prose about what David is doing.
		 */
		$title = self::prefer( $authored_title, '' !== $stream->title ? $stream->title : __( 'Live now', 'dp-core' ) );

		/*
		 * The note stays blank until David writes one — the same answer he gave
		 * for the video cards, and the same reason: Twitch's category ("Software
		 * and Game Development") is a dropdown he once picked, not a sentence
		 * about this stream. A card with no note renders without one, and
		 * `$stream->category` stays on the model for whoever wants it next.
		 */
		$note = $authored_note;

		$entry = new Video(
			null === $authored ? 0 : $authored->id,
			$title,
			VideoSource::Twitch,
			'',
			$authored->tone ?? Tone::Pink,
			'',
			'',
			$note,
			true,
			self::prefer( $authored_meta, $derived ),
			''
		);

		$ticks = '' === $authored_meta && $stream->started > 0;

		return new self(
			$entry,
			$ticks ? $stream->started : 0,
			$ticks ? sprintf( $frame, '%s' ) : ''
		);
	}

	/**
	 * One field, resolved: David's if he wrote one, otherwise Twitch's.
	 *
	 * `AuthorEdits::decide()` is asked rather than reimplemented. With no shadow
	 * and no existing lock it answers exactly the rule this needs — a present
	 * value belongs to whoever put it there — and asking it here means a change
	 * to that rule reaches the live card as well as the sync.
	 *
	 * @param string $authored What is on the `dp_live` post, or '' for no post.
	 * @param string $derived  What Twitch says.
	 * @return string
	 */
	private static function prefer( string $authored, string $derived ): string {
		return FieldDecision::Locked === AuthorEdits::decide( $authored, $derived, null, false )
			? $authored
			: $derived;
	}
}
