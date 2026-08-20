<?php
/**
 * Title: CTA banner — plain
 * Slug: dpaternina/cta-banner
 * Categories: dpaternina
 * Description: A bordered banner with a line of copy and one secondary button. design-source/components/CtaBanner.dc.html.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"className":"dp-cta-banner","layout":{"type":"default"}} -->
<div class="wp-block-group dp-cta-banner"><!-- wp:group {"className":"dp-cta-banner-body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-cta-banner-body"><!-- wp:paragraph {"className":"dp-cta-banner-title"} -->
<p class="dp-cta-banner-title"><?php esc_html_e( 'Something worth building?', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-cta-banner-line"} -->
<p class="dp-cta-banner-line"><?php esc_html_e( 'Product work, agency projects, or just a note about espresso, guitars, or cats. All welcome.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"dp-to-contact dp-button-secondary"} -->
<div class="wp-block-button dp-to-contact dp-button-secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Get in touch', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
