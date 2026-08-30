# Implementation plan — dpaternina.com

Read `design-digest.md` first. Read `../CLAUDE.md` for the rules.
Every phase below is executed by the `wordpress-development-expert` agent.

---

## Architecture at a glance

```
themes/dpaternina/          block theme — theme.json, templates, parts, patterns, CSS
plugins/dp-core/            CPTs, taxonomies, meta, dynamic blocks, REST, WP-CLI
```

**Block theme, not classic.** The Colophon promises "a hand-written block theme" and
"the editor only offers blocks this design system has" — that is `theme.json` +
`allowed_block_types_all`, which a classic theme cannot do properly.

**Data lives in `dp-core`, not the theme.** Switching themes must not delete the
timeline. `dp-core` ships as a normal plugin, not an mu-plugin: mu-plugins cannot be
updated by the WordPress updater, and we want one release mechanism for both.

**Pages are David's.** The theme registers no routes for pages and never branches on a
page slug. Design-specific pages are `dp-`-prefixed custom templates declared in
`theme.json` `customTemplates`, assigned from the admin. See digest §2.1 — the prefix is
what stops the template hierarchy binding them to a slug behind our backs.

**No ACF.** The design's notes suggest it; we use `register_post_meta()` with
`show_in_rest`, typed schemas, and **block bindings** (`core/paragraph`,
`core/heading`, `core/image` bound to meta) for the presentational fields, plus a small
custom sidebar panel built with `@wordpress/scripts` for the structured ones
(`bullets`, `stats`, `artifact`). That keeps the plugin count at four and the meta in
the REST API, where the editor and the tests can both reach it.

**Plugins.** `dp-core`, Stackable, an SMTP/mailer, **Rybbit** (analytics), **AIOSEO**
(all SEO output), and a security plugin (all HTTP headers). Everything on that list
except `dp-core` is David's to install and configure; this repo neither enqueues nor
duplicates any of it. There is no target count — the Colophon's "four plugins" line is
placeholder copy, not a budget.

---

## Phase 0 — Foundation

Nothing WordPress-shaped yet. Get the floor solid.

- `git init`, `.gitignore`, `.editorconfig`, conventional-commit setup.
- Root `composer.json` (PHP `^8.4`) with `squizlabs/php_codesniffer`,
  `wp-coding-standards/wpcs`, `phpcompatibility/phpcompatibility-wp`,
  `phpstan/phpstan` + `szepeviktor/phpstan-wordpress`, `phpunit/phpunit`,
  `yoast/phpunit-polyfills`, `brain/monkey`, `roave/security-advisories`.
- Root `package.json` with `@wordpress/env`, `@wordpress/scripts`,
  `@wordpress/e2e-test-utils-playwright`, `@playwright/test`.
- `.wp-env.json`: PHP 8.4, latest WP, mounts `themes/dpaternina` + `plugins/dp-core`,
  installs Stackable, and defines a `tests` environment.
- `phpcs.xml.dist`, `phpstan.neon.dist` (level 9), `phpunit.xml.dist` (Unit +
  Integration suites), `playwright.config.ts`.
- `.github/workflows/ci.yml`: matrix on PHP 8.4, runs every gate in CLAUDE.md §1.6.
- `docs/adr/` for decisions that outlive a PR.

**Done when:** `npm run env:start` gives a running site, and every command in
CLAUDE.md §1.6 exits 0 against an empty theme and plugin.

---

## Phase 1 — Tokens and the theme skeleton

- `themes/dpaternina/style.css` with full headers, including `Update URI:`.
- `theme.json` v3, transcribed from `design-source/_ds/tokens/`:
  - `settings.color.palette` — every `--dp-*` and every semantic alias, names preserved.
  - `settings.color.gradients` — the three signature gradients.
  - `settings.typography.fontFamilies` with self-hosted `fontFace` for Bricolage
    Grotesque, Manrope, JetBrains Mono (woff2, subset, `font-display: swap`).
  - `settings.typography.fontSizes` — the ten steps plus the two fluid pairs.
  - `settings.spacing.spacingSizes` — the 4px scale.
  - `settings.custom` — radii, shadows, easings, durations, `measure`, control heights,
    the glow strengths, `band`, `accent-text`, and the `hue-*` family.
  - `settings.*.custom*: false` almost everywhere. The editor offers the scale or
    nothing.
- Base stylesheet from `_ds/tokens/base.css` — focus ring, placeholder contrast, media
  overflow, `prefers-reduced-motion` backstop.
- Font subsetting + `wp_enqueue` wiring; `resource_hints` cleanup so nothing points at
  Google.
- Editor styles loaded in **both** contexts.
- `customTemplates` declared for `dp-work`, `dp-about`, `dp-resume`, `dp-contact`
  (and `dp-watch` at Phase 12), each with a human title for the admin dropdown.

**Tests:** a token-parity test that parses the CSS in `design-source/_ds/tokens/` and
asserts every custom property has a matching `theme.json` entry with an identical value.
This is the guard that stops the design and the theme drifting — it runs in CI forever.

Plus a **no-hardcoded-routes test**: greps both packages for `add_rewrite_rule`,
`is_page(` with an argument, `get_page_by_path`, and `templates/page-*.html`, and fails
on anything not on a short allowlist. Cheap, and it holds the line in §5.1 permanently.

**Done when:** the parity test passes, and a bare page renders on the right ground with
the right type at every viewport.

**Done — 2026-08-20.** All six gates green. 129 tokens carried, verified against the CSS
WordPress actually emits rather than against `theme.json` read as a file; the naming
decision and the generated token bridge are in `docs/adr/0002-design-token-naming.md`.
Fonts are self-hosted variable woff2, `latin` + `latin-ext`, zero external requests
confirmed in the browser. `dp-work`, `dp-about`, `dp-resume`, `dp-contact` ship as
declared custom templates with minimal files behind them; Phase 5 fills them in.

---

## Phase 2 ✅ — Release automation, proven end to end

Deliberately early. A pipeline built at the end is a pipeline nobody trusts.

### The mechanism
WordPress core has supported third-party updates natively since 5.8 (plugins) and 6.1
(themes). No updater plugin required.

> **Updated 2026-08-25 (ADR 0015).** The pipeline this phase built in-repo was
> extracted into the canonical `fanxielab/wp-update-client` library
> (`github.com/fanxie-lab/wordpress-updater`), and this repo now consumes it.
> The update host moved from `updates.dpaternina.com` to the existing Fanxie
> Lab instance at `wp-updates.fanxie.cloud`, namespace `dpaternina`. The
> mechanism below is unchanged in substance; only where it lives changed.

1. `style.css` carries
   `Update URI: https://wp-updates.fanxie.cloud/dpaternina/theme-dpaternina`;
   `dp-core.php` carries
   `Update URI: https://wp-updates.fanxie.cloud/dpaternina/plugin-dp-core`.
2. `dp-core` registers the library's `UpdateClient` with our config
   (`DP\Core\Update\UpdateRegistration`). It hooks
   `update_themes_wp-updates.fanxie.cloud` / `update_plugins_wp-updates.fanxie.cloud`,
   fetches each package's signed manifest, verifies it with
   `sodium_crypto_sign_verify_detached()` against the public key compiled into the
   plugin (`DP\Core\Update\UpdateKey::COMPILED`), and hands core the version +
   package URL — pinned inside our namespace on the update host.
