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
- **`themes/dpaternina/templates/front-page.html` is not what :8888 renders.**
  David has customised the template in the site editor, so `wp_template` post 65
  is authoritative there: it detaches the `post-row-compact` and `cta-band`
  patterns into inline copies, and it sets a `dp-pink` preset text colour on one
  label. A pattern edit will not reach his home page; a CSS change will.
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
