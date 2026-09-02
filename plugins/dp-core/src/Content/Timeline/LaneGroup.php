<?php
/**
 * Consecutive lanes at one company, gathered under one header.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content\Timeline;

use DP\Core\Content\Tone;

/**
 * A run of adjacent lanes that all name the same organisation.
 *
 * Two roles at one company are two `dp_role` posts carrying the same
 * `post_title`, because the company **is** the title and the job is
 * `dp_role_title`. Drawn as two independent lanes the company name is printed
 * twice, once above the other, which reads as two employers rather than as one
 * employer and a promotion. This gathers them: one company header, and the role
 * rows beneath it saying what each job was.
 *
 * **Only consecutive lanes group, and that is the whole of ADR-0018 in this
 * class.** Lane order is `menu_order` then post date — `Chart::published()` —
 * which is the sequence David sets in Page Attributes. So the trigger for
 * grouping is two posts with the same title that he has placed next to each
 * other: a state he can see in the admin list, and change there. Gathering
 * *non*-adjacent lanes would be a derivation reordering his record with nothing
 * on the page or in the editor to say that it had happened, which is exactly
 * what rule 2 of that ADR forbids. A company he interrupts with another job is
 * two entries because he said it was two entries.
 *
 * A run of one is still a `LaneGroup`, so the renderer has one loop rather than
 * two. `is_shared()` is what distinguishes it, and a run of one is drawn exactly
 * as a lane has always been drawn — no wrapper, no header, no markup change.
 *
 * Pure, and deliberately free of WordPress, for the reason `Geometry` is:
 * grouping is a decision, it is decided here with unit tests, and the layer
 * above renders what it is handed.
 */
final class LaneGroup {

	/**
	 * Constructor. Private: use `consecutive()`, which is the only rule.
	 *
	 * @param string           $org    The organisation every lane in the run names.
	 * @param string           $key    The element id the header's name carries.
	 * @param Tone|null        $accent The accent the whole run shares, or null when it does not share one.
	 * @param Bar|null         $bar    Earliest start to latest end, or null when no lane has a bar.
	 * @param Lane             $first  The head of the run, which is what a run of one is drawn as.
	 * @param array<int, Lane> $lanes  The lanes, in the order the admin put them. Never empty.
	 */
	private function __construct(
		public readonly string $org,
		public readonly string $key,
		public readonly ?Tone $accent,
		public readonly ?Bar $bar,
		public readonly Lane $first,
		public readonly array $lanes
	) {}

	/**
	 * Gather each run of adjacent lanes that name the same organisation.
	 *
	 * Every lane comes back exactly once and in the order it arrived: this
	 * partitions, it never sorts, drops or promotes.
	 *
	 * The comparison is on the trimmed title, because a stray trailing space is
	 * invisible in the admin and would otherwise be the difference between one
	 * header and two. It is case-sensitive: "Aplyca" and "APLYCA" are two
	 * different pieces of copy, and grouping them would mean picking one of them
	 * to print, which is a decision this class does not get to make. A lane with
	 * an empty title never joins a run, so a half-finished post cannot swallow
	 * the one above it.
	 *
	 * Each lane is compared against the **head** of the run rather than against
	 * the lane before it. The two are the same rule — string equality is
	 * transitive — and comparing against the head is what makes an empty title
	 * unable to start a run that later lanes could join.
	 *
	 * @param array<int, Lane> $lanes Every lane on the chart, in order.
	 * @return list<self>
	 */
	public static function consecutive( array $lanes ): array {
		$groups = array();
		$run    = array();
		$first  = null;

		foreach ( $lanes as $lane ) {
			if ( null !== $first && ! self::same_company( $first, $lane ) ) {
				$groups[] = self::of( $first, $run );
				$run      = array();
				$first    = null;
			}

			if ( null === $first ) {
				$first = $lane;
			}

			$run[] = $lane;
		}

		if ( null !== $first ) {
			$groups[] = self::of( $first, $run );
		}

		return $groups;
	}

	/**
	 * Whether this run is more than one lane, and therefore earns a header.
	 *
	 * @return bool
	 */
	public function is_shared(): bool {
		return count( $this->lanes ) > 1;
	}

	/**
	 * Build one group from a non-empty run.
	 *
	 * @param Lane             $first The head of the run.
	 * @param array<int, Lane> $run   The lanes, all naming the same organisation.
	 * @return self
	 */
	private static function of( Lane $first, array $run ): self {
		/*
		 * `dp-tl-group-` rather than a slug of the company name: it is derived
		 * from an entry key, which is already unique per post, and the prefix
		 * cannot collide with one. Entry keys are `dp-role-…` and `dp-ship-…`
		 * (`Chart::entry_key()`), so nothing WordPress can generate lands here.
		 * The id exists only so the header can name the group for assistive
		 * technology; no URL carries it.
		 */
		return new self(
			$first->org,
			'dp-tl-group-' . $first->key,
			self::shared_accent( $first, $run ),
			self::span( $run ),
			$first,
			$run
		);
	}

	/**
	 * Whether two lanes name the same organisation.
	 *
	 * @param Lane $one  The head of the run.
	 * @param Lane $next The lane after it.
	 * @return bool
	 */
	private static function same_company( Lane $one, Lane $next ): bool {
		$name = trim( $one->org );

		return '' !== $name && trim( $next->org ) === $name;
	}

	/**
	 * The accent every lane in the run carries, or null when they disagree.
	 *
	 * A header drawn in a colour none of its rows uses would be a fourth thing
	 * for the legend to explain, so a run that does not agree gets the default.
	 *
	 * @param Lane             $first The head of the run.
	 * @param array<int, Lane> $run   The lanes.
	 * @return Tone|null
	 */
	private static function shared_accent( Lane $first, array $run ): ?Tone {
		foreach ( $run as $lane ) {
			if ( $first->accent !== $lane->accent ) {
				return null;
			}
		}

		return $first->accent;
	}

	/**
	 * The run's combined bar: earliest start to latest end.
	 *
	 * Read off the lanes' own bars rather than off their dates, so the header
	 * cannot be on a different scale from the rows underneath it. `Geometry` has
	 * already clamped both ends of every one of them onto the track, so the
	 * union is on the track too, and `max_width` is restated the way `Geometry`
	 * states it — `100 - pos(start)` — rather than summed from the parts.
	 *
	 * Lanes with no usable dates carry no bar and are skipped; a run in which
	 * none of them has one gets no bar at all, and the header renders without a
	 * track exactly as such a row already does.
	 *
	 * @param array<int, Lane> $run The lanes.
	 * @return Bar|null
	 */
	private static function span( array $run ): ?Bar {
		$left  = null;
		$right = null;

		foreach ( $run as $lane ) {
			if ( null === $lane->bar ) {
				continue;
			}

			$start = $lane->bar->left();
			$end   = $start + $lane->bar->width();

			$left  = null === $left ? $start : min( $left, $start );
			$right = null === $right ? $end : max( $right, $end );
		}

		if ( null === $left || null === $right ) {
			return null;
		}

		return new Bar( $left, $right - $left, 100.0 - $left, BarKind::Role );
	}
}
