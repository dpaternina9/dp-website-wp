# ADR-0007 — The timeline: three modes in one render, and its state in the URL

## Status

Accepted — 2026-08-20.

## Context

`design-source/components/TimelineChart.dc.html` is the largest single component
in the design and the only one whose behaviour is not decorative. It draws every
role as a lane, hangs everything that shipped off the role it came from, and lets
any number of them be open at once. It has three layouts, a three-way filter, an
expand-all control, and a promise from the `/work` composition above it that
clicking a `WorkCard` "opens the matching entry on the timeline below".

Five constraints shaped what follows, and none of them is optional.

- **`CLAUDE.md` §1.7.** "Every page must be readable and navigable with JS off."
  Not the copy — the *behaviour*. A chart whose rows only open under JavaScript
  fails this, and so does a filter that is three buttons.
- **The design's own closing note.** *"The design's width probe
  (ResizeObserver + polling) exists only because the design tool cannot express
  media queries. The theme must use container queries (`@container`) and the
  `details`/`summary` element for the disclosure behaviour instead."* The design
  is telling us not to copy its implementation.
- **digest §5.2.** The 700px threshold is the **component's** width, never the
  viewport's. At a 760px window the chart sits inside a gutter and a container
  and is under 700px; a media query gets that wrong.
- **`CLAUDE.md` §2.1.** A dynamic block with a render callback is `dp-core`'s.
  Front-end CSS and JS are the theme's.
- **Phase 3.** `Timeline\Geometry`, `Timeline\Bar`, `BarKind` and `Year` already
  exist, with unit tests. Phase 6 renders; it does not compute.

A server render cannot know how wide a container will be, and the one thing that
could — JavaScript — is exactly what the first constraint forbids relying on.
That tension is what this ADR resolves.

Verified environment: WordPress 7.1, PHP 8.4 in-container, container queries and
`cqw` units in Chromium 140, PHPUnit 9.6.36, Playwright 1.56.

## Decision

### 1. One markup, three modes, and the stylesheet decides which

`DP\Core\Blocks\Timeline` renders **one** document for all three modes. The year
track, the chevron that replaces it below 700px, the axis and the scroll mode's
swipe hint are all present in every render, and
`themes/dpaternina/assets/css/components.css` decides which of them is drawn:

```
bars    @container dp-timeline (width >= 700px)   label column 200px, track, no chevron
stack   the default, below 700px                  labels only, chevrons          ← ships
scroll  .is-mobile-scroll, below 700px            label column 128px, sticky, 720px inner
```

Four custom properties carry every number that differs — `--dp-tl-label-base`,
`--dp-tl-gap`, `--dp-tl-rail`, `--dp-tl-bleed` — so a mode is a handful of
declarations rather than a second copy of the component. The ship label column
subtracts the rail back out (`calc(var(--dp-tl-label-base) - var(--dp-tl-rail))`),
which is what keeps a ship bar on the same year axis as the role bar above it.

The Ledger chose **stack**. `scroll` is implemented, styled and reachable through
the block's own `mobileMode` attribute, and it is never what a reader gets unless
the block says so. Its pinned reading width is the design's own formula said in
CSS: `max(240px, calc(100cqw - 60px))`.

Because the modes are container queries, a rendered-markup test cannot see which
one is drawn — so the mode coverage is two-sided.
`tests/Integration/Blocks/TimelineTest` asserts that one markup carries all three
modes *and* reads the stylesheet and asserts the three container queries carry
the design's numbers; `tests/e2e/timeline.spec.ts` measures the label column in a
browser at 1440, checks the chevrons at 390, and — the assertion that earns its
place — proves at a **760px window** that the component is under 700px and stacks
anyway.

### 2. Every row is a `<details>`, and nothing is added on top of it

A `<summary>` is already a disclosure button with an expanded state, keyboard
activation on Enter *and* Space, and correct announcement. So there is no
`role="button"`, no `aria-expanded` and no `tabindex` anywhere in the chart —
each of which the design's markup carries because a design tool has only a click
handler. There is a test asserting their absence, because re-adding them is the
obvious "accessibility improvement" and it would make the chart worse.

Many rows open at once falls out of this for free, which is the requirement.

The label column and the bar share one `<summary>`, so clicking either toggles
the row, exactly as the design's two `onClick`s do. Everything inside is phrasing
content laid out by the stylesheet as a grid: `<summary>` is not a place for flow
content, and a heading inside a disclosure button is announced twice.

### 3. The chart's state is two query args, and JavaScript only mirrors them

| Query arg | Values |
|---|---|
| `dp-filter` | `everything` (default), `roles`, `shipped` |
| `dp-open` | `all`, or a comma-separated list of entry keys |

The server reads both. The filter pills, the expand-all control and the work
cards are all **links** carrying them — `FilterPills.dc.html`'s own note settles
the pattern for the whole site: *"these are real links to filtered archive URLs,
not JS tabs"* — so with the scripts off, filtering, expanding everything and deep
linking are one navigation each and all work.

`assets/js/timeline.js` is an upgrade and nothing else: it intercepts those same
links to avoid a round trip, keeps the URL in step with `replaceState`, and opens
a card's entry in place. Deleting the file changes how fast the chart responds
and nothing about what it can do.

`DP\Core\Content\Timeline\Filter` is an enum, unit-tested with no WordPress
loaded, because four things have to agree on the three-line filter rule: the
render, the links, the controller and the stylesheet.

### 4. A filtered-out row is `hidden`, not absent

