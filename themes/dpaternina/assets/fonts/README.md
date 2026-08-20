# Fonts

Three families, self-hosted, no request ever leaves this origin. `CLAUDE.md` §5:
"Fonts (Bricolage Grotesque, Manrope, JetBrains Mono) are self-hosted and
registered in `theme.json` via `fontFace`. No Google Fonts requests at runtime."

The design system's own `_ds/tokens/fonts.css` is a Google Fonts `@import`. It
does not ship; `theme.json` `settings.typography.fontFamilies[].fontFace`
replaces it.

## What is here, and where it came from

Each family is one **variable** woff2 per subset, taken from the Google Fonts
CSS2 API with a modern browser user-agent, which serves subset, weight-sliced
woff2 files. The URLs below are the exact requests; the files are what those
requests returned on 2026-08-20.

| Family | Role | Axis requested | Files |
|---|---|---|---|
| Bricolage Grotesque | display | `wght@400..800` | `latin` 41 KB · `latin-ext` 19 KB |
| Manrope | body | `wght@400..800` | `latin` 24 KB · `latin-ext` 15 KB |
| JetBrains Mono | mono | `wght@400..700` | `latin` 31 KB · `latin-ext` 12 KB |

```
https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400..800&display=swap
https://fonts.googleapis.com/css2?family=Manrope:wght@400..800&display=swap
https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400..700&display=swap
```

The weight ranges are exactly the ones `_ds/tokens/typography.css` declares:
`--fw-regular` 400 through `--fw-extrabold` 800 for display and body, 400 through
`--fw-bold` 700 for mono.

## Four decisions worth writing down

**Variable, not static instances.** One file covers the whole weight range, so
there is no cliff between the five weights the design uses and no five-file
download for a page that uses three of them.

**Bricolage is requested with its weight axis only.** It ships three axes
(`opsz`, `wdth`, `wght`) and the design varies none but weight. The full
three-axis `latin` file is 131 KB; the weight-only slice is 41 KB. Same
rendering, a third of the bytes.

**`latin` and `latin-ext`, split by `unicode-range`.** The content contains
"Résumé" and "Medellín", and there is Spanish copy. Both of those live in `latin`
(U+0000–00FF), so the common page never fetches a second file — but a page that
does carry a character from the extended range gets the real face instead of a
system fallback. Dropping `latin-ext` would have been a silent trap.

**Normal styles only, no italics.** Neither Bricolage Grotesque nor Manrope
offers an italic on Google Fonts at all, so shipping JetBrains Mono's would make
one of the three families behave differently from the other two for no design
reason. Emphasis is synthesised, consistently, across the whole system. If the
design ever asks for a true italic, this is the note to revisit.

## Preloading

`DP\Theme\Assets::PRELOADED_FONTS` preloads two of the six files —
Bricolage `latin` and Manrope `latin` — and the reasoning for each is in the
docblock there. JetBrains Mono and every `latin-ext` file are deliberately not
preloaded.

## Licences

All three are SIL Open Font License 1.1. `OFL.txt` sits beside the woff2 files in
each directory and travels with the release zip.

| Family | Licence source |
|---|---|
| Bricolage Grotesque | `github.com/ateliertriay/bricolage` → `OFL.txt` |
| Manrope | `github.com/google/fonts` → `ofl/manrope/OFL.txt` |
| JetBrains Mono | `github.com/JetBrains/JetBrainsMono` → `OFL.txt` |
