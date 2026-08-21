# Architecture decision records

A decision that outlives the pull request that made it goes here. Everything else
belongs in the commit message.

## When to write one

Write an ADR when a choice will still be shaping the code in six months and the
reasoning would otherwise have to be reconstructed from the diff:

- Adopting, replacing, or rejecting a dependency.
- A structural rule (where a file lives, what may depend on what).
- Deviating from `docs/plan.md` or from a rule in `CLAUDE.md`.
- Anything `CLAUDE.md` explicitly says needs one — for example, registering a
  third rewrite rule (§5.1), or changing `theme.json` after Phase 4 freezes it.

Do **not** write one for a bug fix, a refactor with no external consequence, or a
choice the plan already made. Record the decision, not the implementation.

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
| [0006](0006-chrome-and-derived-destinations.md) | The chrome: two lines of `theme.json`, and where a link's URL comes from | Accepted — 2026-08-20 |
| [0007](0007-timeline-modes-and-url-state.md) | The timeline: three modes in one render, and its state in the URL | Accepted — 2026-08-20 |
| [0008](0008-unresolved-destinations-degrade-visibly.md) | An unresolved destination degrades visibly, not silently | Accepted — 2026-08-20 |
| [0009](0009-server-rendered-blocks-in-the-editor.md) | A block rendered in PHP still has to exist in the editor | Accepted — 2026-08-21 |
| [0010](0010-test-only-mail-and-sender-identity.md) | The test site answers its own mail, and each e2e run is its own sender | Accepted — 2026-08-21 |
