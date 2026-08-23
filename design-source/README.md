# design-source — READ ONLY

Imported from the Claude Design project
`2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b` ("Website redesign with timeline")
on 2026-08-19 via the claude_design MCP.

**Do not edit anything in this directory.** If the design needs to change, change it in
Claude Design and re-import. This folder is the contract the implementation is measured
against.

## What is here

| Path | What it is |
|---|---|
| `dpaternina.dc.html` | The whole site. Every page state, plus the full content fixture (`LANES`, `POSTS`, `PAGES`, `VIDEOS`, `SERIES`, `TERMS`, `GEAR`). 1861 lines. |
| `components/*.dc.html` | The eleven extracted components + `OgCard`. `ComponentLibrary.dc.html` is the index: props, purpose, and the designer's suggested WordPress mapping. |
| `_ds/tokens/*.css` | The design tokens. **Source of truth for `theme.json`.** |
| `_ds/styles.css` | Import manifest for the tokens. |
| `_ds/_ds_bundle.js` | Compiled design-system primitives: Logo, Badge, Button, Card, GradientText, IconButton, Input, Switch. Read it for exact variant styling; do not ship it. |
| `theme.css` | Site-level scopes the design system does not ship: `--band`, the glow strengths, and the text-safe `--hue-*` / `--accent-text` values. |
| `assets/dp-mark-*.png` | The monogram, 2000×2000 RGBA. |

## Import fidelity notes

- The `*-128.png` variants were **resampled locally** from the 2000px masters rather
  than downloaded, so they may differ by a pixel from the originals in the design
  project. The theme generates its own sizes from the 2000px masters anyway.
- Three components (`SiteHeader`, `SiteFooter`, `CtaBanner`) embedded the monogram as an
  inline `data:image/png;base64,…` URI. On import that was replaced with
  `assets/dp-mark-gradient-128.png`. Nothing else was altered.
- ~~Component files were rewritten to keep the markup and the *meaning* of the logic
  verbatim while dropping the design tool's scaffolding.~~ **This claim was false and it
  cost three failed audits of the work page.**

  Every one of the thirteen component files was imported **without its
  `<script type="text/x-dc">` block**. That block is where the design computes its own
  styles — `orgStyle`, `kindLabelStyle`, `legendStyle`, `headlineStyle`, `rowStyle`,
  `barStyle`, `chevronStyle` — and none of them survived into "the markup or a trailing
  comment block". Roughly half of each component's declared values were simply gone.

  The damage was not just missing data. `docs/adr/0012-design-parity-harness.md` recorded
  those styles as "computed inside the design tool and never reach the export" and told
  future phases not to look for them. They *are* in the export. Nobody had fetched it.

  **Re-fetch, do not re-summarise.** `TimelineChart.logic.js` is the first one restored,
  verbatim, on 2026-08-23. The other twelve are still truncated — see
  `docs/MERGE-QUEUE.md`. `dpaternina.dc.html` kept its script block and is complete.

- `Ledger.dc.html` exists in the design project and **was never imported at all**. Phase 7
  built the résumé ledger block without it.
