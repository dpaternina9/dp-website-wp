# ADR-0001 — Phase 0 toolchain

## Status

Accepted — 2026-08-20.

## Context

Phase 0 of `docs/plan.md` asks for a floor: version control, a dependency
manifest per language, a local WordPress, and the six quality gates in
`CLAUDE.md` §1.6 all exiting 0 against an empty theme and plugin. The plan named
most of the packages. It did not settle how the pieces fit together, and a few
of its assumptions did not survive contact with the environment.

Verified environment at the time of writing: PHP 8.4.8 (host) and 8.4.24
(container), Node 22.15.1, npm 10.9.2, Composer 2.8.9, Docker 28.4.0,
WordPress **7.1** in `wp-env`, `WP_Theme_JSON::LATEST_SCHEMA === 3`.

## Decision

### 1. `dp-core` carries its own `composer.json` and its own `vendor/`

**This is a deliberate departure from Phase 0 of `docs/plan.md`,** which
describes a single root `composer.json` with PSR-4 autoloading "for both
packages". We keep the root manifest and its PSR-4 map, and we add a second,
production-only manifest inside the plugin:

```
composer.json                       dev toolchain; PSR-4 for DP\Core and DP\Theme;
                                    autoload-dev for DP\Tests\*
plugins/dp-core/composer.json       no dependencies; PSR-4 for DP\Core only
```

The root manifest's `post-install-cmd` / `post-update-cmd` run
`composer dump-autoload --working-dir=plugins/dp-core`, so the plugin's
autoloader is regenerated whenever root dependencies change and nobody has to
remember a second command.

The reason is that **the root autoloader is not reachable from where WordPress
loads the plugin.** `wp-env` mounts `plugins/dp-core` at
`wp-content/plugins/dp-core`; the repository root is not its parent inside the
container, and in production it will not exist at all — the release artefact is a
zip of the plugin directory and nothing else. A plugin that is distributed on its
own needs an autoloader it can carry with it. The alternative was a conditional
`require` in `dp-core.php` that probes for a monorepo checkout before falling
back — a runtime branch whose only purpose is to paper over a build-time
omission, and one that would silently fatal in exactly the environment we ship
to. `CLAUDE.md` §1.3 requires "PSR-4 autoloaded via Composer, no `require`
chains"; the honest way to satisfy that for a distributable artefact is to give
the artefact a Composer autoloader of its own.

The cost is one more manifest and one more `vendor/` directory. The benefit is
that Phase 2's release workflow builds the plugin zip by running Composer inside
`plugins/dp-core` — the same command developers already run — rather than
inventing a bundling step.

`plugins/dp-core/vendor/` stays **untracked**. It is generated output, and
`composer install` at the root regenerates it. The theme has no `composer.json`
yet because it has no PHP yet; Phase 1 adds one on the same pattern when it does.

### 2. Static analysis needs two packages the plan did not list

- **`php-stubs/wordpress-tests-stubs`** — `WP_UnitTestCase` lives in the
  WordPress test suite, which `wp-env` supplies inside the container and which is
  deliberately not a Composer dependency. Without symbols for it, PHPStan reports
  `class.notFound` (non-ignorable) on every integration test. Stubs are ~1 MB of
  declarations and add nothing to any runtime path. The alternative,
  `wp-phpunit/wp-phpunit`, would pull the whole suite into `vendor/` to solve a
  symbol-resolution problem.
- **`phpstan/phpstan-phpunit`** — at level 9, `get_post()` returns
  `WP_Post|null` and every property access on it is an error until the null is
  narrowed. This extension teaches PHPStan that `assertNotNull()` and
  `assertInstanceOf()` narrow. Without it, integration tests would accumulate
  casts, `@var` annotations, or ignores — all three of which `CLAUDE.md` §1.6 and
  the PHPStan output itself tell us not to write.

Both are `require-dev` and analysis-only.

### 3. PHPUnit is pinned to `^9.6`

WordPress core's own PHPUnit test suite — the one `wp-env` mounts at
`/wordpress-phpunit` — still targets PHPUnit 9.6, so the Integration suite cannot
run on 10 or 11. Since the Unit suite shares a config file and a vendor tree, the
whole project stays on 9.6.36 (which does support PHP 8.4). `yoast/phpunit-polyfills`
is what keeps the test cases forward-compatible, so the eventual move is a
version bump rather than a rewrite. Revisit when WordPress core does.

### 4. One `phpunit.xml.dist`, two suites, an environment-sensing bootstrap

PHPUnit has no per-suite bootstrap. Rather than split the config file, a single
`tests/bootstrap.php` branches on `WP_TESTS_DIR`:

- set (only true inside the `wp-env` tests container) → load the real WordPress
  test suite and `require` `dp-core.php` on `muplugins_loaded`;
- unset → Composer autoloader only; Brain Monkey does the rest.

