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

1. `style.css` carries `Update URI: https://updates.dpaternina.com/theme`;
   `dp-core.php` carries `Update URI: https://updates.dpaternina.com/core`.
2. `dp-core` hooks `update_themes_updates.dpaternina.com` and
   `update_plugins_updates.dpaternina.com`, fetches a signed manifest, verifies it with
   `sodium_crypto_sign_verify_detached()` against a public key compiled into the
   plugin, and hands core the version + package URL.
3. Tagging `theme-v1.2.3` or `core-v1.2.3` triggers GitHub Actions:
   run every gate → stamp the version from the tag into the headers and `readme.txt` →
   build production assets → zip without dev deps → publish a GitHub Release with the
   zip → sign and publish `manifest.json` to Cloudflare R2 behind
   `updates.dpaternina.com`.
4. `auto_update_theme` / `auto_update_plugin` return true for our two slugs, so the
   site takes the update on its own next cron run. `wp cron event run` forces it.

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
  `show_ui => true`, `show_in_rest => true`, `supports` trimmed to what is used.
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

**Decision: a planned part is a draft `post`** carrying the `dp_series` term,
`dp_series_part`, and two extra meta fields the design needs — `dp_series_years`
("1995 — 2007") and `dp_series_note`. Ordering is `menu_order`. The series template runs
two queries against the same term, one `publish` and one `draft`, and the draft query
selects **title and meta only** — never content, never a permalink.

Why this over the alternatives:

- **It cannot drift.** When David writes the post, he publishes it. It moves from one
  list to the other, in the right position, with no second object to delete. A
  `dp_series_stub` post type would require deleting the stub when the real post lands —
  a manual step that gets forgotten and shows the same part twice.
- **The editing UI is free.** Drafts already have a title, an order, and a place in the
  admin. A stub type means another menu item; term meta holding a JSON array means
  building a bespoke repeater and having nothing queryable at the end of it.
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

---

## Phase 8 — Feeds

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

---

## Phase 9 — Migration

- Export WXR from `dpaternina.com` and `blog.dpaternina.com`.
- A `wp dp migrate` command: map old categories onto the five, derive series membership
  and part numbers, backfill read time, convert classic content to blocks
  (`wp post list` + block parser, not regex), rehost images, and generate a redirect map
  from every old URL — including `blog.dpaternina.com/*` → `dpaternina.com/blog/*`.
- Report what it could not map instead of guessing.
- Any vanity redirect David wants — `/timeline` → the work page being the obvious one —
  is a **row in that map**, editable from the admin. It is never a `wp_redirect()` keyed
  to a hardcoded path in the theme.

**Tests:** run the migration against a fixture WXR in CI and assert the redirect map is
total — every old URL resolves 200 or 301, none 404.

---

## Phase 10 — Accessibility, performance, hardening

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

---

## Phase 11 — Cutover

Runbook, staging rehearsal on wp-env with real exported content, DNS plan, redirect
verification, and a rollback that is a single `wp theme install --activate` of the
previous tag.

---

## Phase 12 — Watch (deferred)

Deprioritised by David — the site ships without it. Built after launch, or earlier if
the Twitch credentials turn up.

- `dp_video` grid, live-now panel, gear list.
- Thumbnails resolve to the public Twitch/YouTube URLs in digest §3.5, fetched and
  cached server-side so the visitor's browser never talks to those hosts before a click.
- Players load only on press.
- A Twitch Helix call for VOD thumbnails, cached in a transient, failing soft.

Nothing about it is a route: David creates a Watch page and assigns the `dp-watch`
template, exactly like the others. Until then the `dp-watch` template simply is not
declared, and the Watch entry stays out of the nav menus rather than pointing at a 404.

---

## Sequencing

```
0 ─ 1 ─ 2 ─ 3 ─ 4 ─┬─ 5 ─ 6 ─┬─ 8 ─ 10 ─ 11 ─ (12)
                   ├─ 7 ─────┤
                   └─ 9 ─────┘
```

Phases 5/7/9 can run in parallel once 4 lands, on separate branches, with different
agents — they share only `theme.json`, which is frozen after Phase 4 except by ADR.
Phase 6 depends on 5 for the chrome it sits inside. Phase 12 is out of the critical path
entirely.

---

## Decisions (settled 2026-08-19)

| | |
|---|---|
| **Update host** | `updates.dpaternina.com` on Cloudflare R2. |
| **Plugin count** | No target. The Colophon's "four plugins" was placeholder copy that should never have become a build rule. |
| **ACF** | Not used. `register_post_meta()` + REST schemas + block bindings. |
| **Analytics** | Rybbit, installed as its own plugin and configured by David. Not theme code, not `dp-core` code, and we do not enqueue it. The Colophon and Privacy copy describing it is David's to write. |
| **Offline** | Cut. No service worker anywhere in this repo; the design's offline state is not built. 404 ships. |
| **SEO** | AIOSEO. Every OG image, canonical, robots directive, sitemap and JSON-LD graph is the plugin's. This repo writes no SEO output. |
| **HTTP headers** | A security plugin of David's, not this repo. No `send_headers` handler, no CSP config, no header tests. We stay loadable under a strict policy instead. |
| **Résumé** | Downloadable PDF via Cloudflare Browser Rendering, cached; print stylesheet as the fallback. |
| **Watch** | Deferred to Phase 12. Ships without it; nav entry removed until it exists. |
| **Series parts** | Draft posts carrying the `dp_series` term, not a stub post type. See Phase 3.1. |
| **Pages** | David creates and manages every page. The theme registers **no page routes** and never branches on a slug — design-specific pages are `dp-`-prefixed custom templates assigned from the admin. |
| **Work page** | David's to name and slug. `/work` is the expectation; a `/timeline` redirect, if wanted, is a row in the migration redirect map, not code. |
| **Content** | Every word in the design is placeholder, including Colophon and Privacy. Seeded verbatim, kept visibly provisional, never invented around. |
