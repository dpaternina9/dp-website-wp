# ADR-0015 — The update pipeline is the extracted `wp-update-client` library

## Status

Accepted — 2026-08-25. Supersedes the *implementation* section of ADR-0004; the
trust model decided there (signed-envelope manifests, a compiled-in Ed25519
public key, fail closed, tag-driven builds) is unchanged and is now enforced by
the library instead of by code in this repo.

## Context

ADR-0004 built a single-site update pipeline inside this repo:
`DP\Core\Update\*` (~450 lines of client), `bin/dp-release.php`,
`bin/dp-build.sh`, and a release workflow publishing ZIPs to GitHub Releases
and signed manifests to a Cloudflare R2 bucket behind `updates.dpaternina.com`.
It shipped and was observed working end to end against WordPress 7.1.

That module has since been extracted and generalized into
[`fanxie-lab/wordpress-updater`](https://github.com/fanxie-lab/wordpress-updater)
— the `fanxielab/wp-update-client` Composer package plus release tooling, a
reusable GitHub Actions workflow, and a Cloudflare Worker write path — serving
multiple client sites from one instance at `wp-updates.fanxie.cloud`. The
local copy became the ancestor of a canonical library that now carries its own
tests, docs and fixes. Keeping the ancestor alive here means every hardening
fix lands twice or drifts.

## Decision

We delete the vendored `DP\Core\Update` module and consume the library.

- **Dependency.** `plugins/dp-core` requires `fanxielab/wp-update-client`
  (`dev-main` until the library tags a release) through a VCS repository
  entry, with a committed `composer.lock`. The plugin ZIP ships the library in
  its own `vendor/`. The root `composer.json` carries the same require-dev so
  PHPStan, PHPUnit and the local wp-env mount resolve the classes; its
  `post-install-cmd` now runs a full `composer install` in the plugin
  directory instead of only dumping an autoloader.
- **Host and namespace.** Updates are served from the existing Fanxie Lab
  instance: host `wp-updates.fanxie.cloud`, namespace `dpaternina`. The
  `updates.dpaternina.com` infrastructure (R2 bucket, DNS, GitHub Release
  downloads) is retired. The `Update URI` headers become
  `https://wp-updates.fanxie.cloud/dpaternina/plugin-dp-core` and
  `https://wp-updates.fanxie.cloud/dpaternina/theme-dpaternina` — exactly
  `UpdateConfig::update_uri()` for each package, held there by a unit test.
- **What stays local.** Two small files in `plugins/dp-core/src/Update/`:
  `UpdateKey` (the compiled-in public key, empty until keygen) and
  `UpdateRegistration` (host, namespace, hook prefix `dpaternina`, and the two
  packages), registered on `init` from `Plugin::register()`. Auto-update
  opt-in needs no local code any more — the library's client answers
  `auto_update_theme` / `auto_update_plugin` for owned packages, so
  `docs/plan.md` Phase 2 item 4 is still satisfied.
- **Release flow.** `.github/workflows/release.yml` keeps the `theme-v*` /
  `core-v*` tag scheme and the gates-first rule (its first job still calls
  `ci.yml`), then hands off to the reusable workflow
  `fanxie-lab/wordpress-updater/.github/workflows/release.yml@main`, which
  builds, signs, verifies, uploads the ZIP, confirms the public URL resolves,
  and publishes the manifest — in that order. The `workflow_dispatch` dry run
  (build/sign/verify, publish nothing) is preserved. The library's build
  script does not know about our `const VERSION` beside the plugin/theme
  header, so the caller stamps it via the workflow's `asset_build` hook — for
  the theme's `functions.php` too, which the old script never stamped (a bug
  this migration fixes).
- **Key rotation.** The old `DP_UPDATE_SIGNING_KEY` pair is retired with its
  host; it never signed a production release, so nothing needs a break-glass
  rotation. A fresh keypair is generated with the library's
  `release.php keygen --write-to=plugins/dp-core/src/Update/UpdateKey.php`,
  run by David in his own terminal — the secret half is printed once and must
  never appear in a transcript, a CI log, or this repo. Future rotation is a
  manual release, as before (library `docs/onboarding.md` §11).

