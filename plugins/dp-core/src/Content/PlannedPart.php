<?php
/**
 * A series part that has not been written yet.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * The four things "Still to come" is allowed to know about a draft.
 *
 * The design publishes a roadmap: the series archive lists parts that do not
 * exist yet, and `docs/plan.md` section 3.1 settles that those live as **draft
 * posts** carrying the series term rather than as a second post type. That
 * decision buys a lot — no stub to delete, ordering and titling for free, a real
 * `WP_Query` to test against — and it costs one thing: draft titles in a series
 * become public.
 *
 * This class is where that cost is contained. It carries a title, a year range,
 * a note and a part number. It does **not** carry the post ID, which is the only
 * reason it cannot carry a permalink: a caller with an ID is one
 * `get_permalink()` away from linking to an unfinished draft, and a caller with
 * one of these has nothing to link to. It does not carry the body either.
 *
 * The guard is therefore structural rather than a rule someone has to remember,
 * which is what plan section 3.1 means by "written to make leaking body content
 * impossible rather than merely unlikely". `ContentSeriesPartsTest` asserts it.
 */
final class PlannedPart {

	/**
	 * Constructor.
	 *
	 * @param string   $title The draft's title, which is the announcement.
	 * @param string   $years The years it will cover, e.g. "1995 — 2007".
	 * @param string   $note  One line on what it is about.
	 * @param int|null $part  Its number in the series, if it has been given one.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $years,
		public readonly string $note,
		public readonly ?int $part
	) {}
}
