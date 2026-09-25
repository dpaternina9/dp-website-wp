# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Visitors to David Paternina's personal site, dpaternina.com: people curious about his
work (roles and things he has shipped), his writing, his streams, and — with the photo
section — the rest of his life. One author, David, manages everything from wp-admin.

## Product Purpose

A personal site, roughly 70/30 personal to professional. Home, Work (a timeline of roles
and shipped items), Blog (with a "My life story" series), Watch, About, Contact, and a
photo section of pictures David has taken: travel, his cats, home, anything.

## Positioning

It is one person's record, hand-built: a hand-written WordPress block theme plus a
companion plugin, not a template. The photo section is his own photography, not a
portfolio pitch.

## Operating Context

- David authors every URL, page, slug, nav item and piece of copy in wp-admin
  (CLAUDE.md rule 2, ADR-0018). Code never invents or silently rewrites an author value;
  anything computed is visible in the editor.
- Prefer core WordPress fields over custom meta (ADR-0016).
- Photos arrive through the Media Library. At scale (hundreds of photos) editing must
  stay fast; hand-curation is wanted, but a per-page block holding hundreds of images is
  a known concern.
- A photo's caption is where David types place and date himself; WordPress's EXIF
  extraction supplies camera settings.

## Capabilities and Constraints

- Photo pop-up shows, when present: title, description, caption (place + date), camera
  settings, prev/next through the set, and a link to a post when the photo belongs to one.
  Most photos have no description; empty fields render nothing.
- Grouping/filtering of photos (by trip, by topic, or both) is **undecided** — to be
  explored as design options.
- Dark ground only; light mode is ruled out.
- Deploy is a git tag; the live site auto-updates.

## Brand Commitments

The design contract in `design-source/` is binding: Bricolage Grotesque / Manrope /
JetBrains Mono, the `--dp-*` tokens, one gradient per view, no gradient fills on buttons
or text, motion 120–380ms decelerating only, no bounce.

## Evidence on Hand

- 34 photos on 500px (https://500px.com/p/dapd007), Fujifilm X30, 2016–2018: Colombia
  (Mocoa, Laguna La Cocha, Santa Marta, Palomino, Duitama, Funza), New York, Washington DC.
  Mostly 4:3 landscape, one ~3.4:1 panorama, one portrait. 6 of 34 have descriptions.
- Instagram @alejopdavid (not reviewed).
- All copy in the design and seed is placeholder — never invent facts about David.

## Product Principles

1. Everything is David's to set in wp-admin; nothing computed hides.
2. The photographs lead; chrome recedes.
3. Absence is normal — a photo with only an image is complete.
4. Scale honestly: hundreds of photos must stay easy to add and browse.

## Accessibility & Inclusion

WCAG 2.2 AA on the public front end (admin screens excluded).
