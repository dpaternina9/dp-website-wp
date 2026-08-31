# design-to-wp — design

**Date:** 2026-08-31
**Status:** Approved, ready for an implementation plan
**Deliverable:** two global skills under `~/.claude/skills/`

---

## 1. What this is

A skill that takes a website design in any source form and produces the design of
a WordPress site that its owner can run entirely from wp-admin — then stays in
force while that site is built.

It is written out of one specific project, `dpaternina.com`, whose own record
(`docs/adr/`, `docs/plan.md`, `design-source/README.md`) is unusually honest
about what went wrong. The failures that skill exists to prevent are named
throughout, with citations, because a rule with a corpse behind it survives and a
rule without one gets argued away.

**Not in scope.** The skill does not scaffold repositories, generate
`theme.json`, or write templates. It plans, it rules, and it audits. Code is
ordinary work done afterwards under the rules it wrote.

---

## 2. Decisions taken

| Question | Decision |
|---|---|
| Authority | Plan **and** govern the build. |
| Rigor | Scales to the job — the tier is chosen in step 0 and everything downstream follows it. |
| Audience | Both David's own builds and handoff to a non-technical owner; handoff-aware by default. |
| Design ingestion | Vendor verbatim, write a digest beside it, record the re-fetch command. |
| Artifacts | Separate ledgers plus one plan. |
| Governance | Rules into the project's `CLAUDE.md`, plus a named checkpoint skill run at phase boundaries. |
| Executor | Scale by tier: substantial work to a `wordpress-development-expert` subagent with a written brief; small diagnosed fixes made directly. |
| Packaging | Two skills: `design-to-wp` (spine, references, ledger templates) and `wp-manageability-audit` (the checkpoint). |

---

## 3. The nine non-negotiables

These are the skill's whole point. They are copied verbatim into the project's
`CLAUDE.md` at step 7 and are what `wp-manageability-audit` checks.

1. **Code never writes a value an author can set.** Anything computed announces
   itself visibly in the editor — a named block, a block binding, a token in a
   visible field.
2. **A field with no editor control does not get registered.**
3. **Core fields before custom meta.** Ask what WordPress already stores before
   inventing a field.
4. **Re-fetch the design; never re-summarize it.**
5. **No rewrite rules, no slug or ID branching, no hidden render-time
   rewriting.** Redirects exist only where a business requirement names them,
   and are listed individually.
6. **Anything that must survive a theme switch lives in the plugin.**
7. **The editor shows the truth.** A block rendered in PHP still exists in the
   canvas; spacing and specificity resolve the same way in both contexts.
8. **Fix the seed, never the database.**
9. **Never write an ADR for a decision being made in the same pass as its code.**

### Where each came from

- **(2)** `dpaternina.com` registered ten fields on `post` — `show_in_rest`,
  written by the seeder, read at render, reachable by nobody. ADR-0016 deleted
  all ten. ADR-0021 then shipped `dp_series_featured`, a term flag with no
  control, which made the link it governed permanently inert; superseded the next
  day.
- **(1) and (5)** ADR-0006 banned hrefs from patterns outright and wrote a test
  to enforce it — which is precisely why render-time code could overwrite an href
  set in the site editor with nobody noticing there was something to preserve.
  Superseded seven days later by ADR-0018.
- **(4)** All thirteen component files were imported without their
  `<script type="text/x-dc">` blocks, where the design computes `orgStyle`,
  `barStyle`, `headlineStyle` and the rest. Roughly half of each component's
  declared values were simply gone, and ADR-0012 then recorded those styles as
  "never reach the export" and told future phases not to look for them. They were
  in the export the whole time. Cost: three failed audits of one page.
- **(7)** All three of `dp-core`'s dynamic blocks drew as `core/missing` in the
  site editor while rendering perfectly on the front end (ADR-0009). Separately,
  `.wp-element-button` ties with core's `:root :where(.wp-element-button)`, and a
  tie breaks on load order — which differs between canvas and front end, so every
  unadorned button was teal on the site and grey in the editor. Block gap then
  added itself to eighteen home-page elements the design had already spaced.
- **(9)** Eighteen ADRs in seven days; three of them *caused* defects. Each was
  written by the party that made the decision, in the same pass, before anybody
  had used the result. What held up over the same period was five unargued
  bullets in `CLAUDE.md` — length and reasoning were not what made a rule
  survive.

---

## 4. The spine — ten steps

`SKILL.md` carries this table and the rules above. Everything else is a reference
loaded when its step begins.

### Step 0 — Intake and tier

Locate the design source and classify it: Claude Design project, Figma, HTML/CSS
export, live URL, or images only. Ask whether a site already exists (content to
migrate, URLs to preserve). Then set the **tier**, which every later step reads:

