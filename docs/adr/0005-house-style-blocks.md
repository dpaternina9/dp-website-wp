# ADR-0005 — The house style: where a block's appearance is written down

## Status

Accepted — 2026-08-20.

## Context

`docs/plan.md` Phase 4 asks for "`theme.json` `styles.blocks` for every block in
digest §5.1". Implementing that literally does not survive contact with the
cascade, and the reasons are worth writing down once because `theme.json`
freezes at the end of this phase.

`design-source/components/PostBlocks.dc.html` specifies each block as a set of
inline declarations: type, colour, margin, padding, border, radius, background,
and — for lists and code blocks — content that is drawn rather than inherited.
Four facts about WordPress decide where each of those can live.

1. **`styles.blocks` cannot express half of it.** There is no schema for
   `max-width`, for a `::before`, for a CSS counter, or for a grid column. The
   measure, the list markers and the code block's label bar have nowhere to go
   in `theme.json` at all.
2. **`styles.blocks` output ties with core's layout rules.** A block node is
   emitted as `:root :where(.wp-block-quote)` — one class of specificity, the
   same as the flow layout's
   `:where(.is-layout-flow) > :where(* + *) { margin-block-start: … }`. Element
   nodes are worse: `styles.elements.h2` is emitted as bare `h2`, which the
   layout rule beats outright. Every margin in the design would therefore be
   decided by source order, or silently lost.
3. **Settings gate the editor's controls, not the theme's styles.** Phase 1
   disabled `border.{color,radius,style,width}`. A theme-origin `styles` value
   for those properties is still emitted — verified on WordPress 7.1 — so
   putting the quote's rule and radius in `theme.json` would not require
   reopening the controls. It would, however, put a border in the file that
   Phase 1's settings say cannot be edited, which reads as a contradiction
   every time someone opens it.
4. **Two of the design's blocks are not blocks.** `note` has no core equivalent,
   and `code`'s label has nowhere in core/code to live.

Verified environment: WordPress 7.1, `WP_Theme_JSON::LATEST_SCHEMA === 3`, PHP
8.4, PHPUnit 9.6.36, Stackable installed on the wp-env sites and absent from the
integration suite.

## Decision

### 1. `theme.json` says what a block is made of; `blocks.css` says how it is put together

`themes/dpaternina/assets/css/blocks.css` is a new hand-written stylesheet,
added to `DP\Theme\Assets::stylesheets()` so the editor and the front end get it
from the same list. The split between it and `theme.json` is stated once and
applies to every block:

| | |
|---|---|
| **`theme.json`** | Font family, size, weight, line height, letter spacing, text transform, text colour. |
| **`blocks.css`** | Margins, padding, borders, radii, backgrounds, layout, markers, label bars, the measure. |

It is not an aesthetic split. Everything in the left column is something
`styles.blocks` can express *and* win with; everything in the right column is
something it cannot express, or cannot win. Writing borders and backgrounds in
CSS also means `theme.json` never states a value for a control Phase 1 turned
off.

`styles.elements.h2`, `h3`, `h4` and `caption` carry the type for the heading
levels and the figure caption. `h4` is mono caps in `--accent-text` and is
emitted after the shared `h1, h2, h3, h4, h5, h6` rule, which is what makes it
win; `HouseStyleTest` asserts that ordering rather than trusting it.

### 2. The paragraph rhythm is `blockGap`, and only the exceptions are written down

The design puts 24px between paragraphs. `--space-5` is 24px and is already the
root `blockGap`, so a paragraph needs no margin rule at all — the flow layout
produces the design's spacing on its own. Only the ten blocks whose spacing
differs get a rule: 48/36/32 above the heading levels, 32 around a quote and an
image, 28 around code, a callout and a table, 20 above a list, 44 around a
separator. Each is on a selector carrying an element name as well as a class, so
it beats the layout rule on specificity rather than on order.

Adjacent margins collapse, so a 32px margin below a quote and the 24px gap above
the next paragraph resolve to the 32px the design draws.

### 3. Core's style variations come off; the design's appearance is the default

The design gives each block one appearance. Core offers alternatives — Plain for
a quote, Wide and Dotted for a separator, Stripes for a table, Rounded for an
image — and every one of them is a way out of the design system by accident.

They are declared in core's own `block.json`, so `unregister_block_style()`
cannot reach them; `DP\Theme\Blocks\CoreStyles` removes them through
`block_type_metadata`, for the vocabulary's blocks only.

