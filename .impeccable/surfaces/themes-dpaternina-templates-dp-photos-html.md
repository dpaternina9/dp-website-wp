---
version: 1
slug: "themes-dpaternina-templates-dp-photos-html"
primary_target: "themes/dpaternina/templates/dp-photos.html"
related_targets: []
---

# Photos — surface brief

Scope: the photo section (custom template `dp-photos`, a page David creates). Mode: Experience.
Audience/job: visitors browsing David's photos (travel, cats, home); David curates hundreds from the Media Library.
Constraints: everything set in wp-admin; photos grouped by Trip and Topic; pop-up shows only fields that exist.
Memorable moment: hovering an index entry lights that group's photos across the wall.
Decided: tall photos capped at 1.8× column width in the grid only (full photo in the lightbox); blank title renders nothing; off-screen hover shows an "N below" hint.

## Direction contract

THESIS: The photos are the page; one adaptive index (margin, spine, dock) groups them without taking width. Refuses the filter-pill bar over a uniform grid.
OWN-WORLD: The site's dark ink ground, graphite surfaces, slate hairlines, teal only for the active state; Manrope list entries with mono counts and dates; 8px-radius tiles, no card chrome.
STORY: The visitor scans the wall, hovers a trip to see where it sits, filters by URL-backed links, opens a photo, steps through the set.
FIRST VIEWPORT: Display "Photos", one-line intro, mono stats; index in the left margin at ≥1400px (spine rail below, bottom dock under 600px); the wall starts above the fold.
FORM: Index + wall, margin/spine hybrid (rank 5, dealt 3rd; steered by David); seed d4ce76d0.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
