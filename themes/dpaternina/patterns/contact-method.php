<?php
/**
 * Title: Contact method
 * Slug: dpaternina/contact-method
 * Categories: dpaternina
 * Description: One labelled row — EMAIL, and the address — as a whole-row link. design-source/components/ContactMethod.dc.html.
 *
 * @package DP\Theme
 */

?>
<!-- wp:buttons {"className":"dp-contact-method"} -->
<div class="wp-block-buttons dp-contact-method"><!-- wp:button {"width":100} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link wp-element-button" href="#"><span class="dp-label"><?php esc_html_e( 'Email', 'dpaternina' ); ?></span> <span><?php esc_html_e( 'hello@example.com', 'dpaternina' ); ?></span></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
