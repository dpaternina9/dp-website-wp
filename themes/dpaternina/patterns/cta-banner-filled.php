<?php
/**
 * Title: CTA banner — filled
 * Slug: dpaternina/cta-banner-filled
 * Categories: dpaternina
 * Description: The same banner on a raised surface, for a page that needs it to carry more weight. design-source/components/CtaBanner.dc.html.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"className":"dp-cta-banner dp-cta-banner--filled","layout":{"type":"default"}} -->
<div class="wp-block-group dp-cta-banner dp-cta-banner--filled"><!-- wp:group {"className":"dp-cta-banner__body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-cta-banner__body"><!-- wp:paragraph {"className":"dp-cta-banner__title"} -->
<p class="dp-cta-banner__title"><?php esc_html_e( 'Something worth building?', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-cta-banner__line"} -->
<p class="dp-cta-banner__line"><?php esc_html_e( 'Product work, agency projects, or just a note about espresso, guitars, or cats. All welcome.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"dp-to-contact dp-button--secondary"} -->
<div class="wp-block-button dp-to-contact dp-button--secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Get in touch', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
