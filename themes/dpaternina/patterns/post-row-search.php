<?php
/**
 * Title: Post list — search results
 * Slug: dpaternina/post-row-search
 * Categories: dpaternina
 * Description: The same rows as the archive, with the empty state a search needs — the design has no search view, so this is the archive's own vocabulary answering a URL WordPress supplies rather than a screen the design draws.
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
<!-- wp:group {"className":"dp-empty","layout":{"type":"default"}} -->
<div class="wp-block-group dp-empty"><!-- wp:paragraph {"className":"dp-empty-count","metadata":{"bindings":{"content":{"source":"dpaternina/archive","args":{"key":"count"}}}}} -->
<p class="dp-empty-count"><?php esc_html_e( '0 posts', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2,"className":"dp-empty-title"} -->
<h2 class="wp-block-heading dp-empty-title"><?php esc_html_e( 'Nothing here matched that.', 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"dp-empty-line"} -->
<p class="dp-empty-line"><?php esc_html_e( 'Search reads titles and bodies, so a word I never wrote finds nothing. Try a shorter word, or browse by category instead.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results -->

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above.
echo DP\Theme\Patterns::pager();
?>
</div>
<!-- /wp:query -->