### Renames a site operator will notice

The library derives its runtime names from the `dpaternina` hook prefix, where
the old module hardcoded `dp_core`:

| | ADR-0004 module | Library |
|---|---|---|
| Refusal action | `dp_core_update_refused` | `dpaternina_update_refused` |
| Manifest transients | `dp_core_update_theme` / `dp_core_update_plugin` | `dpaternina_upd_theme_dpaternina` / `dpaternina_upd_plugin_dp-core` |
| wp-config key override | `DP_CORE_UPDATE_PUBLIC_KEY` | `DPATERNINA_UPDATE_PUBLIC_KEY` |
| Host filter | `dp_core_update_host` | none — the host is compile-time config |
| Error-log prefix | `[dp-core/update]` | `[dpaternina/update]` |
| Package URL | GitHub Release download | `https://wp-updates.fanxie.cloud/dpaternina/packages/{type}-{slug}-{version}.zip` |

The `dp_core_update_host` / `dp_core_update_package_hosts` filters are gone
without replacement on purpose: the library pins package URLs to the update
host and the package's own namespace, and staging points elsewhere by
rebuilding with different headers, not by filtering trust.

### Secrets

| Secret | What it is |
|---|---|
| `DPATERNINA_UPDATE_SIGNING_KEY` | base64 Ed25519 secret key from `release.php keygen`. Its public half must already be committed in `UpdateKey::COMPILED`. |
| `DPATERNINA_UPDATE_UPLOAD_TOKEN` | Bearer token for the `dpaternina` namespace on the `wp-updates.fanxie.cloud` write Worker. |

The five ADR-0004 secrets (`DP_UPDATE_SIGNING_KEY`, `R2_*`) are retired.

## Consequences

- Hardening fixes to the update path land once, upstream, and reach this site
  as a `composer update fanxielab/wp-update-client` — reviewed like any other
  dependency bump. The standing burden of proof for dependencies is met the
  easy way: this library replaces ~450 lines of our own PHP that it *is*.
- `dev-main` is a moving target until the library tags a release; the two
  committed lock files (root and plugin) are what actually pin it. When a tag
  exists, both requires should move to a version constraint.
- Every namespace on the shared host answers the same two hostname-derived
  core filters. The library dispatches by full Update URI path and passes
  unowned URIs through, so sibling tenants coexist — but a hand-rolled filter
  callback that matched on hostname alone would not. Don't write one.
- The plugin's `composer install` now needs network access to GitHub (through
  the committed lock) — locally, in CI, and inside the release stage.
- The reusable workflow builds on PHP 8.3; both package `composer.json` files
  pin `config.platform.php` to 8.4.0 so the staged `--no-dev` install resolves
  for the runtime the packages actually target instead of failing the
  runner's platform check.
- WordPress ≥ 6.1 and PHP ≥ 8.2 + `ext-sodium` are the library's floor; ours
  (6.6 / 8.4) is comfortably above it.
- Until David runs keygen and commits the public half, every update is refused
  and logged, and the reusable workflow refuses to build a release ZIP — the
  same three-layer guard ADR-0004 had, now enforced upstream.

## Alternatives considered

**Keep the vendored module.** It worked. But it is now the unmaintained fork of
its own descendant, and the descendant fixed real things (multi-tenant
dispatch that does not wipe sibling offers; a write Worker that refuses a
manifest whose ZIP is missing, so the publish-order guarantee no longer
depends on CI behaving).

**Git subtree / copy the library sources in.** Same drift problem with extra
steps, and no lock-file pin.

**Point the library at `updates.dpaternina.com`.** The library is
host-agnostic, but the old host's value was that we owned it end to end — and
the Worker write path, tenancy checks and namespace tokens only exist on the
Fanxie Lab instance. Running a second single-tenant instance to keep a vanity
hostname is infrastructure for sentiment. If the hostname ever matters, the
instance supports custom domains in front of the same bucket.
