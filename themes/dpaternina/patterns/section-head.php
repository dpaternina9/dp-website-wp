<?php
/**
 * Title: Section head — kicker
 * Slug: dpaternina/section-head
 * Categories: dpaternina
 * Description: A mono caps kicker with a mono caps meta note. Tone is a class — is-tone-pink, is-tone-gold, is-tone-purple, is-tone-muted; teal is the default.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"className":"dp-section-head","layout":{"type":"default"}} -->
<div class="wp-block-group dp-section-head"><!-- wp:paragraph {"className":"dp-section-head__kicker"} -->
<p class="dp-section-head__kicker"><?php esc_html_e( 'Right now', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-section-head__meta"} -->
<p class="dp-section-head__meta"><?php esc_html_e( 'Aug 2026', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
