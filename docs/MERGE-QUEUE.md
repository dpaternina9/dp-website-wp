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

## Operational finding — do not run two agents' test suites at once
Both wp-env `tests` environments share one database. Phase 3 hit
`Record has changed since last read in table 'wp_options'` and a spurious failure when its
`composer test` overlapped another agent's. Passed on re-run and every run since.

**Rule going forward:** parallel agents either work in worktrees with their own wp-env ports
(as Phase 2 did, on 8898/8899) or do not run `composer test` concurrently.
