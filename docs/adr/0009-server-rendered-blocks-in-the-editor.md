# ADR-0009 — A block rendered in PHP still has to exist in the editor

## Status

Accepted — 2026-08-21.

## Context

`dp-core` registers three blocks that have no content of their own. `dp/timeline`
draws the chart from every published `dp_role` and `dp_ship`. `dp/resume-ledger`
draws the same record, newest first. `dp/contact-form` draws whichever of the
design's three panels the current request is in. All three are
`register_block_type()` plus a render callback, and nothing else — no `edit`, no
`save`, no editor script.

On the front end that is complete and correct. In the block editor it is not.
The editor draws a block from its **client-side** registration; a block type the
server knows about and the client does not becomes core's `core/missing`:

> Your site doesn't include support for the "dp/contact-form" block. You can
> leave it as-is or remove it.

Phase 6 shipped the timeline that way and Phase 7 was about to ship two more,
because the failure is invisible from everywhere the suite was looking. The
templates are right. The rendered markup is right. Every integration test reads
`get_the_block_template_html()`, which is the front end. The only place the
problem exists is the canvas, and the only way to find it is to open the canvas —
which is exactly the lesson the Phase 5b notes already recorded about styles and
which turns out to apply to registration as well.

`CLAUDE.md` §5 states the requirement plainly: **the editor must look like the
front end.** A template whose main content is a grey "not supported" box does not
meet it, and it is worse than cosmetic: David edits these templates in the site
editor, and a block the editor does not understand is one an "Attempt recovery"
click can remove.

## Decision

**Every block `dp-core` renders on the server gets a client-side registration
whose `edit` is `ServerSideRender` for that same block.**

One list, in one file — `plugins/dp-core/src/Blocks/js/dynamic/server-rendered.js`
— iterated at load:

```js
registerBlockType( name, { edit: serverRenderedEdit( name ) } );
```

Three things follow from that shape and each is deliberate.

**Nothing is restated.** The settings object carries `edit` and nothing else.
Title, icon, category, keywords and attributes come from the server definition
WordPress already bootstraps into `wp.blocks` for every registered block type, so
`block.json` stays the only place any of them is written down.

**There is no second renderer.** `ServerSideRender` asks
`/wp/v2/block-renderer/{name}` for the markup the page will show. A hand-written
editor preview would be a second implementation of a chart, a ledger and a
six-gated form, kept in step with the first by nobody.

**It rides the bundle that already exists.** `dp/callout`'s `editorScript` is
this plugin's one editor entry point, and WordPress enqueues every registered
block's editor script on every block-editor screen. So the registrations are an
import in that bundle rather than a fourth script, a fourth `block.json` field
and a fourth build entry.

The list is held against the filesystem by
`DP\Tests\Integration\Blocks\ServerRenderedParityTest`: every `block.json` under
`plugins/dp-core/blocks/` must appear in `SERVER_RENDERED`, must be registered
with a render callback, and must appear in the **compiled** bundle. That last
assertion is the one that earns its place — `npm run build` is a separate step,
so a source-only change with a stale `build/` would otherwise pass locally and
reach David's editor as the bug it was written to fix.

## Consequences

**What this makes easy.** Adding a dynamic block is one line in one array. The
editor shows the real thing, with the real content, in the real styles — the
contact panel in the canvas is the contact panel, drawn by the same PHP that
draws it on the page.

**What it costs.** Each of these blocks now makes a REST request per editor
render, and `ServerSideRender` debounces but does not eliminate re-renders when
an attribute changes. On the résumé and the timeline that request runs the same
two queries the page runs. It is the editor, not the front end, and the front end
still loads no JavaScript from this plugin at all.

**What it commits us to.** `plugins/dp-core/build/` is compiled, not committed —
CI runs `npm run build` before `composer test` for exactly that reason, and the
parity test's third assertion is what makes a stale local build fail loudly
instead of quietly. `wp-server-side-render` is now a dependency of the editor
bundle; it is a core-provided script handle, extracted automatically, and adds
nothing to the repository.

**What it does not cover.** `dpaternina/series-planned` is the theme's dynamic
block and has the same problem on `taxonomy-dp_series`. The theme ships no
JavaScript build at all today, so giving it an editor preview means giving the
theme a build — a decision of its own, not an application of this one. It is in
the merge queue.

## Alternatives considered

**Leave them as `core/missing`.** Rejected: it fails `CLAUDE.md` §5 outright, and
"Attempt recovery" on a missing block is one click from deleting the main content
of a template.

**Write a real `edit` for each.** Rejected: three editor implementations of
things that have no editable content, each able to drift from the PHP that
actually renders them. The contact form's editor twin would have to decide what
to draw for a state only a POST can produce.

**Give each block its own `editorScript` and build entry.** Rejected as cost with
no benefit: WordPress loads every registered block's editor script on every
block-editor screen anyway, so three entry points would be three requests to do
what one import does — and `dp/callout`'s bundle already carries the two house
style extensions that have no block of their own, so the precedent is set.

**A `render` field in `block.json` instead.** Not an alternative: `render` names
a PHP file for the *front end*. It does nothing for the editor's registry.
