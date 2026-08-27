# Design digest — dpaternina.com

What the design actually contains, and what each part has to become in WordPress.
Source: `design-source/`. Where this doc and the design disagree, the design wins.

---

## 1. Decisions already locked (from the design's own Ledger)

These came with the design. They are not open questions.

| | |
|---|---|
| **Platform** | WordPress with a hand-written theme. Switched from Astro in Aug 2026. |
| **Structure** | One site. Home, Work, Blog, Watch, About, Contact. `blog.dpaternina.com` merges into `/blog`. |
| **Work** | No separate portfolio page — work lives on the timeline. Roles and shipped items only, no life events. Shipped items nest under the role they came from. Several can be open at once. |
| **Homepage** | Editorial, framing "1A — Now, then the record". ~70/30 personal. RIGHT NOW carries only current work. Past roles get one quiet strip. |
| **Tense** | MonsterInsights is past tense everywhere, with no availability language attached. Fanxie Lab is an **agency**, not a studio. |
| **Components** | Every repeated part is its own file. Posts and generic pages render through **one** block kit so they cannot drift. Interactive state lives on the page, not inside a component. |
| **Responsive** | **No media queries.** `clamp()` tokens, `auto-fit` grids, and container-relative layout for the four components that need a genuinely different shape. Mobile nav is a hamburger → full-screen panel. Mobile timeline **stacks** (framing A) rather than scrolling horizontally. |
| **Light mode** | **Ruled out.** The accents do not hold up on white. The `.dp-light` scope stays in the CSS in case it comes back — do not wire a toggle to it. |
| **Brand** | No gradient fills on buttons. No gradient-filled text. **One gradient per view**, spent on the monogram, the blog featured panel, the contact form edge, or the rule between post sections. |
| **Motion** | 120–380ms, decelerating curves only. Nothing overshoots, nothing bounces. |
| **Handed off to us** | Post types, fields, and templates. The design's `ACF …` notes are the designer's suggestion, not a decision. |

---

## 2. Page inventory

Fourteen states in `dpaternina.dc.html`, switched by a `previewView` prop.

**Pages are David's, not the theme's.** The theme registers no routes for any of them.
Every URL below that is a page is one David creates in the admin, with the slug he
wants, and to which he assigns a template from the dropdown. The theme's job is to make
the right templates available and to never assume a slug.

The only things the theme/plugin genuinely register are the `dp_series` taxonomy rewrite
and the query var behind the résumé PDF. Everything else is core hierarchy.

| View | URL | How it resolves | Notes |
|---|---|---|---|
| `home` | `/` | `front-page` (core) | Hero + RIGHT NOW strip + "Things I've shipped" band + latest writing |
| `timeline` | David's choice | **custom template `dp-work`** | Featured work cards → the timeline chart |
| `blog` | David's choice | `home` (core, via Settings → Reading) | Featured post, category pills, list, pagination |
| `post` | core permalink | `single` (core) | Lead image, series footer, newer/older nav |
| `category` | core | `category` (core) | Archive + "other categories" band |
| `series` | `/series/{slug}` | `taxonomy-dp_series` | The one registered rewrite. Slug is filterable. |
| `watch` | David's choice | **custom template `dp-watch`** | Deferred to Phase 12 |
| `about` | David's choice | **custom template `dp-about`** | Portrait, long-form, skills |
| `resume` | David's choice | **custom template `dp-resume`** | Ledger of roles + ships; PDF via `?format=pdf` |
| `page` | David's choice | `page` (core) | Uses, Colophon, Privacy — same block kit as posts |
| `contact` | David's choice | **custom template `dp-contact`** | Form / sent / failed states |
| `notfound` | — | `404` (core) | "This one did not survive the merge." |
| `offline` | — | service worker | Theme-bundled shell, precached. Not a page. |
| `site` | — | — | Preview-only: all of the above stacked |

### 2.1 Custom templates must not collide with the hierarchy

A block-theme custom template lives in `templates/` and is declared in `theme.json`
under `customTemplates`. **The file name matters.** A file called `page-work.html` is
also a hierarchy match, so WordPress would silently auto-apply it to any page slugged
`work` — re-creating the slug coupling by accident.

