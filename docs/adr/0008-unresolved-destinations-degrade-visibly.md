# ADR-0008 — An unresolved destination degrades visibly, not silently

## Status

Accepted — 2026-08-20. Supersedes ADR-0006 §2's final paragraph. **Mostly
superseded by
[ADR-0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md) —
2026-08-26**: the destination classes this describes are deleted, so most links
have no unresolved state to degrade from. The rule survives for the three named
blocks that replace them.

## Context

ADR-0006 §2 built the chrome's links without hrefs. A link carries a class —
`dp-to-work`, `dp-to-contact` — and `DP\Theme\Chrome\Navigation` gives it a URL at
render time from something David controls: a Reading setting, core's feed link,
or the published page carrying one of this theme's `dp-` custom templates. That
part stands and is not in question here.

What ADR-0006 also decided was the failure mode:

> **A destination with nothing behind it renders as nothing.** No contact page
> means no "Get in touch" button anywhere on the site.

Reviewing the rendered home page against the design, David reported that the
"Full timeline →" button "is visible in the Site Editor but missing on the front
end". So were "See the work", "Work with Fanxie Lab" and "Say hi" — every button
resolving through an assigned template — and nothing on the page, in the editor,
or in a log said why.

The cause was not a missing page. `Destinations` caches a template-slug-to-page-ID
map in a transient. The map used to be keyed by whatever `_wp_page_template`
happened to hold, which is `dp-work.html` on a seeded site and `dp-work` on one
assigned from the admin dropdown. A later fix normalised the two spellings on the
**write** side only, and gave the transient no version, so every install already
holding the old map went on answering "no such page" for up to a day. The
resolver was wrong; the page was fine.

Two facts made that invisible rather than obvious.

1. **The site editor renders the saved block markup.** It does not run
   `render_block`, so it draws the button whether or not the front end will. A
   block present in the editor and absent from the page is not a state the editor
   can warn about.
2. **`resolve_destination()` returned the empty string**, and a stylesheet rule
   collapsed the empty `wp-block-buttons` wrapper so the layout did not even
   shift. The page looked finished.

The combination means the same symptom — a button that is not there — covers both
"David has not made that page yet", which is expected and temporary, and "the
resolver is broken", which is a bug. Neither is distinguishable from the other,
and neither is distinguishable from nothing being wrong at all.

## Decision

**A destination that cannot be resolved keeps its button and loses its link.**

`Navigation::resolve_destination()` no longer returns the empty string. It always
returns markup, and always stamps the anchor with `data-dp-destination="<name>"`,
so every derived link says in the DOM which destination it asked for. When the
URL resolves, the anchor gets its `href`. When it does not, the anchor gets:

- no `href` — so it is not a link, is not focusable, and cannot reach a 404,
  which was ADR-0006's actual requirement;
- `role="link"` and `aria-disabled="true"`, so it announces as an unavailable
  link rather than as a stray run of text;
- the class `dp-destination-unset`, which the stylesheet dims to the design
  system's own disabled opacity (`Button.jsx`, `0.45`) and takes the pointer
  from.

The editor and the front end now agree: both draw a button that goes nowhere,
because in the saved markup it *is* a button that goes nowhere. The difference
between "not made yet" and "broken" is no longer the difference between a page
that looks finished and a page that looks finished.

Two supporting changes stop the specific cache bug recurring:

- The transient's key carries a **shape version** (`…_template_pages_2`). A
  change to what the map holds takes the suffix with it and the old value is
  left to expire rather than being read as the new one.
- `Destinations::only_int_map()` normalises keys **on read** as well as on write,
  so a map written by any past version either resolves correctly or is ignored.

## Consequences

- **A site with no contact page shows a dimmed "Get in touch" in its header.**
  That is the point, and it is also a cost: it is visible to visitors, not only
  to David. The judgement is that a site mid-setup showing an obviously unwired
  control is better than a site mid-breakage showing nothing, because only one of
  those two states can be diagnosed by looking.
- **The layout no longer changes when a destination is missing**, which means a
  page's vertical rhythm is the same before and after David assigns a template.
  `.wp-block-buttons:not(:has(.wp-block-button))` existed to hide the hole the old
  behaviour left; it is gone, along with the hole.
- **`data-dp-destination` is a public-ish contract.** It is in the rendered HTML
  of every derived link, and the integration suite asserts on it. Renaming it is
  a change to markup other things may come to read.
- **`dp-core` is unaffected.** The `dp_destination_url` filter still answers
  `null` and the plugin still decides for itself what to render — its two links
  are inside its own markup, not inside a `core/button`, and ADR-0006's seam
  between the packages does not move.
- **Digest §2.1's Watch treatment is untouched.** A navigation item for a page
  that does not exist still stays out of the menu; that is core's navigation
  behaviour over pages David curates, not this filter.

## Alternatives considered

**Keep dropping the block, and make the editor drop it too.** The honest version
of ADR-0006's rule. Rejected: the site editor renders saved markup through the
block's `save` output, not through `render_block`, so there is no supported hook
that would make a static `core/button` disappear in the canvas for a reason only
the server knows. The two contexts cannot be made to agree on absence.

**Keep dropping the block, and add an admin notice.** Rejected as a second
mechanism for the same fact, which then has to be kept in step with the first. It
also fires nowhere near where the problem is visible, and it would have said
nothing useful about the cache bug — the notice would have been "no page carries
dp-work", which was false.

**Point an unresolved destination at the home page.** Rejected outright: it is a
link that works, to the wrong place, and it makes the broken state the hardest of
the three to notice.

**Fix only the cache and leave the drop-to-nothing behaviour.** Tempting, and it
would have cleared David's report. Rejected because it fixes one instance of a
class of failure the design of the filter guarantees will recur — every future
resolver bug has the same symptom and the same silence.

**Drop the transient entirely.** The map is one `meta_key` EXISTS query, and the
header alone asks for the contact page twice. Rejected on the same grounds
ADR-0006 gave; the cache was not the problem, the unversioned key was.