**We deliberately do not register the design's appearance as a named style.**
`is-style-dp-pull` and friends would put the house appearance next to an
unstyled default in the editor, which is the opposite of what "this system or
nothing" means. The pull quote, the labelled dark code block, the spectrum
separator and the mono caption are what those blocks look like; there is nothing
to opt into and nothing to forget to apply.

### 4. The allowlist governs posts

`allowed_block_types_all` returns the vocabulary — the nine core blocks, plus
everything registered under `dp/` or `stackable/` — when the editor is opened on
a **`post`**. Pages, the site editor and every other post type are handed back
whatever an earlier filter decided.

This is narrower than "everything else off" as Phase 4 states it, and the
narrowing is not a convenience:

- Pages are David's (`CLAUDE.md` §5.1) and are built from the patterns Phase 5
  ships, which are groups, columns and buttons. Restricting a page to the nine
  blocks a *post* may use would make those patterns uninsertable.
- The site editor edits this theme's templates. Nine blocks leaves no way to
  place a template part, a query loop or post content; it would break the theme
  rather than constrain it.

The house style is a rule about writing, and the reference for it — the
`house-style` fixture — is a post. `dp_allowed_block_types` filters the list, so
a later phase can widen or narrow it without a patch.

Blocks arrive under `dp/` and `stackable/` **by prefix, discovered from the
registry**, never by name. The theme therefore never asserts that a plugin is
installed, `dp/timeline` needs no edit here in Phase 6, and a deactivated
Stackable simply contributes nothing.

### 5. `dp/callout` is a static block in `dp-core`

The design's `note` becomes `dp/callout`: a label above one paragraph, with the
label defaulting to `NOTE`. It is registered by the **plugin**, not the theme,
for the reason `CLAUDE.md` §2.1 gives — a block type registered by a theme turns
every published callout into an invalid block the moment the theme is switched.

It is **static**. There is no render callback, no front-end script, and no PHP
involved in displaying one: the markup is in the post and the CSS is in the
theme. Deactivating `dp-core` takes away the editor UI and leaves every
published callout on the page, styled. `CalloutTest` asserts exactly that, by
unregistering the block and rendering one anyway.

`supports.multiple` stays `true`. One callout per post is a house limit, and
Phase 4 is explicit that the limits are warnings.

### 6. The code block's label is an attribute with no `source`

`core/code` has nowhere to put the design's label, and changing what core's
`save()` writes would invalidate every existing code block the day the plugin
came off. So `dpLabel` is declared through the `blocks.registerBlockType` filter
**with no `source`**, which means WordPress serialises it into the block's HTML
comment and never into the block's markup:

```
<!-- wp:code {"dpLabel":"WP-CLI"} -->
<pre class="wp-block-code"><code>…</code></pre>
<!-- /wp:code -->
```

The saved markup is byte for byte core's. `DP\Core\Blocks\CodeLabel` puts the
value back on the rendered `<pre>` as `data-dp-label`, and the theme's CSS reads
it — falling back to the design's own default, `SHELL`, when the attribute is
absent altogether. An emptied label turns the bar off rather than restoring the
default.

The seam between the two packages is one data attribute: the plugin supplies the
word, the theme draws the bar.

### 7. List markers are drawn, and the list keeps its role

`ul` gets an em dash and `ol` gets a zero-padded index, mono, `--fs-xs`,
`--accent-text`, in a 28px grid column. That needs `list-style: none`, and
removing a list's markers is exactly what stops Safari and VoiceOver announcing
it as a list. `DP\Theme\Blocks\Markup` puts `role="list"` back on the rendered
element through `render_block`.

`decimal-leading-zero` produces `01`, `02` from a CSS counter, so there is no
second list of literals to keep in step. The cost is that no browser API reports
a counter's painted string: the e2e test asserts the whole mechanism — no native
marker, counter incremented per item, marker drawn from it — rather than the
characters.

### 8. The measure is scoped to post content, and the canvas is given the page's column

`--measure` (68ch) caps body copy, narrower than the 880px column the headings
and the block chrome span. Two things fight it, and both are core's:

- The constrained layout caps every direct child of the content area at
  `contentSize` **and centres it** with `!important` on both auto margins. The
  design does not centre body copy, so the inline margins are taken back at the
  only weight that can take them back.
- The editor canvas adds a second rule, of two classes, doing the same job. The
  selector pair `.wp-block-post-content p, .wp-block-post-content p.wp-block-paragraph`
  clears the first on the page and the second in the canvas. (`wp-block-paragraph`
  only reaches the front end from WordPress 7.1; the plain selector covers
  everything older.)