So every custom template is prefixed **`dp-`**: `dp-work`, `dp-watch`, `dp-about`,
`dp-resume`, `dp-contact`. Nothing in the core hierarchy starts with `dp-`, so these can
only ever be applied deliberately, from the admin.

```jsonc
// theme.json
"customTemplates": [
  { "name": "dp-work",    "title": "Work — timeline",  "postTypes": ["page"] },
  { "name": "dp-resume",  "title": "Résumé",           "postTypes": ["page"] },
  { "name": "dp-contact", "title": "Contact",          "postTypes": ["page"] }
  // …
]
```

**Navigation.** Header: Home · Work · Blog · Watch · About, plus a "Get in touch"
secondary button. These are menu items David points at his own pages — the theme ships a
nav block with sensible fallbacks, not hardcoded hrefs. `Blog` reads as active for
`blog`, `post`, `series`, and `category`, which is derived from the queried object, not
from a URL match. Watch is omitted until Phase 12 ships.

Footer: SITE (Work, Watch, About, Contact) · WRITING (All posts, My life story,
Categories) · MORE (Uses, Résumé, Colophon), plus © line, PRIVACY, COLOPHON, RSS.

Chrome (`SiteHeader` + `SiteFooter` + the closing CTA band) wraps every view.

---

## 3. Content model

The design ships a complete fixture. This is what it implies.

### 3.1 Posts — native `post`
Categories exactly as in `TERM_NAMES`: **Dev · My life story · Food · Music ·
Photography**. Each has a description used on its archive (`TERMS`).

**A post carries no fields of ours.** This list used to name five, and
[ADR-0016](adr/0016-a-post-carries-no-fields-of-ours.md) deleted all of them: none had
an editor control, so none could hold anything David put there. What the design draws
is derived at render:

| The design's | Where it comes from now |
|---|---|
| the coloured token on the hero and in `PostRow` | the series part if there is one, else the first category |
| its tone | pink in a series, teal outside one |
| "6 MIN READ" | the body, counted at 200 words a minute |
| the standfirst above the body | the post's own first paragraph |
| the mono caps caption under the lead image (`CAPTIONS`) | the attachment's caption, set in the media library |
| the part number | the post's position among the published posts in its series |

### 3.2 Series — taxonomy `dp_series`
`SERIES` in the fixture: slug `life-story`, title "My life story", a deck, and an
ordered `parts` array. Two kinds of entry:
- **published** — `{ part: 2, slug: 'workaholic-years' }` → resolves to a real post.
- **planned** — `{ title, years, note }` → no post yet, renders in "Still to come".

**Settled.** The taxonomy carries **no meta of ours**. The deck is the term's own
`description`, which core already draws on the Add Series and Edit Series screens
and which survives a WXR export; `dp_series_deck` and the term-edit control built
for it are gone. A planned part is a **draft
post** carrying the term (plan §3.1); its note is the draft's excerpt and its position
is the draft's date. Nothing stores a part number: it is the post's index among the
published parts, oldest first
([ADR-0016](adr/0016-a-post-carries-no-fields-of-ours.md)).

### 3.3 Roles — CPT `dp_role`
The timeline lanes. From `LANES`:
`org` · `title` · `start` (decimal year) · `end` (decimal year) · `range` (display
string) · `detail` · `stack` · `accent` (optional, e.g. Fanxie Lab = pink).
Fractional years encode months: `2026.4` ≈ May 2026.

Six in the fixture: Backbone Technology, Imaginamos, Aplyca, Globant, MonsterInsights,
Fanxie Lab.

### 3.4 Shipped work — CPT `dp_ship`
Nested under a role. `org` (the name) · `start` · `end` · `range` · `headline` ·
`detail` · `bullets[]` · `role` · `stack` · `artifactLabel` · `artifact` (a preformatted
terminal/code block) · `stat1` + `stat1Label` · `stat2` + `stat2Label` · parent role ·
featured flag · shot (for `WorkCard`) · optional write-up post.

Four in the fixture: Natural-language queries, Performance work, Kiveo, Agency platform & ops.

