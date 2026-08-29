# ADR-0020 — `theme.json` opens a second time, for one custom template

## Status

Accepted — 2026-08-27. Written after the work, per [the README](README.md).
`CLAUDE.md` §6 requires an ADR to change `theme.json` after Phase 4 froze it;
this is that record, not a case for the change.

## Context

`/series/` was a 404. `register_taxonomy()` creates `/series/{term}/` and nothing
else — WordPress has no term-index route for a flat taxonomy and no template in
the hierarchy for one. It was one of the four complaints that opened this whole
pass.

§5.1 rules out the two obvious fixes. A rewrite rule is forbidden outright: the
only registered page-facing rewrite in the project is `dp_series`' own, and a
second needs its own ADR and a better reason than this. A template named
`page-series.html` is worse — the hierarchy would auto-apply it to any page
David happened to slug `series`, which is the exact coupling §5.1 exists to
prevent.

That leaves the pattern §5.1 prescribes: a `dp-`-prefixed custom template, which
must be declared in `theme.json` `customTemplates` to be offered in the admin.

## Decision

**One entry, and nothing else:**

```jsonc
{ "name": "dp-series", "title": "Series index", "postTypes": [ "page" ] }
```

No `settings`, no `styles`. This is the precedent ADR-0006 §1 set when it added
`templateParts`: metadata about files the phase ships, with no effect on the
editor's vocabulary, what a block may be given, or how anything is drawn. Phase
4's reasons for the freeze — that the editor's vocabulary stops moving, and that
later phases can build against a fixed file — are untouched.

The page itself is David's. The seeder creates one and assigns the template; he
may rename it, re-slug it, or delete it, and the only thing that makes it the
series index is the template assignment.

## Consequences

**`customTemplates` is now a list that grows.** Six entries became seven, and
the precedent is explicit: a design-specific page view is a `dp-` template, and
adding one is a one-line change plus this paragraph. That is the intended cost —
it is cheap enough to do and visible enough that nobody does it absent-mindedly.

**`/series/` is a page, so its URL is not ours.** It answers on whatever slug
David gives it, and the theme still names no slug anywhere. If he deletes the
page, `/series/` 404s again — correctly, because then there is no series index.

**The listing is a block, so it is visible.** `dpaternina/series-index` appears
in the inserter and renders the same markup in the editor and on the page, per
ADR-0018. It reuses `taxonomy-dp_series.html`'s existing class vocabulary and
added no CSS.

**One string now has one author.** `ArchiveFacts::series_written()` was split so
`parts_line( int $term_id )` produces "N parts up · M drafted" for both the
single-series hero and every row of the index, rather than the index restating
it.

## Alternatives considered

**A rewrite rule for `/series/`.** Rejected — §5.1, and it would make the URL the
theme's rather than David's.

**Put the list on an existing page** — the writing index, say — and skip the
template. Rejected: it makes the series index something David cannot move,
rename or remove without editing a template.

**A `page-series.html` template.** Rejected: auto-applied by the hierarchy to any
page slugged `series`. The `dp-` prefix is the whole defence against that.
