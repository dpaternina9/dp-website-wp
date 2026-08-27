# ADR-0006 — The chrome: two lines of `theme.json`, and where a link's URL comes from

## Status

Accepted — 2026-08-20. Section 2's last paragraph — "a destination with nothing
behind it renders as nothing" — is superseded by
[ADR-0008](0008-unresolved-destinations-degrade-visibly.md). **The destination
mechanism is superseded by
[ADR-0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md) —
2026-08-26**: a link to a page is set in the site editor, not resolved from a
`dp-to-*` class at render. Section 1 (the two lines of `theme.json`) stands.

## Context

Phase 5 builds the parts of the site that appear on every page: a header, a
footer, a mobile menu, and the closing CTA band. Three constraints meet here and
two of them pull against each other.

1. **`theme.json` is frozen.** ADR-0005 §9: "Phase 4 ends and `theme.json` stops
   changing. A change after this point is an ADR, not an edit." Phases 5, 7 and 9
   run in parallel against it.
2. **Pages belong to David.** `CLAUDE.md` §5.1 forbids a route, a slug, a
   `get_page_by_path()`, a `page_on_front` read, and "no hardcoded href to a
   page". A site header is exactly where a theme normally writes five of them.
3. **Every page must work with JavaScript off** (`CLAUDE.md` §1.7), and the
   design's mobile menu is a full-screen panel that the plan and digest §6 both
   specify as a `<dialog>` — an element that, left alone, only opens from
   `showModal()`.

Verified environment: WordPress 7.1, PHP 8.4, PHPUnit 9.6.36, Playwright against
the wp-env `tests` site.

## Decision

### 1. `theme.json` gains `templateParts`, and nothing else

Two entries, declaring `header` and `footer` and the area each belongs to:

```jsonc
"templateParts": [
  { "name": "header", "title": "Header", "area": "header" },
  { "name": "footer", "title": "Footer", "area": "footer" }
]
```

This is the change the freeze exists to make deliberate, and it is the smallest
one that works. A template part in `parts/` renders without being declared; what
the declaration adds is the **area**, and the area is what puts the part under
Header and Footer in the site editor instead of in an undifferentiated list, and
what lets the "replace" flows offer the right alternatives. It is metadata about
files this phase ships, not a setting and not a style: nothing in it changes what
the editor offers, what a block may be given, or how anything is drawn. Phase 4's
reasons for freezing the file — that the editor's vocabulary stops moving, and
that three phases can run against it — are untouched.

Nothing else in `theme.json` moves. In particular the hero and the section heads
take their type from a stylesheet, not from `styles.blocks`, exactly as ADR-0005
§1 divides the two.

### 2. A link says which destination it wants; the theme says where that is

No template, part or pattern in this phase contains an href to a page. A link
carries a class — `dp-to-posts`, `dp-to-contact`, `dp-to-work`, `dp-to-about`,
`dp-to-resume`, `dp-to-feed` — and `DP\Theme\Chrome\Navigation` gives it a URL at
render time. There is an integration test asserting that no pattern contains an
href at all.

Each destination resolves from something David controls, and the list of *kinds*
of thing is closed:

| Destination | Resolved from |
|---|---|
| `posts` | `page_for_posts`, a Reading setting. Falls back to `home_url( '/' )`, which is where the posts are when nothing is chosen. |
| `feed` | `get_feed_link()`. |
| `contact`, `work`, `about`, `resume` | The published page carrying that `dp-` custom template. |

The last row is §5.1's own instruction — "branch on the assigned template
(`get_page_template_slug()`) … never on a slug" — read as a lookup rather than a
branch. A page carrying `dp-contact` **is** the contact page, by David's
decision, under any slug, and moving or renaming it moves the link.

**A destination with nothing behind it renders as nothing.** No contact page
means no "Get in touch" button anywhere on the site, which is the treatment
digest §2.1 already gives Watch — "the nav entry stays out of the menus rather
than pointing at a 404" — applied consistently.

The template names are stored **without the `.html` extension**, because that is
what WordPress stores: a block theme's custom templates are offered to the admin,
and validated by the REST API, under their slugs. `Destinations` normalises
either spelling, and a test asserts that every template the chrome names is one
`wp_get_theme()->get_post_templates()` actually offers. This was a real bug
before it was a rule.

### 3. "The blog is active" is derived from the shape of the queried object

Digest §2.1 wants Blog to read as active for the index, a post, a category and a
series. Core cannot do it: a navigation item is marked current when its target
*is* the queried object, and on a post the queried object is the post.

`Navigation::viewing_writing()` answers instead, and it answers by **shape, not
by name**: the queried object is a post, or it is the posts index, or it is a
term whose taxonomy is attached to `post`. That last clause covers `category` and
`dp_series` without this theme ever repeating a taxonomy slug that `dp-core`
owns, and covers whatever a later phase registers with the same shape.

The item is then found by comparing hrefs against the posts index — which is not
the URL matching §2.1 rules out, because the URL being compared is the one
WordPress derived from the Reading setting a moment earlier, not a path this
theme decided on. What is *asserted* comes from the queried object alone. The
whole pass is skipped when David has chosen no posts page, because otherwise the
item pointing at the site root would light up on every post, and that item is
Home.