| Tier | Shape |
|---|---|
| **Brochure** | Theme, seed, a linter. No monorepo, no CI matrix, no parity harness. |
| **Standard** | Theme plus companion plugin, local env, linting and static analysis, a seed, manual audit passes. |
| **Engineered** | Monorepo, `wp-env`, CI gates, static analysis at a declared level, parity harness, tag-driven signed releases. |

The tier is a *default*, overridable per concern — a brochure site can still want
signed releases.

### Step 1 — Ingest the design as a contract

Vendor the source verbatim into a read-only `design-source/`, **including
computed-style and script blocks**. Write its `README` with: where it came from,
when, and the exact command to re-fetch. Name explicitly anything that could not
be vendored, rather than letting a digest quietly stand in for it.

`references/ingestion.md` covers each source type. Note for the Claude Design
case: `DesignSync` is main-session only and unavailable to subagents, so ingestion
never gets delegated.

### Step 2 — Design digest

A human-readable reading aid — tokens, type scale, components with their variants
and states, breakpoints, motion, every page state, and a copy inventory. The
digest is never the contract; `design-source/` is.

### Step 3 — Architecture decision

Three sub-decisions, each recorded with its reason in the decisions log:

**Theme kind.** Default is a block theme, overridden only by a named constraint:

- Editor must be constrained to a token scale → block theme
  (`theme.json` + `allowed_block_types_all`); a classic theme cannot do this properly.
- Owner needs to edit header, footer or templates visually → block theme.
- Project depends on a page builder or a classic-only plugin → classic with block support.
- Extending an existing classic theme, or heavy legacy PHP templates → hybrid:
  classic plus `theme.json` plus block templates for selected views.

**Companion plugin.** Decided by rule 6, not by taste: anything that must survive
a theme switch — post types, taxonomies, meta, dynamic blocks, REST routes, CLI —
goes in the plugin. Presentation goes in the theme. A plugin ships as a normal
plugin, not an mu-plugin, so one update mechanism covers everything.

**Boundaries and identity.** What the build owns versus what the site owner
installs (SEO, forms, analytics, security headers, caching — duplicating these is
a standing own-goal). Then theme slug, plugin slug, text domain, block prefix,
and the custom-template prefix. The prefix matters: it is what stops the template
hierarchy binding a custom template to a page slug behind your back.

### Step 4 — Content-model ledger *(confirm with the user)*

Every content type and every field. Per field, in order: does WordPress already
store this? If not, what is the editor control? A field with no answer to the
second question is not registered.

`references/content-model.md` carries the control patterns: `core/post-meta`
block bindings for presentational fields (no JavaScript), small `inserter: false`
blocks for booleans, enums, lists and multi-line text, post-reference pickers
instead of typing IDs, a document sidebar panel generated from the REST schema
where a page must keep its canvas, and `register_post_type()`'s `template` plus
`template_lock` where a type should open as a locked form. It also carries the
trap: `use_block_editor_for_post_type()` returns false without `editor` in
`supports`, so a type without it silently opens the classic editor, whose only
offer for a registered field is the raw custom-fields table.

### Step 5 — Template ledger *(confirm with the user)*

Per template: name, where it sits in the hierarchy, how it is assigned from the
admin, which parts it uses, which blocks compose it, which fields it reads, what
is structural versus authorable, and its locking policy.

### Step 6 — Editability ledger *(confirm with the user)*

The core artifact. Every string, URL, image, icon, list and number in the design
gets a row naming its admin home: post content, site option or customizer setting,
nav menu, `core/site-logo`, a meta field, a template part, a pattern default, or
**static by agreement** — an explicit, listed exception rather than an oversight.
Business-required redirects are listed here too, individually, as the only URL
logic in the build.

### Step 7 — Rules and `CLAUDE.md`

Ask for the project's development rules. Write `CLAUDE.md` if there is none;
amend rather than replace if there is. It carries the nine non-negotiables, the
gates ledger (which check runs for which kind of change — running everything every
time is how gates get skipped), the executor rule, and the accessibility, PHP,
standards and i18n targets. Accessibility scope is the public site; admin screens
are out of scope unless the user says otherwise.

### Step 8 — Environment, seed, release

Local environment. A seed script that produces a *navigable* site — pages created,
templates assigned, menus built, logo set, front page set — idempotent and
re-runnable. Then distribution, asked here and built early:

- `fanxielab/wp-update-client` against `wp-updates.fanxie.cloud` — signed-envelope
  manifests, compiled-in Ed25519 public key, fail closed, tag-driven builds, one
  instance serving multiple sites
- the wordpress.org repository
- a manual zip
- git-based deploy

### Step 9 — Plan and handoff