So `composer test:unit` runs on the host and `composer test:integration` runs
`vendor/bin/phpunit` inside `tests-cli`. `composer test` runs both in that order.
Running the Integration suite on the host fails loudly with a message naming the
right command, which is the behaviour we want — an integration suite that
silently reports an empty pass is worse than one that errors.

For this to work, the `tests` environment maps the repository root to
`wp-content/dp-repo`. `wp-env` mounts only the theme and the plugin by default,
which leaves `vendor/bin/phpunit` and `tests/` unreachable from inside the
container. The mapping is scoped to the `tests` environment so the development
site (`:8888`) never serves the repository.

### 5. Everything else

- **Stackable** is installed from
  `https://downloads.wordpress.org/plugin/stackable-ultimate-gutenberg-blocks.zip`.
  `wp-env` resolves local paths, git remotes, and `.zip` URLs; a bare
  wordpress.org slug is not a source form it understands.
- **`lifecycleScripts.afterStart`** activates `dpaternina` in both environments,
  because `wp-env` installs themes but does not activate them, and an
  unactivated theme makes the e2e suite assert against Twenty Twenty-Five.
- **PHPCS** runs `WordPress` with exactly one sniff excluded,
  `WordPress.Files.FileName`, which mandates `class-foo.php` and is incompatible
  with the PSR-4 requirement in `CLAUDE.md` §1.3. Global prefixes are
  `dpaternina`, `dp_core`, `dp_site`, `dp_theme`, `DP\Core`, `DP\Theme`,
  `DP\Tests` — WPCS rejects any prefix of three characters or fewer, so bare
  `dp` is not usable as a prefix even though `wp dp …` remains the CLI namespace.
- **`typescript`** is a direct dev dependency. `@wordpress/scripts` treats it as
  an optional peer, but `@typescript-eslint` fails to load without it, so
  `npm run lint` cannot run at all until it is installed.
- **E2E targets `:8889`**, the tests environment, so a failing run cannot leave
  David's local content in a strange state. Playwright artefacts go to
  `artifacts/`, which is ignored. The e2e **global setup activates the theme
  itself** rather than trusting `wp-env start` to have done it: the WordPress
  test-suite bootstrap re-installs WordPress into the same database that site
  runs on, so `composer test` resets its active theme and every option on it. A
  suite that inherited its preconditions would pass or fail according to what ran
  before it. Establishing them in `globalSetup` makes the gates order-independent,
  which is verified — `composer test` immediately followed by
  `npm run test:e2e` is green.
- **`.nvmrc`** pins Node 22.15.1 so CI and local agree without duplicating the
  version in the workflow.
- **Conventional commits** are enforced twice: husky's `commit-msg` hook locally,
  and a `commitlint --from…--to` job on pull requests, because a hook is advice
  and CI is a gate.

## Consequences

- The six gates in `CLAUDE.md` §1.6 pass against an empty theme and plugin, and
  each one has something real to run against: Brain Monkey genuinely intercepts
  `add_action`, the integration suite genuinely boots WordPress and writes to a
  database, and Playwright genuinely loads the front end in Chromium.
- Docker is now a hard requirement for `composer test`, not just for
  `npm run env:start`. `composer test:unit` remains runnable without it.
- The `tests` site at `:8889` is **not** a stable browsing environment: any
  integration run reinstalls the database underneath it. Treat it as a fixture,
  not a preview. `:8888` is the site to look at.
- Every future e2e spec inherits the rule this exposed: **set up what you assert
  on.** Later phases must not assume seeded content is present just because
  `wp dp seed` was run at some point.
- Phase 2's release workflow must run Composer inside `plugins/dp-core` before
  zipping. That is written down here so it is not discovered during the release.
- Two `vendor/` directories exist. Both are ignored; neither is ever committed.
- The project is pinned to PHPUnit 9.6 until WordPress core moves.

## Alternatives considered

**A single root `composer.json` with no plugin manifest** — what the plan
described. Rejected because the mounted plugin and the released zip both need an
autoloader that travels with the plugin directory; see §1.

**A hand-rolled `spl_autoload_register` in `dp-core.php`** — no second manifest,
no second `vendor/`. Rejected: `CLAUDE.md` §1.3 says PSR-4 via Composer, and
hand-rolled autoloaders drift from PSR-4 in exactly the edge cases (case
sensitivity, nested namespaces) that are hardest to debug.

**`wp-phpunit/wp-phpunit` as a dev dependency, running PHPUnit on the host** —
would remove the container round trip. Rejected: it still needs a WordPress core
install and a reachable MySQL on the host, which means either a second local
WordPress or exposing `wp-env`'s database on a fixed port. `CLAUDE.md` §3 says
`wp-env` is the only supported local environment; running the tests where the
environment is, is the smaller commitment.

**Mapping the repository root into both environments** — simpler config.
Rejected: it would serve `vendor/`, `.git`, and `node_modules` from the
development site.

**Making the repository root itself the plugin** (the standard single-package
`wp-env` layout, `"mappings": { "wp-content/plugins/x": "." }`) — rejected because
this is a monorepo with a theme and a plugin that version independently.