### 4. The mobile panel opens twice, and the CSS one is not the fallback

The panel is a `core/group` with `tagName: dialog`, so a real `<dialog>` element
ships in the template part with no PHP involved. It opens two ways:

- **Without JavaScript.** The hamburger is a link to the panel's id, and
  `.dp-panel:target` overrides the UA stylesheet's `display: none`. Escape and
  the focus trap are absent; the panel covers the page and the close control is
  a link back to the header.
- **With JavaScript.** `assets/js/nav-panel.js` intercepts the same link and
  calls `showModal()`. The top layer supplies Escape and the focus trap for
  nothing, which is the reason the element is a dialog rather than a `<details>`;
  the script adds the scroll lock, which browsers still leave to the page, and
  returns focus to the hamburger on close.

Both states are styled deliberately and both are tested in a browser — one spec
drives the modal from the keyboard alone, another switches scripting off and
opens the panel anyway.

### 5. One block is registered by the theme

`dpaternina/series-planned` renders "Still to come" on the series archive. It is
the one section of this phase no core block can produce: a planned part is a
**draft post** (plan §3.1) and `core/query` has no `postStatus` attribute, so the
only way through core would be to hand a `core/post-template` a set of drafts —
at which point `core/post-title` links them and `core/post-excerpt` prints the
opening of an unfinished piece of writing.

It is registered by the **theme**, against the letter of `CLAUDE.md` §2.1's table
and with its rule of thumb: "if switching themes would destroy content or break a
URL, it is not theme code". Nothing here is content. The drafts, the term and the
meta are `dp-core`'s and stay put; what disappears with the theme is one
arrangement of them, used by one of this theme's own templates and marked
`inserter: false` so it can never appear in anyone's writing. ADR-0005 §5's
objection to theme-registered blocks — that they invalidate saved content — does
not reach a block that appears in none.

It reads the parts through Phase 3's `SeriesParts`, which returns `PlannedPart`
objects carrying no post ID, so there is nothing to build a permalink from even
by accident. With `dp-core` deactivated it renders nothing rather than fatalling.

## Consequences

- **`theme.json` has been opened once.** The precedent is narrow on purpose:
  metadata about files a phase ships, with no effect on the editor's vocabulary.
  A change to `settings` or `styles` still needs its own ADR and a better reason.
- **A link with a broken class silently disappears.** `dp-to-contct` renders
  nothing at all rather than an obviously wrong link. The integration suite walks
  every destination for exactly this reason.
- **David must set Settings → Reading to get a blog.** Without a posts page the
  site still works — the front page carries the latest writing and the category
  archives are reachable — but there is no blog index, nothing in the navigation
  reads as the blog, and the All pill points at the site root. That is the
  correct behaviour and it is not obvious; it is on the cutover runbook.
- **The navigation's fallback lists every published page.** That is core's
  `WP_Navigation_Fallback`, and on a site with a dozen pages it is a wall rather
  than the design's five items. It is a fallback: David curates the menu in the
  site editor and it becomes his. The theme cannot curate it for him without
  naming pages, which is the thing §5.1 forbids.
- **Two nav renders per page.** The wide navigation and the panel's navigation
  are the same menu rendered twice, so the links exist twice in the DOM. Only one
  is ever exposed — above 720px the panel is a closed `<dialog>`, below it the
  wide navigation is `display: none` — but a change to either has to be made in
  the part twice.
- **The header's container query decides the panel's reachability.** The script
  closes the panel when the hamburger stops being visible, so the two never
  disagree, and neither knows the number 720.

## Alternatives considered

**Leave `templateParts` undeclared.** Rejected: it works, and it puts the header
and the footer in the site editor as uncategorised parts with no area, which
breaks the replace flows and reads as an oversight every time someone opens it.
The cost of the ADR is one file; the cost of the omission is permanent.

**Use `core/navigation`'s own `overlayMenu`.** Rejected on two counts. Its
breakpoint is a **media query at 600px** in core's stylesheet, and digest §5.2
puts this threshold at 720px of the header's own width; and its overlay is opened
by the Interactivity API, so with scripting off the menu cannot be opened at all.

**Build the panel from `<details>`/`<summary>`.** Attractive: it opens without
JavaScript on its own terms. Rejected because the plan and digest §6 both name
`<dialog>`, and the reason they do is that the top layer gives Escape and the
focus trap for nothing — with `<details>` both would be ours to write, and a
hand-written focus trap is a bug waiting for a browser update.

**Put the destinations in `theme.json` or in an option.** Rejected: it is a
second place for David to keep in step with the pages he already has, and the
template he assigns already says which page is which.

**Let David add the blog link to the menu himself and drop the derivation.**
Rejected: he still can, and the derivation is about the *active* state, which no
menu entry can express. Core marks an item current only when it is the queried
object, and a post is never the page it is listed on.

**Register `dpaternina/series-planned` in `dp-core`.** Rejected on §2.1's rule of
thumb — no content is at stake — and because it would put a block in the plugin
whose only purpose is one of this theme's templates. If a future theme wants a
"still to come" list, `SeriesParts` is already the plugin's public answer.
