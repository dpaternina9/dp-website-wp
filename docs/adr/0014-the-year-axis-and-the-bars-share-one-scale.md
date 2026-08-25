# ADR-0014 — The year axis is drawn on the bars' scale, and the track ends now

## Status

Accepted — 2026-08-24. Amends the parity harness of ADR-0012 with a third kind of
entry, and the block attributes of ADR-0007 §7. Supersedes nothing.

## Context

David, looking at the chart on the work page:

> the lines don't go all the way to the end. In this case they read like they
> ended sometime in 2025 instead of going all the way to 2026.

He was right, and the cause is not in the build. It is two separate defects that
happen to appear at the same corner of the same element, and they compound.

### 1. The axis and the bars are on two different scales

`TimelineChart.dc.html` states its own positioning at the bottom of the file:

```
pos(y) = ((y - yearStart) / (yearEnd - yearStart + 1)) * 100
```

`DP\Core\Content\Timeline\Geometry` transcribes that, and its docblock has
explained the `+ 1` since Phase 3: the track covers **thirteen whole years**,
2014 through 2026 inclusive, so the last year *occupies* the final thirteenth
rather than terminating the axis. On that scale:

| | |
|---|---|
| a year is worth | 7.69% of the track |
| `pos(2026)` | **92.31%** |
| a role running to `2026.4` (May 2026) ends at | **95.38%** |

The year labels are a different element and a different rule. The design draws
them as a flex row — `<div style="display: flex; justify-content: space-between">`,
thirteen `<span>`s inside it — and the theme transcribed that faithfully. But
`space-between` divides the free space by the number of *gaps*, which is twelve:

| | |
|---|---|
| a label advances | 8.33% of the track |
| the "2026" label's left edge | **100.00%** |

By the right-hand edge the two scales are **7.69 percentage points apart**. So a
role that genuinely runs to the present ends at 95.4% while the label naming its
own year has been pushed to 100%, and the eye reads the bar as stopping before
2026 — which is exactly the sentence David wrote. Every bar on the chart is off
against the axis by a growing amount, from zero at 2014 to a whole year's width
at the right.

This is a defect **in the design**, not in the transcription of it. Both halves
are stated plainly in `design-source/`; they simply disagree with each other, and
nothing in the design tool would have shown it, because the design's own fixture
draws bars and labels that are close enough at the left of the chart to look
right in a screenshot.

### 2. The track does not advance

`Blocks\Timeline::geometry()` read `lastYear` from the block's attributes and
fell back to `Geometry::DESIGN_LAST_YEAR`, which is 2026 because that is the
track the design ships. `block.json` also declared `"lastYear": { "default": 2026 }`.

A declared default is sent on every render, so the fallback was never even
reached — and either way, nothing moved the number. On 1 January 2027 the chart
would still have ended at 2026, with a role marked "now" running past the final
tick, and it would have gone on doing that until somebody edited a block
attribute. ADR-0007 recorded "David can extend the track past 2026 by editing two
block attributes" as a *feature*; that is a maintenance task disguised as one.

The two defects hide each other. Under `space-between` the label for the last
year sits at the far edge whatever the last year is, so a stale axis and a
correctly-drawn one look the same from a distance.

## Decision

### 1. The labels sit on `Geometry`'s scale, and this is a deliberate divergence from the design

`.dp-tl-years` becomes a grid of equal columns:

```css
.dp-tl-years {
	display: none;                          /* grid, in the two modes with a track */
	grid-auto-flow: column;
	grid-auto-columns: minmax(0, 1fr);
}
```

`grid-auto-flow: column` gives each label a column of its own and
`grid-auto-columns: minmax(0, 1fr)` makes them equal, so with `n` labels the
`n`th begins at `n / n_total` of the track — which **is** `Geometry::position()`
of the year it names, by construction. No count reaches the stylesheet, no
percentage is written down, and adding a fourteenth year cannot put the two out
of step, because there is no arithmetic to keep in step.

`justify-content` is dropped rather than carried over: a grid whose columns
already fill the container has no free space to distribute, so keeping the
declaration would be a line that reads as intent and does nothing.

The invariant, stated once and asserted on both sides:

> **For every labelled year, the label's left edge and `Geometry::position( year )`
> agree.**

`tests/Unit/TimelineGeometryTest::test_each_year_label_starts_where_its_own_year_does`
asserts it as arithmetic over four different track lengths;
`tests/e2e/timeline.spec.ts` asserts it as rendered pixels, after first checking
that the axis box and the bar track box are the same box.

**CLAUDE.md §5 says the design wins. David has overridden that here**, on
2026-08-24, and does not want the change round-tripped through Claude Design. So
`design-source/` keeps saying `space-between`, and the divergence is recorded
rather than reconciled.

### 2. The parity harness records the divergence as a divergence

