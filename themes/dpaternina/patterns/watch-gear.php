<?php
/**
 * Title: Watch — the gear list
 * Slug: dpaternina/watch-gear
 * Categories: dpaternina
 * Description: "What the stream runs on" — four toned groups of kit, each item a name and a note. dpaternina.dc.html, the GEAR fixture. Editor content David owns, not a post type.
 *
 * @package DP\Theme
 */

/*
 * Digest section 3.6: "a block, not a post type". The gear list is page
 * content — the seeder starts the Watch page's body from this pattern, and
 * from then on it is David's, edited like any other page. Nothing is echoed
 * from PHP at render time and no code knows what the groups are.
 *
 * The section head is here rather than in `dp-watch.html` because it belongs
 * to this content: delete the gear and its heading goes with it.
 *
 * Every word is the design's placeholder copy (CLAUDE.md section 6), seeded
 * verbatim. The word "Uses" in the intro is deliberately not a link — a
 * shipped pattern carries no hrefs (ADR-0018); David links it once, to the
 * page he made.
 */

?>
<!-- wp:group {"className":"dp-section-head","layout":{"type":"default"}} -->
<div class="wp-block-group dp-section-head"><!-- wp:heading {"className":"dp-section-head-heading"} -->
<h2 class="wp-block-heading dp-section-head-heading"><?php esc_html_e( 'What the stream runs on', 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-section-head-meta"} -->
<p class="dp-section-head-meta"><?php esc_html_e( 'Updated Aug 2026', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"dp-gear-intro"} -->
<p class="dp-gear-intro"><?php esc_html_e( 'Nothing here is sponsored and none of it is new. It is the same desk I work at, with a light and a mic added. The full list of what I build with lives on Uses.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"dp-gear","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear"><!-- wp:group {"className":"dp-gear-group","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-group"><!-- wp:heading {"level":3,"className":"dp-label dp-tone-teal dp-gear-label"} -->
<h3 class="wp-block-heading dp-label dp-tone-teal dp-gear-label"><?php esc_html_e( 'Desk', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( '16-inch laptop, docked', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Lid closed, one 27-inch display above it. Same machine I build on.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Mechanical keyboard, tactile switches', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Quiet enough that nobody on a call has complained yet.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Arm-mounted display', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Bought to get the camera to eye level. Kept for the desk space.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-group","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-group"><!-- wp:heading {"level":3,"className":"dp-label dp-tone-gold dp-gear-label"} -->
<h3 class="wp-block-heading dp-label dp-tone-gold dp-gear-label"><?php esc_html_e( 'Camera & light', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Mirrorless body, 35mm prime', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'The same camera I shoot photography with. Clean HDMI out.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'One key light, one practical', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Softbox at 45°, and a lamp behind me so the room is not a void.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Capture card', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Unremarkable, which is the highest praise a capture card gets.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-group","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-group"><!-- wp:heading {"level":3,"className":"dp-label dp-tone-purple dp-gear-label"} -->
<h3 class="wp-block-heading dp-label dp-tone-purple dp-gear-label"><?php esc_html_e( 'Audio', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Dynamic mic on a boom', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Dynamic, not condenser — it ignores the cats and the street.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Small interface', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Two inputs, one of which is a guitar when the stream goes off-topic.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Closed-back headphones', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'For monitoring, and for pretending the doorbell did not ring.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-group","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-group"><!-- wp:heading {"level":3,"className":"dp-label dp-tone-teal dp-gear-label"} -->
<h3 class="wp-block-heading dp-label dp-tone-teal dp-gear-label"><?php esc_html_e( 'Software', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'OBS, with three scenes', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Code, camera, and a break card. More scenes than that and I never switch.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'Neovim and a terminal', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Font size cranked up so it is readable at 1080p.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-gear-item","layout":{"type":"default"}} -->
<div class="wp-block-group dp-gear-item"><!-- wp:paragraph {"className":"dp-gear-name"} -->
<p class="dp-gear-name"><?php esc_html_e( 'A tiny overlay set', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-gear-note"} -->
<p class="dp-gear-note"><?php esc_html_e( 'Built from this site’s design system, so the stream matches the site.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
