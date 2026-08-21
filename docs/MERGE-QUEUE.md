# Merge queue

Work completed in isolation, waiting to land. Delete a section once it is merged.

## Phase 2 — release automation
Branch: `worktree-agent-ad398f43ea81af990`
Blocked on: Phases 3 and 4 finishing in the main checkout.

### Merge chores
- [x] **DONE.** `Plugin::register()` now wires all four collaborators (content, cli,
      Blocks, Timeline, UpdateClient). Verified on `main`. Later phases: add a line, do not
      restructure the method.
- [ ] `docs/adr/README.md` index needs rows for **0003** and **0004**; Phase 2 deliberately
      did not edit it to avoid colliding with Phase 3.
- [x] `phpcs.xml.dist`: excluded the PHPCompatibility enum false positive (done in main).
      The file-level suppressions in `PackageType.php` (Phase 2) and Phase 3's enums can
      now be deleted — do that at merge.
- [ ] Preserve mode `100755` on `bin/dp-build.sh`.
- [ ] Phase 2 modified `.github/workflows/ci.yml` (+4 lines: `workflow_call:`) so
      `release.yml` can reuse it rather than restating every gate. Only edit outside its lane.

### David's setup, before any release can happen
- [ ] `php bin/dp-release.php keygen --write`, put the secret in `DP_UPDATE_SIGNING_KEY`,
      commit the compiled-in public half, clear scrollback.