3. Tagging `theme-v1.2.3` or `core-v1.2.3` triggers GitHub Actions: run every gate
   (`ci.yml`, called from `release.yml`) → hand off to the reusable workflow
   `fanxie-lab/wordpress-updater/.github/workflows/release.yml@main`, which stamps
   the version, zips without dev deps, signs the manifest, verifies it against the
   key the build ships, uploads the ZIP to `wp-updates.fanxie.cloud`, confirms the
   public URL resolves, and only then publishes the manifest.
4. The library's client answers `auto_update_theme` / `auto_update_plugin` with true
   for our two packages, so the site takes the update on its own next cron run.
   `wp cron event run` forces it.

### Why not the alternatives
- **Git Updater plugin** — mature and would work, but it is a fifth plugin doing work
  ~200 lines of ours does, and the Colophon says the list gets shorter.
- **rsync/SSH deploy from Actions** — simpler, but bypasses the WordPress updater, has
  no rollback, and does not scale past this one site. Worth keeping as a documented
  break-glass path; not the primary.
- **Composer + private Satis** — the right answer if the whole of `wp-content` were
  Composer-managed. It is not, and making it so is a hosting change, not a theme change.

**Tests:** integration tests that feed a fake manifest through the update filters and
assert (a) a good signature produces an update offer, (b) a bad signature produces
nothing and logs, (c) a lower version is ignored. Plus a `workflow_dispatch` dry run.

**Done when:** tagging `theme-v0.1.0` on a scratch branch results in the wp-env site
offering, and taking, the update — observed, not assumed.

---

## Phase 3 ✅ — `dp-core`: the content model

- CPTs `dp_role`, `dp_ship`, `dp_video` — `public => false`,
  `publicly_queryable => false`, `rewrite => false`, `has_archive => false`,
  `show_ui => true`, `show_in_rest => true`, `supports` trimmed to what is used —
  plus `editor`, which is what §3.2 turned out to depend on.
  None of them has a single view: roles and ships expand inline on the timeline, videos
  render in the Watch grid. They are structured data David edits, not URLs.
- Taxonomy `dp_series` on `post`, with term meta for the deck. Its rewrite slug
  (`series`) is the project's only registered page-facing rewrite, and it goes through a
  `dp_series_rewrite_slug` filter so David can change it without a code edit.
- `register_post_meta()` for every field in digest §3, typed, `show_in_rest` with
  explicit schemas, `auth_callback` on all of them.
- An `Enum` for video source and for tone, so tone is never a loose string.
- Decimal-year value object with validation (`2026.4` ≈ May 2026) — this is the piece
  the timeline geometry depends on, so it gets its own unit tests.
- `bin/seed.php` + `wp dp seed` WP-CLI command that reproduces the design's fixture
  exactly, placeholders included.

**Tests:** unit tests for the year value object and the timeline geometry
(`pos()`, bar width, clamping). Integration tests for registration, meta round-trips
through REST, and permission callbacks.

### 3.1 Series parts — decided: drafts, not stubs

The `SERIES` fixture mixes two kinds of entry in one ordered list:

```js
parts: [
  { part: 1, slug: 'care-looks-like' },      // published — resolves to a real post
  { part: 2, slug: 'workaholic-years' },     // published
  { title: 'Before any of it was a job', years: '1995 — 2007', note: '…' },  // planned
  …
]
```

The series page renders the first group as "Start with these" and the second as "Still
to come". The question was where the planned entries live.

**Decision: a planned part is a draft `post`** carrying the `dp_series` term, and
nothing else. The series template runs two queries against the same term, one
`publish` and one `draft`, and the draft query selects **title and excerpt only** —
never content, never a permalink.

**Amended 2026-08-26 — [ADR-0016](adr/0016-a-post-carries-no-fields-of-ours.md).**
This section originally added three meta fields to that draft — `dp_series_part`,
`dp_series_years` and `dp_series_note` — and ordered the series by `menu_order`. All
four are gone:

- **Ordering is the publish date, ascending.** `post` does not declare
  `page-attributes`, so the Order box is not on the post editor and `menu_order` was
  zero on every post; the date tiebreak that sat beside it was doing the whole sort
  already. *Reversed 2026-08-27 —
  [ADR-0019](adr/0019-a-series-is-ordered-by-hand.md).* The order is
  `menu_order ASC, date ASC`, written by a drag-to-reorder screen at
  **Posts → Series → Order parts**. `post` still declares no `page-attributes` and
  there is still no Order box: the rejection above assumed the box was the price of
  the column, and `wp_update_post()` writes `menu_order` without it. A series nobody
  has ordered still reads by date, which is why this needed no migration.
- **A part is numbered by its position** among the published posts in the term, so the
  number and the order the page draws in cannot disagree.
- **The note is the draft's excerpt**, which is a core field with a sidebar box David
  can actually type into. It must be read as the *stored* excerpt: `get_the_excerpt()`
  falls back to trimming `post_content`, which would publish the opening of an
  unfinished post under a public heading.
- **The years are dropped.** The design labels every planned row `DRAFT`, flat, and
  says in its own deck that a part gets its number when it goes up.

Why this over the alternatives:

- **It cannot drift.** When David writes the post, he publishes it. It moves from one
  list to the other, in the right position, with no second object to delete. A
  `dp_series_stub` post type would require deleting the stub when the real post lands —
  a manual step that gets forgotten and shows the same part twice.
- **The editing UI is free.** Drafts already have a title, an excerpt, a date and a
  place in the admin. A stub type means another menu item; term meta holding a JSON
  array means building a bespoke repeater and having nothing queryable at the end of
  it. This was the strongest argument for the decision and it was the one the original
  implementation then walked away from, by adding three fields with no UI on top of the
  four core ones that had it.
- **It is testable.** `WP_Query` with an explicit `post_status`, asserted directly.

The cost is real and worth naming: **draft titles in this series become public.** That
is exactly what the design does — "Still to come" is a published roadmap — but it makes
the series term the switch. A post is announced when it gets the term, not when it is
created, so an unfinished draft stays invisible until David deliberately files it under
the series. The template is written to make leaking body content impossible rather than
merely unlikely, and there is an integration test asserting a draft's content and
permalink never reach the response.

Fallback if David dislikes public drafts: `dp_series_stub`, with a WP-CLI command that
reconciles stubs against published posts and warns on duplicates.

---

### 3.2 The editing surface — every field has a control ✅

ADR-0016 audited the *screens* rather than the code and found that none of the ten
fields on `post` had an editor control: registered with `show_in_rest`, written by the
seeder, read at render, and unreachable by hand. It deleted all ten, because a post
already knew what they held. The thirty-three on `dp_role`, `dp_ship` and `dp_video`
and the two on `page` are the remainder of that audit, and they cannot be deleted —
nothing else knows what they hold — so they get controls.

**The three custom types open as a locked form.** `register_post_type()`'s `template`
carries the form `DP\Core\Editor\FieldForm` generates from the type's registered
fields, and `template_lock => 'all'` holds it together. That needed `editor` in
`supports`: `use_block_editor_for_post_type()` returns false without it, and all three
types were opening in the *classic* editor, whose only offer for a registered field is
the raw Custom Fields table.

**A field is a bound `core/paragraph` unless it cannot be.** Seventeen of the
thirty-three are core's own `core/post-meta` bindings, which cost no JavaScript. The
other sixteen — booleans, enums, lists, decimal years, post references, and the
multi-line fields whose line breaks `sanitize_textarea_field()` would strip out of rich
text — are one of six small blocks, all `inserter: false`.

