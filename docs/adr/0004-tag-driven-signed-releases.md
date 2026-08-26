# ADR-0004 — Tag-driven, signed releases through core's own update filters

## Status

Accepted — 2026-08-20. **Implementation superseded by ADR-0015** — 2026-08-25:
the mechanism decided here (signed-envelope manifests, compiled-in key, fail
closed, tag-driven builds) stands, but it now lives in the extracted
`fanxielab/wp-update-client` library rather than in this repo, and the update
host moved from `updates.dpaternina.com` (R2 + GitHub Releases) to
`wp-updates.fanxie.cloud`.

## Context

Phase 2 of `docs/plan.md` asks for a release pipeline built early, "because a
pipeline built at the end is a pipeline nobody trusts": a tag runs the gates,
builds a ZIP, publishes a GitHub Release, and publishes a signed manifest that
WordPress core's own `update_themes_{$hostname}` / `update_plugins_{$hostname}`
filters turn into an update offer. `CLAUDE.md` §4 makes the same commitment and
adds that there is no manual deploy path until it exists.

The plan's description of the mechanism cites the 5.8/6.1-era documentation. The
brief for this phase asked for that to be checked against the WordPress actually
in the container rather than trusted. It was, and most of it holds — but not all
of it, and the parts that do not hold are the parts that would have failed
silently in production.

Verified against **WordPress 7.1** (`$wp_version = '7.1'`, `$wp_db_version =
61833`) as mounted by `wp-env`, reading `wp-includes/update.php` and
`wp-admin/includes/class-wp-automatic-updater.php` in the running container.

### What WordPress 7.1 actually does

1. **The filter names are unchanged.** `update_plugins_{$hostname}` (line 555)
   and `update_themes_{$hostname}` (line 833) are still there, still built from
   `wp_parse_url( sanitize_url( $data['UpdateURI'] ), PHP_URL_HOST )`.

2. **They take four arguments, not three.** `false, $plugin_data, $plugin_file,
   $locales` — the fourth being the site's installed locales, for translation
   packages. The plan's summary names three. We accept three and ignore
   translations, which is correct for a proprietary package with no language
   packs, but the arity is worth knowing before someone adds one.

3. **Core does the version comparison itself.** After casting our return with
   `(object)` and requiring a `version` property, core runs
   `version_compare( $update->new_version, $data['Version'], '>' )` and files the
   offer under `$transient->response` if newer and `$transient->no_update` if
   not. **Returning an older version cannot produce an update offer**, and
   returning a non-newer offer is not a mistake — it is what populates the
   "Auto-updates enabled" column on the Plugins and Themes screens. So the client
   returns every verified manifest and lets core decide, and logs when the
   manifest is not newer rather than suppressing it.

4. **Core fills in `plugin` for plugins and does *not* fill in `theme` for
   themes.** Both paths force `$update->id` from the `Update URI` header, and the
   plugin path additionally forces `$update->plugin = $plugin_file`. The theme
   path forces nothing else. `WP_Automatic_Updater::update()` then reads
   `$item->theme` to decide what to upgrade. **A theme offer that omits `theme`
   is accepted into the transient and then fails at install time.** This is the
   single most expensive thing a naive implementation gets wrong, and it is
   encoded in `PackageType::identity_field()` with the reason attached.

5. **Themes are stored in the transient as arrays; plugins as objects.** Core
   casts the theme offer back with `(array) $update` before storing, and runs the
   plugin offers through an `array_walk` that casts to object and strips
   `translations` and `compatibility`. Anything reading those transients has to
   know which it is holding.

6. **The `Update URI` loop never runs if wordpress.org is unreachable.** Both
   `wp_update_plugins()` and `wp_update_themes()` `return` early on
   `is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code(…)`
   — *before* the loop that applies our filter. A site that cannot reach
   `api.wordpress.org` therefore gets no first-party updates either. That is
   core's behaviour, not something we can fix from a filter, and it is the reason
   the integration tests have to serve a plausible wordpress.org response before
   they can exercise anything of ours.

7. **`auto_update_{$type}` is `apply_filters( "auto_update_{$type}", $update,
   $item )`**, where `$update` may be `null` — core uses `null` to detect that
   nothing has hooked the filter at all. A pass-through must return the value it
   was given, `null` included.

## Decision

### 1. The manifest is a signed envelope, and the signature covers bytes

`updates.dpaternina.com/theme.json` and `/core.json` each contain:

```json
{ "schema": 1, "payload": "<base64 of the manifest JSON>", "signature": "<base64 detached Ed25519>" }
```

The manifest travels base64-encoded rather than as a sibling JSON object with a
`signature` field beside the data. Verifying the latter shape means re-encoding
the parsed data to recover the bytes that were signed, and any disagreement
between signer and verifier about key order, unicode escaping or float
formatting becomes either a false rejection or — much worse — a signature that
covers something other than what was parsed. With the payload carried as opaque
bytes, the bytes verified and the bytes parsed are provably identical.

