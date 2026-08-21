# ADR-0011 — The brand mark is content, and every spacing rule out-specifies the block gap

## Status

Accepted — 2026-08-21. Extends ADR-0008; adds two entries to `theme.json`
`customTemplates`, which CLAUDE.md §5 says needs an ADR.

## Context

David reviewed the rendered site against the design and reported four things.
Three of them turned out to be one problem each; the second turned out to be a
class of problem the theme had been carrying since Phase 5.

**"I can't edit the logo."** The mark was not an image. `parts/header.html`
rendered `core/site-title` with the class `dp-brand`, and `chrome.css` painted
`assets/img/dp-mark-gradient-128.png` as a `background` on `.dp-brand a` with the
title text pushed off-screen by `text-indent: -100vw`. The footer was the same
rule at 30px. Nothing in the admin could change any of it: swapping the mark
meant editing a stylesheet and shipping a release. The link's accessible name
came from a site title nobody could see rather than from the mark itself.

**"Spacing is off throughout."** WordPress emits its layout styles as
`:root :where(.is-layout-flow) > * { margin-block-start: var(--wp--style--block-gap) }`
and `:root :where(.is-layout-flex) { gap: … }`. Both are **one class** of
specificity — exactly the same as a bare `.dp-bento`, `.dp-latest` or
`.dp-badges` in this theme's stylesheets. A tie is broken by load order, and the
load order differs between the two contexts: the front end prints the global
styles first, the block editor injects them last (ADR-0008). So a one-class rule
here is the design's value on the site and core's 24px block gap in the canvas.

Worse, where the theme had written no rule at all, the block gap simply applied.
Measured on `:8888`, the RIGHT NOW bento's tiles each carried a 24px
`margin-block-start` on top of the grid's own 16px `gap` — a 40px row gap where
the design draws 16 — and the three "Things I've shipped" rows, which the design
stacks with `gap: 0` and separates with a border, floated 24px apart. The
`.dp-right-now` section had no block padding at all: its top read correctly only
because core's block gap happened to be the same number as the design's 24px, and
its bottom was missing entirely, so the record strip ran into the band below it.
A sweep of the home page found **18 elements** whose margins or gaps differed
between the page and the canvas.

**"FOOTER — add the missing menu."** There was exactly one `wp_navigation` post
and none of the three `wp:navigation` blocks in the chrome carried a `ref`, so
the header, the mobile panel and the footer's "Site" group all resolved to the
same menu. The design's footer has three groups — SITE, WRITING, MORE — and a
bottom bar carrying PRIVACY and COLOPHON. MORE did not exist; neither did the two
bar links.

**"Remove the content block."** `core/post-content` on `front-page.html` and
`dp-work.html` drew whichever page happened to be queried — the posts index, on a
site with no static front page — inside a `.dp-section` group.

## Decision

### 1. The brand mark is `core/site-logo`, and `dp-core` seeds the default

All three places render `core/site-logo`. It reads the `site_logo` option, so
David swaps the mark from Appearance → Editor → Styles or from the Customizer,
at any time, with no code and no deploy. `chrome.css` keeps the sizing — 34px in
the header and the panel, 30px at 0.85 opacity in the footer — keyed off the
block's own classes rather than off a background image.

`themes/dpaternina/assets/img/dp-mark-gradient-128.png` stays in the theme and
stays the default. `dp-core`'s seeder sets it as the site logo on a site that has
none, so a fresh install is not blank; it asks the theme for the path through a
new `dp_brand_logo_path` filter rather than reaching into the theme's directory,
which is the same seam as `dp_destination_url`. It never replaces a logo David
chose, on the first run or on any run after it.

`DP\Theme\Chrome\Brand` makes one correction to core's block: the link's `href`
comes from `Navigation::url_for( 'home' )` and the link is stamped
`data-dp-destination="home"`, so the mark answers to the same resolver as every
other link in the chrome and says what it asked for.

### 2. Every rule that sets a margin or a gap names a second class or an element

The sweep is mechanical and the rule is now stated: a selector in this theme's
stylesheets that sets `margin-block-*`, `gap`, `row-gap` or `column-gap` on a
block must be at least two classes, or one class and an element name. Where the
design's own composition carries the spacing — the bento, the shipped rows, the
section heads, the footer's grid and columns, the work cards — the theme
explicitly zeroes core's block gap on the children, at
`.dp-x.wp-block-group > *`, which is two classes and therefore wins in both
contexts.

The values themselves stay where they were: in the hand-written stylesheets,
against the design's own token names. **No `theme.json` `styles` entry was added
for spacing.** The root `blockGap` is still `--space-5`, because post content
genuinely wants it; what changed is that the theme's own compositions now opt out
of it by name instead of fighting it by accident.

### 3. The footer's three groups are `dp-to-*` links, not menus