**`dp_role_id` and `dp_writeup_id` are pickers.** Attaching a shipped thing to a role
was typing a post ID into a key/value table; it is now a search by name.

**A page keeps its canvas.** Its two fields are a document sidebar panel, generated
from the REST schema so the labels are the ones `Meta` registered.

Two things the phase found: the three types had no block editor at all, and the seeder
was passing unslashed data to `wp_insert_post()`, which silently ate a backslash out of
every block attribute containing a quotation mark.

## Phase 4 ✅ — The house style: blocks and editor constraints

- `theme.json` `styles.blocks` for every block in digest §5.1. Note the two traps:
  `h4` is mono caps in the accent colour, and list markers are rendered, not native.
- Custom block `dp/callout` (the `note`) — static, with a label attribute.
- Block styles/variations for the labelled dark code block, the pull quote, the
  spectrum separator, and the figure caption.
- `allowed_block_types_all` — an explicit allowlist: the core blocks above, `dp/*`,
  and the Stackable blocks David actually wants. Everything else is off.
- Stackable: additive only. Every template must still render with it deactivated —
  there is an integration test for exactly that.
- Editor-side soft warnings for the house limits (2 quotes, 6 list items, 15 code
  lines, 1 callout).

**Tests:** JS unit tests for the block; integration tests asserting rendered markup for
each block matches the design's structure; a test that deactivating Stackable does not
fatal or blank any template.

---

## Phase 5 ✅ — Chrome, homepage, blog, archives

- Template parts `header` / `footer`, built as `SiteHeader` / `SiteFooter`.
  Mobile panel via `<dialog>`: Escape, focus trap, scroll lock, `HERE` marker.
  `@container` at 720px — no media query.
- Patterns: `PageHero`, `SectionHead` (five tones, kicker/heading, meta *or* action),
  `CtaBanner`, `ContactMethod`, `PostRow` (`list` + `compact`), `WorkCard`.
- `front-page`: hero with the accent word, RIGHT NOW strip, "Things I've shipped" band,
  latest writing.
- `home`: featured post, category pills, list, pagination. Which page this is comes from
  Settings → Reading, not from a slug. The template must render correctly whether that
  page is `/blog`, `/writing`, or unset.
- `category`, `taxonomy-dp_series`, `single` (lead image, series footer, prev/next).
- `FilterPills` are **real links** to filtered URLs. JS may upgrade them; it may not be
  required for them to work.

**Tests:** integration tests per template (correct query, correct counts, no notices);
e2e for the mobile panel (keyboard only) and for filtering with JS disabled.

**Done — 2026-08-20.** All six gates green. The chrome, five templates and ten
patterns ship; every link in them says which destination it wants and is given a
URL at render time from Settings → Reading, from core's feed link, or from the
page carrying a `dp-` template — no href anywhere, asserted by a test. The mobile
panel is a `<dialog>` that opens from `:target` with no JavaScript at all and is
upgraded to a real modal when there is some. The 720px and 560px switches are
container queries; there is not one media query in either new stylesheet.

`theme.json` was opened once, for two `templateParts` entries, under
`docs/adr/0006-chrome-and-derived-destinations.md`. Deviations from the design —
the broken gradient monogram, the footer's link groups, the year in the © line —
are named in the same ADR and in the phase report.

---

## Phase 6 ✅ — The timeline

The hard one. Budget accordingly.

- Dynamic block `dp/timeline`, rendered server-side from `dp_role` + `dp_ship`.
- Three modes from `@container`, exactly as specified in
  `design-source/components/TimelineChart.dc.html`: bars ≥700px, stacked <700px,
  scroll variant retained but not the default (the Ledger picked stack).
- Geometry from the decimal years; bar min-widths 64/40; open state tint and glow ring
  as specified.
- Disclosure with `<details>`, so it works with JS off. JS adds: expand/collapse all,
  URL hash sync, and the `WorkCard` → open-this-entry link.
- Filter (Everything / Roles / Shipped) as query-arg links, upgraded to instant.
- Legend picks up any role carrying its own accent (Fanxie Lab = pink).

**Tests:** unit tests on geometry (already from Phase 3) plus the filter reducer;
integration tests on rendered markup for each mode and filter; e2e for open/close,
expand-all, deep link, and reduced-motion.

---

## Phase 7 ✅ — Contact and the remaining pages

- **Contact.** Normal POST first; `fetch` upgrade second. Nonce + capability +
  sanitize + rate limit + honeypot + timing check. No third-party captcha (it would be
  a tracker). `wp_mail` through the SMTP plugin. Renders the design's three states.
- **About**, **Uses / Colophon / Privacy** through the shared block kit, **404**.
  Uses, Colophon and Privacy are plain `page` posts: `templates/page.html` binds
  their `dp_updated` eyebrow and `dp_lead` deck, and the body is the
  `dpaternina/page-body` pattern — one of every block the house style allows.
  404 renders **without site chrome and without the closing band**, which is what
  the design does (`const chrome = view !== 'notfound' …`); so do Contact and the
  résumé for the band alone.
- **Résumé**, with a real downloadable PDF (see §7.1).
- ~~Service worker + offline page.~~ **Cut 2026-08-21.** No service worker, no
  registration, no precache, and the design's chrome-less offline state is out of scope.
  Caching is a plugin's job if David ever wants one. 404 still ships.

### 7.1 The résumé PDF

`dompdf`/`mpdf` are out: the design leans on `clamp()`, `color-mix()`, and container
queries, none of which those engines support. The PDF has to come from a real browser.

The PDF hangs off a registered **query var**, not a route: `?format=pdf` on whatever page
David has assigned the `dp-resume` template to. A `template_redirect` handler checks
`get_page_template_slug() === 'dp-resume'` and takes over; on any other page the query
var is ignored. That is the whole footprint — no rewrite rule, no slug, and it keeps
working if David renames or moves the page.

It renders the print stylesheet through **Cloudflare Browser Rendering** — David already
runs Cloudflare, so this adds an API call rather than a service. The result is cached to `uploads/` keyed on the résumé's `post_modified` plus
the modified time of the newest `dp_role`/`dp_ship`, so it regenerates exactly when the
content behind it changes and is a static file every other time. If the renderer is
unavailable the link falls back to the print view rather than erroring; a stale cached
PDF is always preferred over no PDF.

A self-hosted Gotenberg container is the swap-in if the Cloudflare route disappoints —
the interface is one `render_pdf(string $url): string` port with two adapters, so this
is a config change, not a rewrite.

**Tests:** integration tests on the form handler (each rejection path individually) and
on the PDF cache key (regenerates on role change, does not regenerate otherwise, serves
stale on renderer failure); e2e for the three form states.

### 7.2 What Phase 7 found

Two things that were true of the whole project, not of this phase:

- **A block registered only in PHP does not exist in the block editor.** All three
  of `dp-core`'s dynamic blocks drew as `core/missing` in the site editor while
  rendering perfectly on the front end. Every one of them now has a
  `ServerSideRender` preview from the editor bundle `dp/callout` already ships —
  which means `npm run build` after pulling. ADR-0009.
- **Every unadorned button was teal on the site and core's grey in the canvas.**
  `.wp-element-button` ties with core's `:root :where(.wp-element-button)`, and a
  tie is broken by load order — which differs between the two contexts. The base
  rule in `chrome.css` now carries an element, like every other component rule in
  this theme. The `.dp-button-*` variants already did, which is why only the base
  ever diverged.

