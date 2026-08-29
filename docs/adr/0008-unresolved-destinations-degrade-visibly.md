# ADR-0008 — An unresolved destination degrades visibly, not silently

## Status

Superseded by
[ADR-0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md) —
pruned to this tombstone 2026-08-29.

## What survives

The `dp-to-*` destination classes this ADR patched are deleted (ADR-0018), so
most links no longer have an unresolved state. The rule survives only for the
three named blocks whose URLs genuinely cannot be typed
(`dpaternina/series-parts-link`, `dpaternina/resume-download`,
`dpaternina/feed-link`): **when the URL cannot be resolved, the block keeps its
element and loses its href** — no `href`, `aria-disabled="true"`, dimmed via
`dp-destination-unset` — never removed from the page. Broken must look broken;
an element must never vanish silently. The original mechanism and the
transient-cache fixes are in this file's git history.
