<?php
/**
 * Title: Work cards
 * Slug: dpaternina/work-card
 * Categories: dpaternina
 * Description: The shipped work David marked featured, as cards. design-source/components/WorkCard.dc.html.
 *
 * @package DP\Theme
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":1,"offset":0,"postType":"dp_ship","order":"desc","orderBy":"date","inherit":false,"dpLoop":"featured-ships"},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-cards","layout":{"type":"default"}} -->
<!-- wp:group {"className":"dp-card","layout":{"type":"default"}} -->
<div class="wp-block-group dp-card"><!-- wp:post-featured-image {"isLink":false,"className":"dp-card__shot"} /-->

<!-- wp:group {"className":"dp-card__body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-card__body"><!-- wp:group {"className":"dp-card__meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group dp-card__meta"><!-- wp:paragraph {"className":"dp-card__year","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"dp_range"}}}}} -->
<p class="dp-card__year"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-card__org","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"dp_stack"}}}}} -->
<p class="dp-card__org"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":3,"isLink":false,"className":"dp-card__title"} /-->

<!-- wp:paragraph {"className":"dp-card__line","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"dp_detail"}}}}} -->
<p class="dp-card__line"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