### 3.5 Videos — CPT `dp_video`
`source` ('TWITCH' | 'YOUTUBE') · `tone` · `vod` or `yt` id · `title` · `dur` · `when` ·
`note`. Plus a single "live now" state (`LIVE_NOW`) with `live`, `title`, `meta`, `note`.

Thumbnails are **never uploaded** — they resolve to public URLs:
- Live: `static-cdn.jtvnw.net/previews-ttv/live_user_<login>-1280x720.jpg`
- YouTube: `i.ytimg.com/vi/<id>/maxresdefault.jpg`
- Twitch VOD: `thumbnail_url` from Helix `/videos`, with `%{width}`/`%{height}` substituted

Twitch login in the fixture: `patsypatz`.

### 3.6 Gear — the Watch page's kit list
`GEAR`: four groups (Desk / Camera & light / Audio / Software), each with a tone and
three `{ name, note }` items. A block, not a post type.

### 3.7 Pages
`uses`, `colophon`, `privacy` — each with `title`, `updated`, `deck`, and a `body`
array in the same block vocabulary as posts. Plus About, Contact, Watch, Résumé.

---

## 4. Tokens → `theme.json`

Everything in `design-source/_ds/tokens/` is transcribed. Names are preserved.

- **Colour.** Five brand hues (teal `#08d9d6`, pink `#ff2e63`, gold `#ffb84c`, coral
  `#f4795e`, purple `#6b479c`) with 600/100 steps, seven neutrals, three gradients,
  and a semantic layer (`--bg-page`, `--text-primary`, `--accent`, …).
  The token file carries **contrast corrections in its comments** — `--dp-gray` was
  raised to `#9095a0` because the old value failed AA. Do not "tidy" these values.
- **Tone mixing.** A brand hue as text on its own tint does not clear AA. Components
  mix toward the current theme's text colour via `--tone-mix` / `--tone-toward`.
  `theme.css` adds `--accent-text` and `--hue-teal|pink|gold|purple` for hue-as-text.
  **Rule: `--dp-*` for fills, `--hue-*` for text.**
- **Type.** Bricolage Grotesque (display) · Manrope (body) · JetBrains Mono (labels,
  dates, code, captions). Ten sizes in two deliberate bands, plus `--fs-display` and
  `--fs-section` fluid pairs. `--measure: 68ch`.
- **Spacing.** Strict 4px grid, `--space-0`…`--space-10`. Control heights 36/44/52.
  Fluid `--gutter`, `--section-y`, `--section-y-sm`. Containers 640/880/1120/1320.
  Touch targets: 44px primary, 36px secondary.
- **Effects.** Seven radii, two border widths, five shadows (with a lighter light-mode
  set), three decelerating easings, three durations.
- **Base layer.** `:focus-visible` ring, placeholder contrast, media overflow, and a
  global `prefers-reduced-motion` backstop. This becomes the theme's base stylesheet
  almost verbatim.

Fonts are **self-hosted** and declared via `theme.json` `fontFace` — the design's
`@import` from Google Fonts does not ship.

---

## 5. Components → WordPress

| Component | Becomes | Interactive? |
|---|---|---|
| `SiteHeader` | template part `header` | Yes — mobile panel (`details`/`dialog`), Escape to close, scroll lock |
| `SiteFooter` | template part `footer` | No |
| `PageHero` | pattern / block variation | No |
| `SectionHead` | pattern (`label`, `as`, `tone`, `meta`, `action`) | No |
| `FilterPills` | template part; real links to filtered URLs | No — **not** JS tabs |
| `PostRow` | query-loop pattern, `list` and `compact` variants | No |
| `WorkCard` | query-loop pattern over `dp_ship` | Link only |
| `CtaBanner` | pattern, `plain` / `filled` | No |
| `ContactMethod` | pattern | No |
| `PostBlocks` | **`theme.json` block styles**, not a template part | No |
| `TimelineChart` | dynamic block `dp/timeline` | Yes — the only genuinely stateful piece |
| `OgCard` | server-rendered 1200×630 social image | N/A |

Primitives from the design-system bundle — **Button, Badge, Input, Switch** — are not
redefined by the library. They become block styles and small patterns.