A fixture entry may now carry a **`divergence`**: a map of property to reason,
beside the existing `skip` and `omitted`. The three say different things, and the
difference is the point:

| Field | What it means |
|---|---|
| `omitted` | The entry pins part of a style object and lists the properties it did not take. |
| `skip` | The design is silent, or contradicts itself, and the theme had to pick. |
| `divergence` | The design is perfectly clear, and we deliberately do something else. |

`chart.years` carries a `divergence` on `display` and on `justify-content`, each
with the arithmetic above and David's name and date. `design-parity.spec.ts`
skips those two properties and nothing else.

Three things keep this from becoming a hole:

1. **The entry stays.** Deleting it would make the sweep pass by measuring
   nothing, which is the failure the second amendment to ADR-0012 spent four days
   inside. `DesignBaselineTest::test_the_axis_divergence_is_recorded_rather_than_dropped`
   and a named test in `design-parity.spec.ts` both fail if it disappears, if a
   second entry grows one, or if the design's own declarations stop being
   `display: flex; justify-content: space-between`.
2. **The anchor is unchanged.** It still has to match exactly one element of
   `TimelineChart.dc.html`. If that row moves in Claude Design, the anchor stops
   matching and `composer design:check` fails — which is the signal to re-read
   this ADR, not to re-run the generator.
3. **A reason has to name an ADR.** `DesignBaseline::assert_recorded()` throws on
   a divergence whose reason does not contain `ADR-`, so a disagreement with the
   design cannot be introduced in a fixture and explained in a commit message.

### 3. An unpinned track ends at the current year

`Geometry::through( ?int $first, ?int $last, int $current_year )` is the new
named constructor, and `Blocks\Timeline::geometry()` is the only caller.

- `firstYear` stays 2014, still declared in `block.json`.
- **`lastYear` has no default in `block.json` any more.** Absent, it means
  "wherever we are now". Present, it means David pinned the track — to hold it
  still, or to run it past the present for something already scheduled. A
  declared default of 2026 would have been indistinguishable from the second of
  those, which is why it had to go.
- The year comes from `wp_date( 'Y' )` — the **site's** timezone. On 31 December
  a site in Bogotá is still five hours short of the UTC one, and `date()` would
  read the container's clock, which is a third answer nobody chose. The fallback,
  if `wp_date()` ever fails, is `gmdate( 'Y' )`: a year that is wrong by hours one
  day a year beats a year that is wrong forever.

**The year is injected, never read inside `Geometry`.** That class is
WordPress-free and unit-tested with nothing bootstrapped, and it stays that way;
the merge queue already records that Brain Monkey cannot stand in for `time()`.
So a test that wants a year boundary passes the boundary. `Blocks\Timeline` takes
an optional `?int $current_year` for the same reason, and the integration suite's
`render()` helper defaults it to `Geometry::DESIGN_LAST_YEAR` so that every bar
percentage asserted in that file goes on meaning the same thing after midnight on
31 December — a suite that passes all year and fails on New Year's Day would find
out at the worst possible moment.

### 4. Degenerate tracks resolve; they never throw

`through()` runs on a public page, so it raises nothing:

- a `first_year` outside `Year::MIN_YEAR..MAX_YEAR` is pulled back inside it;
- a `last_year` at or before `first_year` — David typing 2010 into a block that
  starts in 2014 — is **discarded**, and the default is used. The half of his
  intent that *is* answerable is kept: the track still begins where he said. The
  previous behaviour threw away both halves and fell back to the design's whole
  track.
- a `last_year` past the end of the calendar is clamped to it.

`test_nothing_a_block_can_carry_can_fatal_a_page` sweeps `PHP_INT_MIN`,
`PHP_INT_MAX`, 0, 1899, 2201 and `null` in every combination against five
different "current years" and asserts a drawable track comes back from all of
them.

### 5. A future-dated role does **not** extend the track

David can date something into 2028. The track still ends at the current year, and
`Geometry::position()` clamps the bar to the right-hand edge — the same thing the
design already does to a role that starts before the first labelled year.

Three reasons, and the first is the one that decides it:

- **The track is a statement, and `lastYear` is where it is made.** Reading the
  furthest end date out of the content instead would let one post silently
  rescale every bar on the chart, with nothing on the page to say which post did
  it. The reader would see the whole record move and have no way to find the
  cause.
- **It would cost a second pass.** The geometry is built *before* the lanes are
  read — `Chart` is constructed with a `Geometry` — so extending would mean
  querying the maximum `dp_end` first and then querying again for the rows.
- **The clamp is not a silent truncation of anything a reader can measure.** A
  bar that reaches the final edge reads as "still going", which is what a role
  dated into the future is. If David wants the future *shown*, the attribute that
  shows it already exists and is now the only thing that means "pin".

## Consequences

**What this makes easy.**

- A role running to the present reaches the label for its own year, at every
  width, on every track length, for as long as the site is up.
