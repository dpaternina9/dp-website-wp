# design-to-wp Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build two global skills — `design-to-wp`, which turns any website design source into the plan for a WordPress site its owner can run entirely from wp-admin, and `wp-manageability-audit`, which checks that promise at every phase boundary.

**Architecture:** Prose skills under `~/.claude/skills/`, built by RED-GREEN-REFACTOR per `superpowers:writing-skills`: run a fixture scenario against a subagent *without* the skill, record the verbatim failure, write the minimal skill text that addresses that failure, re-run, close loopholes. `design-to-wp/SKILL.md` is a short spine (ten steps, nine rules, a reference-loading table); each reference file and ledger template is added by the task whose baseline failure demands it. `wp-manageability-audit` is a separate skill so it can be invoked at a phase boundary months later and cited by name from a project's `CLAUDE.md`.

**Tech Stack:** Markdown skills with YAML frontmatter; `~/.claude` git repo (allowlist `.gitignore`); subagents as the test harness.

**Spec:** `docs/superpowers/specs/2026-08-31-design-to-wp-design.md` — read it before Task 1. The plan argues from it and copies values out of it verbatim.

## Global Constraints

- **Skills live at** `~/.claude/skills/design-to-wp/` and `~/.claude/skills/wp-manageability-audit/`.
- **`~/.claude` is a git repo with an allowlist `.gitignore`.** Lines 19–23 read `!/skills/`, `/skills/*`, then one `!/skills/<name>/` per tracked skill. A new skill is invisible to git until its line is added. Every commit in this plan is made in `~/.claude`, not in the WordPress repo.
- **Frontmatter:** two required fields, `name` and `description`, max 1024 characters total. `name` is letters, numbers and hyphens only. `description` is third person, starts with "Use when…", states **only triggering conditions** — it must never summarize the workflow, or agents follow the description instead of reading the skill.
- **The Iron Law:** no skill text before a recorded failing baseline. This applies to edits as well as new files. Text written before its baseline gets deleted, not adapted.
- **The nine non-negotiables** are spec §3. They are copied **verbatim**, not paraphrased, everywhere they appear: `SKILL.md`, `templates/CLAUDE.md.template`, and the audit skill's checks.
- **Word budget:** `design-to-wp/SKILL.md` under 500 words of prose outside its tables — it is the always-loaded part. Reference files carry the detail.
- **Cross-references** use skill names with explicit markers (`**REQUIRED SUB-SKILL:** Use superpowers:writing-plans`). Never `@`-links — they force-load and burn context.
- **Fixtures live at** `~/.claude/skills/design-to-wp/tests/fixtures/` and are committed with the skill, so every later edit can be re-tested.
- **Subagent dispatch is required** for every RED and GREEN step. Baseline subagents must be given the fixture and the task with **no mention of these skills**.

---

### Task 1: Fixtures and the recorded baseline

The RED phase for the whole `design-to-wp` skill. Nothing else in this plan may be written until this task's output exists, because the skill's job is to fix *these specific* failures, not the ones we imagine.

**Files:**
- Create: `~/.claude/skills/design-to-wp/tests/fixtures/nimbus/index.html`
- Create: `~/.claude/skills/design-to-wp/tests/fixtures/nimbus/tokens.css`
- Create: `~/.claude/skills/design-to-wp/tests/fixtures/nimbus/components.html`
- Create: `~/.claude/skills/design-to-wp/tests/fixtures/harbor/brief.md`
- Create: `~/.claude/skills/design-to-wp/tests/baseline.md`

**Interfaces:**
- Produces: `nimbus/` — a vendorable HTML design used by Tasks 2, 4, 5, 6, 7, 9, 10. `harbor/` — a non-vendorable design with an existing site, used by Tasks 3 and 6. `baseline.md` — the verbatim failure record every later task cites when justifying its text.

- [ ] **Step 1: Write the `nimbus` fixture — the design source**

`~/.claude/skills/design-to-wp/tests/fixtures/nimbus/index.html`:

```html
<!doctype html>
<title>Nimbus Consulting</title>
<link rel="stylesheet" href="tokens.css">
<header class="site-header">
  <a class="brand" href="/"><img src="logo.svg" alt="Nimbus"></a>
  <nav><a href="/services">Services</a><a href="/team">Team</a><a href="/notes">Notes</a><a href="/contact">Contact</a></nav>
</header>
<main>
  <section class="hero">
    <h1>Clarity, on a deadline.</h1>
    <p class="deck">We help operations teams make decisions they can defend.</p>
    <a class="btn" href="/contact">Start a conversation</a>
  </section>
  <section class="services">
    <h2>What we do</h2>
    <article class="card"><h3>Diagnostics</h3><p>Two weeks, one honest answer.</p></article>
    <article class="card"><h3>Interim leadership</h3><p>A steady hand while you hire.</p></article>
    <article class="card"><h3>Board advisory</h3><p>Papers that survive the room.</p></article>
  </section>
  <section class="team">
    <h2>Who you get</h2>
    <article class="person">
      <img src="ana.jpg" alt=""><h3>Ana Ruiz</h3><p class="role">Managing partner</p>
      <p class="bio">Fifteen years in operations turnaround.</p>
      <a href="https://linkedin.com/in/example">LinkedIn</a>
    </article>
    <article class="person">
      <img src="tom.jpg" alt=""><h3>Tom Behar</h3><p class="role">Principal</p>
      <p class="bio">Ex-CFO. Reads a cap table for fun.</p>
      <a href="https://linkedin.com/in/example2">LinkedIn</a>
    </article>
  </section>
  <section class="notes">
    <h2>Recent notes</h2>
    <article class="note"><h3>The two-week diagnostic</h3><time datetime="2026-06-02">2 June 2026</time><p>Why a fortnight is the right box.</p></article>
    <article class="note"><h3>Hiring an interim</h3><time datetime="2026-05-11">11 May 2026</time><p>What to ask in the first hour.</p></article>
  </section>
</main>
<footer>
  <p>Nimbus Consulting Ltd · 4 Cross Street, Manchester M2 7AQ · <a href="mailto:hello@nimbus.example">hello@nimbus.example</a></p>
  <nav><a href="/privacy">Privacy</a><a href="/terms">Terms</a></nav>
  <p class="social"><a href="https://linkedin.com/company/example">LinkedIn</a><a href="https://example.com/rss">RSS</a></p>
</footer>
```