### 5.1 The block vocabulary (the "house style")
The complete allowed set, from `PostBlocks` and the `house-style` fixture post:

`p` `h2` `h3` `h4` `quote(+cite)` `ul` `ol` `code(+label)` `note(+label)`
`image(+caption)` `table{head,rows}` `rule`

Mapping: `p`→`core/paragraph` · `h2..h4`→`core/heading` · `ul`/`ol`→`core/list` ·
`quote`→`core/quote` · `code`→`core/code` (wrapped, labelled, forced dark) ·
`note`→**custom `dp/callout`** · `image`→`core/image` · `table`→`core/table` ·
`rule`→`core/separator` (1px spectrum gradient at 60%).

Two details that are easy to miss:
- **`h4` is mono caps in the accent colour**, not the display face.
- **List markers are rendered, not native**: `—` for `ul`, zero-padded `01`/`02` for
  `ol`, mono, `--fs-xs`, `--accent-text`, in a 28px grid column.

House limits, stated in the reference post: 2 quotes, 6 list items, 15 code lines,
1 callout per post. Worth enforcing as editor warnings, not hard blocks.

### 5.2 Where the breakpoints actually are
The design measures **component width**, never viewport. In the theme these become
`@container` queries:

| Component | Threshold | Below it |
|---|---|---|
| `SiteHeader` | 720px | Hamburger → full-screen panel |
| `PostRow` | 560px | Date + category move above the title |
| `TimelineChart` | 700px | Bars drop; stacked disclosure list |
| `ComponentLibrary` | 900px | (dev tool only) |

---

## 6. What needs real JavaScript

Almost nothing. Everything else is CSS or a server render.

1. **Mobile nav panel** — open/close, Escape, focus trap, scroll lock. `<dialog>` gets
   most of this for free.
2. **Timeline disclosure** — many rows open at once, "expand all", deep-linking to a
   specific entry so a `WorkCard` can open it. `<details>` + a small controller.
3. **Timeline filter** — Everything / Roles / Shipped. Should also work as plain
   links with a query arg, JS only upgrading it to instant.
4. **Contact form** — progressive enhancement over a normal POST.
5. **Video click-to-play** — players must not load until pressed. This is a privacy
   promise on the Privacy page, not a nicety.
6. **Service worker** — for the `offline` view.

No jQuery. No framework. Every page must read and navigate with JS off.

---

## 7. Analytics and the privacy copy

The Colophon and Privacy pages in the design read like commitments — "no analytics
script, no cookie banner", "no third-party analytics, advertising, or social scripts
load on any page", "four plugins, all load-bearing". **They are placeholder copy, not
requirements.** Do not derive build constraints from them.

The actual decision: **the site runs Rybbit, installed as its own plugin.** It is not
theme code and not `dp-core` code — David installs and configures it, and nothing in
this repo enqueues an analytics script. The CSP that allows its origin is a security
plugin's, also David's — this repo sets no headers at all. What is left for us is
nothing; what is left for David is the Colophon and Privacy copy describing what Rybbit
collects on his configuration, which is **his to write** — not ours to draft, and not an
acceptance criterion for any phase.

What still holds, because it is a design property rather than a copy promise:

- **Video players do not load until pressed.** This is a performance and layout decision
  as much as a privacy one — the Watch grid renders from cached thumbnail URLs and stays
  static until a click.
- **No cookie banner exists in the design.** There is no UI for one anywhere in
  `dpaternina.dc.html`. If the analytics configuration turns out to need consent, that is
  a design change to raise, not a component to improvise.

**Flag for David before launch:** the Privacy page copy currently states the opposite of
what the site will do, and the Colophon's plugin count is now wrong in both directions.
Both are his to rewrite; Privacy is the one page where placeholder copy shipping as-is
would be actively misleading.

## 8. Placeholders — do not invent replacements

The fixture is deliberately incomplete. Four of the six roles say
"Placeholder role description". Stats read `—` and `EXAMPLE`. Kiveo's description is
"One line on what Kiveo does … copy to come."

Seed data must carry these through **as placeholders**, visibly. The Ledger is explicit:
"all dates, stats, and descriptions are placeholders you replace on the real site."
