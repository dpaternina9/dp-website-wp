<?php
/**
 * Title: Post list
 * Slug: dpaternina/post-row-list
 * Categories: dpaternina
 * Description: The blog index and archive list — date, title and excerpt, category — with pagination and an empty state. design-source/components/PostRow.dc.html, variant "list".
 *
 * @package DP\Theme
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-rows"} -->
<!-- wp:group {"className":"dp-row","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row"><!-- wp:post-date {"format":"M j, Y","className":"dp-row__date"} /-->

<!-- wp:group {"className":"dp-row__body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row__body"><!-- wp:post-title {"level":3,"isLink":true,"className":"dp-row__title"} /-->

<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":24,"className":"dp-row__excerpt"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-row__aside","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row__aside"><!-- wp:post-terms {"term":"category","className":"dp-row__cat"} /-->

<!-- wp:post-date {"format":"M j, Y","className":"dp-row__date"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:group {"className":"dp-empty","layout":{"type":"default"}} -->
<div class="wp-block-group dp-empty"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Nothing filed under this one yet.', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'I write when there is something worth writing down, which means some categories sit empty for a while. Try another, or read the series instead.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"dp-to-posts dp-button--secondary"} -->
<div class="wp-block-button dp-to-posts dp-button--secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Show everything', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results -->

<!-- wp:query-pagination {"className":"dp-pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous {"label":"Prev"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":"Next"} /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:query -->