And one that is the harness rather than the code: **`wp_mail()` returns false on
wp-env**, so the design's *sent* panel was unreachable in a browser. A test-only
must-use plugin, mapped into the `tests` environment alone, answers the send and
gives each e2e run its own rate-limit counter. ADR-0010.

### 7.3 Phase 7b — chrome and home-page fidelity ✅

A review pass over the rendered site, not a new capability. Four corrections, all
landed:

- **The brand mark is `core/site-logo`** in the header, the mobile panel and the
  footer, so David swaps it from the admin. It was a `background: url()` on a
  visually-hidden `core/site-title`. `dp-core`'s seeder sets the theme's bundled
  mark as the default; `DP\Theme\Chrome\Brand` points the link at the `home`
  destination like every other link in the chrome. ADR-0011.
- **The block gap was adding itself to every composition the design already
  spaced.** The RIGHT NOW bento's row gap was 40px where the design draws 16,
  the "Things I've shipped" rows floated 24px apart where the design butts them
  together, and `.dp-right-now` had no block padding at all. Eighteen elements on
  the home page had different spacing in the site editor from the front end.
  Every one is fixed in the stylesheets, at a specificity that wins in both
  contexts, and `tests/e2e/spacing.spec.ts` sweeps for a nineteenth.
- **The footer has the design's three groups.** SITE / WRITING / MORE, plus
  PRIVACY and COLOPHON in the bottom bar. There was one `core/navigation` with no
  `ref`, so the footer could only mirror the header. Every link is now a
  `dp-to-*` destination; `privacy` resolves through Settings → Privacy and
  `uses` / `colophon` through two new page templates that are `page.html` under
  another name.
- **`core/post-content` is off the home and work templates**, with the empty
  groups that held it.

### 7.4 Phase 7c — the work page ✅

The same kind of pass over `dp-work`, and the same two mechanisms behind most of
it.

- **The featured cards were bound to the wrong two fields.** `.dp-card-org`
  printed `dp_stack` and `.dp-card-line` printed `dp_detail`. The design's
  `featuredWork` fixture carries `year`, `org`, `title` and `line` on the card
  and puts `stack` and `detail` in the timeline's expanded panel, so neither
  substitution was a near-miss. `org` is derived through `dp_role_id` — a role's
  post title already is the organisation, and `DP\Core\Content\Meta`'s own rule
  is that `org` is never stored twice. `dp_line` is a new registered field with
  the design's verbatim copy behind it.
- **`:root :where(p)` beat the container again.** `theme.json`'s
  `core/paragraph` style names font-size, line-height *and* colour, so the two
  paragraphs in the card's meta row ignored `.dp-card-meta`'s `--fs-xs` and drew
  at 16px body type in the muted-then-secondary grey. Same shape as the Phase 5b
  label bug, restated against the element.
- **Core's block gap was a second gap.** 56px under "Featured work" where the
  design draws 32, and 48px between the lede and the chart where it draws 24 —
  in both cases because the element above already declares the gap below it.
- **The hero was `tight` and had no deck.** The design uses `tight` only on the
  generic page view; the work hero is the full 40px and carries a deck, now
  bound to `dp_lead` like every other `dp-` template.

`tests/e2e/spacing.spec.ts` now sweeps `dp-work` in both contexts as well as the
home page, and pins the four numbers above.

### 7.5 Phase 7d — the work page, against the design ✅

The third pass, and the first with the design as the baseline. Phases 7b and 7c
both leant on `spacing.spec.ts`, which compares the rendered page with the site
editor's canvas — a real bug class, and both sides of it are the theme, so it
cannot notice two contexts agreeing on the wrong number. It reported "0
divergences across 374 elements" over a page whose filter chips were half again
as tall as the design draws them.

The two defects David could see were both **properties the design never
declares**, which is why neither earlier pass found them:

- `box-sizing`. The chips carry the design's `min-height: var(--target-min)`, and
  with no border-box reset the padding and border were added on top of the 36 —
  a secondary control taller than the 44px primary one, which inverts the
  hierarchy `_ds/tokens/spacing.css` sets out in prose.
- `line-height`. `TimelineChart.dc.html` declares one on three elements and on
  nothing else; `theme.json`'s root is `--lh-relaxed`, so nineteen mono caps
  labels inherited 1.65 where the design renders about 1.2.

Three more came out of building the harness: the expand-all control losing its
dashed border to a more specific pill rule, the quiet button variant keeping
`line-height: 1` from the pill it strips, and a ship's label column giving 16px
back to a year axis that does not exist in stack mode.

**The harness itself is the deliverable.** `composer design:baseline` reads
`design-source/` — the inline `style` attributes through `DesignMarkup`, and the
style objects each component *computes* through `DesignLogic` — and writes both
beside the theme selector that plays the same role.
`tests/e2e/design-parity.spec.ts` measures each element, appends a classless
probe of the same tag carrying the design's declarations verbatim, and compares
only the longhands those declarations expand to. Both sides are resolved by one
engine, in one inherited font context, at one width — which is what makes `ch`,
`em`, `clamp()` of a viewport unit and `color-mix()` assertable rather than
re-implemented in PHP as numbers that rot. `DP\Tests\Unit\DesignBaselineTest`
fails the fast gate when the committed baseline and the design have drifted.

**The second half arrived late and was the larger half.** Every component was
imported without its `<script type="text/x-dc">` block, so about half of each
one's declared values were simply absent — and ADR-0012's first draft wrote them
off as unexportable and told the next phase not to look. They were restored on
2026-08-23 as `design-source/components/*.logic.js`, and the sweep went from 62
entries to 141 and turned up 162 divergences on a template that had been audited
three times. **If `design-source/` appears not to say something the design
plainly does, re-fetch it before reasoning about why it cannot be there.**

A sweep is a page, a width and an open state — `bars`, `bars-closed`, `stack`,
`stack-closed`, `home` — because a closed row and an open one are different
assertions, and the harness measured no closed row at all until then.

The reasoning, what it cannot answer, and the alternatives are in
`docs/adr/0012-design-parity-harness.md`. The next template review inherits the
machinery and adds a map, not a mechanism.

### 7.6 Phase 7e — the four writing templates, against the design ✅

The same kind of pass as 7d, over `home`, `single`, `category` and
`taxonomy-dp_series`. Twenty-eight divergences from `design-source/`, and the
three worth remembering are the three that had been invisible from every
direction the suite was looking:

- **`p.dp-row-excerpt` matched nothing.** `core/post-excerpt` renders a `<div>`
  wrapping a `<p>`, so every list row had been drawing its excerpt at
  `--fs-base` with no measure and core's 24px block gap on top. It also hid the
  design's deliberate inversion — `PostRow.logic.js` gives the **compact**
  variant the larger `--fs-base` and the list variant `--fs-sm`, and flags it
  "not a typo in the export" — because both were rendering at the same size.
- **`:first-child` is a pseudo-class.** Core's flow-layout rule
  `:root :where(.is-layout-flow) > :first-child` is *two* units of specificity,
  not the one its `:where()` makes it look like, so it beats a one-class rule
  outright rather than on load order. That is a corner of ADR-0008's hazard the
  earlier notes had not reached, and it is why the pill row's 24px top margin
  was silently zero.