`~/.claude/skills/design-to-wp/tests/fixtures/nimbus/tokens.css`:

```css
:root {
  --nb-ink: #10151c;
  --nb-paper: #fbfaf7;
  --nb-accent: #1f6f5c;
  --nb-muted: #5d6b7a;
  --nb-space-1: 4px;
  --nb-space-2: 8px;
  --nb-space-3: 16px;
  --nb-space-4: 24px;
  --nb-space-5: 40px;
  --nb-text-sm: 0.875rem;
  --nb-text-base: 1rem;
  --nb-text-lg: 1.25rem;
  --nb-text-xl: clamp(1.75rem, 4vw, 2.75rem);
  --nb-radius: 6px;
  --nb-font-body: "Source Sans 3", system-ui, sans-serif;
  --nb-font-display: "Fraunces", Georgia, serif;
}
```

`~/.claude/skills/design-to-wp/tests/fixtures/nimbus/components.html` — the trap that makes this fixture worth having. It carries a computed-style block of the kind the `dpaternina.com` import dropped:

```html
<article class="card" data-component="ServiceCard">
  <h3 data-bind="title"></h3>
  <p data-bind="summary"></p>
</article>
<script type="text/x-dc">
  // Computed styles. An importer that "keeps the markup and the meaning"
  // and drops this block loses half of what the component declares.
  const cardStyle = (i) => ({
    borderTop: `3px solid color-mix(in oklab, var(--nb-accent) ${60 + i * 20}%, white)`,
    padding: "var(--nb-space-4)",
    borderRadius: "var(--nb-radius)",
  });
  const roleStyle = { font: "var(--nb-text-sm)/1.4 var(--nb-font-body)", color: "var(--nb-muted)", textTransform: "uppercase", letterSpacing: "0.08em" };
</script>
```

- [ ] **Step 2: Write the `harbor` fixture — the hard case**

`~/.claude/skills/design-to-wp/tests/fixtures/harbor/brief.md`:

```markdown
# Harbor Marine — redesign brief

The design exists only as four Figma frames the client will not export:
Home, Fleet listing, Fleet detail, Contact. We have viewing access, no
dev-mode seat, no token export.

The current site is WordPress, 6 years old, ~180 posts and 40 "vessel"
entries built with a page builder. Every URL is indexed and the client
will not accept broken links.

One business requirement: `/charter` must redirect to `/fleet`, because a
2024 print campaign put `/charter` on 20,000 leaflets.

The client's office manager updates the site. She is not technical.
```

- [ ] **Step 3: Dispatch the baseline subagent for `nimbus` — no skill**

Dispatch a general-purpose subagent with **exactly** this prompt, and with no skill named:

```
Read the design at ~/.claude/skills/design-to-wp/tests/fixtures/nimbus/
(index.html, tokens.css, components.html).

Plan how to turn this into a WordPress site that the client can manage
themselves from wp-admin. Produce whatever planning documents you think
the job needs. Do not write theme or plugin code.
```

- [ ] **Step 4: Dispatch the baseline subagent for `harbor` — no skill**

Same, with:

```
Read ~/.claude/skills/design-to-wp/tests/fixtures/harbor/brief.md and plan
how to rebuild this site in WordPress so the client can manage it from
wp-admin. Produce whatever planning documents you think the job needs.
Do not write theme or plugin code.
```

- [ ] **Step 5: Record the baseline verbatim**

Write `~/.claude/skills/design-to-wp/tests/baseline.md`. For each run, record what the agent **actually did**, quoting it. The spec predicts these failures; record whether each occurred, and quote the wording:

| # | Predicted failure | Occurred? | Verbatim quote |
|---|---|---|---|
| 1 | Invented values the author should set (phone, address, social URLs hardcoded in a template) | | |
| 2 | Proposed a field with no editor control | | |
| 3 | Invented meta for something WordPress already stores (`post_date`, `excerpt`, featured image, author) | | |
| 4 | Summarized the design instead of vendoring it; dropped `components.html`'s `<script type="text/x-dc">` block | | |
| 5 | Proposed slug branching, a rewrite rule, or `is_page('team')` | | |
| 6 | Put CPTs in the theme, or never asked theme-vs-plugin | | |
| 7 | Never mentioned editor/front-end parity | | |
| 8 | No seed; setup described as manual admin clicking | | |
| 9 | Never asked about distribution/updates | | |
| 10 | (harbor) Papered over the un-exportable Figma source | | |
| 11 | (harbor) Missed URL preservation, or invented redirect logic beyond `/charter` | | |
| 12 | (harbor) Ignored the non-technical office manager | | |

Add a row for any failure not predicted. **A predicted failure that did not occur is a row the skill does not need to spend words on** — note it and let the later task drop that guidance.

- [ ] **Step 6: Commit**

```bash
cd ~/.claude
# .gitignore is an allowlist — the skill is invisible to git without this line
printf '!/skills/design-to-wp/\n' >> .gitignore
git add .gitignore skills/design-to-wp/tests/
git commit -m "test(design-to-wp): fixtures and recorded baseline

Two fixtures: nimbus (vendorable HTML + a computed-style block an
importer will drop) and harbor (un-exportable Figma, existing site,
one business-required redirect, non-technical owner).

baseline.md records what a subagent does with each of them and no
skill. Every later task cites a row in it."
```

---

### Task 2: The spine — `design-to-wp/SKILL.md`

GREEN for the workflow itself: an agent that reads only this file should run the ten steps in order and stop at the step-4 confirmation gate.

**Files:**
- Create: `~/.claude/skills/design-to-wp/SKILL.md`

**Interfaces:**
- Consumes: `tests/baseline.md` rows 1–9.
- Produces: the reference-loading table every later task adds a row to; the nine rules that `templates/CLAUDE.md.template` (Task 10) and `wp-manageability-audit` (Task 11) both cite.

- [ ] **Step 1: Write the frontmatter**

Triggering conditions only — no workflow summary, or agents will follow the description and skip the file:

```yaml
---
name: design-to-wp
description: Use when turning a website design into a WordPress site — a Figma file, a Claude Design project, an HTML/CSS export, a live URL or a set of screenshots — and the result has to be fully manageable from wp-admin by its owner. Also use when planning a WordPress theme or companion plugin from a design, deciding block theme versus classic, or auditing whether a design's content is actually editable.
---
```