- [ ] GitHub secrets: `DP_UPDATE_SIGNING_KEY`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`,
      `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`.
- [ ] Bind the R2 bucket to `updates.dpaternina.com`; confirm it serves `application/json`.

### Still unproven (needs a remote / a real install)
No tag has ever run the workflow. Untested: `gh release create --verify-tag`, the R2
upload, DNS/TLS on `updates.dpaternina.com`, WordPress actually unpacking a zip, and the
`wp_maybe_auto_update` cron path. The full closing sequence is in the Phase 2 agent's
report — ask for it when you are ready to run it.

> **Hazard.** Do **not** run `wp plugin update dp-core` against the normal wp-env site.
> `.wp-env.json` bind-mounts `plugins/dp-core` and `themes/dpaternina` from the repo. The
> upgrader deletes the old directory and moves the new one in — pointed at a bind mount it
> either fails on a busy device or eats the source tree. Prove installs on a disposable
> environment with no repo mounts.

---

## Phase 3 — content model (already committed to `main`, `087a86e`..`13e22b4`)

Chores it could not do from its lane:
- [x] `phpcs.xml.dist`: added `dp_series` to the `PrefixAllGlobals` prefix list, so
      `dp_series_rewrite_slug` no longer needs a line-level ignore. Delete that ignore at merge.
- [x] `docs/adr/README.md` index rows for **0003** and **0004** (and 0005 if Phase 4 writes one).
- [ ] `docs/plan.md`: tick Phases 2, 3, 4.
- [x] **DONE.** All registration lines landed.

Facts later phases inherit (from ADR-0003):
- `WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`. **From the second test
  in a run onward, everything registered on `init` is gone.** Any integration test touching
  the model must re-register in `set_up()` — without it the suite asserts against an empty
  model and *passes*.
- `supports` must include `custom-fields` on the CPTs or `WP_REST_Posts_Controller` drops the
  `meta` property entirely and the model is registered-but-invisible.
- Decimal years are **twelfths**, not tenths. `2026.6` is August, not July.
- Seeded block markup is coupled to Phase 4's `dp/callout` and `dpLabel` saved shapes. If
  either changes, re-run `wp dp seed` — never hand-edit content.

## Phase 5 — chrome, homepage, blog, archives (committed to `main`, `dfd09d5`..)

Chores it could not do from its lane:
- [ ] `docs/plan.md`: Phases 2, 3 and 4 are still unticked. Phase 5 ticked its own.
- [ ] `Plugin::register()` is still the three-way merge point Phase 3 described.
      Phase 5 did not touch `dp-core` at all.

Facts later phases inherit:
- **`core/query` silently drops a post type that is not publicly viewable.**
  `build_query_vars_from_block_context()` only honours `postType` when
  `is_post_type_viewable()` agrees, and none of `dp_role`/`dp_ship`/`dp_video`
  is. A query block asking for one gets **posts** instead. `DP\Theme\Query\QueryLoops`
  puts the type back through `query_loop_block_query_vars`; Phase 6 will need the
  same for the timeline if it queries from a template.
- **`core/post-meta` refuses meta on those types too**, for the same reason —
  its callback returns null unless `is_post_publicly_viewable()`. The theme's
  `dpaternina/post` binding source carries an explicit allowlist of the design's
  public copy, per post type. Add to that list, do not widen the rule.
- **WordPress stores a block theme's custom template under its slug**, without
  the `.html`. A page assigned Contact from the admin carries `dp-contact`.
  `Destinations` normalises both spellings and `ChromeTest` asserts the names the
  chrome uses are ones the admin actually offers.
- **`WP_Theme::get_block_patterns()` is cached in a transient.** Adding the
  `patterns/` directory to a theme that had none registers nothing until the
  cache expires or `wp_clean_themes_cache()` runs. It cost half an hour; on a
  fresh install it does not arise.
- **A hierarchy template core does not recognise is offered as a page template.**
  `taxonomy-dp_series` is not in `get_default_block_template_types()`, so it was
  classified custom and appeared in the admin dropdown.
  `DP\Theme\Blocks\TemplateHierarchy` states the rule core is missing.
- The e2e suite is `fullyParallel` against one site. `tests/e2e/chrome.spec.ts`
  runs serial and sweeps only its own slugs; a spec that clears content will pull
  the fixture out from under whichever other spec is mid-run.

### For David
- [x] **The gradient monogram is a broken export.** Closed in Phase 5b. David
      supplied a good 2000px file directly; it is the master at
      `themes/dpaternina/assets/img/dp-mark-gradient.src.png` and the chrome now
      draws `dp-mark-gradient-128.png` generated from it.
      `design-source/assets/dp-mark-gradient*.png` is still broken and is still
      not the source for anything.
- [ ] **Set Settings → Reading.** Without a posts page there is no blog index,
      nothing in the navigation reads as the blog, and the All pill points at the
      site root. The site works; it just has no blog.
- [ ] **Curate the navigation menu.** The theme ships core's fallback, which
      lists every published page. The design's header is five items.

## Phase 6 — the timeline (committed to `main`, `cccab06`..)

Chores it could not do from its lane:
- [ ] `docs/plan.md`: Phases 2, 3, 4 and 5 are ticked in the working tree but not committed.
      Phase 6 ticked its own.
- [x] `Plugin::register()` — Phase 6 added its one line
      (`( new Blocks\Timeline( plugin_dir_path( $this->file ) ) )->register();`) without
      restructuring the method. It remains the merge point Phase 2 still has to land in.
- [x] `tests/e2e/global-setup.ts` now activates **dp-core** as well as the theme. This is
      shared infrastructure Phase 5 owns; the change is one call with a comment. Without it
      a fresh `composer test:integration` leaves the plugin deactivated on :8889 and every
      dynamic block is simply missing from the page.

Facts later phases inherit:
- **The theme fatals if it names a `dp-core` class unguarded.** `DP\Theme\Blocks\Timeline`
  reads `DP\Core\Blocks\Timeline::BLOCK_NAME` to build a hook name and needed a
  `class_exists()` guard around it. `composer test:integration` deactivates every plugin,
  so this is not hypothetical — it shows up as a 500 on the tests site and an
  "Unexpected end of JSON input" from `requestUtils.rest`, which names neither cause.
  Any phase adding a cross-package reference in the theme needs the same guard.
- **`prefers-reduced-motion` did not reach the document through Playwright's
  `test.use({ reducedMotion: 'reduce' })`** under this project's fixtures. `viewport`,
  `storageState` and `javaScriptEnabled` all do. `page.emulateMedia()` in a `beforeEach`
  works; every reduced-motion assertion in `timeline.spec.ts` now asserts the media query
  matches first, so it cannot become a test of nothing.
- **:8889 has plain permalinks.** A page's `link` already carries `?page_id=N`, so
  `` `${link}?dp-open=x` `` produces a second `?` WordPress never parses. One test passed
  anyway — through the fragment, which the front-end controller acts on. Build URLs with
  `new URL()`; `timeline.spec.ts` has a `workUrl()` helper.
- **`WP_UnitTestCase::go_to()` empties `$_GET`** and rebuilds it from the URL's query
  string. A test that sets `$_GET` and then calls a helper that calls `go_to()` is
  asserting against an empty request. Put the arg in the URL.
- **`wp_scripts()` outlives a test.** An "is this enqueued yet" assertion has to null
  `$GLOBALS['wp_scripts']` first or it depends on which test ran before it.
- **The chart's element id is the constant `dp-timeline`** and `dp/timeline` is
  `multiple: false`. Anything linking into an entry uses
  `DP\Core\Content\Timeline\Chart::entry_key()` — never a format string of its own.
- **`dp-filter` and `dp-open` are the two query args the timeline reads.** A caching layer
  has to vary on them. Core's `rel_canonical` already handles the SEO side.

### For David
- [ ] **Assign the Work template.** The chart only appears on a page carrying the `dp-work`
      custom template. `/work` on :8888 already has it; a fresh site will not.
- [ ] **Featured work is a flag, not an order.** A shipped thing shows as a card when
      `dp_featured` is on. The seed marks three of the four.
- [x] **`design-source/assets/dp-mark-gradient*.png` is still the broken export** Phase 5
      reported. Phase 6 did not touch it and used nothing from it. Closed in Phase 5b —
      see the Phase 5 entry above.

---

## Phase 5b — design fidelity corrections (committed to `main`, `daf39dd`..)

David reviewed the rendered home page against the design. What he found, and what
it turned out to be:

- **Tile labels and badges were 16px and grey.** `.dp-tile p` and
  `.dp-shipped-item p` are one class and one element; `.dp-label` was one class.
  The container won and the component lost. Container rules now exclude the
  components they contain, through `:where()` so the exclusion costs no
  specificity of its own.
- **"Full timeline →" rendered in the site editor and not on the front end.** So
  did three other buttons. The destination cache was keyed by whichever spelling
  of the template `_wp_page_template` held, the write side started normalising
  the two, and the stored map went on answering "no such page". ADR-0008 has the
  fix and the reason the failure was invisible.
- **24px of page background between full-bleed bands.** Core's block gap on the
  top-level flow children, showing as a seam between two black bands and as a
  gap above and below the closing CTA.

Facts later phases inherit:

- **The block editor injects WordPress's global styles *after* the theme's editor
  styles; the front end prints them before.** So a hand-written rule at one class
  — `.dp-label`, `.dp-tone-gold`, `.wp-site-blocks > *` — wins on the site and
  loses in the canvas, against `:root :where(p)` and
  `:root :where(.is-layout-flow) > *`. Every component rule in this theme has to
  carry an element or a second class or it is not the same in both contexts. Most
  already do (`p.dp-badge`, `h3.dp-tile-title`); the ones that did not are fixed.
  **A style change is not verified until it has been looked at in the editor too.**
- ~~**`themes/dpaternina/templates/front-page.html` is not what :8888 renders.**~~
  **No longer true — David cleared the customisation on 2026-08-21.** There are now
  zero `wp_template` posts; the theme's template files are authoritative on :8888 and
  a pattern edit does reach the home page again. The general hazard stands: the moment
  a template is touched in the site editor, WordPress forks it into the database and
  the file stops rendering. Check `wp post list --post_type=wp_template` before
  concluding that a template edit "did nothing".
- **`*.src.*` is the convention for an asset master.** Nothing links to it and
  `bin/dp-build.sh` drops it before zipping a release.
- **`data-dp-destination` is on every link the chrome derives**, resolved or not.
  It is the fastest way to see what a page asked for and what it got.

### For David
- [ ] **Two labels in the RIGHT NOW bento are content, not theme.** The design puts
      a pulsing pink dot before "MY AGENCY · TAKING PARTNERS" and a second muted
      label, "NATIVE iOS", opposite "LIVE ON THE APP STORE" in the Kiveo tile.
      Neither is in the template. Both are yours to add.
- [ ] **The `dp-pink` text colour you set on the agency label is redundant** — the
      `dp-tone-pink` class it already carries sets the same value through
      `--hue-pink`, which is the token that stays legible if the ground ever
      changes. Clearing the colour in the editor loses nothing.

---

## Operational finding — do not run two agents' test suites at once
Both wp-env `tests` environments share one database. Phase 3 hit
`Record has changed since last read in table 'wp_options'` and a spurious failure when its
`composer test` overlapped another agent's. Passed on re-run and every run since.

**Rule going forward:** parallel agents either work in worktrees with their own wp-env ports
(as Phase 2 did, on 8898/8899) or do not run `composer test` concurrently.

---

## Phase 7 — contact, the résumé and the remaining pages (this branch)

Branch: `phase-7-contact-and-pages`. **This repository has no git remote**, so there is
no pull request — the branch is local and merges by hand. Same finding as Phase 2's
"needs a remote"; adding one is David's.

Chores it could not do from its lane:
- [x] `docs/plan.md`: Phases 2 to 6 were ticked in the working tree but never
      committed. They are committed here, with the offline / SEO / headers cuts that
      were sitting uncommitted beside them. Phase 7 ticked its own.
- [ ] `docs/adr/README.md` now has rows for **0009** and **0010**.
- [x] `.wp-env.json` gained one mapping for the `tests` environment only:
      `wp-content/mu-plugins` → `./tests/Support/mu-plugins`. **A checkout that was
      already running needs `npm run env:start` before `npm run test:e2e` passes** —
      without the bind mount, `wp_mail()` fails, and the *sent* test fails showing the
      *failed* panel, which names neither cause. ADR-0010.
- [x] `themes/dpaternina/assets/css/chrome.css` — the base button rule. Phase 5's file,
      one three-selector change, no variant touched. See the defect note below.
- [x] `plugins/dp-core/src/Blocks/js/callout/index.js` — one import. Phase 4's file.
      `plugins/dp-core/build/` is compiled, not committed; CI already runs
      `npm run build` before `composer test`. Run it locally after pulling.

Defects found in the landed Phase 7 code (`f7dc576`), and what they were:

- **The honeypot had no stylesheet rule.** `ContactForm::honeypot()` emits
  `<div class="dp-hp">` with a labelled text input, and `.dp-hp` was in no CSS file —
  so a sighted visitor saw a field saying "Leave this field empty", filled it in, and
  was refused for it. Fixed in `components.css`; asserted twice, once against the file
  and once in a browser (`not.toBeInViewport()`).
- **Contact and the résumé both carried the closing CTA band.** The design's own
  line is `showCta: view !== 'contact' && view !== 'notfound' && view !== 'offline'
  && view !== 'resume'`. Both templates now omit it and `PagesTest` asserts it.
- **The `fetch` upgrade had a server but no client.** `Handler::wants_json()` and
  `dp_contact_panel_html` were both there and nothing ever set the header. The
  controller is `themes/dpaternina/assets/js/contact-form.js`, enqueued from the
  block's render like the timeline's.

Facts later phases inherit:

- **A block registered only in PHP is `core/missing` in the block editor.** It was
  true of `dp/timeline` from Phase 6 and would have been true of both Phase 7 blocks.
  Every dynamic block in `plugins/dp-core/blocks/` now needs an entry in
  `src/Blocks/js/dynamic/server-rendered.js` **and a rebuilt bundle**;
  `ServerRenderedParityTest` fails on either omission. ADR-0009.
- **`plugins/dp-core/build/` is gitignored and built in CI.** The parity test asserts
  the compiled bundle carries every name, so a stale local build fails instead of
  passing quietly. Run `npm run build` after pulling this branch.
- **A one-class rule loses in the canvas and wins on the page.** Phase 5b said it
  about `.dp-label`; it was also true of `.wp-element-button` against core's
  `:root :where(.wp-element-button, .wp-block-button__link)`, which is the same
  specificity. Every button on the site was grey in the editor and teal on the page.
  The base rule now names an element.
- **`core/button` markup must be exactly what core's `save` produces.** A bare
  `download` attribute on the résumé's PDF link made the block invalid in the editor
  ("Block contains unexpected or invalid content"). The `Content-Disposition` header
  already forces the download; the attribute is gone.
- **`WP_UnitTestCase` empties `$_GET` and `$_POST` between tests but not `$_SERVER`.**
  A test that sets `REQUEST_METHOD` leaves every test after it looking like a POST.
  `ContactTestCase` saves and restores it.
- **`wp_insert_post()` writes `post_modified` as "now" on every update**, so two edits
  inside one second are the same timestamp and the résumé's cache key cannot tell them
  apart. `ResumeTestCase::touch_post()` writes the column directly; fixtures are dated
  2020 so an update visibly moves it.
- **Brain Monkey cannot stand in for `time()`**, which is a PHP internal, so
  `RateLimiter::remaining()` reads the real clock. `RateLimiterTest` models the store
  as core writes it — an absolute expiry in the option row — and moves that timestamp
  to make time appear to pass.
- **`tests/bootstrap.php` now defines the four WordPress time constants for the unit
  harness.** A class constant like `12 * HOUR_IN_SECONDS` is a constant expression PHP
  evaluates on first read, so without them the failure arrives from inside the class
  under test and names nothing.
- **Two new derived destinations**: `home` (always resolves, `home_url('/')`) and
  `resume-pdf` (the page carrying `dp-resume`, plus the query variable, built by
  `ResumePdf::download_url()` behind a `class_exists()` guard). `Destinations` grew
  `id_by_template()` for the second.

### Still open, for a later phase

- [ ] **`dpaternina/series-planned` is still `core/missing` in the site editor.** It is
      the theme's dynamic block, on `taxonomy-dp_series`. ADR-0009 covers `dp-core`'s
      three; the theme ships no JavaScript build at all, so giving it a preview means
      giving the theme a build — a decision of its own.
- [ ] **The Watch tile is missing from the 404's "OR TRY ONE OF THESE" grid.** The
      design has three; the digest omits Watch from the navigation until Phase 12
      ships, so this ships with two. Phase 12 adds the third, a `dp-watch` entry in
      `Navigation::TEMPLATES`, and `watch` to `DESTINATIONS`.
- [ ] **The résumé PDF has never been rendered by a real browser.** Every path around
      the renderer is tested through the port; `CloudflareBrowserRendering` and
      `Gotenberg` have never made a request, because David has no credentials. The
      site as shipped falls through to the print view, which is the documented
      behaviour, not a gap.

### For David
- [ ] **Assign the About, Contact and Résumé templates.** :8888 already has all three;
      a fresh site will not. `?format=pdf` means nothing until a page carries
      `dp-resume`, and the "Get in touch" button in the header stays inert until one
      carries `dp-contact`.
- [ ] **The Uses, Colophon and Privacy pages are seeded with no template**, which is
      right — they render through `page.html`. If you assign one of the `dp-` templates
      to them by mistake, the eyebrow and the deck disappear.
- [ ] **The Privacy page still says the opposite of what the site does.** Digest §7
      flagged it; nothing in this phase changed it, and it is the one page where the
      placeholder copy shipping as-is would be actively misleading.
- [ ] **`dp_contact_recipient` and `dp_contact_public_address` are both unset.**
      Messages go to Settings → General's administration address, and no address is
      published on the page at all until you give one — deliberately, because where
      mail is delivered and what you publish are two decisions.

---

## Phase 7b — chrome and home-page fidelity (this branch)

Branch: `phase-7b-chrome-and-home-fidelity`. Local, no remote, merges by hand.

David's four review items, and what each turned out to be:

- **The logo could not be edited** because it was not an image. `parts/header.html`
  rendered `core/site-title` and `chrome.css` painted the monogram behind it as a
  `background`, with the title text pushed off-screen. All three marks are now
  `core/site-logo`. ADR-0011.
- **"Cards spacing is off by default"** was core's block gap adding 24px to every
  grid item on top of the grid's own `gap`. The bento's row gap was 40px where the
  design draws 16; the work cards' bodies were 48px taller than the design.
- **"That section is missing padding at the bottom"** was `.dp-right-now`, which had
  no `padding-block` at all. Its *top* read correctly only because core's block gap
  happens to be the same 24px the design asks for.
- **The footer could only mirror the header** because there was one `wp_navigation`
  post and no `wp:navigation` block carried a `ref`. The three groups are now
  `dp-to-*` links.

Chores it could not do from its lane:
- [x] `docs/adr/README.md` has a row for **0011**.
- [x] `docs/plan.md` gained §7.3.
- [x] `plugins/dp-core/src/Fixture/Seeder.php` — Phase 3's file, one new step
      (`seed_brand()`) and one new count in the report. `ContentSeedTest`'s exact-counts
      assertion moved with it.
- [x] `themes/dpaternina/src/Theme.php` — one line, plus `Navigation` is now built
      before `Assets` so `Brand` can be given it. The registration order is otherwise
      untouched; it remains the merge point Phase 2 still has to land in.

### Facts later phases inherit

- **`wp_template_part` forks are a separate list from `wp_template`.** The
  constraint note for this phase said the template customisations were cleared, and
  they were — but `wp post list --post_type=wp_template_part` still held a forked
  **header** (ID 71), so `parts/header.html` was not what :8888 rendered and none of
  the header work reached the page. Deleted, with the content saved into this phase's
  report. **Check both post types before concluding a chrome edit did nothing.**
- **`core/site-logo` renders nothing when `site_logo` is unset.** That is the block's
  own behaviour and is deliberately not patched: a PHP fallback would draw a mark on
  the page that the editor's canvas does not, which is the ADR-0008 divergence. The
  seeder is what stops a fresh site being blank.
- **`.dp-x { gap: … }` and `.dp-x { margin-block-*: … }` are one class, and so are
  core's layout rules.** Phase 5b said this about type; it is equally true of
  spacing, and it was true of 18 elements on the home page. Every spacing rule in
  the theme now carries a second class or an element name, and
  `tests/e2e/spacing.spec.ts` sweeps the home page in both contexts to keep it that
  way.
- **A theme cannot bind a navigation block to a particular menu.** `ref` is a post
  ID and a template file cannot carry one; supplying it server-side leaves the block
  editor drawing a different menu from the front end. Anything the chrome links to
  has to be a derived destination, not a menu entry. ADR-0011.
- **`core/categories` is a `<ul>` on the page and, briefly, something else in the
  canvas** while it fetches its terms. A rule written `ul.dp-footer-cats` is right
  most of the time; `.dp-footer-group .dp-footer-cats` is right always.
- **The `tests` environment's mu-plugin mapping is not applied by a plain
  `wp-env start` on containers that already exist.** `WPMU_PLUGIN_DIR` was empty and
  the contact form's *sent* e2e failed showing the *failed* panel — exactly the
  ADR-0010 symptom. `npx wp-env start --update` recreates the container and fixes it.

### For David

- [ ] **Your forked header template part was deleted.** It was created by editing the
      header in the Site Editor, and while it existed the theme's `parts/header.html`
      did nothing — which is why the logo change would have appeared not to work. The
      only thing in it that was not in the theme's own file was a `"ref":66` on the two
      navigation blocks, which core's fallback resolves to the same menu anyway. The
      full markup is in the phase report if you want any of it back.
- [ ] **Assign the Uses and Colophon templates.** Two new entries in the page
      template dropdown, both identical to the default page template — assigning one
      is how the footer's MORE group learns which page is which. Nothing about how
      those pages render changes. This supersedes Phase 7's warning that assigning a
      `dp-` template to them would lose the eyebrow and the deck; that is not true of
      these two. Done on :8888 already.
- [ ] **Set Settings → Privacy.** The footer's PRIVACY link resolves from
      `wp_page_for_privacy_policy` and is inert until you choose a page. Pointed at
      the seeded Privacy page on :8888 already.
- [ ] **The site logo is now a media item you can swap** — Appearance → Editor →
      Styles, or the Customizer. The seeder put the theme's own monogram there. If
      you clear it, the header, the panel and the footer lose their mark; that is the
      block behaving correctly, not a bug.
- [ ] **Your header menu still has one item ("Work").** Phase 5's chore is still
      open: the design's header is Home · Work · Blog · About plus "Get in touch".
- [ ] **The footer's WRITING group lists every category**, because `core/categories`
      is live data. The design shows three links — All posts, My life story,
      Categories. Say the word if you want the literal three instead; "My life story"
      and "Categories" both need somewhere to point that the theme can derive.
