# ADR-0013 — E2E content that a global query reads belongs to nobody

## Status

Accepted — 2026-08-23. Complements ADR-0012; supersedes nothing.

## Context

`tests/e2e/` runs `fullyParallel` against **one** WordPress site. The convention
that made that safe was that each spec published its own fixtures under its own
slugs and deleted them again, so no two files could sweep away each other's
content. That convention holds for anything addressed by slug. It does not hold
for anything a **global query** reads, and the work page has two of those:

- the featured cards are `dpLoop: featured-ships` — `dp_ship` posts carrying
  `dp_featured`, three of them, ordered by `dp_end`
  (`DP\Theme\Query\QueryLoops::featured()`);
- the chart below them is every published `dp_role` and `dp_ship` on the site,
  whichever page it happens to be rendered on
  (`DP\Core\Content\Timeline\Chart::published()`).

Neither is scoped to the page it appears on, so a spec that publishes a role, a
ship, or a featured ship is not setting up its own page. It is editing
everyone's. Three files were doing exactly that — `timeline.spec.ts`,
`spacing.spec.ts` and `design-parity.spec.ts` — each under distinct slugs, which
solved the deletion problem and said nothing whatever about the sharing problem.

The suite was set to `workers: 1` to make it stop. That bought a green line at
60–150× the cost — 18 to 39 minutes against 15 seconds — and it did not diagnose
anything. Worse, it *added* a failure mode: three tests that pass alone and in
parallel failed only under serialisation, and `describe.configure( { mode:
'serial' } )` reports the rest of a describe as "did not run", so a single
failure hid seven other results.

Run in parallel and repeated, the suite showed two real defects, and neither of
them was the one the serial run had been reasoning about.

**One was the shared chart.** `timeline.spec.ts` tabs from the top of the page to
its own row and allows forty presses. How far away that row is depends on how
many rows are above it — that is, on how many fixtures the other two files
happen to have alive at that instant. With three files publishing into one chart
it ran out of Tab presses:

```
Error: "#dp-role-timeline-fixture-backbone .dp-tl-summary" was not reachable
within 40 presses of Tab.
```

**The other was not a fixture problem at all**, and is the reason this ADR is
worth more than a commit message. `design-parity.spec.ts` reported divergences
like these:

```
row.role.open (.dp-tl-row-role[open]) backgroundColor —
  design: color(srgb 1 1 1 / 0.04) / theme: oklab(0.999994 … / 0.0241512)
row.ship.open (.dp-tl-row-ship[open]) backgroundColor —
  design: color(srgb 1 1 1 / 0.025) / theme: oklab(0.999994 … / 0.0150944)
```

Both theme values are the design's alpha multiplied by the same 0.6038, and both
are serialised in oklab. That is not a wrong value; it is a **value in motion**.
`.dp-tl-row` carries `transition: background`, `.dp-tl-row[open]`'s tint is
declared inside a container query, and a container query cannot be resolved
before the container has been laid out — so the row's background changes once
*after* first paint, which starts a transition on a page nobody has touched.
Sample during it and you get 60% of the way there, interpolated in oklab because
that is the interpolation space for non-legacy colours. Under several workers
the machine is slow enough to land inside that window. The measurement was
reporting a timestamp.

## Decision

**Content that a global query reads is established once, in
`tests/e2e/global-setup.ts`, and no spec creates or deletes any of it.**

1. Global setup publishes one set: a post (which is also every ship's
   `dp_writeup_id` target and the term `core/categories` needs), a role nothing
   hangs off, a role everything hangs off, three featured shipped things
   carrying the whole meta vocabulary, and one page assigned the `dp-work`
   template. Slugs are prefixed `e2e-shared-`.
2. It is an **upsert, never a delete**. WordPress treats a POST to a single-post
   route as an update, so creating and correcting are one code path, and a run
   after an edit to the fixture repairs the existing content rather than needing
   a database reset. Nothing removes it, because a fixture a spec can remove is a
   fixture the next spec can lose.
3. The three ships carry **distinct** `dp_end` values, so "the first card" names
   one thing rather than whichever row the database returns first out of a tie.
