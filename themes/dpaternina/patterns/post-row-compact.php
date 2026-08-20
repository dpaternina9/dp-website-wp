<?php
/**
 * Title: Post list — compact
 * Slug: dpaternina/post-row-compact
 * Categories: dpaternina
 * Description: The tighter row used for "latest writing" and for related lists — title and excerpt, with the category over the date. design-source/components/PostRow.dc.html, variant "compact".
 *
 * @package DP\Theme
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-rows"} -->
<!-- wp:group {"className":"dp-row dp-row-compact","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row dp-row-compact"><!-- wp:group {"className":"dp-row-body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row-body"><!-- wp:post-title {"level":3,"isLink":true,"className":"dp-row-title"} /-->

<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":22,"className":"dp-row-excerpt"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"dp-row-aside","layout":{"type":"default"}} -->
<div class="wp-block-group dp-row-aside"><!-- wp:post-terms {"term":"category","className":"dp-row-cat"} /-->

<!-- wp:post-date {"format":"M j, Y","className":"dp-row-date"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