- [ ] **Step 2: Write the body**

Structure, in this order:

1. `# design-to-wp` and a two-sentence overview.
2. `## The nine non-negotiables` — copied **verbatim** from spec §3, numbered 1–9, no commentary. These are the whole point of the skill and they lead.
3. `## The ten steps` — the table from spec §4, one row per step: number, name, output, and **which reference to load**. Mark steps 4, 5 and 6 `confirm with the user`.
4. `## Tier` — the three-row table from spec §4 step 0 (brochure / standard / engineered) and the sentence that the tier is a default, overridable per concern.
5. `## Handoff` — `**REQUIRED SUB-SKILL:** Use superpowers:writing-plans` at step 9. If superpowers is absent, say so, point at the install, and continue with `templates/plan-fallback.md`.
6. `## Red flags — stop` — a list drawn from the *observed* baseline rows, each phrased as the thought an agent has just before the failure. Draft, to be trimmed to what Task 1 actually saw:

```markdown
## Red flags — stop

- "I'll note the phone number and address in the template for now" → rule 1
- "I'll register a field for the publish date" → rule 3
- "The design's meaning is captured in my digest" → rule 4
- "I'll check `is_page('team')` to load the right layout" → rule 5
- "The CPT can live in the theme, it's a one-theme site" → rule 6
- "It renders correctly on the front end" → rule 7
- "I'll set the menus up in the admin afterwards" → rule 8

Every one of these means: re-read the rule it points at.
```

- [ ] **Step 3: Verify word budget**

```bash
wc -w ~/.claude/skills/design-to-wp/SKILL.md
```

Expected: prose outside tables under 500 words. Over budget means detail belongs in a reference file.

- [ ] **Step 4: Re-run the `nimbus` scenario WITH the skill**

Dispatch a fresh subagent:

```
Use the design-to-wp skill to plan a WordPress site from the design at
~/.claude/skills/design-to-wp/tests/fixtures/nimbus/.
```

Expected: it classifies the source, sets a tier, vendors the design, and **stops at step 4 to confirm the content-model ledger with you** rather than running to a finished plan. Baseline rows 1, 5 and 6 must not recur.

- [ ] **Step 5: Record the result and close loopholes**

Append a `## Run 2 — spine present` section to `tests/baseline.md` with what changed and any *new* rationalization. A new rationalization gets an explicit counter in the red-flags list; then re-run step 4 once.

- [ ] **Step 6: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/SKILL.md skills/design-to-wp/tests/baseline.md
git commit -m "feat(design-to-wp): the spine — ten steps and nine rules

Nine non-negotiables verbatim from the spec, ten steps with their
reference files and the three confirmation gates, the tier table, and
a red-flags list built from the rows baseline.md actually recorded."
```

---

### Task 3: Ingestion — `references/ingestion.md`

Closes baseline rows 4 and 10: the design gets summarized instead of vendored, and an un-exportable source gets papered over.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/ingestion.md`
- Create: `~/.claude/skills/design-to-wp/templates/design-digest.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — steps 1 and 2 point at these

**Interfaces:**
- Consumes: `SKILL.md`'s reference-loading table.
- Produces: `design-source/README.md` and `docs/design-digest.md` in a target project.

- [ ] **Step 1: Write `references/ingestion.md`**

Content, in order:

- **The rule, first line:** *Re-fetch, do not re-summarize.* Then the evidence, because a rule with a corpse behind it survives: all thirteen `dpaternina.com` component files were imported without their `<script type="text/x-dc">` blocks, where the design computed `orgStyle`, `barStyle`, `headlineStyle` and the rest; roughly half of each component's declared values were lost; an ADR then recorded those styles as "never reach the export" and told future phases not to look for them. Cost: three failed audits of one page.
- **What "verbatim" means:** every file the source will give you, byte for byte, including script blocks, comments and unused variants. You may add files (`README.md`, a `*.logic.js` extraction); you may not edit or trim one.
- **Per source type** — for each: how to fetch, what is vendorable, what is not, and what to write in the README.

| Source | Fetch | Vendorable | Watch for |
|---|---|---|---|
| Claude Design project | `DesignSync` MCP — **main session only, never a subagent** | Everything, including `<script type="text/x-dc">` | Components exported without their script block |
| Figma, dev-mode seat | Export frames + variables | Markup approximation + tokens | Auto-layout ≠ the CSS you will write |
| Figma, view-only | Nothing | **Nothing** | Say so in the README; do not let the digest stand in silently |
| HTML/CSS export | Copy the tree | Everything | Inline `data:` URIs; extract to real files and note the substitution |
| Live URL | Fetch HTML + stylesheets + fonts | Rendered markup | This is the *built* site, not the design — note it |
| Screenshots only | Copy the images | The images | Every value is inferred; the digest is explicitly an inference |

- **When a source cannot be vendored:** write the README anyway, with a `## Not vendored` section naming the source, why, who can grant access, and what the digest is therefore inferring rather than recording. Never let absence be silent.
- **The README's required sections:** where it came from, when, the exact re-fetch command, `## Not vendored`, and `## Import fidelity notes` for every substitution made.
- **Read-only:** `design-source/` is the contract. Changes go back to the design tool and get re-imported.

- [ ] **Step 2: Write `templates/design-digest.md`**

A skeleton with `<!-- fill -->` markers and a header stating in its own first line that the digest is a reading aid and `design-source/` is the contract. Sections: tokens (colour, type scale, spacing, radii, motion), font stack and hosting plan, components with variants and states, breakpoints, page states, copy inventory, and `## Inferred, not recorded` for anything not vendored.

- [ ] **Step 3: Run the `harbor` scenario WITH the skill**

```
Use the design-to-wp skill to plan a WordPress site from the brief at
~/.claude/skills/design-to-wp/tests/fixtures/harbor/brief.md.
```

Expected: the agent states plainly that the Figma frames cannot be vendored, writes the `## Not vendored` section, and marks the digest as inference — rather than producing a confident digest that reads like a record. Baseline row 10 must not recur.

- [ ] **Step 4: Run the `nimbus` scenario and check the script block survived**

```bash
diff -r ~/.claude/skills/design-to-wp/tests/fixtures/nimbus/ <project>/design-source/
```

Expected: no differences other than added files. Baseline row 4 must not recur.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/ingestion.md skills/design-to-wp/templates/design-digest.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): ingestion — vendor verbatim, name what you cannot

