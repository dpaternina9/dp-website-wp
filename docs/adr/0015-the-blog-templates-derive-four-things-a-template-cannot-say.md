# ADR-0015 — The blog templates derive four things a template cannot say

## Status

Accepted — 2026-08-25. **Partly superseded by
[ADR-0016](0016-a-post-carries-no-fields-of-ours.md) — 2026-08-26**: the
`dp_series_featured` nomination below is replaced by a derivation, and the part
number in `%dp-part%` is now the post's position in its series rather than a
stored field. Counts, page state and the dead pager step stand as written.

## Context

The four writing templates — `home`, `single`, `category`, `taxonomy-dp_series` —
were built in Phase 5 and reviewed against `design-source/` for the first time in
this pass. Most of what was missing was markup and CSS. Four things were missing
because **no block, and no template, can express them**, and each of the four had
simply been dropped rather than solved:

1. **Counts.** The design's pager prints `1–10 OF 24 POSTS`, its category head
   prints `24 POSTS · NEWEST FIRST`, and its series hero prints
   `3 PARTS UP · 4 DRAFTED`. All three are computed in the design's own script
   block from a result set. Core has no block that prints a count, and a
   template is static markup.

2. **Page state.** `pager.show` is `matching.length > PER_PAGE` and `pager.atEnd`
   is `curPage === pageCount`; the design draws a pager bar and a closing panel
   only when those hold. `core/query-pagination` hides *itself* when empty, but
   the bar the design draws around it — a rule, 40px of margin and a range line —
   is the theme's markup, and it would render on a one-page archive as a stray
   horizontal line above a wall of nothing.

3. **A step with nowhere to go.** `stepStyle(enabled)` renders PREV on page one
   at `opacity: 0.45`. Core renders the empty string for a step with no target,
   so the control row is a different width on page one than on page two.

4. **Two links whose target is content, not a route.** "READ MY LIFE STORY IN
   ORDER →" on the blog index points at a series archive; "← PART 1" in a post's
   series footer names the part number of the post it points at. CLAUDE.md §5.1
   forbids the theme from naming either by slug, and neither is a site-wide
   destination of the kind ADR-0006 and ADR-0008 resolve — one is content David
   chooses, the other is a property of the post being read.

## Decision

**Each of the four is derived at render time from something already in the
database, through the smallest mechanism WordPress already provides.**

**Counts come from a block bindings source.** `DP\Theme\Query\ArchiveFacts`
registers `dpaternina/archive` with four allowlisted keys — `range`, `count`,
`deck`, `series-written` — reading the *main* query and the queried term. This is
the mechanism `DP\Theme\Chrome\SiteFacts` already established for the footer's
year: a template binds a paragraph, an unlisted key returns `null`, and `null`
leaves the block's own content in place. The copy around each number stays in the
template as a `text` argument, so it stays translatable and David's.

`deck` is on that list for a reason worth naming: the series archive drew **no
deck at all**, because the template used `core/term-description` and the design's
deck is `dp_series_deck` term meta. The block was in the template, the value was
in the database, and the two had never been introduced.

**Page state is a class the template asks by.** `DP\Theme\Query\Pagination`
drops any block carrying `dp-when-paginated` unless the archive runs to more than
one page, and `dp-when-last-page` unless this is its last. The class names what it
tests, so a template that uses one is readable without opening the class.

**A dead step is drawn, not dropped.** The same filter puts back the step core
omitted, as a `<span>` with `aria-disabled="true"` and no `href` — the exact
treatment ADR-0008 gives an unresolved destination, because it is the same
situation: a control that must occupy its space and must not be reachable.

**The series is nominated, the part is substituted.**
`dp_series_featured` is a new registered term meta field on `dp-core`'s series
taxonomy, and the `series` destination resolves to the term carrying it. With
nothing nominated and exactly one series — a fresh site, and the design's own
fixture — that one is the answer, so the link works before David decides
anything. With several and nothing nominated it does not resolve, and ADR-0008
takes over.

The part number travels in the navigation label as the token `%dp-part%`, which
`previous_post_link` / `next_post_link` substitute from the adjacent post those
filters are handed. No second query, and the token is visible in the site
editor's label field, which is the point: the template says out loud that the
label is computed.

## Consequences

**What this makes easy.** Every one of the four is now a line of template markup
that says what it wants, and one class that knows how to answer. A second archive
template gets the pager by echoing `DP\Theme\Patterns::pager()`; a second count
is a key on an existing allowlist.

**What it costs.** `Pagination::hide_when_the_query_says_so()` runs on
`render_block`, so it is called for every block on every page. It reads one
attribute and returns, and the alternative — a filter per block name — would have
to name blocks the template is free to change.

**What it commits us to.** `dp-when-paginated` and `dp-when-last-page` are
public-ish: they are in the saved template markup and David can move the blocks
that carry them. So is `%dp-part%`, which is visible copy in the editor.

**The site editor draws all of it, always.** The canvas renders saved markup and
has no page number, so the pager bar and the closing panel are both visible there
whatever the front end decides. That is the same asymmetry `core/query-no-results`
has had since it shipped, and it is the one ADR-0008 says cannot be closed: there
is no supported hook that removes a static block from the canvas for a reason only
the server knows.

**`dp_series_featured` has no admin field yet.** It is registered with
`show_in_rest`, so it is settable through the REST API and the seeder sets it —
but so is `dp_series_deck`, which has had no term-edit UI since Phase 3. Both
want the same twenty-line `dp_series_edit_form_fields` panel, and that is one
change, not two. It is not in this ADR because it is an implementation gap, not a
decision; it is in the phase report as something David needs.

## Alternatives considered

**A dynamic block for the pager.** Rejected. The theme's one dynamic block,
`dpaternina/series-planned`, still draws as `core/missing` in the site editor
(ADR-0009's closing note), because the theme ships no JavaScript build. A second
one would double a known defect to buy markup a bindings source and a filter
already give us — and the range line would stop being a paragraph David can
reword.

**Hardcode the series slug.** Rejected outright by CLAUDE.md §5.1, and it is also
the failure ADR-0008 calls the hardest of the three to notice: a link that works,
to the wrong place.

**Derive the featured series by rule** — the one with the most published parts,
or the oldest term. Rejected: both are guesses dressed as derivations, and
neither is a thing David could correct without editing code. A flag he sets is a
decision recorded where the decision belongs.

**Put the part number in the label by hand.** "← Earlier part" instead of
"← PART 1". Rejected: the design prints the number, the number is the reading
order the whole series page is about, and `previous_post_link` hands us the
adjacent post for free.

**Hide the pager bar in CSS** with `:has()` over the rendered pagination.
Rejected: the bar's border and margin would still occupy the document in the
editor *and* on any page where the selector failed, and "is there more than one
page" is a fact about the query, not about the DOM.
