# dpaternina.com — WordPress rebuild

Monorepo for the new dpaternina.com: a hand-written WordPress **block theme** plus a
companion plugin. Replaces the split between `dpaternina.com` (work) and
`blog.dpaternina.com` (writing) — one site, posts imported via WXR.

---

## 1. Non-negotiable rules

### 1.1 All development goes through the agent
**Every development task in this repo MUST be executed by the
`wordpress-development-expert` agent** via the Agent tool. That includes: PHP, theme
templates, `theme.json`, block registration, JS/TS, build config, CI workflows,
tests, and WP-CLI scripts.

The main session's job is: digest requirements, plan, split work into briefs, dispatch
to `wordpress-development-expert`, review what comes back, and report to David. The main
session does not write production code itself. Reading files, running read-only
commands, and asking clarifying questions are fine.

When dispatching, always give the agent: the phase, the exact files it owns, the
acceptance criteria, and the relevant design-source paths. Never let two agents own the
same file at the same time.

### 1.2 No page builders. Ever.
No Elementor, Divi, Blocksy, Bricks, WPBakery, Beaver Builder, ACF-driven layout
builders, or any "theme framework". The site is the block editor + our own blocks.

**Stackable** is the one third-party block library in play, for utility blocks we
deliberately choose not to author ourselves. Stackable is additive only — nothing in the
theme may depend on Stackable being active. If Stackable is deactivated, every template
must still render.

### 1.3 PHP 8.4, modern, typed
- `declare(strict_types=1);` at the top of every PHP file.
- Namespaced (`DP\Theme\…`, `DP\Core\…`), PSR-4 autoloaded via Composer. No
  `require`-chains, no global functions except the handful WordPress forces on us.
- Use real 8.x language features where they earn their keep: constructor property
  promotion, readonly properties, enums, first-class callable syntax, `match`, named
  arguments, `#[\Override]`, property hooks and asymmetric visibility (8.4).
- Typed properties, parameter types, and return types everywhere. `mixed` is a smell.
- Prefer small final classes with injected dependencies over static utility bags.
- Never `extract()`, never variable variables, never `@` suppression.

### 1.4 Security and escaping — no exceptions
- Escape at the point of output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Every form/AJAX/REST write path: capability check **and** nonce **and** sanitization.
- All DB access through `$wpdb->prepare()` or, better, `WP_Query`/core APIs.
- No `eval`, no unserializing untrusted input, no raw `$_GET`/`$_POST`/`$_SERVER` reads
  without `wp_unslash()` + a sanitizer.
- Nothing enqueues from a CDN. All assets are local and versioned. The one deliberate
  external origin is the Rybbit analytics endpoint, named explicitly in the CSP
  (`script-src` + `connect-src`) and nowhere else.

### 1.5 Tests are mandatory
No PR merges without tests where testable behaviour changed.
- **Unit** (`tests/Unit`): pure logic, no WordPress bootstrap. Brain Monkey for
  hook/function mocking.
- **Integration** (`tests/Integration`): real WordPress, real DB, via `wp-env` +
  `wp-phpunit`. Anything touching queries, CPTs, taxonomies, REST routes, or block
  render callbacks lives here.
- **JS** (`@wordpress/scripts test-unit-js`): block edit/save logic and front-end
  behaviour.
- **E2E** (Playwright via `@wordpress/e2e-test-utils-playwright`): the critical paths —
  homepage, post, timeline interaction, contact form, archive filters.
- Render callbacks return strings; assert on markup, not on echo side effects.
- A bug fix starts with a failing test that reproduces it.

### 1.6 Quality gates (all must pass in CI and locally before "done")
```
composer lint      # PHPCS: WordPress-Coding-Standards + PHPCompatibilityWP (8.4)
composer analyse   # PHPStan level 9 + szepeviktor/phpstan-wordpress
composer test      # PHPUnit unit + integration
npm run lint       # wp-scripts lint-js, lint-style
npm run test:unit  # Jest
npm run test:e2e   # Playwright
```
Never report work as complete without pasting the actual output of the gates you ran.
"Should pass" is not a result.

### 1.7 Accessibility and performance are acceptance criteria
- WCAG 2.2 AA minimum. The design system's tokens already encode the contrast fixes —
  do not "improve" a colour without re-checking the ratio.
- Keyboard operable, visible focus (`--focus-ring`), correct landmarks, one `h1`,
  no heading level skips.
- Respect `prefers-reduced-motion` on every animation.
- No render-blocking JS. No jQuery. Front-end JS is small, vanilla, and
  progressive-enhancement only — every page must be readable and navigable with JS off.
- Images: `wp_get_attachment_image()` with `loading`/`decoding`/`sizes` handled, AVIF/WebP.

---

## 2. Repository layout

```
.
├── CLAUDE.md
├── docs/                       # plan, architecture decisions, design digest
├── design-source/              # READ-ONLY. Imported from Claude Design. Never edit.
│   ├── dpaternina.dc.html      # the full design: every page state
│   ├── components/*.dc.html    # component specs
│   ├── _ds/tokens/*.css        # design tokens — source of truth for theme.json
│   ├── _ds/styles.css
│   ├── theme.css
│   └── assets/                 # dP monogram marks
├── themes/
│   └── dpaternina/             # the block theme
└── plugins/
    └── dp-core/                # companion plugin: CPTs, taxonomies, dynamic blocks
```

`design-source/` is the contract. When the design and the implementation disagree, the
design wins — or we change the design first, in Claude Design, and re-import.