Per-source fetch table, what verbatim means, and the required
'Not vendored' section so an un-exportable source is stated rather
than quietly replaced by a digest."
```

---

### Task 4: Architecture — `references/architecture.md`

Closes baseline row 6: theme-versus-plugin decided by taste, or never asked.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/architecture.md`
- Create: `~/.claude/skills/design-to-wp/templates/decisions-log.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — step 3 points at these

**Interfaces:**
- Consumes: the tier from step 0.
- Produces: `docs/decisions-log.md`, whose theme-kind and plugin rows Tasks 5, 6 and 9 read.

- [ ] **Step 1: Write `references/architecture.md`**

Three sub-decisions, each with its rule and its recording requirement.

**Theme kind.** Default block theme; overridden only by a *named* constraint:

| Constraint | Choice |
|---|---|
| Editor must be constrained to a token scale | Block theme — `theme.json` + `allowed_block_types_all`. A classic theme cannot do this properly. |
| Owner needs to edit header, footer, templates visually | Block theme |
| Depends on a page builder or a classic-only plugin | Classic with block support |
| Extending an existing classic theme, or heavy legacy PHP templates | Hybrid — classic + `theme.json` + block templates for selected views |

"It's a small site" is not a named constraint.

**Companion plugin.** Rule 6, applied mechanically: *anything that must survive a theme switch* — post types, taxonomies, registered meta, dynamic blocks, REST routes, CLI commands — goes in the plugin. Presentation goes in the theme. Ship a normal plugin, not an mu-plugin: mu-plugins cannot be updated by the WordPress updater, and you want one update mechanism for both. If nothing survives a theme switch, there is no plugin — say so explicitly rather than creating an empty one.

**Boundaries and identity.** What the build owns versus what the owner installs — SEO, forms, analytics, security headers, caching. Duplicating any of these is a standing own-goal; name the boundary in the log. Then: theme slug, plugin slug, text domain, block prefix, and the **custom-template prefix**. The prefix is what stops the template hierarchy binding a custom template to a page slug behind your back — `dp-work` is a template you assign; `page-work.html` is a template WordPress assigns for you, which is rule 5 by the back door.

- [ ] **Step 2: Write `templates/decisions-log.md`**

One table: `Decision | Choice | Reason | Date`. A `Reason` cell reading "simplest" or "standard" is not a reason — the log exists so the decision is not relitigated at phase 6.

- [ ] **Step 3: Run the `nimbus` scenario, stopping after step 3**

Expected: block theme chosen with a named reason; team members and notes identified as plugin-owned if they become custom types; the identity block filled in with a real prefix. Baseline row 6 must not recur.

- [ ] **Step 4: Adversarial re-run**

Dispatch a fresh subagent with the same task plus pressure:

```
Also: the client's budget is small and they want this in a week. Keep
the architecture as simple as you possibly can.
```

Expected: the agent may choose a leaner tier, but must **not** move surviving data into the theme or skip the decisions log. If it does, the rule is not binding — add the rationalization to `architecture.md` as an explicit counter and re-run.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/architecture.md skills/design-to-wp/templates/decisions-log.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): architecture — theme kind, plugin boundary, identity

Block theme by default, overridden only by a named constraint. Plugin
membership decided by what survives a theme switch, not by taste.
Custom-template prefixing recorded as the thing that keeps the
hierarchy from assigning templates behind your back."
```

---

### Task 5: Content model — `references/content-model.md`

Closes baseline rows 2 and 3, the two most expensive failures in the source project.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/content-model.md`
- Create: `~/.claude/skills/design-to-wp/templates/content-model-ledger.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — step 4 points at these

**Interfaces:**
- Consumes: the plugin decision from Task 4.
- Produces: `docs/content-model-ledger.md`, read by Tasks 6, 7 and by `wp-manageability-audit` check 1.

- [ ] **Step 1: Write `references/content-model.md`**

- **The two questions, in this order, for every field:** *Does WordPress already store this?* Then: *What is the editor control?* No answer to the second means the field is not registered.
- **The evidence:** `dpaternina.com` registered ten fields on `post` — `show_in_rest`, written by the seeder, read at render, reachable by hand from nowhere. ADR-0016 deleted all ten, because a post already knew what they held. ADR-0021 then shipped `dp_series_featured`, a term flag with no control, which made the link it governed permanently inert; superseded the next day.
- **What WordPress already stores** — check this list before inventing anything: title, content, excerpt (the deck), author, publish date and modified date, featured image, menu order, parent, status, slug, comment state; plus taxonomies for anything an editor will filter or group by, and attachment metadata (caption, alt, description) for images.
- **When a CPT is justified:** it needs its own archive, its own capabilities, its own admin list, or it must not appear in the blog's loops. "It's a different kind of thing" is not enough — a taxonomy term or a page often is the answer.
- **Control patterns**, cheapest first:

| Field shape | Control |
|---|---|
| Presentational text or image on the front end | `core/post-meta` block binding on `core/paragraph`, `core/heading`, `core/image` — no JavaScript |
| Boolean, enum, list, decimal, multi-line text | A small `inserter: false` block, one per shape |
| Reference to another post | A search-by-name picker, never a typed post ID |
| Structured data on a post that keeps its canvas | A document sidebar panel generated from the REST schema, so labels match what was registered |
| A type that should open as a fixed form | `register_post_type()`'s `template` plus `template_lock => 'all'` |

- **The trap, stated as a trap:** `use_block_editor_for_post_type()` returns false without `editor` in `supports`. A type without it opens in the *classic* editor, whose only offer for a registered field is the raw custom-fields table — so the type looks registered, looks fine, and is unmanageable. All three of `dpaternina.com`'s custom types shipped this way.
- **Sanitization:** `sanitize_textarea_field()` strips the line breaks out of rich text; pick the sanitizer from the control, not by habit. Data passed unslashed to `wp_insert_post()` silently eats a backslash out of every block attribute containing a quotation mark.

- [ ] **Step 2: Write `templates/content-model-ledger.md`**

Two tables. Types: `Type | Theme or plugin | Why not a page/taxonomy | Archive? | Supports`. Fields, with **every column required**: `Field | Type | Already in WP? | Editor control | Sanitizer | Read by`. An empty `Editor control` cell is a failure, not a to-do.

- [ ] **Step 3: Run the `nimbus` scenario through step 4**

