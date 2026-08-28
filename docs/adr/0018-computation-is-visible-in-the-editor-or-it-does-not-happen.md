# ADR-0018 — Computation is visible in the editor, or it does not happen

## Status

Accepted — 2026-08-26. Supersedes the destination half of
[ADR-0006](0006-chrome-and-derived-destinations.md), most of
[ADR-0008](0008-unresolved-destinations-degrade-visibly.md), and the derived
featured series in [ADR-0016](0016-a-post-carries-no-fields-of-ours.md).

## Context

`CLAUDE.md` §5.1 says the theme may not hardcode an href to a page. David creates
every page, picks its slug, and may move it; a template that ships
`href="/contact"` is the theme deciding something that is not the theme's to
decide. That rule is right, it is not in question here, and **it is not what
produced the mechanism below** — a point worth making precisely, because the
first draft of this ADR got it wrong and proposed amending §5.1 on the strength
of it.

§5.1's bullet sits in a list of *code* assumptions — `add_rewrite_rule`,
`page_on_front`, `is_page( 'contact' )`, a slug in a template name. Every item
forbids code from deciding something that is David's to decide. None of them
requires that a link's URL be computed, and none of them says anything about
overwriting a value that is already there. A link David sets in the site editor
violates no bullet in that section.

The leap happens in **ADR-0006 §2**, in one sentence: "No template, part or
pattern in this phase contains an href to a page … **There is an integration test
asserting that no pattern contains an href at all.**" That is a much stronger
rule than §5.1's, it was invented there, and a test was written to hold it. Once
*no href may exist in the markup*, an author-set href is not a case the code
failed to preserve — it is a case the architecture has defined out of existence,
which is precisely why `set_attribute()` could be written unconditionally without
anyone noticing. The same section justifies every destination against the front
end and does not mention the site editor once.

What is in question, then, is the mechanism ADR-0006 §2 built and Phase 5b
extended. A `core/button`
in a template carries a class — `dp-to-contact`, `dp-to-posts`, `dp-to-series` —
and ships **with no href at all**. At render time
`DP\Theme\Chrome\Navigation::resolve_destination()` matches the class against a
list of twelve destinations, resolves each from something David controls (a
Reading setting, `get_privacy_policy_url()`, the page carrying an assigned
custom template), and writes the `href` in.

The rule was satisfied. Four things were not foreseen.

**1. It overwrites an href a person set.** `resolve_destination()` ends in an
unconditional `set_attribute( 'href', $url )`. The reasoning that produced it is
reconstructible and was never written down: *the shipped templates carry no href,
therefore there is never an href to preserve.* True on a fresh install, and false
the moment the site editor is opened, because that edit is saved as a
`wp_template` post and **that** markup does carry one. So David can select the
button, set a link, watch the editor show it, and watch the front end discard it.
Nothing reports this, because the editor renders saved markup and never runs the
filter. The overwrite is not a decision anybody made; it is a consequence of an
assumption that stopped being true and was never re-checked.

**2. The trigger is invisible.** Nothing about `class="dp-to-contact"` says that
PHP will rewrite this element. It cannot be seen in the site editor, cannot be
inferred from the markup, and cannot be found by grepping for the symptom.

**3. It has already cost a day.** `Destinations`' own class docblock records it:
a stale transient "silently removed four buttons from the home page for a day".
ADR-0008 was written in response and treated the symptom — an unresolved
destination now keeps its element and loses its href, so the failure is visible.
The cause was the layer of resolution itself.

**4. It grew.** The same shape was copied four more times into
`themes/dpaternina/src/Chrome/`, and a review of the folder found three of the
four to be doing work that is not necessary at all:

- `Brand::link_home()` filters `render_block_core/site-logo` to overwrite the
  logo's href with `home_url( '/' )` — which is what core had already put there.
  Its docblock names the motive exactly: "the right URL and the wrong
  provenance". It exists so the logo resolves through `Navigation` like
  everything else, and it silently overrides core's own homepage-logo linking
  setting to achieve that.
- `FilterPills::add_all_pill()` splices an `<li>` into rendered
  `core/categories` output when the block carries `dp-filter-pills`. The editor
  draws the category list; the front end draws one more item than the editor
  drew.
- `FilterPills::wrap_the_counts()` is a regular expression over rendered HTML,
  moving core's `(3)` text node inside the anchor so it can be coloured. It is
  anchored on markup core is free to change. **This ADR also claimed it corrupts
  the name of any category containing parentheses. That was wrong** — asserted
  from reading the pattern, never reproduced. Phase B2 tried to write the failing
  test first, could not, and probed nine names through the live walker
  (`Tools (beta)`, `Half (`, `A) (B`, `Say "hi" (loudly)` …); every one rendered
  correctly, because `[^<]*` cannot cross `</a>` and the only thing
  `Walker_Category` writes after the anchor is ` (N)`. The correction is left
  here rather than edited out; the deletion stands on the fragility alone.
- `PostPresentation::link_the_series()` is `resolve_destination()` again, for
  `dp-to-post-series`. Unlike the other four its *value* is legitimate: the
  series a post belongs to is a property of the post being read and there is no
  fixed URL anybody could type.

Set against those, two mechanisms in the same folder are right, and the
difference between them and the five above is the whole of this decision:

- **`%dp-part%`** (`PostPresentation::PART_TOKEN`). A `core/post-navigation-link`
  label containing that token has the adjacent post's part number substituted in.
  The token sits in the label field in the site editor. Its own docblock: "the
  template says out loud that the label is computed."