The measure is scoped to post content on purpose: it is a rule about body copy
in an article, not about every paragraph on the site.

That leaves one difference that is core's rather than ours. The front end puts
the content inside a column of `contentSize` and lets blocks fill it; the canvas
leaves the container the full width of the writing area and gives each block
that width as a max-width with automatic margins. Blocks exactly `contentSize`
wide look identical either way — until one is narrower, as body copy now is, and
it centres itself against everything above it. Two editor-only rules in
`blocks.css` give the canvas the same column the page has. Both selectors exist
only inside the editor, so neither reaches a published page.

### 9. `theme.json` is frozen

Phase 4 ends and `theme.json` stops changing. Phases 5, 7 and 9 run in parallel
against it. A change after this point is an ADR, not an edit — including
re-enabling any of the controls Phase 1 disabled.

## Consequences

- **The house style is global, not scoped to posts.** `:root :where(p)` and
  bare `h2` reach every paragraph and heading on the site, including in
  templates and patterns. That is what makes the editor canvas match the front
  end, which no context-scoped rule can do. Phase 5 overrides per block where a
  hero or a section head wants a different size; the defaults it inherits are
  the design's body defaults, which is the right way round.
- **Two files, one appearance.** Anyone changing a block's look has to know
  which of the two to open. The rule in §1 is short enough to remember and the
  header of `blocks.css` restates it.
- **One `!important`, plus two editor-only rules.** All three exist to undo
  core's own `!important` centring. They are commented where they sit.
- **A code block's label is lost if `dp-core` is deactivated and the post is
  then re-saved.** The editor drops attributes it does not know about. Nothing
  is invalidated and nothing breaks; the bar falls back to `SHELL`.
- **`role="list"` is added at render time**, so it is the theme's and disappears
  with it. The editor canvas does not get it — the editor renders its own list —
  which is a gap in the writing experience, not in the published page.
- **Removing core's style variations is not retroactive.** Content already
  carrying `is-style-plain` keeps the class; the house rule is written to hold
  for it anyway.
- **The build is now load-bearing.** `dp/callout` is registered from
  `plugins/dp-core/build/callout`, which `npm run build` produces and `.gitignore`
  excludes. CI builds before starting wp-env; a developer who skips it gets a
  named failure from `CalloutTest`, not a mystery.
- **Stackable's absence is now tested, not asserted in prose.** The integration
  suite never loads it, so every template rendered there is rendered on a site
  where it has never existed — and `StackableOptionalTest` asserts that
  precondition before it asserts anything else.

## Alternatives considered

**Put every declaration in `theme.json`, as Phase 4 states it.** Rejected on the
four facts in the Context: the measure, the markers and the label bar have no
schema, and the margins would be decided by source order against core's layout
rules.

**Register the design's appearance as named block styles** (`is-style-dp-pull`,
`is-style-dp-spectrum`). Rejected: an optional style implies an unstyled
default, and the default is what a pasted or imported block would land on. The
plan's wording — "block styles/variations" — is satisfied by the appearances
being the blocks' own.

**A custom `dp/code` block, so the label is ours.** Rejected: digest §5.1 maps
`code` onto `core/code`, and replacing a core block to add one label would cost
the block's own toolbar, its transforms, and every paste path into it.

**Add `dpLabel` to core/code's saved markup** via `getSaveContent.extraProps`.
Rejected outright: it changes what `save()` writes, so every existing code block
becomes invalid the moment the filter is not running. The comment-serialised
attribute gets the same result with no exposure.

**Style the callout from its own `block.json` `style` handle.** Rejected: the
theme owns presentation, and a stylesheet shipped by the plugin would be a
second place where the design's values live and a second thing to load in the
editor.

**Restrict the whole editor to the vocabulary.** Rejected: it breaks the site
editor and Phase 5's page patterns. See §4.

**Native list markers** — `list-style-type: "—"` for `ul` and
`decimal-leading-zero` for `ol`, styled through `::marker`. Attractive because
it keeps list semantics without a `role` attribute. Rejected: `::marker` cannot
be placed in a fixed 28px column, and `content` on `::marker` is not supported
widely enough to rely on. The design is explicit about the column.

**Set `contentSize` to `--measure`.** It would make everything align without a
specificity fight. Rejected: the design spans headings, quotes, code, images and
tables across the full 880px column and caps only the running text. Narrowing
the column narrows all of them.