Expected, checked row by row:
- The note's date is `post_date`, its summary is the excerpt, its heading is the title — **no** registered fields. (Baseline row 3.)
- A team member's `role` and `bio` get real controls; `bio` is not sanitized with `sanitize_text_field()`.
- The LinkedIn URL on a person is a field with a control, not a hardcoded href.
- Every field row has a filled `Editor control` cell. (Baseline row 2.)

- [ ] **Step 4: Adversarial re-run**

```
The client also wants a "featured" flag on team members so one appears
first on the home page. Add it.
```

Expected: the agent gives the flag a real control, or uses `menu_order` and says so. A flag with no UI is exactly ADR-0021 and must not recur. If it does, add the rationalization verbatim to `content-model.md` and re-run.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/content-model.md skills/design-to-wp/templates/content-model-ledger.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): content model — core fields first, every field has a control

The two questions in order, the list of what WordPress already stores,
the control patterns cheapest first, and the supports => editor trap
that opened three custom types in the classic editor."
```

---

### Task 6: Templates — `references/templates.md`

Closes baseline row 5: slug branching and rewrite rules.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/templates.md`
- Create: `~/.claude/skills/design-to-wp/templates/template-ledger.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — step 5 points at these

**Interfaces:**
- Consumes: the theme kind and the custom-template prefix from Task 4.
- Produces: `docs/template-ledger.md`, read by Task 7 and by `wp-manageability-audit` check 2.

- [ ] **Step 1: Write `references/templates.md`**

- **Hierarchy mapping**: which of `index`, `home`, `front-page`, `single`, `page`, `archive`, `taxonomy`, `search`, `404` a design state maps to — and when it is none of them, a **prefixed custom template** declared in `theme.json` `customTemplates` with a human title, assigned from the admin.
- **Rule 5, made concrete.** These are the four things that put routing back in code, with what to do instead:

| Instead of | Do |
|---|---|
| `add_rewrite_rule()` | Nothing. If a URL shape is genuinely required, it is a business requirement and gets recorded in the editability ledger. |
| `is_page('team')` | A custom template the author assigns |
| `get_page_by_path('contact')` | A nav menu item, or a link the author sets |
| `templates/page-team.html` | `templates/nb-team.html` in `customTemplates` |

- **Template parts** for anything appearing on more than one template; the chrome is parts, not repeated markup.
- **Locking policy**, chosen explicitly and recorded: `allowed_block_types_all` allowlist, `template_lock` per type, `settings.*.custom*: false` so the editor offers the scale or nothing. State the cost out loud — locking trades authoring freedom for design integrity, and a site locked past what its owner needs is not manageable either.
- **Default content is a pattern**, not hardcoded markup: a template ships a pattern whose text the author then edits, and editing it does not fight the theme on the next update.

- [ ] **Step 2: Write `templates/template-ledger.md`**

Columns: `Template | Hierarchy slot | How assigned in admin | Parts | Blocks | Fields read | Authorable vs structural | Lock`.

- [ ] **Step 3: Run the `nimbus` scenario through step 5**

Expected: Services and Team are prefixed custom templates assigned from the page editor; Notes maps to `home`/`archive`; **no** `page-*.html`, no `is_page(`, no rewrite rule. Baseline row 5 must not recur.

- [ ] **Step 4: Run the `harbor` scenario for the redirect**

Expected: `/charter` → `/fleet` recorded as the one business-required redirect, and the ~180 existing URLs handled as a preservation requirement — **not** as a reason to write URL logic. If the agent invents a general redirect layer, add the counter and re-run.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/templates.md skills/design-to-wp/templates/template-ledger.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): templates — prefixed custom templates, no routing in code

Hierarchy mapping, the four routing shortcuts with their replacements,
an explicit locking policy with its cost named, and default content as
a pattern the author can edit."
```

---

### Task 7: Editability — `references/editability.md`

The core artifact. Closes baseline row 1.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/editability.md`
- Create: `~/.claude/skills/design-to-wp/templates/editability-ledger.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — step 6 points at these

**Interfaces:**
- Consumes: the content-model and template ledgers.
- Produces: `docs/editability-ledger.md`, read by `wp-manageability-audit` checks 2, 3 and 6.

- [ ] **Step 1: Write `references/editability.md`**

- **The method:** walk the design *element by element*, not section by section. Every string, URL, image, icon, list and number gets a row. Walking by section is how a footer's address becomes "the footer" and stops being three separate authorable values.
- **The categories of admin home** — the complete list, so "somewhere in the admin" is never an answer:

| Home | For |
|---|---|
| Post/page content | Body copy the author writes |
| Site title, tagline, `core/site-logo` | Identity |
| Nav menu (`core/navigation` with a distinct `ref`) | Every link list, header and footer alike |
| Site option / customizer setting | Site-wide single values — address, contact email, social URLs |
| Registered meta with its control | Per-entry structured values |
| Template part | Repeated composition |
| Pattern default | Starting copy the author then edits |
| Widget / block area | Author-arranged sidebars |
| **Static by agreement** | Listed explicitly, with the reason, and confirmed by the user |

- **Chrome is content.** The logo is `core/site-logo`; `dpaternina.com` shipped a `background: url()` on a visually-hidden site title. The footer needs its **own** `core/navigation` `ref` — one navigation with no ref means the footer can only mirror the header, which is what shipped.
- **Business-required redirects** are listed here individually, each with the requirement behind it. They are the only URL logic in the build.
- **The completeness test:** if a value in the design has no row, the ledger is not finished. A row whose home is "static by agreement" without a confirmed reason is rule 1 with a coat on.

- [ ] **Step 2: Write `templates/editability-ledger.md`**

Columns: `Element | Where in the design | Admin home | Who sets it | Default | Notes`. Then a `## Static by agreement` table (`Element | Why | Confirmed by | Date`) and a `## Redirects` table (`From | To | Business requirement`).

- [ ] **Step 3: Run the `nimbus` scenario through step 6**

Check specific rows exist with real homes:
- `hello@nimbus.example`, `4 Cross Street, Manchester M2 7AQ` → site options, **not** template text
- Both LinkedIn URLs and the RSS link → fields / site options
- Header nav and footer nav → **two** menus with distinct refs
- The logo → `core/site-logo`
- "Clarity, on a deadline." and every service card → pattern defaults inside authorable content
- The `Privacy` link → Settings → Privacy, not a hardcoded href

Baseline row 1 must not recur.

- [ ] **Step 4: Adversarial re-run**

```
Keep the ledger short — just cover the main content areas, the footer is
boilerplate.
```

Expected: the agent refuses to collapse the footer, or moves each footer value to `## Static by agreement` **and asks you to confirm** each one. Silent collapse means the completeness test is not binding; strengthen it and re-run.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/editability.md skills/design-to-wp/templates/editability-ledger.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): editability ledger — every element names its admin home

