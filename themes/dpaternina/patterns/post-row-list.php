<?php
/**
 * Title: Post list
 * Slug: dpaternina/post-row-list
 * Categories: dpaternina
 * Description: The blog index — date, title and excerpt, category — with the design's pager bar, its end-of-archive panel, and the empty state it draws when a filter matches nothing. design-source/components/PostRow.dc.html, variant "list".
 *
 * @package DP\Theme
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-rows"} -->
<?php
// Block markup compiled into the theme; there is no path from a request to it.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo DP\Theme\Patterns::post_row();
?>
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:group {"className":"dp-empty","layout":{"type":"default"}} -->
<div class="wp-block-group dp-empty"><!-- wp:paragraph {"className":"dp-empty-count","metadata":{"bindings":{"content":{"source":"dpaternina/archive","args":{"key":"count"}}}}} -->
<p class="dp-empty-count"><?php esc_html_e( '0 posts', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"className":"dp-empty-title"} -->
<h3 class="wp-block-heading dp-empty-title"><?php esc_html_e( 'Nothing filed under this one yet.', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-empty-line"} -->
<p class="dp-empty-line"><?php esc_html_e( 'I write when there is something worth writing down, which means some categories sit empty for a while. Try another, or read the series instead.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"dp-empty-actions"} -->
<div class="wp-block-buttons dp-empty-actions"><!-- wp:button {"className":"dp-button-secondary"} -->
<div class="wp-block-button dp-button-secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Show everything', 'dpaternina' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"dp-button-ghost"} -->
<div class="wp-block-button dp-button-ghost"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Read the series', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results -->

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above.
echo DP\Theme\Patterns::pager();
?>

<!-- wp:group {"className":"dp-at-end dp-when-last-page","layout":{"type":"default"}} -->
<div class="wp-block-group dp-at-end dp-when-last-page"><!-- wp:paragraph {"className":"dp-at-end-line"} -->
<p class="dp-at-end-line"><?php esc_html_e( 'That is the end of the archive — you have reached the oldest post I kept. The life-story series starts further back than this list goes.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"dp-at-end-action"} -->
<div class="wp-block-buttons dp-at-end-action"><!-- wp:button {"className":"dp-button-secondary"} -->
<div class="wp-block-button dp-button-secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Start the series', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:query -->