4. Specs read it. `sharedWorkPageUrl()` is a lookup, so every worker asks the
   same question and gets the same answer, and `describe.configure( { mode:
   'serial' } )` comes off all three files.
5. `design-parity.spec.ts` names the three cards it is measuring, in order, in a
   test of its own. If a spec ever publishes a featured `dp_ship` again, that
   test says so in one sentence instead of the sweep quietly measuring somebody
   else's card.
6. `design-parity.spec.ts` measures under `prefers-reduced-motion: reduce`, and
   asserts the preference actually reached the document before it measures
   anything. `base.css` blunts every transition to 0.01ms under that preference
   and `components.css` removes the chart's outright, so there is no in-between
   state to sample.
7. `workers` goes back to `process.env.CI ? 2 : undefined`.

A spec that genuinely needs a *different* featured set has no way to get one
without mutating global state, and would have to say so here rather than reach
for `workers: 1` again. None of the three needs one: what they assert about the
cards is the markup, the type scale and the spacing, none of which depends on
which ships fill the slots.

## Consequences

**The suite is 15 seconds instead of 18 to 39 minutes**, and it is green because
nothing collides rather than because nothing overlaps.

**A failure is now local.** With serial mode gone from all three files, one
failing test no longer takes seven passing ones down with it as "did not run".

**Three specs stopped owning content and started asking questions.**
`timeline.spec.ts` lost about a hundred lines of fixture; `spacing.spec.ts` and
`design-parity.spec.ts` lost theirs entirely. What each file now contains is the
thing it actually claims.

**The shared fixture is a coupling, and it is a real cost.** Five files read it,
so widening it for one of them widens it for all five, and a spec that needs
content shaped differently has to add to the shared set rather than make its own.
That is the trade being bought: a coupling that is written down in one file,
instead of three files coupling through a database and nobody saying so.

**Non-global fixtures did not move.** `blocks.spec.ts`, `chrome.spec.ts` and
`contact.spec.ts` publish posts and pages addressed by slug, and a slug is not a
contended resource. Only the two global queries changed hands.

**A page transitions on load, and that is not only a test problem.** The
container-query resolution that trips the sweep is a real repaint: the open rows
visibly settle into their tint after the page has drawn. Reduced motion hides it
from the measurement; it does not fix it, and it is worth a look on its own.

**Reduced motion is now load-bearing for the parity sweep.** If the two
reduced-motion blocks ever change a property that is not motion, that spec starts
measuring something the design did not ask for. Today they change
`animation-duration`, `animation-iteration-count`, `transition-duration`,
`scroll-behavior`, and a `transform` on the 404 page's button, which is neither
on this page nor in the baseline — and the sweep already drops every transition
and animation longhand. The assertion added in point 6 is what keeps this
honest: the preference cannot silently stop applying.

## Alternatives considered

**`workers: 1`.** What was there. It is not a fix — it is an ordering that makes
one class of collision improbable, and it costs two orders of magnitude of wall
clock to do it. It also proved unstable in its own right: three tests failed
under serialisation that pass both alone and in parallel, which is a suite
telling you the serialisation is the variable.

**Give each spec its own site.** `wp-env` can run more instances, and separate
databases would make every one of these problems disappear. Rejected on cost:
the containers, the setup time and the CI matrix all multiply, to isolate five
files that share one page. The shared fixture buys the same isolation for the
price of one function.

**Scope the queries to the page.** Making the chart and the card grid read only
content attached to the page they are on would remove the global query
altogether. Rejected because it is the wrong direction for the product: there is
one record and one set of featured work on this site, and the design says so.
The test suite does not get to redesign the content model to make itself easier
to write.

**Wait for the transitions to finish instead of switching motion off.** Polling
`document.getAnimations()` until nothing finite is running would also settle the
page. Rejected as strictly weaker: the CTA band's orb animation never finishes,
so the wait needs a rule for which animations count, and a rule like that is one
more thing to get subtly wrong. Switching motion off means there is nothing to
wait for.

**Raise the Tab budget in `timeline.spec.ts`.** The immediate way to make that
failure stop, and it makes the test worse: the number would then encode how much
of other files' content happens to be on the page, which is the coupling, not a
bound on it.