Element-by-element walk, the closed list of admin homes, chrome-is-content
with the site-logo and distinct-nav-ref failures behind it, and
'static by agreement' as an explicit confirmed exception."
```

---

### Task 8: Editor parity — `references/editor-parity.md`

Closes baseline row 7. Pure reference: three traps, each with its fix.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/editor-parity.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — cited from steps 5 and 9

**Interfaces:**
- Produces: the trap list `wp-manageability-audit` check 4 tests for.

- [ ] **Step 1: Write `references/editor-parity.md`**

Three traps, each stated as symptom → cause → fix:

1. **A block registered only in PHP draws as `core/missing` in the editor** while rendering perfectly on the front end. Fix: every dynamic block gets an editor-bundle registration with a `ServerSideRender` preview — which means a build step runs after every pull. All three of `dpaternina.com`'s dynamic blocks shipped broken this way.
2. **A base element style ties with core's and the tie breaks on load order**, which differs between canvas and front end. `.wp-element-button` versus `:root :where(.wp-element-button)` made every unadorned button teal on the site and core's grey in the editor. Fix: base rules carry an element, like every other component rule — the variant classes already did, which is why only the base ever diverged.
3. **Block gap adds itself to compositions the design already spaced.** Eighteen home-page elements had different spacing in the canvas than on the front end — a 40px row gap where the design drew 16, rows floating 24px apart that the design butts together, a section with no block padding at all. Fix: every spacing rule out-specifies the block gap, in both contexts, and a spec sweeps for the nineteenth.

Plus the standing requirement: **editor styles are enqueued in both contexts**, and parity is checked by looking at the canvas, not by trusting the front end.

- [ ] **Step 2: Retrieval test**

Dispatch a fresh subagent:

```
A WordPress block I registered in PHP renders correctly on the front end
but shows as "This block has encountered an error" in the site editor.
Use the design-to-wp skill's references to explain why and fix it.
```

Expected: it finds trap 1 and prescribes the `ServerSideRender` editor registration plus the build step — not a generic debugging ramble.

- [ ] **Step 3: Gap test**

```
My theme's buttons are the right colour on the front end and grey in the
editor. Use the design-to-wp skill's references.
```

Expected: trap 2, named as a specificity tie broken by load order. If the agent guesses instead, the symptom wording in the file is not matching how the problem is described — rewrite the symptom line in the user's words and re-test.

- [ ] **Step 4: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/editor-parity.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): editor parity — the three traps that shipped broken

core/missing for PHP-only blocks, the .wp-element-button specificity tie
broken by load order, and block gap out-specified by the design's own
spacing. Each with its symptom, its cause and its fix."
```

---

### Task 9: Environment, seed and release — `references/env-seed-release.md`

Closes baseline rows 8 and 9, and carries the distribution question David asked for explicitly.

**Files:**
- Create: `~/.claude/skills/design-to-wp/references/env-seed-release.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — step 8 points at it

**Interfaces:**
- Consumes: the tier from step 0.
- Produces: the seed contract `wp-manageability-audit` check 5 runs.

- [ ] **Step 1: Write `references/env-seed-release.md`**

**Local environment**, by tier: `wp-env` where CI will run the same thing; Studio or Local for a brochure build; whatever the client already uses when extending an existing site.

**The seed.** A fresh install must produce a *navigable* site — not an empty one an author has to assemble. Required: pages created, custom templates assigned, both nav menus built and populated, logo set, front page and posts page set, and one representative entry per content type. Idempotent and re-runnable. **Fix the seed, never the database** — a site state reachable only by clicking in the admin is a state nobody can reproduce.

**Distribution**, asked at step 8 and built at plan-phase 3, because a pipeline built at the end is a pipeline nobody trusts. Four options, presented as a question:

| Option | When |
|---|---|
| `fanxielab/wp-update-client` against `wp-updates.fanxie.cloud` | Fanxie-hosted or Fanxie-maintained sites wanting real WordPress update notifications. Signed-envelope manifests, compiled-in Ed25519 public key, fail closed, tag-driven builds, one instance serving multiple sites. Composer: `fanxielab/wp-update-client`, repository `https://github.com/fanxie-lab/wordpress-updater`, plus its reusable GitHub Actions workflow. |
| wordpress.org repository | The theme or plugin is genuinely public and will accept the review process |
| Manual zip | One-off handoff, owner updates rarely, no pipeline justified |
| Git-based deploy | Developer-run site, no update UI needed |

The choice, with its reason, goes in the decisions log.

- [ ] **Step 2: Run the `nimbus` scenario through step 8**

Expected: the agent **asks** about distribution and names the four options rather than assuming, and specifies a seed covering all six required items. Baseline rows 8 and 9 must not recur.

- [ ] **Step 3: Run the `harbor` scenario for the same step**

Expected: given a non-technical office manager and an existing site, the agent picks a distribution route that gives her real update notifications, and says why.

- [ ] **Step 4: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/references/env-seed-release.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): env, seed and release