### 2.1 Theme vs plugin — where does it go?
| Belongs in the **theme** | Belongs in **dp-core** |
|---|---|
| `theme.json`, templates, template parts, patterns | Custom post types, taxonomies |
| Presentational block styles & variations | Dynamic blocks with render callbacks |
| `style.css`, front-end CSS/JS | REST routes, contact form handling |
| Block bindings for presentation | Data migration / import mapping, WP-CLI commands |

Rule of thumb: **if switching themes would destroy content or break a URL, it is not
theme code.** `dp-core` ships as a normal plugin (mu-plugins cannot be updated through
the WordPress updater, and we want the same tag-driven release flow for both).

---

## 3. Local environment

`wp-env` is the only supported local environment. Docker required.

```
npm run env:start      # wp-env start
npm run env:stop
npm run env:cli -- <wp-cli args>
npm run env:reset      # wp-env destroy && start && seed
```

- PHP 8.4, latest WordPress, plus a second `tests-wordpress` instance for integration.
- `.wp-env.json` mounts `themes/dpaternina` and `plugins/dp-core` and activates
  Stackable from the plugin directory.
- Seed content is scripted (`bin/seed.php`, run via WP-CLI) so a reset gives a site that
  matches the design's fixtures: the timeline lanes, the sample posts, the series,
  the categories, the Uses/Colophon/Privacy pages.
- Never hand-edit the local DB to make a test pass. Fix the seed script.

---

## 4. Release and deployment

Theme and plugin are updated by **tagging in GitHub**. Nothing is uploaded by hand and
nothing is edited on the server.

- Semver tags: `theme-v1.2.3` and `core-v1.2.3` (independent versions).
- Tag push → GitHub Actions runs the full gate suite → builds a production zip (no dev
  deps, compiled assets, `readme.txt` + version headers stamped from the tag) → publishes
  a GitHub Release with the zip attached and a signed `update.json` manifest.
- The site pulls updates through WordPress core's own
  `update_themes_{$hostname}` / `update_plugins_{$hostname}` filters pointed at that
  manifest — no third-party updater plugin, no phoning home to a service we don't own.
- WordPress auto-updates stay enabled for both, so a tag is the whole deploy.

Details and the fallback options are in `docs/plan.md`. Until that pipeline exists,
there is no manual deploy path — building it is a phase, not an afterthought.

---

## 5. Design fidelity

- The token CSS in `design-source/_ds/tokens/` is transcribed into `theme.json`
  (`settings.color.palette`, `settings.typography.fontSizes`, `settings.spacing`,
  `settings.custom`) so the editor offers exactly these values and nothing else.
  Custom properties keep their names: a token called `--dp-teal` stays `--dp-teal`.
- Dark is the only ground. **Light mode is ruled out** — the design's own Ledger says
  the accents do not hold up on white. The `.dp-light` scope stays in the CSS in case
  it comes back; do not wire a toggle to it and do not "finish" it.
- `--dp-*` hues are for **fills**. `--hue-*` / `--accent-text` are for **text**. A brand
  hue used directly as text fails AA; the token comments explain each correction. Do not
  "clean up" a colour value without re-measuring the ratio.
- One gradient per view. No gradient fills on buttons, no gradient-filled text.
- The design uses inline styles because the design tool has no stylesheet. The theme does
  **not**: everything becomes `theme.json` + a class-based stylesheet. Values must match,
  the delivery mechanism must not.
- Fonts (Bricolage Grotesque, Manrope, JetBrains Mono) are self-hosted and registered in
  `theme.json` via `fontFace`. No Google Fonts requests at runtime.
- The editor must look like the front end. Every block style is loaded in both contexts.

### 5.1 Pages belong to David, not to the theme
- **Register no routes for pages.** No `add_rewrite_rule`, no `page_on_front` assumption,
  no `is_page('contact')`, no slug in a template name, no hardcoded href to a page.
  David creates every page in the admin, picks its slug, and assigns a template.
- Design-specific pages get a **custom template** declared in `theme.json`
  `customTemplates`, prefixed `dp-` (`dp-work`, `dp-watch`, `dp-about`, `dp-resume`,
  `dp-contact`). The prefix is load-bearing: a template file named `page-work.html` would
  be auto-applied by the hierarchy to any page slugged `work`, which is the exact
  coupling we are avoiding.
- Branch on the **assigned template** (`get_page_template_slug()`) or the queried object,
  never on a slug or an ID.
- The only registered rewrites in the whole project are the `dp_series` taxonomy slug
  (filterable) and the résumé `format` query var. Adding a third needs an ADR.
- Redirects are data in the migration redirect map, editable by David. They are never
  `wp_redirect()` calls keyed to a hardcoded path.

---

## 6. Working agreements

- Read `docs/design-digest.md` once, then `docs/plan.md` before starting a phase; update
  the plan when a phase completes. Decisions that outlive a PR go in `docs/adr/`.
- **No ACF.** The design's notes suggest it; we use `register_post_meta()` with REST
  schemas and block bindings instead. See `docs/plan.md` for why.
- One phase, one branch, one PR. Conventional commits.
- Do not add a dependency without saying what it replaces and why twenty lines of PHP
  won't do. There is no target plugin count — just a standing burden of proof.
- **All copy in the design is placeholder**, including the Colophon and Privacy pages.
  Seed it verbatim, keep it visibly provisional, and never write plausible-sounding
  facts about David to fill a gap. Nothing in that copy is an acceptance criterion.
- If a design detail is genuinely ambiguous, ask. Don't guess and don't quietly simplify.
