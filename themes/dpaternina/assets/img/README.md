# Marks

## What is served

| File | Where it is used | Drawn at |
|---|---|---|
| `dp-mark-gradient-128.png` | `.dp-brand` — the header, the mobile panel's head, the footer | 34px, 34px, 30px @ 0.85 |
| `dp-mark-white-128.png` | `.dp-featured-art` — the placeholder art on a featured post | 96px @ 0.55 |

Both are 128px so a 2× screen has pixels to use at every one of those sizes.
Neither is a `core/site-logo`: the mark ships with the theme and must not depend
on David having uploaded anything, so it is a background image on the link
`core/site-title` already renders, with the site's name kept as the link's
accessible name and pushed off-screen.

The gradient mark is the design's choice everywhere in the chrome. The white one
stays on `.dp-featured-art` on purpose — that tile is already a spectrum
gradient, and a gradient mark on a gradient ground does not read.

## What is not served

`dp-mark-gradient.src.png` is the 2000px master David supplied. It is a build
input, not an asset: nothing links to it, and `bin/dp-build.sh` drops every
`*.src.*` file before it zips a release. Regenerate the served size from it:

```
sips -Z 128 --setProperty format png \
  themes/dpaternina/assets/img/dp-mark-gradient.src.png \
  --out themes/dpaternina/assets/img/dp-mark-gradient-128.png
```

`design-source/assets/dp-mark-gradient.png` and `dp-mark-gradient-128.png` are a
**broken export** — both carry only the top arc of the monogram and part of one
letter, at 2000px as much as at 128px, so neither can be scaled out of the
problem. They are not the source for anything here. If the design is re-exported
with a good file, that becomes the master and this note goes away.
