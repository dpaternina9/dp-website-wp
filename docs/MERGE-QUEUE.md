# Merge queue

Work completed in isolation, waiting to land. Delete a section once it is merged.

## Phase 2 — release automation
Branch: `worktree-agent-ad398f43ea81af990`
Blocked on: Phases 3 and 4 finishing in the main checkout.

### Merge chores
- [ ] Add to `DP\Core\Plugin::register()`:  `Update\UpdateClient::register();`
      (Phase 4 will want a second line here for `dp/callout` — expect two.)
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
- [ ] `Plugin::register()` is now a **three-way merge point**. It currently has Phase 3's two
      lines; it needs Phase 2's `Update\UpdateClient::register();` and Phase 4's blocks line.

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
- [ ] **The gradient monogram is a broken export.** `design-source/assets/dp-mark-gradient.png`
      and `dp-mark-gradient-128.png` both carry only the top arc of the mark and
      part of one letter, at 2000px as well as at 128px. The chrome ships the
      white mark instead. Re-export from Claude Design and re-import; the swap is
      one URL in `chrome.css`.
- [ ] **Set Settings → Reading.** Without a posts page there is no blog index,
      nothing in the navigation reads as the blog, and the All pill points at the
      site root. The site works; it just has no blog.
- [ ] **Curate the navigation menu.** The theme ships core's fallback, which
      lists every published page. The design's header is five items.

## Operational finding — do not run two agents' test suites at once
Both wp-env `tests` environments share one database. Phase 3 hit
`Record has changed since last read in table 'wp_options'` and a spurious failure when its
`composer test` overlapped another agent's. Passed on re-run and every run since.

**Rule going forward:** parallel agents either work in worktrees with their own wp-env ports
(as Phase 2 did, on 8898/8899) or do not run `composer test` concurrently.