Every link in the footer names a destination and is given a URL at render time,
exactly like the rest of the chrome. Two destinations resolve through core's own
settings — `posts` through Settings → Reading and, new here, `privacy` through
Settings → Privacy. Four resolve through an assigned custom template.

Two of those templates are new. `templates/dp-uses.html` and
`templates/dp-colophon.html` are **byte-identical to `page.html`**: assigning one
changes nothing about how the page renders, and the assignment *is* how David
tells the theme which page is which. `Navigation::TEMPLATES` gains `uses` and
`colophon`; `theme.json` `customTemplates` gains two entries.

Watch stays out until Phase 12, the same rule Phase 5 applied to the header.

### 4. `core/post-content` and its wrapper leave both composed templates

Removed from `front-page.html` and `dp-work.html`, together with the
`.dp-front-content` and `.dp-work-intro` groups that held them, and the one CSS
rule that styled `.dp-work-intro`.

## Consequences

**The mark can now be missing.** `core/site-logo` renders nothing when no logo is
set. That is correct behaviour for the block David asked for — a logo he can swap
is a logo he can clear — and it is honest in the editor, which shows its own
"add a site logo" placeholder. The seeder is what stops a fresh site from being
blank. A site running this theme with `dp-core` deactivated and no logo chosen
has no mark in its header until somebody uploads one.

**Three identical logo links on the front page.** Core stamps
`aria-current="page"` on a site-logo link when the home page is being viewed, and
there are three of them (one is inside the closed mobile panel). This is core's
own behaviour for the block and is not corrected here.

**The spacing rule is a standing tax on new CSS.** Every new component rule in
this theme has to carry a second class or an element name. It is already the
convention for type (`p.dp-badge`, `h3.dp-tile-title`) after Phase 5b; it now
applies to spacing as well. `tests/e2e/spacing.spec.ts` sweeps the home page in
both contexts and fails on any new one-class rule, naming the element — which is
the enforcement, because nothing in the PHP suite can see the cascade.

**Two more entries in David's template dropdown.** "Uses" and "Colophon" now
appear alongside "About", "Contact", "Résumé" and "Work — timeline". They have to
be assigned before the footer's MORE group and the bar's COLOPHON link resolve;
until then those links render visibly and inert, which is ADR-0008's behaviour
and is the signal that something is unassigned.

**The merge queue's note about those pages is now wrong.** Phase 7 told David
that assigning a `dp-` template to Uses or Colophon would lose the eyebrow and
the deck. That was true of the templates that existed then. It is not true of
`dp-uses` and `dp-colophon`, which are `page.html`.

**Two files duplicate `page.html`.** If `page.html` changes, they have to change
with it. There is no template-part or pattern indirection that would avoid this
without editing `page.html`, which this phase was told not to touch.

## Alternatives considered

**Keep the mark as a background image and add a filter to change it.** Rejected:
the thing David asked for is an admin control, and every mechanism short of
`core/site-logo` reinvents one WordPress already ships.

**Render the theme's bundled mark from PHP when no logo is set.** This would make
the header impossible to blank, with no dependency on the plugin or the seed.
Rejected because the block editor would still show its own empty-state
placeholder: the front end would draw a mark the canvas does not, which is
exactly the divergence ADR-0008 exists to stop. The seeder gives the same
protection with both contexts agreeing.

**Fix the spacing in `theme.json` `styles.blocks`.** A `blockGap` there applies to
every instance of a block type, so zeroing `core/group`'s gap would take it away
from post content too. Per-instance spacing in `theme.json` does not exist.

**Bake the spacing into the template markup as `style` attributes.** This is what
the block editor writes when a value is nudged in the inspector, and it would
work. Rejected on David's own acceptance bar: those values become content he
maintains by hand, in six places, and "out of the box" means the theme already
knows them.

**Give each footer group its own `wp_navigation` menu.** This is the obvious
WordPress answer and it is not available to a theme. A navigation block names its
menu with `ref`, which is a post ID; a template file shipped in a theme cannot
carry one. Supplying it server-side — from a class on the block, resolved to a
menu by slug — works on the front end and leaves the site editor drawing
whichever menu its own fallback picks, because the editor renders navigation from
the block's attributes on the client. One menu on the page and a different one in
the canvas is a worse bug than the one being fixed.

**Omit Uses and Colophon, as Watch is omitted.** Rejected: Watch is omitted
because the page does not exist yet and will in Phase 12. Uses and Colophon exist,
are seeded, and leaving them out would reduce the design's three-item MORE group
to one.

**Identify Uses and Colophon by slug, or by a `get_page_by_path()` lookup.**
Forbidden outright by CLAUDE.md §5.1, and asserted against by
`NoHardcodedRoutesTest`.

**Register post meta — "this page is the Uses page" — with an editor panel.**
Honest, and a real alternative. Rejected as more machinery than the problem
deserves: it would need a REST schema, a `PluginDocumentSettingPanel`, and a
JavaScript build in the theme, to reproduce something `_wp_page_template` already
does and that the chrome already reads.