The server renders **every** lane and **every** ship, and marks the ones the
filter excludes with the `hidden` attribute.

This is not a shortcut. `[hidden]` is honoured by every user agent's own
stylesheet and by assistive technology, so with the scripts off the three filters
show precisely what the design says they show. And because the whole record is
already in the document, the controller can switch filter by changing an
attribute rather than by fetching a page — which is what "query-arg links,
upgraded to instant" has to mean if the upgraded version is to behave like the
plain one. Rendering only the visible rows would make the plain version correct
and the upgraded one impossible.

The cost is stated in §Consequences: the markup for hidden rows is always sent.

### 5. An entry is identified by its slug, and the chart's id is a constant

The design keys its open state on `lane.org + ship.org` — a string built out of
display copy, so renaming a project breaks every link to it. Here an entry is
`dp-role-{slug}` or `dp-ship-{slug}`, built once by
`DP\Core\Content\Timeline\Chart::entry_key()` and asked for by the theme when it
turns a `WorkCard` into a link. That is the **one** seam between the two
packages, named in one place rather than as a format string in two files that
drift.

The chart's own element id is the constant `dp-timeline`, and `block.json`
declares `multiple: false` so it stays unique. The design has one chart; an id
that changed with the number of charts on a page is an id no link could be
written against, including the ones on the cards above it.

### 6. `theme.json` is not touched

The freeze holds (ADR-0005 §9). `dp-work` was already declared in
`customTemplates` by Phase 1, so composing `templates/dp-work.html` needed no
settings change, no new preset and no new style. The chart's appearance is one
section of `components.css`, which the editor and the front end are handed from
the same list.

### 7. `Geometry` is transcribed, never re-derived

`Chart` hands `Geometry` two `Year`s and stores the `Bar` it gets back;
`Bar::style()` writes the four numbers. There is no percentage arithmetic
anywhere in Phase 6, and `TimelineTest` recomputes the expected bars from
`Geometry` rather than typing them in, so a stray calculation would disagree with
its own source. Geometry is also the **only** thing that reaches the page as an
inline style — there is a test asserting that every `style` attribute in the
chart matches `left:…%;width:…%;max-width:…%;min-width:…px` and nothing else.
Colour arrives as a class the stylesheet maps to a token, per `CLAUDE.md` §5.

## Consequences

**What this makes easy.**

- The chart works with JavaScript off, on a slow connection, and in a reader
  view, and the e2e suite proves it rather than asserting it in prose — with a
  scripting probe so the JS-off tests cannot silently become duplicates of the
  JS-on ones.
- A layout change is a CSS change. No PHP, no build, no re-render.
- Any state the chart can be in has a URL, so a link into a specific entry —
  from a card, from a post, from an email — is a plain href.
- David can extend the track past 2026 by editing two block attributes.

**What this makes hard, and what it costs.**

- **Hidden rows are still sent.** A record of six roles and four ships is a few
  kilobytes; a record of two hundred would not be. `Chart::MAX_ROWS` caps a
  single chart at 100 of each, which is far beyond any real career and still
  bounded. If the record ever approaches that, this decision is the one to
  revisit.
- **One chart per page.** `multiple: false` is a real limit, taken deliberately
  in exchange for a stable id.
- **A deep link is a slug.** Changing a shipped thing's slug after publication
  breaks links to it. WordPress does not change `post_name` when a title is
  edited, so this is a deliberate act, not an accident — but it is not free, and
  it is the same bargain every permalink on the site already makes.
- **Filtered URLs are extra URLs.** Core's `rel_canonical` already points a
  filtered work page at the unfiltered permalink, so nothing further is needed;
  but a future caching layer has to know that `dp-filter` and `dp-open` vary the
  response.
- **The theme now names two of the plugin's classes.** `SeriesPlanned` set the
  precedent and `Timeline` follows it, guarded by `class_exists()` — the guard
  is not decorative: without it the theme fatals on any site where `dp-core` is
  deactivated, which is exactly what a fresh `composer test:integration` leaves
  behind.

## Alternatives considered

**Copy the design: measure the component with a `ResizeObserver` and switch
modes in JavaScript.** Rejected by the design's own closing note and by
`CLAUDE.md` §1.7. It would also mean the first paint has no layout, which is a
visible reflow on every load of the page the site exists to show.

**Media queries on the viewport.** Simpler, supported everywhere, and wrong:
digest §5.2 is explicit that the threshold is the component's width, and the
760px case in the e2e suite is the counterexample — the viewport is over the
threshold and the component is under it.

**Render only the rows the filter shows.** Half a line shorter in PHP, and it
makes the instant filter impossible without a fetch, which would put the
upgraded path on a different code path from the plain one. Two behaviours to
keep in step instead of one.

**Buttons plus `aria-expanded`, as the design's markup has.** A `<summary>` does
all of it natively and does it with the scripts off. Buttons would need
JavaScript to open anything at all.

**A REST endpoint and a client-rendered chart.** Would have made the filter and
the deep link trivial, and would have made the page blank without JavaScript.
Not a trade this project is allowed to make.

**Store the open state in `localStorage` rather than the URL.** Survives a
reload, cannot be linked to, cannot be shared, and cannot be read by the server —
so the `WorkCard` promise would need JavaScript. The URL is the state.

**Key an entry on `org + title`, as the design does.** Human-readable and
brittle: it is display copy, and it changes when the copy is edited. A slug is
the same idea attached to something WordPress already keeps stable.