The seed must yield a navigable site and is fixed instead of the
database. Distribution asked at step 8 and built at plan-phase 3, with
the fanxie wp-update-client route alongside wp.org, zip and git deploy."
```

---

### Task 10: `CLAUDE.md`, handoff and the plan fallback

The durable channel. Everything else in this skill is forgotten when the session ends; this file is not.

**Files:**
- Create: `~/.claude/skills/design-to-wp/templates/CLAUDE.md.template`
- Create: `~/.claude/skills/design-to-wp/references/handoff.md`
- Create: `~/.claude/skills/design-to-wp/templates/plan-fallback.md`
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — steps 7 and 9 point at these

**Interfaces:**
- Consumes: the decisions log and all three ledgers.
- Produces: the target project's `CLAUDE.md`, which cites `wp-manageability-audit` by name — the interface Task 11 depends on.

- [ ] **Step 1: Write `templates/CLAUDE.md.template`**

Sections, with `<!-- fill -->` markers for the project-specific parts:

1. **The two rules** — the executor rule (substantial work to a `wordpress-development-expert` subagent with a brief naming files owned and acceptance criteria; a small diagnosed fix made directly, because spending a fresh context and a full verification pass on a one-line CSS change is the failure mode, not the discipline) and the manageability rule.
2. **The nine non-negotiables**, verbatim from spec §3.
3. **Where things are** — `design-source/` read-only, the three ledgers, the decisions log, the plan.
4. **The gates ledger** — which check runs for which kind of change, with the rule that CI runs the full suite on the PR and that is what it is for. Never re-run a gate to feel sure.
5. **At every phase boundary, run `wp-manageability-audit`.** Named, so it is reachable without re-entering this skill.
6. **Targets** — PHP version, coding standards, static analysis level, accessibility (WCAG level, **front end only**; admin screens are out of scope unless stated), i18n and text domain.
7. **ADR discipline**, if the project keeps ADRs: an ADR records a reversal or an externally imposed constraint. Never one for a decision being made in the same pass as its code — three of `dpaternina.com`'s eighteen ADRs caused the defects they documented, each written by the party that made the decision before anyone had used the result.

**If a `CLAUDE.md` already exists: amend, never replace.** Add the nine rules and the audit citation; leave everything else alone.

- [ ] **Step 2: Write `references/handoff.md`**

- Templates, blocks, patterns and fields carry names a non-developer can act on — the name shows in the admin, so "Team member — role" beats "person_meta_2".
- The editor-facing document: how to add each content type, how to assign each custom template, where each site-wide value lives, what is deliberately locked and why.
- **The acceptance criterion is a walkthrough**, not a document: the owner (or someone standing in for them) changes one value of each kind from the admin, and it appears on the site. A handoff doc nobody has executed is untested.

- [ ] **Step 3: Write `templates/plan-fallback.md`**

A phase-ordered plan skeleton for when superpowers is absent: foundation → tokens and `theme.json` → **release pipeline** → content model → house style and blocks → chrome → templates → seed → accessibility and performance → handoff. Each phase has `Goal`, `Files`, `Done when`, and `Audit` (the `wp-manageability-audit` checks that must pass).

- [ ] **Step 4: Full end-to-end run on `nimbus`**

Dispatch a fresh subagent:

```
Use the design-to-wp skill to plan a WordPress site from the design at
~/.claude/skills/design-to-wp/tests/fixtures/nimbus/. Answer the skill's
questions yourself with sensible choices and note what you chose. Run all
ten steps.
```

Verify the generated `CLAUDE.md` contains all nine rules **verbatim** — diff its rule text against spec §3 rather than reading it approvingly:

```bash
diff <(sed -n '/non-negotiable/,/^---/p' <project>/CLAUDE.md) \
     <(sed -n '/^## 3\./,/^### Where each/p' docs/superpowers/specs/2026-08-31-design-to-wp-design.md)
```

Expected: the nine rule sentences match. Paraphrase is a failure — it is how a rule softens across a project.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/templates/ skills/design-to-wp/references/handoff.md skills/design-to-wp/SKILL.md
git commit -m "feat(design-to-wp): CLAUDE.md template, handoff and plan fallback

The rules file is the only channel that survives the session, so it
carries the nine non-negotiables verbatim, the gates ledger and a
by-name citation of wp-manageability-audit. Handoff is accepted by a
walkthrough, not by a document."
```

---

### Task 11: `wp-manageability-audit`

Its own skill, its own baseline. RED first: an agent asked to check manageability without it should miss the field that has no control.

**Files:**
- Create: `~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/nimbus-core.php`
- Create: `~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/templates/nb-team.html`
- Create: `~/.claude/skills/wp-manageability-audit/tests/baseline.md`
- Create: `~/.claude/skills/wp-manageability-audit/SKILL.md`

**Interfaces:**
- Consumes: a project root, a tier, and the three ledgers written by `design-to-wp`. Runs without the ledgers too, in reduced form — an existing project may have none.
- Produces: a pass/fail line per check, each failure naming the specific file, field or element.

- [ ] **Step 1: Write the broken fixture**

Three planted defects, one per check, so a pass/fail is unambiguous.

`~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/nimbus-core.php`:

```php
<?php
/**
 * Plugin Name: Nimbus Core
 * Deliberately broken fixture for wp-manageability-audit.
 * Three planted defects: see tests/baseline.md.
 */

declare( strict_types=1 );

namespace Nimbus\Core;

// Defect 1 — `nb_featured` has no editor control anywhere. It is registered,
// it is read at render, and no screen can set it. (Check 1 must catch this.)
function register_meta_fields(): void {
	register_post_meta( 'nb_person', 'nb_role', array(
		'type'         => 'string',
		'single'       => true,
		'show_in_rest' => true,
	) );
	register_post_meta( 'nb_person', 'nb_linkedin', array(
		'type'         => 'string',
		'single'       => true,
		'show_in_rest' => true,
	) );
	register_post_meta( 'nb_person', 'nb_featured', array(
		'type'         => 'boolean',
		'single'       => true,
		'show_in_rest' => true,
	) );
}
add_action( 'init', __NAMESPACE__ . '\register_meta_fields' );

// Defect 3 — registered in PHP only, so it draws as core/missing in the
// editor while rendering fine on the front end. (Check 4 must catch this.)
function register_blocks(): void {
	register_block_type( 'nimbus/team-grid', array(
		'render_callback' => __NAMESPACE__ . '\render_team_grid',
	) );
}
add_action( 'init', __NAMESPACE__ . '\register_blocks' );

function render_team_grid(): string {
	return '<div class="nb-team-grid"></div>';
}
```

`~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/templates/nb-team.html`:

