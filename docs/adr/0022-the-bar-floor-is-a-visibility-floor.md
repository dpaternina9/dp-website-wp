# ADR-0022 — The bar floor is a visibility floor, not the design's 64 and 40

## Status

Accepted — 2026-09-02. Records a second divergence from `TimelineChart.dc.html`'s
POSITIONING block, alongside the axis divergence of ADR-0014. Supersedes nothing.

## Context

David, looking at the work page after entering his real employment history:

> the calculation in the timeline is a little off, see these two overlapping a lot

Two roles — Imaginamos, April to June 2019, and Aplyca, July to December 2019 —
rendered as two bars of identical length overlapping by roughly three quarters,
reading as concurrent jobs rather than consecutive ones.

`Geometry` was not wrong. The two bars abut to the floating-point digit; the
positions were correct and remain so. What was wrong was the width, and the
width came from the last line of the design's own specification:

```
bar.minWidth = 64 (roles) / 40 (ships)
```

`BarKind::min_width()` transcribed that faithfully in Phase 3. The problem is
what the number means once real data arrives. The work page gives the track
about 832px for a thirteen-year span, so **a year is worth about 64px** — the
role floor is, to within a rounding error, exactly one year wide. Every role
shorter than a year was therefore drawn as a year, and two three-month roles
three months apart came out as two year-long bars sixteen pixels apart.

The floor never showed this in seven phases of development because
`Fixture::roles()` has no role shorter than two years. The floor never binds on
the fixture, so nothing — not the unit tests, not the parity harness, not a
review — could have caught it. It took the real record.

The floor's stated justification was clickability: a sliver "would be
unclickable". That was never true of this build. `TimelineRows::summary()` puts
the label column and the track inside a single `<summary>`, so the whole row is
the disclosure toggle and the bar is decoration. A 10px bar is exactly as
clickable as a 64px one; the design tool's own preview, where a bar was the
click target, is where that reasoning came from and it did not survive the
translation to `<details>`.

## Decision

We keep a floor and we shrink it. `BarKind::min_width()` returns **10px for a
role and 8px for a ship**. The floor's job is to clear the smallest mark a
reader can see, not to guarantee a hit target the row already provides.

The two divergences are recorded in `tests/Support/DesignBaseline.php` against
all four bar entries, citing this ADR, as ADR-0014 §2 requires.

Roles keep the larger of the two numbers so a role still outranks a ship when
both are floored, which is the one thing the design's 64:40 ratio was saying
that survives.

## Consequences

- A bar's length is now its duration to within about six weeks at the work
  page's track width. Before, anything under a year was a lie.
- Sub-year roles render as small ticks. They are legible and clickable, but they
  are visually slight — a three-month job looks like a three-month job, which is
  the point and is also, unavoidably, less prominent than it was.
- We now disagree with `design-source/` in two places rather than one. Both are
  in the same POSITIONING block, and a re-import will flag both. That is the
  harness working, not a failure.
- The numbers stay coupled to a track width we do not control. If the work
  page's layout changes substantially, "10px is about six weeks" changes with
  it. `tests/Unit/TimelineGeometryTest.php` pins the relationship — the floor
  must stay under what a quarter-year is worth — so the coupling fails loudly.

## Alternatives considered

**Keep 64/40 and merge the short roles into longer entries.** Rejected: it asks
David to falsify his record to suit a rendering constant. CLAUDE.md rule 2 runs
the other way — the content is his and the code accommodates it.

**Express the floor as a percentage of the track.** Rejected as more machinery
than the problem needs. The floor exists to clear a perceptual threshold, and a
perceptual threshold is an absolute number of pixels; making it proportional
would make it shrink on exactly the narrow tracks where visibility is already
worst.

**Drop the floor entirely.** Rejected: a zero-length bar — a role whose start and
end are the same month, which the admin permits — would render as nothing at
all, and a row with an empty track reads as missing data rather than as a brief
job. `test_a_zero_length_bar_keeps_its_minimum()` pins that case.

**Widen the track or narrow the year span so 64px is honest.** Rejected: the
span is David's record, not a display parameter, and `firstYear`/`lastYear` are
his to set. Choosing the axis to flatter the floor inverts which of the two is
the fact.
