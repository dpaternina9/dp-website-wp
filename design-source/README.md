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
- Component files were rewritten to keep the markup and the *meaning* of the logic
  verbatim while dropping the design tool's scaffolding (`<helmet>` link soup, the
  `ResizeObserver` width probe, `DCLogic` boilerplate). Every number, colour, and rule
  from the original is preserved in the markup or in a trailing comment block.
  `dpaternina.dc.html` is untouched and is the tiebreaker if anything disagrees.
