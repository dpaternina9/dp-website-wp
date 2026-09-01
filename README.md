# dpaternina.com

Monorepo for [dpaternina.com](https://dpaternina.com): a hand-written WordPress
block theme and the companion plugin that holds its content model. One site, no
page builder, dark ground only.

```
themes/dpaternina/     block theme — theme.json, templates, parts, patterns, CSS, blocks
plugins/dp-core/       CPTs, taxonomies, meta, dynamic blocks, REST, WP-CLI, updates
design-source/         the read-only design contract (imported from Claude Design)
docs/                  plan, ADRs, going-live runbook
bin/                   token build, design baseline, seed
tests/                 Unit + Integration (PHPUnit), e2e (Playwright), js (Jest)
```

**Data lives in `dp-core`, not the theme.** Switching themes must not delete the
timeline. It ships as a normal plugin rather than an mu-plugin so one release
mechanism covers both packages.

**Everything is manageable from wp-admin.** Every URL, every word of copy, every
nav item, page and slug is set in the editor. The theme registers no page
routes, branches on no slug, and writes no copy — design-specific pages are
`dp-`-prefixed custom templates assigned from the Template dropdown. See
[ADR-0018](docs/adr/0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md).

## Requirements

| | |
|---|---|
| PHP | 8.4 (strict types, typed properties throughout) |
| WordPress | 6.6+ |
| Node | see `.nvmrc` (22.15.1) — npm 10.9+ |
| Docker | for `wp-env` |

## Local development

```sh
nvm use
composer install          # also installs plugin vendor + dumps theme autoload
npm install
npm run build             # editor JS for dp-core's blocks
npm run env:start         # http://localhost:8888  (admin / password)
npm run env:seed          # content fixture
```

`npm run env:reset` destroys, restarts and re-seeds. The seed is
`bin/seed.php` — **fix the seed, never the database**; anything you click into
the local site is gone at the next reset.

The tests environment runs alongside on port 8889 with the repo mounted at
`wp-content/dp-repo`.

Other useful commands:

```sh
npm run env:cli -- <args>   # wp-cli against the dev site, e.g. env:cli -- plugin list
npm run start               # watch mode for the block editor JS
composer tokens:build       # regenerate assets/css/tokens.css from design-source/
composer design:baseline    # refresh the design parity baseline
```

## The gates

Run only the ones your change can actually break. CI runs the full suite on the
pull request; that is what it is for.

| Changed | Run |
|---|---|
| PHP | `composer lint`, `composer analyse`, `composer test` |
| CSS or a template | `npm run lint` plus the one affected spec |
| Editor / front-end JS | `npm run test:unit` |
| Behaviour on a page | `npm run test:e2e` — once, at the end |

`composer lint` is WPCS, `composer analyse` is PHPStan at level 9, and
`composer test` is the Unit suite locally plus the Integration suite inside
`wp-env`. WCAG 2.2 AA holds on the front end.

## Deploying

Deploy is a git tag. Nothing else.

```sh
git tag theme-v1.0.1   && git push origin theme-v1.0.1
git tag plugin-v1.0.1  && git push origin plugin-v1.0.1
```

The release workflow runs every CI gate, then stages, stamps the version, zips,
signs an Ed25519 manifest, uploads to `wp-updates.fanxie.cloud` and publishes —
and the site picks the update up through the normal WordPress updater. There is
no manual upload path. `workflow_dispatch` on the Release workflow does
everything except publish, which is how to test the pipeline without minting a
tag. See [ADR-0015](docs/adr/0015-adopt-the-wp-update-client-library.md) and
[docs/wp-updates-fanxie-cloud.md](docs/wp-updates-fanxie-cloud.md).

## Documentation

| | |
|---|---|
| [docs/plan.md](docs/plan.md) | the phased implementation plan and the settled decisions |
| [docs/design-digest.md](docs/design-digest.md) | what the design actually asks for, section by section |
| [docs/going-live.md](docs/going-live.md) | the one-time wp-admin setup: pages, templates, menus, settings |
| [docs/adr/](docs/adr/) | decisions that outlive a pull request — read its README first, the bar is high |
| [design-source/](design-source/) | the read-only design contract; change it in Claude Design and re-import |
| [CLAUDE.md](CLAUDE.md) | the working rules for anyone (or anything) making changes here |

## Third-party plugins

`dp-core` is the only plugin this repo ships. Stackable, SEO (AIOSEO),
analytics (Rybbit), SMTP and the security/headers plugin are installed and
configured in wp-admin; this repo neither enqueues nor duplicates any of them,
and writes no SEO output and no HTTP headers of its own.

## A note on the copy

Every word in `design-source/` and in the seed is **placeholder**. None of it is
a claim about David, and nothing in this repo should invent one.

## License

Proprietary. All rights reserved.
