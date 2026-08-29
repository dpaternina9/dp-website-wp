<?php
/**
 * Title: Post list — archive
 * Slug: dpaternina/post-row-archive
 * Categories: dpaternina
 * Description: The same rows as the blog index, with the closing rule the design draws over a term archive and the one-line empty state it uses there instead of the index's panel. design-source/dpaternina.dc.html, the isCategory view.
 *
 * @package DP\Theme
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"className":"dp-query"} -->
<div class="wp-block-query dp-query"><!-- wp:post-template {"className":"dp-rows dp-rows-ruled"} -->
<?php
// Block markup compiled into the theme; there is no path from a request to it.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo DP\Theme\Patterns::post_row();
?>
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"className":"dp-archive-empty"} -->
<p class="dp-archive-empty"><?php esc_html_e( 'Nothing filed here yet.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above.
echo DP\Theme\Patterns::pager();
?>
</div>
<!-- /wp:query -->

<!-- wp:group {"className":"dp-archive-outro","layout":{"type":"default"}} -->
<div class="wp-block-group dp-archive-outro"><!-- wp:paragraph {"className":"dp-archive-outro-line"} -->
<p class="dp-archive-outro-line"><?php esc_html_e( 'That is everything filed under this one. The full archive is in date order, all categories together.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"dp-archive-outro-action"} -->
<div class="wp-block-buttons dp-archive-outro-action"><!-- wp:button {"className":"dp-button-secondary"} -->
<div class="wp-block-button dp-button-secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'All writing', 'dpaternina' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
