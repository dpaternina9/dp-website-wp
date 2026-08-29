# dpaternina.com — WordPress rebuild

Monorepo for dpaternina.com: a hand-written WordPress block theme
(`themes/dpaternina`) plus a companion plugin (`plugins/dp-core`). One site,
posts imported via WXR.

## The two rules

1. **All development goes through the `wordpress-development-expert` agent.**
   The main session digests requirements, dispatches briefs (files owned,
   acceptance criteria), reviews what comes back, and reports to David. It
   writes no code beyond trivial one-file fixes.

2. **Everything we build is manageable from wp-admin.** David sets every URL,
   every piece of copy, every nav item, every page and slug in the editor. Code
   never invents, rewrites, or overwrites a value an author can set; anything
   computed announces itself visibly in the editor (a named block, a block
   binding, a token in a visible field). No hardcoded hrefs, no hardcoded copy,
   no rewrite rules, no slug/ID branching, no hidden render-time rewriting.
   (See ADR-0018.)

## Facts, not rules

- `design-source/` is the read-only design contract; `docs/plan.md` tracks
  phases; decisions that outlive a PR go in `docs/adr/` (read its README —
  the bar for writing one is deliberately high).
- Local env is `wp-env`: `npm run env:start`, `npm run env:cli -- <args>`,
  `npm run env:reset` (re-seeds via `bin/seed.php` — fix the seed, never the DB).
- Done means green gates: `composer lint`, `composer analyse`, `composer test`,
  `npm run lint`, `npm run test:unit`, and `npm run test:e2e` once at the end.
  Paste real output; run each once, no verification loops.
- Deploy is a git tag (`theme-vX.Y.Z` / `core-vX.Y.Z`) — CI builds, signs, and
  publishes to `wp-updates.fanxie.cloud`; the site auto-updates. No manual path.
- PHP 8.4 strict + typed, WPCS/PHPStan level 9 enforced by the gates. WCAG 2.2 AA
  on the front end. Dark is the only ground; light mode is ruled out.
- All copy in the design and seed is placeholder — never invent facts about David.
- If a design detail is ambiguous, ask; don't guess and don't quietly simplify.
