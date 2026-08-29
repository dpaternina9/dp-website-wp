<?php
/**
 * Title: Closing CTA band
 * Slug: dpaternina/cta-band
 * Categories: dpaternina
 * Description: The glowing "Let's build something." band that closes every view. dpaternina.dc.html, the showCta section.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"dp-cta-band","layout":{"type":"default"}} -->
<section class="wp-block-group alignfull dp-cta-band"><!-- wp:heading {"className":"dp-cta-band-title"} -->
<h2 class="wp-block-heading dp-cta-band-title"><?php esc_html_e( "Let's build something.", 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-cta-band-line"} -->
<p class="dp-cta-band-line"><?php esc_html_e( 'Product work, agency projects, or just a note about espresso, guitars, or cats. All welcome.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"dp-button-lg","metadata":{"name":"Contact link"}} -->
<div class="wp-block-button dp-button-lg"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Say hi', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:group -->