```html
<!-- wp:group -->
<div class="wp-block-group">
  <!-- wp:heading --><h2>Who you get</h2><!-- /wp:heading -->
  <!-- wp:nimbus/team-grid /-->
  <!-- Defect 2 — a hardcoded href and hardcoded contact copy. Check 2. -->
  <!-- wp:paragraph --><p><a href="https://linkedin.com/company/example">Follow us on LinkedIn</a> or email hello@nimbus.example</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 2: Dispatch the baseline subagent — no skill**

```
Review the WordPress code at
~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/.
The promise is that the site's owner can manage everything from wp-admin.
Report anything that breaks that promise.
```

- [ ] **Step 3: Record the baseline**

Write `tests/baseline.md` recording which of the three defects were found, which were missed, and verbatim what was said about each. The expectation from the source project is that defect 1 is the one that goes unnoticed — it looks like correct, idiomatic registration.

- [ ] **Step 4: Write `wp-manageability-audit/SKILL.md`**

Frontmatter — triggering conditions only:

```yaml
---
name: wp-manageability-audit
description: Use when checking that a WordPress theme or plugin is actually manageable from wp-admin — at a phase boundary, before a handoff, or when reviewing a build for hardcoded content, registered fields with no editor control, blocks that break in the site editor, or a site that only works after manual admin setup.
---
```

Body: the seven checks from spec §5, each as **what to run**, **what a failure looks like**, and **what to report**. Concretely:

| # | Check | How |
|---|---|---|
| 1 | Field/control parity | Enumerate `register_post_meta`, `register_meta`, `register_post_type`, `register_taxonomy`. For each field, find its control: a `core/post-meta` binding in a template or pattern, a block that writes it, a sidebar panel, or a `register_post_type` `template`. Unmatched = fail, named. |
| 2 | Hardcoded values | Grep templates, patterns and PHP for `href="http`, `mailto:`, `add_rewrite_rule`, `is_page(` with an argument, `get_page_by_path`, `templates/page-*.html`. Cross-check hits against the editability ledger's `## Static by agreement`. |
| 3 | Ledger coverage | Every editability-ledger row names an admin home, and that home exists in the code. Skipped with a note if no ledger. |
| 4 | Editor parity | Every `register_block_type` has a matching editor-side registration. Then spot-check spacing and button styling in the canvas against the front end. |
| 5 | Fresh-seed navigability | Reset, seed, crawl from the home page: every nav destination resolves, front page set, logo set, custom templates assigned. |
| 6 | Chrome is content | Logo is `core/site-logo`; each navigation is `core/navigation` with a **distinct** `ref`; the footer is not the header's menu. |
| 7 | Handoff sanity | Templates, blocks and fields carry names a non-developer can act on. |

Then: **tier scaling** — engineered turns checks 1, 2, 4 and 6 into CI tests; lighter tiers run them manually and write the result down. And the **reporting contract**: one line per check, `PASS` or `FAIL`, and every `FAIL` names the file and the specific field, element or block. A summary without specifics is not a report.

- [ ] **Step 5: Run the audit on the broken fixture**

```
Use the wp-manageability-audit skill on
~/.claude/skills/wp-manageability-audit/tests/fixtures/broken/. Tier: standard.
```

Expected, exactly:
- Check 1 FAIL naming `nb_featured`
- Check 2 FAIL naming the LinkedIn href and `hello@nimbus.example` in `nb-team.html`
- Check 4 FAIL naming `nimbus/team-grid`
- Checks 3, 5, 6, 7 skipped or reported as not-applicable **with a reason** — a silent skip is a failure of the reporting contract

- [ ] **Step 6: Run the audit on a known-good project (no false positives)**

```
Use the wp-manageability-audit skill on
"/Users/david/Documents/DP/DP Website/dp-site-wordpress". Tier: engineered.
```

Expected: checks 1, 2, 4 and 6 pass. This repo went through the audits these checks are drawn from, so a failure here is either a real regression or a check that is too blunt. Investigate before adjusting — and if the check is wrong, fix the check, not the repo.

- [ ] **Step 7: Commit**

```bash
cd ~/.claude
printf '!/skills/wp-manageability-audit/\n' >> .gitignore
git add .gitignore skills/wp-manageability-audit/
git commit -m "feat(wp-manageability-audit): seven checks, with a broken fixture

Its own skill so a phase boundary can invoke it by name without
re-entering design-to-wp. Fixture plants one defect per check;
the field with no control is the one a baseline agent misses."
```

---

### Task 12: Wire the two skills together and verify from cold

**Files:**
- Modify: `~/.claude/skills/design-to-wp/SKILL.md` — cite the audit at steps 7 and 9
- Modify: `~/.claude/skills/design-to-wp/templates/CLAUDE.md.template` — audit cited by name
- Create: `~/.claude/skills/design-to-wp/README.md`

- [ ] **Step 1: Add the cross-references**

In `SKILL.md` step 9 and in the `CLAUDE.md` template's phase-boundary section:

```markdown
**REQUIRED SUB-SKILL:** Use wp-manageability-audit at every phase boundary.
```

Skill name only, with the marker. No `@`-link.

- [ ] **Step 2: Write `README.md`**

Ten lines: what the pair is for, the two skill names, where fixtures live, and how to re-run the tests after an edit. This is what a future editor reads before touching either skill.

- [ ] **Step 3: Cold discovery test**

Dispatch a fresh subagent with **no skill named at all**:

```
I have a Figma design for a small consultancy website and I want to build
it as a WordPress site the client can manage themselves. Where do I start?
```

Expected: it finds and invokes `design-to-wp` from the description alone. If it does not, the description is missing the words a user actually types — add them and re-test. This is the only test of whether the skill will ever get used.

- [ ] **Step 4: Verify everything is tracked**

```bash
cd ~/.claude
git status --short
git ls-files skills/design-to-wp/ skills/wp-manageability-audit/ | wc -l
```

Expected: clean tree, and both skills' files listed. The allowlist `.gitignore` makes an untracked skill easy to miss.

- [ ] **Step 5: Commit**

```bash
cd ~/.claude
git add skills/design-to-wp/ skills/wp-manageability-audit/
git commit -m "feat: wire design-to-wp and wp-manageability-audit together

Audit cited by name from the spine and from the CLAUDE.md template, so
a phase boundary reaches it without re-entering the workflow. README
records how to re-run the fixtures after an edit."
```

---

## Notes for the executor

- **Subagent dispatch is the test harness.** Every RED step needs a subagent that has never seen these skills. If dispatch is unavailable in your session, this plan cannot be executed as written — stop and say so rather than writing skill text against an imagined baseline.
- **The Iron Law binds edits too.** Adding a section to a finished skill needs its own failing baseline first.
- **Baseline rows are the budget.** A predicted failure Task 1 did not observe does not earn words in the skill. Cut it.
- **Verbatim means verbatim** for the nine rules. Diff, don't read.
