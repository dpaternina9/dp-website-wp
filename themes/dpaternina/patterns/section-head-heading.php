<?php
/**
 * Title: Section head — heading
 * Slug: dpaternina/section-head-heading
 * Categories: dpaternina
 * Description: A display-face heading with a mono caps note beside it. The component takes a meta note or an action, never both.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"className":"dp-section-head","layout":{"type":"default"}} -->
<div class="wp-block-group dp-section-head"><!-- wp:heading {"className":"dp-section-head__heading"} -->
<h2 class="wp-block-heading dp-section-head__heading"><?php esc_html_e( 'Featured work', 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-section-head__meta"} -->
<p class="dp-section-head__meta"><?php esc_html_e( 'Click one to open it on the timeline', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