- The chart is correct on 1 January without anybody touching it, and the year
  boundary is asserted in both directions in three suites.
- The axis has no arithmetic in it. A fourteenth year is a row in the database
  and nothing else — no CSS, no count, no attribute.

**What this makes hard, and what it costs.**

- **`design-source/` and the theme now disagree on one element, permanently.**
  That is a real cost and this ADR is the payment. Anyone re-importing the design
  will find `chart.years` failing its anchor if the row has moved, and has to
  come back here to decide what to do; the fixture's `note` says so, and so does
  the comment above the rule in `components.css`.
- **The last label has less room than it used to.** On the design's scale it ran
  to the right-hand edge; on the bars' scale it begins at 92.3% with the final
  thirteenth to sit in. Measured, that is about 36px at the narrowest width bars
  mode is ever drawn at — the container query's own 700px threshold, minus a
  200px label column and a 24px gap — against four mono characters at `--fs-xs`
  of roughly 32px. It fits, and it is checked rather than assumed:
  `tests/e2e/timeline.spec.ts` asserts the last label's `scrollWidth` fits its own
  column *and* that its right edge does not pass the track's, at two widths. If a
  future track ever runs long enough to break that — around twenty years at the
  narrowest bars width — the failure names the column width and the ink width,
  which is enough to decide between a shorter label and a wider threshold.
- **`composer design:check` no longer asserts two properties it used to.** They
  are replaced by stronger assertions in a different file — a rendered position
  compared against `Geometry::position()`, rather than a declaration compared
  against a declaration — but the sweep itself is two properties smaller, and
  that is worth writing down rather than netting off.
- **The chart's markup now depends on the day it is rendered.** Any cache in
  front of the work page has to expire at least yearly. Nothing in the project
  caches rendered blocks today; this is a note for whatever does.
- **`Geometry::DESIGN_LAST_YEAR` is now a fact about the design and nothing
  else.** It is still 2026, it still describes the track
  `TimelineChart.dc.html` ships, and it is deliberately *not* repurposed to mean
  "now" — the constant is what a re-import has to disagree with.

**What is invisible today.** The current year is 2026 and the design's last year
is 2026, so §3 changes nothing a reader can see until 1 January 2027. That is the
argument for the unit and integration tests carrying an injected year rather than
the clock: without them this change would be untested for four months.

## Alternatives considered

**Fix the design instead: change `space-between` in Claude Design and re-import.**
The correct move by CLAUDE.md §5, and David explicitly ruled it out for this
change. It is also not obviously expressible: the design tool has no stylesheet
and no container to hang a grid on, and the axis would have to become thirteen
absolutely-positioned spans with computed `left` values — which is a worse
drawing of the same idea, and one that would then have to be transcribed back
into CSS anyway.

**Give each label a computed `left: pos(year)%` inline.** Exactly the design's
own arithmetic, said in the markup. Rejected on ADR-0007 §7: geometry is the only
thing allowed to reach the page as an inline style, and there is an integration
test asserting that every `style` attribute in the chart matches
`left:…%;width:…%;max-width:…%;min-width:…px`. Thirteen more inline styles would
either break that test or force it to be loosened, and the grid says the same
thing with no per-element state at all.

**Pass the span into the CSS as a custom property and use `repeat(var(--n), 1fr)`.**
Works, and needs an inline style on `.dp-tl-years` carrying a count — the same
objection as above, plus a number that has to be kept in step with the number of
children. `grid-auto-flow: column` derives it from the children themselves.

**Drop the `chart.years` entry from the baseline.** The obvious way to make
`composer design:check` pass, and the reason this ADR has a section about it. An
entry that is not in the fixture cannot fail; a sweep that measures nothing
passes. ADR-0012's second amendment is four days of precisely that failure mode,
and repeating it deliberately would be worse than repeating it by accident.

**Keep `DESIGN_LAST_YEAR` as the default and let David bump it.** The status quo,
and ADR-0007 called it a feature. It is a recurring manual task with a silent
failure mode: nothing on the page says the axis is stale, and the person who
would notice is the one person who already knows what the chart is supposed to
say.

**Extend the track to the furthest `dp_end` in the content.** Covered in §5. One
post rescaling the whole chart, plus a second query, in exchange for removing an
attribute that already does the job explicitly.

**Read the year with `date( 'Y' )` or `gmdate( 'Y' )`.** `date()` is the
container's clock, which on this project is UTC in CI, UTC in `wp-env` and
whatever the host says in production — three answers to one question. `gmdate()`
is at least honest about being UTC, and is kept as the fallback, but the year
David is living in is the year the site's timezone says it is.

**Mock the clock in the unit tests.** Brain Monkey cannot stand in for `time()`,
which the merge queue already records. Injecting the year is smaller than any
mock would have been, keeps `Geometry` free of WordPress, and makes the year
boundary a parameter of a test rather than a fixture of one.
