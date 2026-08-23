<?php
/**
 * Title: Work cards
 * Slug: dpaternina/work-card
 * Categories: dpaternina
 * Description: The shipped work David marked featured, as cards. design-source/components/WorkCard.dc.html.
 *
 * @package DP\Theme
 */

/*
 * The card face carries exactly four things, and the design's `featuredWork`
 * fixture names all four: `year`, `org`, `title`, `line`.
 *
 *   year  -> dp_range   the range as it is printed, uppercased by the stylesheet
 *   org   -> org        derived: the title of the role this ship hangs off
 *   title -> the post title, which is also the link into the timeline
 *   line  -> dp_line    the card's own sentence
 *
 * `stack` is in the same fixture entry and is deliberately **not** here: the
 * design prints it in the timeline's expanded panel (`.dp-tl-stack`), not on the
 * card. Nor is `detail`, which is the panel's paragraph — Kiveo's begins "One
 * line on what Kiveo does … copy to come", which is not a card face.
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":1,"offset":0,"postType":"dp_ship","order":"desc","orderBy":"date","inherit":false,"dpLoop":"featured-ships"},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-cards","layout":{"type":"default"}} -->
<!-- wp:group {"className":"dp-card","layout":{"type":"default"}} -->
<div class="wp-block-group dp-card"><!-- wp:post-featured-image {"isLink":false,"className":"dp-card-shot"} /-->

<!-- wp:group {"className":"dp-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group dp-card-body"><!-- wp:group {"className":"dp-card-meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group dp-card-meta"><!-- wp:paragraph {"className":"dp-card-year","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"dp_range"}}}}} -->
<p class="dp-card-year"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"dp-card-org","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"org"}}}}} -->
<p class="dp-card-org"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":3,"isLink":false,"className":"dp-card-title dp-card-open"} /-->

<!-- wp:paragraph {"className":"dp-card-line","metadata":{"bindings":{"content":{"source":"dpaternina/post","args":{"key":"dp_line"}}}}} -->
<p class="dp-card-line"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
