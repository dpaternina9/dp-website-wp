<?php
/**
 * Title: Page hero
 * Slug: dpaternina/page-hero
 * Categories: dpaternina
 * Description: The title, deck and mono caps meta line at the top of a page. design-source/components/PageHero.dc.html.
 *
 * @package DP\Theme
 */

?>
<!-- wp:group {"tagName":"section","className":"dp-hero","layout":{"type":"default"}} -->
<section class="wp-block-group dp-hero"><!-- wp:heading {"level":1,"className":"dp-hero__title"} -->
<h1 class="wp-block-heading dp-hero__title"><?php esc_html_e( 'Writing.', 'dpaternina' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-hero__deck"} -->
<p class="dp-hero__deck"><?php esc_html_e( 'Photography, travel, music, food, and occasional ramblings.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->