A detached `.sig` file beside the manifest would have the same property at the
cost of a second HTTP request on every check.

### 2. The public key is compiled in, empty by default, and not filterable

`DP\Core\Update\PublicKey::COMPILED` ships as `''`. `php bin/dp-release.php
keygen --write` generates an Ed25519 keypair, writes the public half into that
constant for committing, and prints the secret half once for the GitHub secret.

There is **no filter** on the key. A trust anchor any other plugin can replace,
or that anyone with one `wp_options` write can replace, is not a trust anchor.
The only override is the `DP_CORE_UPDATE_PUBLIC_KEY` constant in `wp-config.php`,
which already implies filesystem access, and exists so a staging site can trust
a staging key without a second build. The manifest *host* is filterable
(`dp_core_update_host`) because it is configuration rather than trust — though
only half-filterable in practice, since core derives the filter name it calls
from the `Update URI` header, so a staging host also needs a staging build.

Shipping an empty key means every update is refused and the refusal is logged
until David runs `keygen`. That is the correct failure mode for a missing trust
anchor, and `bin/dp-build.sh` refuses to produce a release ZIP while the constant
is empty, so a keyless build cannot reach a site at all.

### 3. The cache holds the envelope, and the signature is re-checked on every read

`ManifestSource` caches the raw signed envelope in a site transient for six
hours and re-runs `sodium_crypto_sign_verify_detached()` every time it reads it.
Caching the *conclusion* instead would make the signature check a one-time
formality and turn any writable object cache — a shared Redis, a persistent-cache
plugin, one `wp_options` row — into a way to inject an update offer. Tens of
microseconds is a fair price. A cached envelope that fails verification is
replaced with a negative marker rather than left to be re-read.

Failures are cached too, for fifteen minutes. `wp_update_plugins()` runs on
`admin_init`; without a negative cache, an update host that is down means a
blocking HTTP request on every admin page load.

### 4. Fail closed, always, and never trust the incoming filter value

The hook callbacks are static array callables rather than bound closures, because
WordPress ids a closure by `spl_object_id()` — making our filters unremovable by anyone
but the object that added them, and making a duplicate registration possible. Static
callables make two clients on one filter unrepresentable, and give a site owner a line
they can actually write to switch the updater off.

Every path that does not end in a verified signature returns `false` — including
when a previous callback has already put something in `$update`. We own this
hostname; passing a stranger's array through would let any plugin on the site
hand the WordPress upgrader a package URL under our name. Refusals are announced
on the `dp_core_update_refused` action (so a test, or a future monitor, can see
them) and written to `error_log()` under `WP_DEBUG`, the way
`WP_Automatic_Updater` logs its own.

Defence in depth beyond the signature: the package URL must be HTTPS on
`github.com` or the update host; the manifest's `slug` and `type` must match the
package being checked; the version must be plain semver, so nothing exotic gets
handed to `version_compare()`; and the manifest URL is derived from the
`Update URI` header only if that header's host matches the one this build trusts.

### 5. Auto-update opt-in is scoped twice

`auto_update_theme` / `auto_update_plugin` return `true` only when the offer's
`id` is on our host **and** the item is `dpaternina` or `dp-core/dp-core.php`.
Either condition alone is insufficient. Everything else — including `core` and
`translation` offers, which have neither field — is returned untouched.

### 6. The build stages, stamps, and zips with ZipArchive

`bin/dp-build.sh` copies the package into `dist/stage/<slug>` before stamping the
version, so a build never dirties the working tree; runs `composer install
--no-dev` *inside the staged package*, as `docs/adr/0001` §1 said Phase 2 would
have to; prunes development files; and hands off to `bin/dp-release.php zip`,
which uses PHP's `ZipArchive` so that the archive's single top-level directory is
named explicitly rather than inherited from the current working directory. An
archive whose internal directory is `dp-core-1.2.3` installs *beside* the running
plugin and updates nothing, which is a failure with no symptom.

`readme.txt` is stamped if present. Neither package has one yet; adding one is a
change to files this phase does not own.

### 7. The release workflow calls `ci.yml` rather than restating the gates

`.github/workflows/release.yml` runs on `theme-v*` / `core-v*` tags and on
`workflow_dispatch`. Its first job is `uses: ./.github/workflows/ci.yml`, which
required adding `workflow_call:` to that workflow's triggers — a deliberate,
one-line edit to a file this phase did not otherwise own. The alternative was to
copy every gate step into the release workflow, where it would drift from CI, and
"the gates a release runs" quietly ceasing to mean "the gates" is exactly the
rot that makes a pipeline untrustworthy.