- **The series deck was never on the page.** The design's `SERIES.deck` was
  `dp_series_deck` term meta at the time; the template rendered
  `core/term-description`, which was a different field. The block was in the
  template, the value was in the database, and nothing connected them. The gap
  was closed from the other side a day later — the deck is the description now,
  and the second amendment below says why.

Four things a template cannot say are now derived rather than dropped — counts,
page state, a dead pager step, and two links whose target is content rather than
a route. ADR-0021 has the reasoning. `theme.json` was not opened.

**Amended 2026-08-26 — [ADR-0016](adr/0016-a-post-carries-no-fields-of-ours.md).**
Two of the mechanisms this phase built rested on meta fields that had no editor
control, which is a thing none of its tests could see. `dp_series_featured` — the
term David was supposed to flag to nominate a series — had no term-edit field, so
on any site with more than one series the blog index's "read my life story in
order" link was permanently inert and there was nothing he could do about it. It
is derived now: the series with the most published parts, lowest term ID on a
tie. `%dp-part%` still substitutes a part number into the navigation label, but
the number is the post's position in its series rather than a stored field.

The same pass deleted the other eight fields on `post` for the same reason, and
gave `dp_series_deck` — which survived that round, and which ADR-0021's own
closing note flagged as needing a panel — the twenty-line term-edit field it had
been missing since Phase 3. `theme.json` was not opened for any of it.

**Amended 2026-08-26 — a series' deck is its description.** That term-edit field
lasted a day. A `dp_series` term already had a textarea for one or two sentences
about itself, on both of the screens the new control drew on, with a column in the
terms list table, a REST property and a place in a WXR export: **`description`**.
Core has shipped it since taxonomies existed. The duplication had been visible in
the read path the whole time and nobody read it that way —
`DP\Theme\Query\ArchiveFacts::deck()` returned the meta if it was set and *fell
back to `$term->description`* if it was not, written as a courtesy and in fact an
admission that the two fields held the same thing. What it produced on the term
screen was two adjacent textareas asking for the same sentence, where the one that
worked was the one core drew and the one that was ours was the one the design
named.

So: `dp_series_deck` is unregistered, `SeriesDeckField` and its test are deleted,
`ArchiveFacts::deck()` reads `$term->description` and nothing else, and the seeder
writes each fixture series' deck into the description when it creates the term.
`dp-core` now registers no term meta at all, and `MetaAuth::term_meta()` went with
it. The design's word for it is still "deck"; the label on the screen is core's,
and a filter on the taxonomy's labels is not worth the indirection to rename one
field on one screen.

What it costs is that `description` is a field other code may reach for — an SEO
plugin seeding a meta description from it will now see the deck. That is the
normal condition of a core field, and the trade is worth it: a private key nobody
else can find is only an advantage until it is the reason nobody can edit it
either. `description` also permits limited HTML where the meta field was
`sanitize_textarea_field`; the deck is bound into a `core/paragraph`, and
`WP_Block::replace_html()` runs a rich-text binding through `wp_kses_post()`, so
this changes what can be stored rather than what can be rendered.

Existing `termmeta` rows are left inert, exactly as ADR-0016 left the `postmeta`
ones and for the same reason. `wp dp seed --fresh` clears them. The standing rule
this leaves behind: **before registering a field, check whether the object already
has one.**

The seed grew from seven posts to twenty-nine, and the additions announce
themselves as filler in every field they have: three pages of pagination, a
middle page, an end-of-archive panel, a second series, a term with nothing in it
and a captioned lead image are all states the design draws and a seven-post
fixture could not reach.

**What is still open.** The design-parity harness (ADR-0012) was **not** extended
to these four templates in this pass, and it should be next. Two blockers, both
concrete: the blog index's URL comes from Settings → Reading, which
`chrome.spec.ts` mutates inside a serial block, so a parallel sweep over it is a
race; and the shared fixture (ADR-0013) has no post carrying a category, a
series, a read time or a featured image, so the post view has nothing to measure.
Both are fixture work in `tests/e2e/global-setup.ts`, not new mechanism.

**"BROWSE BY CATEGORY →" is cut. 2026-08-25.** The design's blog index carries two
mono links above the list; only the series one ships. The second one's handler is
`openArchive('MY LIFE STORY')` — it opens one *named category archive*, which §5.1
forbids the theme from hardcoding, and neither the design nor the site has a
categories index to point at instead. The row's geometry is identical with one
link. David's call; do not re-add it, and do not build a `dp-categories` template
to justify it.

### 7.7 Phase 7f — the seed makes a site you can navigate ✅

[ADR-0018](adr/0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md)
deleted the `dp-to-*` destination system, and named the bill it was leaving:
"Fresh installs ship with blank links … `dp-core`'s seeder sets them on a seeded
site, so `npm run env:reset` still produces a working site." Nothing did that.
`wp dp seed --fresh` produced a site with **three** pages, no template assigned
to any of them, `show_on_front` still `posts`, the privacy setting still pointing
at WordPress's own draft, and every chrome button inert — four of the theme's six
`customTemplates` assigned to nothing at all.

Four things closed it, and only the last one needed a mechanism.

**Nine pages, not three.** The design's `PAGES` has three; the other six views it
draws — the front page, the writing index, Work, About, the résumé, Contact — are
built from data and still need a page behind them or their templates cannot be
reached. Each carries only the words the design actually prints: the `<h1>` its
template binds to `core/post-title`, the deck it binds to `dp_lead`, and body
copy where the template renders `core/post-content`. Where a template renders no
body, the page carries a callout saying so rather than invented prose. The Work
page is titled *"Where I worked, what came out of it."* because that is the
design's `<h1>` and `dp-work.html` binds the `<h1>` to the post title; a page
called "Work" would draw a page headed "Work".

**Templates and settings are seeded as data.** `Fixture::pages()` gained
`template` and `role` fields; the seeder writes `_wp_page_template` (the slug,
`dp-work` — `wp_update_post()` rejects the `.html` spelling) and points
`show_on_front`, `page_on_front`, `page_for_posts` and
`wp_page_for_privacy_policy` at the pages it just made. `page_for_posts` does
nothing while `show_on_front` is `posts`, so those three move together. `--fresh`
gives all of them back, and only where they point at a page the seed created.
None of this is a route: nothing registers a rewrite, branches on a slug, or
looks a page up by name, and re-slugging any of them breaks nothing.

**And the URL shape, which the first pass missed entirely.** A fresh install has
an empty `permalink_structure`, and under plain permalinks *no rewrite rule
exists at all* — so `/writing/`, `/work/`, `/series/life-story/` and
`/category/dev/` were every one of them a 404 on a freshly reset site, including
`dp_series`' rewrite slug, which §5.1 names as the one registered page-facing
route in the project. The seeder now fills an **empty** structure with
`/%postname%/` and leaves any structure David already chose, because any
non-empty structure gives the routes their rules and replacing a dated one would
invalidate every URL on the site to gain nothing.

Two things about that are worth writing down, because both are easy to get wrong
and one of them was:

- **Setting the option is not enough.** `register_taxonomy()` adds a permastruct
  only when `is_admin()` or a structure already exists, and under WP-CLI on a
  fresh install neither is true — so `category`, `post_tag` and `dp_series` all
  have *no* permastruct by the time the seeder runs. Change the option at that
  point and `get_term_link()` still returns `?dp_series=life-story` (which the
  chrome links would then be built from and saved with), and a flush writes a
  rule set with no taxonomy rules in it that `wp_rewrite_rules()` serves from the
  option forever, because it only regenerates when that option is empty. So
  `create_initial_taxonomies()` and `ContentModel::register()` are re-run before
  the flush. The side effect — core's two taxonomies reset to their declared
  arguments — is why this happens only on a site that had no structure at all.
- **The `.htaccess` is the environment's, not the plugin's**, and it is a
  separate failure: with the option set and the rules correct, Apache still
  answered `/writing/` with its own 404 because the request never reached PHP.
  A plugin cannot honestly write that file — `got_mod_rewrite()` is false under
  WP-CLI because there is no server to ask, so a hard flush silently does
  nothing, and filtering `got_rewrite` to get past it would be writing Apache
  config into a site root on a guess. WP-CLI's own `rewrite` command gets there
  through the `apache_modules` key in the `wp-cli.yml` **wp-env already ships**.
  So `.wp-env.json`'s `afterStart` runs `wp rewrite structure '/%postname%/'
  --hard` on both environments, which writes the file and sets the structure
  before the seed even starts; the seeder's own copy is the safety net for a site
  wp-env did not build. The two values are asserted to agree, because they are
  written twice.
- **`wipe()` restores the structure only if it recorded writing it.** Matching on
  the value is not the same claim: `.wp-env.json` sets that exact structure, so a
  value comparison had `--fresh` clearing the environment's work and putting it
  back a moment later. The index gained a `settings` map for the purpose — the
  other three settings need no such record, since each holds the ID of a post the
  index already vouches for.
- **A sweep driven by `get_permalink()` cannot see any of this.** The first
  pass's verification asked WordPress what URL it would generate and then checked
  that WordPress could resolve it; under a plain structure that is `?page_id=47`,
  which returns 200 and proves nothing. The tests now assert the *shape* — a path,
  and the expected one — and resolve it with `go_to()`, which parses the URL back
  through the rewrite rules rather than through the function that produced it.
  The `.htaccess` half is outside what an integration test can see at all, and is
  verified by requesting the paths over HTTP after a real reset.

**The chrome links go in through a seam.** `dp-core` may not know the theme's
files, block names or labels, so it hands over a map of *its* destination keys to
URLs through `dp_seed_chrome_links` and the theme hands back finished markup,
which the plugin saves as a `wp_template` / `wp_template_part` post and inspects
none of. That post is byte-for-byte the kind of thing the site editor saves when
David links a button by hand, so **nothing is computed at render time** and the
editor and the front end draw the same links — which is what ADR-0018 asked for
and what the deleted system could not give. The trigger is a `metadata.name` on
the button, visible in List View ("Contact link", not "Button"), because ADR-0018
rule 2 says a bare CSS class is not an announcement. A button that already
carries a `url` is left alone.

**Staleness is the hazard, and it is handled by regeneration.** A stored override
beats the theme's file for as long as it exists, so one kept across releases
freezes that template silently — this project has had exactly that bug, a `home`
override still drawing a block the theme had replaced. So: every run deletes
every override carrying the seeder's own meta mark *before* writing any, and the
theme rebuilds each from `get_block_file_template()`, which reads the file and
ignores the stored copy. Deletion is scoped by the mark, never by post type, so
an override David saved is untouched by a normal run and by `--fresh` alike. The
cost, accepted for a development site and not for a real one: a re-seed discards
his edits to those five templates.

Five files are covered — `header`, `footer`, `front-page`, `home`, `404` — and no
more, because every override is a frozen template. The closing CTA band is
inlined into the two of those that carry it, since a `core/pattern` reference is
resolved at render time and cannot carry a link into the pattern; a pattern whose
expansion gains no link stays a reference, so the query loop and the pager are
never frozen into a seeded copy.

**What is still open, and is David's or a later phase's.**

- **The header's navigation has no menu.** `core/navigation` ships with no `ref`,
  so core falls back to a page list: every published page, by title, including
  WordPress's own "Sample Page" and the long design titles. Every item resolves,
  and none of it is wrong — it is simply not the design's six-item nav. A
  `wp_navigation` menu is the answer and ADR-0011 has the reason it was not built
  here.
- **Buttons outside the five covered files are still David's to link**, which is
  ADR-0018 working as intended rather than a gap: "See the record" and "Get in
  touch" on About, "Open the timeline" and "Get in touch" on the résumé, "Read
  the series →" on Work, and the CTA band's "Say hi" on the seven templates that
  are not seeded. Covering them means freezing those templates too, and the trade
  gets worse the further it goes.
- **The Contact page's three method rows** — email, X, the agency — are the
  theme's `contact-method` pattern, which the plugin may not name. The page says
  so on its face instead of pretending they are there.

---

---

## Phase 8 — Feeds (done — 2026-08-29)

Almost nothing. **SEO is AIOSEO's**, installed and configured by David: OG images,
canonicals, `robots`, sitemaps and JSON-LD all come from the plugin. This repo writes
none of it — no `OgCard` renderer, no meta-tag output, no schema graph.

Two notes so nobody re-adds the work:

- The old bullet said "sitemaps extended for the CPTs". That was wrong regardless of
  plugin. `dp_role`, `dp_ship` and `dp_video` are `public => false` with no single view
  — they have no URLs to submit. AIOSEO will not list them and must not be made to.
- If David wants the timeline expressed as `Role`/`CreativeWork` schema, that is an
  AIOSEO custom-schema entry against the Work page, not code here.

What is left:

- RSS at `/rss.xml` (the footer links it), with the full house-style markup surviving.
- **Rybbit is a plugin, not our code.** David installs and configures it: self-hosted
  vs cloud, whether it stores IPs, whether logged-in users are counted. Nothing in this
  repo enqueues an analytics script, registers a site id, or reads a Rybbit constant.
  The CSP that has to allow it is not ours either — see the headers note in Phase 10.
  One coupling survives and it is small:
  - **`DP\Theme\ExternalRequests` drops foreign resource hints.** Its
    `wp_resource_hints` filter keeps only hints whose host matches the site host, so a
    `preconnect`/`dns-prefetch` the Rybbit plugin adds for its analytics host is
    silently removed. Harmless — the script still loads — but it should be a decision,
    not a discovery.
- No other third-party script. Nothing else sets a cookie.

**Outcome.** No SEO, schema, sitemap or analytics code was written and none should be.
The feed already worked and now has a test that says so: `tests/Integration/Templates/FeedTest.php`
renders the real `feed-rss2.php` document and holds it to the things a reader depends on
— well-formed RSS 2.0, the channel naming this site, one item per post with the post's
own permalink, GMT `pubDate` and category, the excerpt as `<description>`, and the whole
house style arriving *rendered* in `content:encoded` (no `<!-- wp:` delimiters, the
`dp/callout` render callback having run). `dp_role`, `dp_ship` and `dp_video` are not
syndicated, for the same reason they are not in a sitemap. Until now only the *query*
behind the feed was tested (`HomeTest`) and the *link* to it (`ChromeTest`,
`ComputedLinksTest`); nothing had ever looked at the XML.

**`/rss.xml` does not resolve, and deliberately will not.** The design writes
`<a href="/rss.xml">` and WordPress serves the feed at `/feed/` under pretty permalinks
and `?feed=rss2` without. Serving the design's exact path needs a rewrite rule, and
CLAUDE.md §5.1 allows this project exactly two registered rewrites. So the footer link
follows core — `DP\Theme\Blocks\FeedLink` renders `get_feed_link()`, which is right under
both permalink structures and moves when David changes the setting — and the design's
path is left unserved. `FeedTest::test_the_designs_rss_xml_path_is_not_a_registered_route`
pins that: no rewrite rule anywhere in the project mentions `rss.xml`. If David ever
wants the literal path it is a redirect in his edge configuration, like the migration
redirects in Phase 9, never a rule in this repo.

**The Rybbit preconnect is a decision now.** `DP\Theme\ExternalRequests` still drops it,
and its class docblock says why: a resource hint advertises a third party in the HTML of
every page before anything has asked for it, dropping one costs a connection setup on a
request that is not on the critical path, and it neither needs nor grants a CSP
exception. The escape hatch is the `dp_resource_hint_hosts` filter, which takes the
allowlist — the site's own host and nothing else by default — so David can permit a host
from a plugin or an mu-plugin without editing the theme. A filter that answers with the
wrong shape narrows the list rather than widening it, and the unit suite covers both
directions.

---

## Phase 9 — Migration (David's, not this repo's — 2026-08-29)

David runs the migration by hand: WXR export/import with slugs preserved. Posts move
from `blog.dpaternina.com` to `dpaternina.com` keeping their slugs, and a single
Cloudflare redirect rule covers the old host. No `wp dp migrate` command, no redirect
map, and no migration tests are built here. Any vanity redirect is likewise a
Cloudflare rule, never code in this repo.

---

## Phase 10 — Accessibility, performance, hardening (done — 2026-08-29)

- Automated axe pass on every template, plus a manual keyboard-only run.
- Contrast re-verified against the token comments, not against a fresh opinion.
- Lighthouse/CWV budgets in CI: no render-blocking JS, LCP image preloaded,
  CSS under budget, zero third-party requests on first paint.
- Security review of every write path.
- **Headers are not ours.** CSP, `Referrer-Policy` and `Permissions-Policy` come from
  David's security plugin. This repo ships no `send_headers` handler and no header
  configuration. Our obligation runs the other way: emit nothing that would force him
  to loosen the policy — no inline `<script>`, no inline `style=`, no `onclick`, no
  off-origin request. That holds today and there is an audit for it; the check to add
  here is that it still holds after Phases 7–9, not a header to write.
- `php-lts-compat-audit` over both packages.

**Outcome.** All thirteen front-end templates are now swept in CI rather than reasoned
about: `tests/e2e/a11y.spec.ts` covers twelve and `chrome.spec.ts` the thirteenth (the
blog index, which only exists behind that file's Settings → Reading flip), each held to
the same four facts — axe at WCAG 2.2 AA, zero off-origin requests on first paint, no
parser-blocking script, and nothing a `script-src` without `'unsafe-inline'` would drop.
The last of those is new: Phase 10's claim that "there is an audit for it" was only half
true, since `TimelineTest` pinned the inline-`style=` half and nothing pinned inline
`<script>` or `on*` handlers; `tests/e2e/front-end.ts` is the rest, and it passes — the
only inline script core emits is its own `<script type="speculationrules">` and there is
no `on*` attribute anywhere. Keyboard operability was extended from the header and the
timeline's disclosures to the timeline's filter pills, the Watch play control and a
whole contact-form submission, each reached by Tab rather than `.focus()`, because the
ring in `base.css` is `:focus-visible` and a programmatically focused link never paints
it — the old Watch assertion could not have failed. Contrast was re-measured against the
token comments rather than re-argued: every number in `_ds/tokens/colors.css` reproduces
exactly (ink on teal-600 7.52, muted `#9095a0` 6.51 on `--bg-page`, purple 2.80). The
security review found every write path gated — the contact POST behind nonce, capability,
honeypot, signed stamp, completeness and rate limit; the series reorder behind a
term-scoped `check_admin_referer()` and `edit_others_posts`; both Settings sections
through core's `options.php` with sanitise callbacks; post meta behind an explicit
`auth_callback`. No REST route of our own exists to review. PHP 8.4 is clean, though the
signal is the 8.4 container and PHPStan rather than PHPCompatibility, which is pinned at
9.3.5 and knows nothing after 8.0.

