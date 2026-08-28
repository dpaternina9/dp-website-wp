# Architecture decision records

A decision that outlives the pull request that made it goes here. Everything else
belongs in the commit message.

## When to write one

**An ADR records a reversal, or a constraint imposed from outside that the code
has to live with. It never records a choice made in the same pass as the code
that implements it.** That goes in the commit message.

Write one when:

- We are **undoing or contradicting an earlier ADR**, so the next person to read
  the old one finds out it is no longer true.
- A constraint arrives from outside and we are accepting it — a dependency
  adopted, replaced or rejected; a platform limit we must design around.
- `CLAUDE.md` explicitly demands one, such as registering a third rewrite rule
  (§5.1) or changing `theme.json` after Phase 4 freezes it.

Do **not** write one for a bug fix, a refactor, a choice the plan already made,
or — the important one — a decision you have just made and are about to
implement.

### Why the bar is here

This rule replaced a looser one on 2026-08-26, after eighteen ADRs in seven days
and a review of what they had actually done. Three of them **caused** defects
rather than preventing them:

- **ADR-0006 §2** invented the rule that no pattern may contain an href at all,
  and wrote a test to enforce it. That rule is why render-time code could
  overwrite an href set in the site editor without anyone noticing there was
  something to preserve. Superseded by ADR-0018, seven days later.
- **ADR-0015** introduced `dp_series_featured`, a term flag with no editor
  control, so the link it governed was permanently inert. Superseded the next
  day.
- **ADR-0016** corrected eight fields and in the same pass introduced a derived
  featured series and kept a redundant deck field. Two of its halves were
  superseded within hours.

The mechanism is worth naming, because it is not carelessness. An ADR is a
**commitment device**: it converts a choice somebody has just made into a
documented, argued, hard-to-question decision. When the choice is sound that is
the point of the format. When it is a guess, the ADR launders the guess into a
principle, and the next person builds on it. Every one of the three above was
written by the party that made the decision, in the same pass, before anybody had
used the result.

Note what did hold up over the same period: `CLAUDE.md` §5.1 — five bullets, no
argument, in a file read at the start of every session. Length and reasoning were
not what made a rule survive.

So the surviving ADRs are the ones written **against** a decision rather than for
it. A reversal has a fact behind it that nobody can argue with: the thing was
built, and it did not work.

## Format

One file per decision, never deleted and never rewritten. Numbered sequentially,
four digits, kebab-case title:

```
docs/adr/0001-phase-0-toolchain.md
docs/adr/0002-….md
```

Each file follows the same five headings:

| Heading | What goes in it |
|---|---|
| **Status** | `Proposed`, `Accepted`, `Superseded by ADR-NNNN`, or `Rejected`. Plus the date. |
| **Context** | The forces in play. What was true when the decision was made. |
| **Decision** | What we are doing, in the active voice: "We use X." |
| **Consequences** | What this makes easy, what it makes hard, and what it commits us to. Name the costs; an ADR that lists only benefits is marketing. |
| **Alternatives considered** | What else was on the table and the specific reason it lost. |

A decision that turns out to be wrong is **superseded**, not edited. Write the new
ADR, set the old one's status to `Superseded by ADR-NNNN`, and leave the original
reasoning intact — the record of why we believed something is the point.

## Index

| ADR | Title | Status |
|---|---|---|
| [0001](0001-phase-0-toolchain.md) | Phase 0 toolchain | Accepted — 2026-08-20 |
| [0002](0002-design-token-naming.md) | One source of truth for design tokens, and the bridge that keeps their names | Accepted — 2026-08-20 |
| [0003](0003-content-model-edges.md) | The content model's edges | Accepted — 2026-08-20 |
| [0004](0004-tag-driven-signed-releases.md) | Tag-driven signed releases | Accepted — 2026-08-20 |
| [0005](0005-house-style-blocks.md) | The house style: where a block's appearance is written down | Accepted — 2026-08-20 |
| [0006](0006-chrome-and-derived-destinations.md) | The chrome: two lines of `theme.json`, and where a link's URL comes from | Accepted — 2026-08-20, destination half superseded by 0018 |
| [0007](0007-timeline-modes-and-url-state.md) | The timeline: three modes in one render, and its state in the URL | Accepted — 2026-08-20 |
| [0008](0008-unresolved-destinations-degrade-visibly.md) | An unresolved destination degrades visibly, not silently | Accepted — 2026-08-20, mostly superseded by 0018 |
| [0009](0009-server-rendered-blocks-in-the-editor.md) | A block rendered in PHP still has to exist in the editor | Accepted — 2026-08-21 |
| [0010](0010-test-only-mail-and-sender-identity.md) | The test site answers its own mail, and each e2e run is its own sender | Accepted — 2026-08-21 |
| [0011](0011-the-brand-mark-is-content-and-spacing-out-specifies-the-block-gap.md) | The brand mark is content, and every spacing rule out-specifies the block gap | Accepted — 2026-08-21 |
| [0012](0012-design-parity-harness.md) | A template is checked against `design-source/`, not against itself | Accepted — 2026-08-23, amended twice the same day: the design's script blocks were in the export all along |
| [0013](0013-one-fixture-nobody-owns.md) | E2E content that a global query reads belongs to nobody | Accepted — 2026-08-23 |
| [0014](0014-the-year-axis-and-the-bars-share-one-scale.md) | The year axis is drawn on the bars' scale, and the track ends now | Accepted — 2026-08-24 |
| [0015](0015-the-blog-templates-derive-four-things-a-template-cannot-say.md) | The blog templates derive four things a template cannot say | Accepted — 2026-08-25, partly superseded by 0016 |
| [0016](0016-a-post-carries-no-fields-of-ours.md) | A post carries no fields of ours | Accepted — 2026-08-26, featured-series half superseded by 0018, `menu_order` rejection by 0019; deck field reversed in commit |
| [0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md) | Computation is visible in the editor, or it does not happen | Accepted — 2026-08-26, one claim corrected 2026-08-27 |
| [0019](0019-a-series-is-ordered-by-hand.md) | A series is ordered by hand | Accepted — 2026-08-27 |
| [0020](0020-theme-json-opens-for-a-custom-template.md) | `theme.json` opens a second time, for one custom template | Accepted — 2026-08-27 |
