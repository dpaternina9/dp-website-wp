# ADR-0012 — A template is checked against `design-source/`, not against itself

## Status

Accepted — 2026-08-23. Complements ADR-0008 and ADR-0011; supersedes nothing.

## Context

The work template was reviewed three times. Twice it was reported correct, and
twice David looked at it and said it was not.

The first cause was a **methodology defect**. `tests/e2e/spacing.spec.ts` sweeps
every `dp-` element on a page and compares its margins and gaps with the same
element in the site editor's canvas. That is a genuine bug class — a single-class
rule wins on the front end and loses in the canvas, because the two contexts load
WordPress's global styles in opposite orders (ADR-0008, ADR-0011) — and the sweep
catches it reliably. Phase 7c ran it over the work page and reported "0
divergences across 374 elements". The number was true. It also could not have
been evidence of design fidelity, because both sides of that comparison are the
theme. A page and a canvas that agree on the wrong value agree perfectly.

The second cause was **spot-checking**. Each round began from a short list of
suspect elements, checked those, fixed them, and stopped. Nothing ever compared
the whole template to the whole design, so each round left a different remainder.

The third cause is the interesting one, and it is why this is an ADR rather than
a commit message. The two divergences David could actually see were both in
properties that **the design does not declare**:

- The filter chips rendered at 54px against the design's 36. The design declares
  `min-height: var(--target-min)` and says nothing about `box-sizing`; the theme
  had no border-box reset, so the 16px of padding and the 2px border were added
  on top of the 36 — producing a secondary control taller than the 44px primary
  one, which inverts the hierarchy `_ds/tokens/spacing.css` describes in prose.
- Every mono caps label in the chart was about four pixels too tall.
  `TimelineChart.dc.html` declares a line-height on three elements and on nothing
  else, so the rest render in the browser's `normal` line box; `theme.json`
  declares a root `--lh-relaxed` of 1.65, which every one of them inherited.

A property the design declines to set is invisible to any test that walks the
theme's own rules, and invisible to any test that compares the theme with itself.

`design-source/` is machine-readable, and this is what makes it worth saying:
every component expresses its values as inline `style` attributes, because the
design tool has no stylesheet. Read by a person that is a liability. Read by a
program it is a complete, per-element declaration block — a baseline waiting to
be extracted.

## Decision

**We keep a generated design baseline and assert the rendered page against it.**

1. `composer design:baseline` runs `bin/dp-design-baseline.php`, which reads
   `design-source/*.dc.html` through `DP\Tests\Support\DesignMarkup` and writes
   `tests/e2e/fixtures/work-design-baseline.json`. The file is committed and
   never hand-edited, exactly like `assets/css/tokens.css` (ADR-0002).
2. `DP\Tests\Support\DesignBaseline` holds the only hand-written part: a map from
   a theme selector to one element of one design file, anchored by that element's
   **exact** `style` attribute. Mapping design to theme is a judgement about
   role, and no generator can make it. Everything to the right of it is read from
   the source.
3. `tests/e2e/design-parity.spec.ts` measures each element. It reads the target's
   computed style, then appends a **probe** of the same tag beside it, carrying
   the design's declarations verbatim and no classes at all, and reads that too.
   Only the longhands the design's own declarations expand to are compared — the
   CSSOM says which those are — so nothing is asserted that the design did not
   say.
4. Values are written to the fixture **unevaluated**: `var(--fs-xs)` stays
   `var(--fs-xs)`, `clamp(20px, 3vw, 32px)` stays as it is. The browser resolves
   both sides.
5. Where the design declares a font-size and no line-height, the generator
   derives `line-height: normal` and records the derivation in the entry's
   `derived` field. The design's document sets `font-family` and `color` on
   `body` and nothing else, so that absence *is* a value.
6. Where the theme legitimately differs, the entry carries a `skip` naming the
   property and the reason, in the fixture, in prose.