- **Block bindings** — `SiteFacts`, `ArchiveFacts`, `PostPresentation::value()`.
  Core's sanctioned mechanism for feeding a block a computed string. The binding
  is an attribute on the block, visible in the editor, allowlisted per key, and
  returns `null` for an unknown key so the block keeps the content the author
  typed.

Both are computed. Neither is hidden, and neither destroys anything a person set.

## Decision

**Computation is visible in the editor, or it does not happen.**

Three rules, in force for both packages:

1. **A template says what a template can say.** A link to a page David made is a
   link he sets in the site editor. The theme ships the button with no href and
   no class asking for one; setting it is his, once, per link.
2. **Code that must compute something announces itself.** The trigger is a block
   bindings source, a named block in the inserter, or a literal token in a
   visible field. A bare CSS class is not an announcement.
3. **Code never overwrites a value an author set.** A derivation fills a blank.
   Where a value is present, the author's value wins, and no filter may take it
   away.

Applied, that means the `dp-to-*` destination system is **deleted** — the class
matching, `Navigation::DESTINATIONS`, `Navigation::TEMPLATES`,
`Navigation::UNRESOLVED_CLASS`, `Brand::link_home()`, and the parts of
`Destinations` that exist only to feed them. Roughly fourteen link sites across
the templates, parts and patterns become ordinary links.

Three cases survive because they are genuinely not typeable, and each becomes a
**named block** rather than a class:

| Was | Becomes | Why it cannot be typed |
|---|---|---|
| `dp-to-post-series` | `dpaternina/series-parts-link` | The series of the post being read. Different on every post. |
| `dp-to-resume-pdf` | `dpaternina/resume-download` | Built from a query variable `dp-core` owns, whose name the theme is not allowed to know. |
| `dp-to-feed` | `dpaternina/feed-link` | `get_feed_link()`. Changes with the permalink setting. |

`FilterPills` becomes one dynamic block, `dpaternina/filter-pills`, which renders
the All pill and the counts as its own markup. Both rewrites go with it — for the
fragility and the editor/page divergence, not for the parenthesis bug this ADR
wrongly claimed above. `Destinations::posts_index()` survives as its href, because the
posts index is a Reading setting rather than a slug this theme invented.

`PostPresentation::add_tone_class()` stays — it adds a class rather than
replacing an author's value, and `dp-tone-auto` does say it is automatic — but it
narrows from bare `render_block` to the block types that use it.

`ArchiveFacts`, `SiteFacts`, `PostPresentation`'s bindings source and
`%dp-part%` are unchanged. They are the pattern, not the problem.

## Consequences

**What this makes easy.** The markup says what it does. A link that points
somewhere wrong is fixed where it is wrong, in the editor, by the person who can
see it. The editor and the front end draw the same thing for every link on the
site, which is what ADR-0008 wanted and could not get by patching the failure
mode of the thing that caused the divergence.

**What it costs, and it is a real cost.** A link set by hand is a literal URL. Re-slug
a page afterwards and its links go stale, silently, until somebody notices. The
derived system could not go stale that way — that was the point of it. We are
trading a rare, invisible, unfixable failure for a common, visible, one-click
one. On a site with one author and about fourteen links, that is the right trade;
on a site with two hundred pages it would not be.

**Fresh installs ship with blank links.** Every `dp-to-*` button loses its class
and gains nothing, so a newly installed theme has buttons that go nowhere until
David sets them. `dp-core`'s seeder sets them on a seeded site, so `npm run
env:reset` still produces a working site and the e2e suite still has links to
click. A real install is a one-time setup pass. The site is not live, so that
pass costs nothing now and would have cost something later.

**`CLAUDE.md` §5.1 is unchanged, and deliberately so.** It never required this
mechanism; ADR-0006 §2 did. What is superseded is that section and the
integration test asserting no pattern contains an href at all — a test that
encoded the rule this ADR removes, and that will now fail correctly. §5.1 keeps
its meaning: shipped code may not decide David's slugs. A link he sets in the
editor is him deciding, which that section never forbade.

**What it commits us to.** Rule 2 is the acceptance criterion for every phase
after this one, and Phase E — the field panels on the three custom post types —
is the first thing it is measured against.

## Alternatives considered

**Make `resolve_destination()` yield to an existing href.** The obvious minimal
fix, and the first one proposed: read the href, return early if there is one,
derive only into a blank. It repairs the data loss and repairs nothing else. The
trigger is still an invisible class, the editor still cannot tell you that this
button behaves differently from the one beside it, and the four copies of the
pattern elsewhere in the folder are all still there. It makes a hidden mechanism
polite rather than removing it.

**Keep the system and add a visible control for it** — a real block with a
destination dropdown, so "Contact page" is a choice in the inspector. Genuinely
considered, and it satisfies all three rules. Rejected on weight: it is a block,
a build, an inspector and a test suite to reproduce what `core/button`'s existing
link picker already does, for the sole benefit of surviving a re-slug. The three
survivors above earn a block each because nothing else can produce their URL.
This one would not.

**Use `core/navigation` and a menu for the chrome.** Rejected for the header, and
ADR-0011 has the reason: a menu is referenced from a template by post ID, so the
editor and the front end can draw different menus. It remains a reasonable answer
if the header ever needs to be re-orderable by David, which it does not today.

**Leave `FilterPills` alone and only fix the regex.** Rejected. The regex is the
symptom; the block that renders one thing in the editor and another on the front
end is the defect, and a dynamic block removes both for about the same work as
hardening the pattern.