Hand to `superpowers:writing-plans`. Recommended phase order: foundation →
tokens and `theme.json` → **release pipeline** → content model → house style and
blocks → chrome → templates → seed → accessibility and performance → handoff.
Release sits third deliberately: a pipeline built at the end is a pipeline nobody
trusts.

Every phase's exit criteria include a `wp-manageability-audit` run. Handoff
produces an editor-facing document and an admin walkthrough, and the walkthrough
is an acceptance criterion, not a courtesy.

---

## 5. `wp-manageability-audit`

Its own skill so it can be invoked at any phase boundary, months later, without
re-entering the planning workflow — and so the project's `CLAUDE.md` can cite it
by name. Seven checks:

1. **Field/control parity** — enumerate registered meta, post types and
   taxonomies; match each to a reachable editor control. Unmatched is a failure.
2. **Hardcoded values** — grep for literal `href`s, literal copy the editability
   ledger marks authorable, `add_rewrite_rule`, `is_page(` with an argument,
   `get_page_by_path`, and `templates/page-<slug>.html`.
3. **Ledger coverage** — every editability-ledger row names an admin home, and
   that home exists.
4. **Editor parity** — no block draws as `core/missing` in the canvas; spacing
   and button styling spot-checked in both contexts.
5. **Fresh-seed navigability** — reset, seed, crawl from the home page: every nav
   destination resolves, front page set, logo set, custom templates assigned.
6. **Chrome is content** — logo is `core/site-logo`, navigations are real
   `core/navigation` blocks with distinct refs, the footer is not forced to
   mirror the header.
7. **Handoff sanity** — templates and blocks carry names a non-developer can act on.

Tier-scaled: on the engineered tier these become CI tests; on lighter tiers they
run as a manual pass whose result is written down.

---

## 6. File layout

```
~/.claude/skills/design-to-wp/
  SKILL.md                    spine: ten steps, nine rules, which reference to load when
  references/
    ingestion.md              per source type; what "verbatim" means; re-fetch recording
    architecture.md           theme-kind aid; theme/plugin boundary; third-party boundary; identity
    content-model.md          core-fields-first; when a CPT is justified; control patterns and traps
    templates.md              hierarchy mapping, prefixing, parts, locking, patterns as default content
    editability.md            the audit-table method and the categories of admin home
    editor-parity.md          the known traps, with their fixes
    env-seed-release.md       local env, idempotent seed, distribution options
    handoff.md                editor-facing doc, plain-language naming, admin walkthrough
  templates/
    design-digest.md
    content-model-ledger.md
    template-ledger.md
    editability-ledger.md
    decisions-log.md
    CLAUDE.md.template

~/.claude/skills/wp-manageability-audit/
  SKILL.md
```

Digest and ledgers live in the project's `docs/`. Spec and plan go to
`docs/superpowers/specs/`. If superpowers is not installed, the skill says so,
points at the install, and continues with its own plan template.

---

## 7. Interfaces

- **`design-to-wp` → the user.** Three explicit confirmation gates (steps 4, 5, 6)
  plus the architecture decision at step 3. Nothing downstream proceeds on an
  unconfirmed ledger.
- **`design-to-wp` → `superpowers:writing-plans`.** Hands over the ledgers, the
  decisions log and the phase order. The plan is writing-plans' output, not this
  skill's.
- **`design-to-wp` → `CLAUDE.md`.** The only durable channel into every future
  session. It carries the nine rules and cites `wp-manageability-audit` by name.
- **`wp-manageability-audit` → anyone.** Takes a project root and a tier; returns
  a pass/fail per check with the specific offending file, field or element.
- **The project's `CLAUDE.md` → the executor rule.** Substantial work goes to a
  `wordpress-development-expert` subagent with a brief naming files owned and
  acceptance criteria; a small diagnosed fix is made directly. Dispatching a fresh
  context and a full verification pass for a one-line CSS change is the failure
  mode, not the discipline.

---

## 8. Testing the skills themselves

Skills are prose, so verification is behavioural: run `design-to-wp` against two
fixtures and check the outputs.

1. **A vendored HTML/CSS design, no existing site.** Expect: tier chosen,
   `design-source/` vendored with script blocks intact, all three ledgers,
   a `CLAUDE.md` carrying the nine rules, and a plan whose phase three is release.
2. **A design whose source cannot be vendored (images only), plus an existing
   site to migrate.** Expect: the un-vendorable source named as such rather than
   papered over, a migration and URL-preservation decision, and redirects listed
   individually in the editability ledger.

Then run `wp-manageability-audit` against this repository, whose known-good state
should pass all seven checks, and against a deliberately broken copy — a meta
field with its control removed — which must fail check 1 and name the field.