7. `DP\Tests\Unit\DesignBaselineTest` regenerates the fixture in the fast gate
   and fails if the committed file is stale, or if any anchor has stopped
   matching exactly one element.

`spacing.spec.ts` keeps its job unchanged. The two files answer different
questions and both questions are real.

## Consequences

**A number in the fixture came from the design or it is not there.** That is the
property this whole apparatus exists to buy, and it is what makes a fourth review
round different from the first three: the diff is exhaustive over the elements
mapped, and each line names the design file it came from.

**Resolving in the browser is what makes hard units assertable.** `68ch` depends
on font metrics, `0.14em` on the element's own size, `clamp(20px, 3vw, 32px)` on
the viewport, and `color-mix()` on a serialisation that differs between engines.
Re-implementing any of them in PHP would produce a number that rots the first
time a font subset or a test viewport changes. Handing the design's own
expression to the same engine, in the same inherited context, at the same pinned
width, compares like with like and pins nothing that can drift.

**Four of the chart's styles cannot be asserted at all, and are not.**
`legendStyle`, `kindLabelStyle`, `orgStyle` and `headlineStyle` are computed
inside the design tool and never reach the exported file. The theme has to supply
something; this harness does not pretend that something came from the design. The
same is true of the open role row's vertical padding and of `.dp-tl-summary`,
which have no counterpart in the export at all. They are named here so the next
reviewer does not spend a day looking for them.

**An anchor is a coupling to the design's current text.** Change a declaration in
Claude Design, re-import, and the anchor stops matching — which fails the unit
gate with the file and the expression in the message. That is deliberate: the
alternative is an anchor that silently matches nothing and an assertion that
silently stops running. The cost is that a re-import is not free; it needs a pass
over the map.

**The probe cannot answer questions about the rendered box.** It is empty, so it
has no height of its own, and it is a sibling rather than an occupant of the
target's grid or flex slot — which is why `auto-fit` track lists are skipped on
the card grid. Where the design specifies a *size* rather than a declaration —
the chips' 36px target — the spec asserts the rendered box in a named test of its
own, with the design's own sentence quoted above it.

**Its fixture is not its own.** The chart the harness measures is drawn from a
global query, so the content it reads is established once for the whole suite and
owned by no spec. ADR-0013 has the reasoning, including the reason this file's
sweep runs with motion switched off.

**It covers one template.** The map is the work page. Extending it to another
template is more map, not more machinery, and the next review should inherit the
harness rather than invent one.

## Alternatives considered

**Screenshot comparison against an exported design image.** The obvious answer,
and it fails on the thing that matters: a pixel diff says *where* two images
differ, never *which declaration* differs, so every failure becomes an
investigation. It is also font-rendering-dependent across machines and CI, which
means either a tolerance large enough to hide a four-pixel line box — the exact
bug we were trying to catch — or a permanent stream of false failures.

**Rendering `design-source/` in a browser and diffing computed styles against the
theme.** Genuinely attractive, and rejected on a fact: the components' most
interesting styles (`cardStyle`, `gridStyle`, `detailGridStyle`, `rowStyle`) are
computed by JavaScript that lives in the design tool and is not in the export.
A rendered `.dc.html` would draw the chart with those attributes empty, so the
comparison would be against a version of the design that does not exist. The
prose LAYOUT NOTES are what the design says about those, and quoting them is
honest in a way that measuring a broken render is not.

**Resolving every value to a literal in PHP and pinning the number.** Simpler to
read, and it is what the token bridge does — correctly, because a token *is* a
literal. A component's declaration is not: half of them are relative to a font, a
viewport or a container, and the resolved number is only true for one width and
one font file. See the second consequence above.

**Extending `spacing.spec.ts` instead of adding a file.** Rejected on the
grounds that its failure message would then mean two unrelated things. Its
message tells you to give a rule a second class; this one tells you to change a
value or re-import the design. Merging them would make both worse.