Publishing order is Release first, manifest second, with a `curl` between them
confirming the package URL actually resolves. A manifest that names a download
which does not exist yet is an update offer that fails on every site that takes
it. `workflow_dispatch` performs every step including signing and verification,
and stops before publishing.

Publishing uses `gh` and `aws`, both preinstalled on `ubuntu-latest`. A release
pipeline is a poor place to hand a marketplace action write access to the
repository.

### 8. Secrets David must create

| Secret | What it is |
|---|---|
| `DP_UPDATE_SIGNING_KEY` | base64 Ed25519 secret key from `php bin/dp-release.php keygen`. Its public half must already be committed in `PublicKey::COMPILED`. |
| `R2_ACCOUNT_ID` | Cloudflare account id — it is the R2 S3 endpoint hostname. |
| `R2_ACCESS_KEY_ID` | R2 API token with Object Read & Write on the bucket. |
| `R2_SECRET_ACCESS_KEY` | The matching secret. |
| `R2_BUCKET` | Bucket name served at `updates.dpaternina.com`. |

## Consequences

- A tag is the whole deploy, and the mechanism is ~450 lines of our own PHP with
  no runtime dependency beyond libsodium, which PHP has bundled since 7.2.
- Nothing updates until `keygen` has been run and its public half committed.
  That is one manual step, exactly once, and it is guarded at three places: the
  build script, the workflow, and the client itself.
- Rotating the signing key is a plugin release, because the public key ships
  inside the plugin. A compromised key means: generate a new pair, commit the new
  public half, release `dp-core` **by hand** to every site that has the old key —
  there is only one site, and the break-glass path is "upload the ZIP over
  SFTP" — and only then resume tagging. There is no revocation list and no
  second key slot; adding one is a change worth making the day there is a second
  site, and not before.
- Every update check makes one extra HTTPS request per package per six hours.
- The site still cannot get *our* updates when `api.wordpress.org` is
  unreachable, because core bails before our filter runs. Nothing in our code
  can change that.
- `plugins/dp-core/src/Update/` ships in the theme's ZIP not at all and in the
  plugin's ZIP always — the theme has no update code of its own, and `dp-core`
  being deactivated means neither package updates. That is a real coupling. It is
  the same coupling `CLAUDE.md` §2.1 already accepts by putting one release
  mechanism in the plugin for both packages.
- PHPCompatibility 9.x parses enum method bodies as plain functions and reports
  every `$this` inside one as an error. `PackageType` carries a file-level
  `phpcs:disable` for that sniff. The next enum any phase adds will need the same
  thing, which is an argument for excluding it centrally in `phpcs.xml.dist`.

## Alternatives considered

**Git Updater** — mature, and would work. Rejected for the reason the plan gives:
a fifth plugin doing work ~450 lines of ours does, plus a supply-chain surface on
the update path itself, which is the one place that surface is worst.

**rsync/SSH deploy from Actions** — simpler, and it stays documented as the
break-glass path for a key rotation. Rejected as the primary because it bypasses
the WordPress updater, has no rollback, and cannot install a plugin the site does
not already have.

**Composer + private Satis** — right if the whole of `wp-content` were
Composer-managed. It is not, and making it so is a hosting change.

**Signing the ZIP instead of the manifest** — would let WordPress verify the
artefact itself. Core has the machinery: `download_url()` takes a
`$signature_verification` flag, consults `wp_signature_hosts`, fetches
`$package_url.sig`, and checks it with `verify_file_signature()` against
`wp_trusted_keys()` — all three of which are filterable. But reading WordPress
7.1 rather than the documentation: **`WP_Upgrader::run()` calls
`download_package( $package, false, … )`**, so plugin and theme packages are
never signature-checked at all, whatever those filters say. Only `Core_Upgrader`
opts in. `wp_trusted_keys()` is also empty in 7.1 — its one key expired on 1
April 2021 and the replacement is still a `// TODO` — and `wp_signature_softfail`
defaults to `true`, so even where verification does run, failing it does not stop
the install.

Signing the manifest is therefore the only signature on this path that WordPress
will actually act on, and it is also the right one: the manifest is what an
attacker would tamper with to point the upgrader somewhere else, and signing only
the ZIP leaves the URL that names it unsigned. Publishing a `.sig` beside each
ZIP and hooking `upgrader_pre_download` to verify it ourselves is a real
hardening step available later; it is not a substitute for this one.

**A `dp_core_update_public_key` filter for testability** — rejected; see §2. The
tests inject a `ManifestSource` through `UpdateClient::register()` instead, so
testability costs nothing at runtime.

**Suppressing non-newer manifests instead of returning them** — rejected once
core's actual behaviour was read: it would remove the `no_update` entry that
makes the auto-update UI work, in exchange for nothing.