**Two debts, both measured and neither ours to fix here** (the ledger in
`tests/e2e/axe.ts` names them as nodes on live rules, not as disabled rules):

1. **`--hue-purple` fails AA as text on dark.** `design-source/theme.css` ships the raw
   brand hue on the dark scope, which measures **2.80:1** on `--bg-page` — the exact
   number `_ds/tokens/colors.css` gives as its reason for the tone-mix rule in the first
   place. Teal (11.08), gold (11.37), pink (5.42) and coral (7.21) clear AA raw; purple
   does not, and it is used as `color:` on `.dp-label`, `.dp-badge`, the section-head
   kicker, the Watch gear label and the featured panel's kicker badge. axe measures it
   in the browser at 3.00 on `--band`, 2.79 on `--bg-page`, 2.73 on the purple-tinted
   card and 2.55 on `--bg-surface`; those four are the whole of the sweep's contrast
   ledger, and no other colour anywhere fails. The design's own rule fixes all four —
   75% toward white is `#9075b5`, which measures 5.40 / 5.02 / 4.90 / 4.59, the last
   being the "worst case 4.59 (purple)" the token comment already records. (It would be
   4.21 on `--bg-raised`, where nothing puts it today; a future component that did would
   need its own correction.) The fix is a one-line change in `design-source` and a
   re-import, not a theme edit: `design-source/` is read-only and `TokenParityTest` pins
   the theme to it verbatim, so changing `theme.json` alone would just break a gate.
2. **`list` on `.wp-block-navigation__container`.** Core renders `core/page-list` inside
   `core/navigation` as a `<ul>` directly inside a `<ul>`. That is core's markup on the
   *fallback* path, which is what a site with no navigation post gets; the curated
   navigation David builds in the one-time link pass (ADR-0018) is `core/navigation-link`
   items and renders one valid list.

**Flagged, not built.** The timeline bar's four geometry numbers are still an inline
`style=` attribute (`Blocks/TimelineRows.php:153`), which is a deliberate, tested
exception (`TimelineTest::test_geometry_is_the_only_inline_style`) and the only one on the
whole front end. It costs nothing under a policy that already allows `style-src
'unsafe-inline'` — which core forces anyway, emitting nineteen `<style>` elements on the
home page — but it would break under a tightened `style-src-attr 'none'`, so it is worth
knowing about rather than rediscovering. Separately, the Watch featured panel's thumbnail
(`Watch/WatchFeatured.php:120`) is that template's LCP element and correctly declines
`loading="lazy"`, but does not set `fetchpriority="high"`; the single post's lead image
does, and home and the blog index carry no content image at all, so nothing is regressed
— it is a one-attribute improvement available whenever David wants it.

---

## Phase 11 — Cutover (David's, not this repo's — 2026-08-29)

David handles the cutover himself: install, page creation and template assignment, the
one-time link pass in the site editor (ADR-0018), DNS, and the Cloudflare redirect
rule. This repo's only obligation is tagged releases that install cleanly; rollback is
installing the previous tag.

---

## Phase 12 — Watch (built — 2026-08-29)

Un-deferred: David moved it ahead of the accessibility pass and feeds. Built now,
before launch.

- `dp_video` grid, live-now panel, gear list.
- Thumbnails resolve to the public Twitch/YouTube URLs in digest §3.5, fetched and
  cached server-side so the visitor's browser never talks to those hosts before a click.
- Players load only on press.
- A Twitch Helix call for VOD thumbnails, cached in a transient, failing soft.
- **The archive is imported, not typed** (added 2026-08-29). David creates no `dp_video`
  by hand except the live-now entry, whose copy is his.

Nothing about it is a route: David creates a Watch page and assigns the `dp-watch`
template, exactly like the others (the seeder does both on a seeded site). The Watch
links in the footer's SITE column and on the 404's "or try one of these" grid are named
buttons with no href, filled by the seeder and set once by David (ADR-0018); the header
nav picks the page up the way it picks up every page, through the menu David owns.

**How it is built** (`DP\Core\Watch`, plus `templates/dp-watch.html`,
`patterns/watch-gear.php` and `assets/js/watch.js` in the theme):

- **Thumbnail caching** is a static file under `uploads/dp-watch/`, written once by
  `Watch\Thumbnails` and served by the web server — the same mechanism as the résumé's
  `PdfCache`, chosen over sideloading into the media library because "thumbnails are
  never uploaded" is a design property, and over a serving endpoint because a static
  file needs no route and no PHP on the read path. YouTube images need no key
  (`maxresdefault.jpg`, falling back to `hqdefault.jpg`, which is the only one YouTube
  guarantees); Twitch VODs resolve through Helix `/videos` first. Failures are
  negative-cached in a transient for fifteen minutes, each block render spends at most
  a small budget of remote calls warming the cache, and a card with no cached file
  simply keeps its glow art. The live preview refreshes when its file is five minutes
  old, serving the stale frame if the refetch fails.
- **The live check** (`Watch\LiveStatus`) is Helix `/streams`, cached two minutes in a
  transient shared by both blocks, failing soft to "not live". The featured panel is
  the design's rule exactly: live → the `dp_live` entry David wrote; not live → the
  newest archived video, with the grid starting from the second.
- **Credentials** — Twitch login, client ID, client secret — are a "Watch page"
  section on Settings → General (`Watch\Settings`), never constants. The secret lives
  in `wp_options`, an accepted single-author trade the field's own description
  discloses. Unset, everything degrades: no live panel, no VOD thumbnails, and the
  page still renders its archive.
- **Click-to-play** is the theme's `watch.js`, enqueued only where the blocks render.
  Cards ship as plain links to the video on its host; the press builds the iframe
  (YouTube via `youtube-nocookie.com`; Twitch with the `parent` the browser knows),
  respecting `prefers-reduced-motion` by not autoplaying.
- **The gear list** is editor content David owns: the `dpaternina/watch-gear` pattern,
  seeded as the Watch page's starting body through the `dp_seed_watch_body` seam —
  the plugin never learns the theme's markup, and a themeless seed leaves a callout
  saying the gear is missing.
- **The archive imports itself** (`Watch\VideoSync`, added 2026-08-29). Twitch VODs come
  from Helix `/users` then `/videos?type=archive`; YouTube uploads come from the Data API
  v3 — `channels.list` → the uploads playlist → `playlistItems.list` → `videos.list` —
  which is the API rather than the free RSS feed because the design's card prints a
  runtime and the feed carries none. Both are parsed into one `RemoteVideo` shape and
  upserted against `_dp_sync_key` (`twitch:<id>` / `youtube:<id>`), so syncing twice
  changes nothing. Hourly under WP-Cron (`dp_core_watch_sync`, cleared on deactivation),
  plus `wp dp watch sync` and a "Sync now" button in the Watch section on Settings →
  General — beside the credentials, because every way it fails is a credential. Two new
  settings there: a YouTube channel (`UC…` id or `@handle`) and a Data API key.
  - **Seven fields are synced** — title, status, source, platform id, runtime, month,
    tone — and `dp_note` is deliberately not one of them. No API text goes in the note;
    the card renders without one until David writes it.
  - **ADR-0018 rule 3 is enforced by a shadow copy.** Every write is recorded in
    `_dp_sync_shadow`; the next run compares the shadow with what is stored, and a field
    that has moved is added to `_dp_sync_locked` and never written again. No `save_post`
    hook and no suppression flag, so it is right whatever route an edit arrived by. See
    `Watch\AuthorEdits`.
  - **A video the platform stops listing is drafted, not deleted** — Twitch VODs expire on
    a timer, and deleting would destroy the note and title David wrote about one. It
    republishes if the video comes back, unless he was the one who unpublished it.
  - **Fail soft, and never partially.** A platform that does not answer whole is skipped
    entirely, because a truncated listing is indistinguishable from a deleted channel and
    reconciling against one would empty the page during an outage. A run that reached
    nothing says so rather than reporting a success, in the notice, in WP-CLI, and in the
    "last run" line the settings row prints — which is the only place a stalled schedule
    is visible.
  - **A site with no credentials syncs nothing and renders everything it already has.**
    That is the seeded development site: the fixture's eight `dp_video` entries carry no
    sync key, so the import can never adopt, update or unpublish one, and the Watch page
    is complete with no API configured.

---

## Sequencing

Reordered 2026-08-29 to wrap the project up. Phases 0–7 are done; what remains in this
repo, in order:

```
contact-form fix ─ 12 (Watch) ─ 10 (a11y/perf) ─ 8 (feeds) ─ tag v1
```

Phases 9 (migration) and 11 (cutover) are David's own work, outside this repo — see
their sections. The original dependency graph is in git history.

---

## Decisions (settled 2026-08-19)

| | |
|---|---|
| **Update host** | `wp-updates.fanxie.cloud`, namespace `dpaternina`, via the `fanxielab/wp-update-client` library (ADR 0015; originally `updates.dpaternina.com` on our own R2 bucket). |
| **Plugin count** | No target. The Colophon's "four plugins" was placeholder copy that should never have become a build rule. |
| **ACF** | Not used. `register_post_meta()` + REST schemas + block bindings. |
| **Analytics** | Rybbit, installed as its own plugin and configured by David. Not theme code, not `dp-core` code, and we do not enqueue it. The Colophon and Privacy copy describing it is David's to write. |
| **Offline** | Cut. No service worker anywhere in this repo; the design's offline state is not built. 404 ships. |
| **SEO** | AIOSEO. Every OG image, canonical, robots directive, sitemap and JSON-LD graph is the plugin's. This repo writes no SEO output. |
| **HTTP headers** | A security plugin of David's, not this repo. No `send_headers` handler, no CSP config, no header tests. We stay loadable under a strict policy instead. |
| **Résumé** | Downloadable PDF via Cloudflare Browser Rendering, cached; print stylesheet as the fallback. |
| **Watch** | Was deferred; un-deferred 2026-08-29 and built before launch (Phase 12, next after the contact-form fix). |
| **Series parts** | Draft posts carrying the `dp_series` term, not a stub post type. See Phase 3.1. |
| **Pages** | David creates and manages every page. The theme registers **no page routes** and never branches on a slug — design-specific pages are `dp-`-prefixed custom templates assigned from the admin. |
| **Work page** | David's to name and slug. `/work` is the expectation; a `/timeline` redirect, if wanted, is a Cloudflare rule, not code. |
| **Content** | Every word in the design is placeholder, including Colophon and Privacy. Seeded verbatim, kept visibly provisional, never invented around. |
