<?php
/**
 * Title: Page — the block kit
 * Slug: dpaternina/page-body
 * Categories: dpaternina
 * Description: One of every block a page or a post is allowed to use, in the house style. dpaternina.dc.html, the PAGES fixture.
 *
 * @package DP\Theme
 */

/*
 * The whole allowed vocabulary, once, with the design's Uses page as the copy.
 *
 * Digest section 5.1 lists what a page or a post may contain — `p` `h2` `h3`
 * `h4` `quote` `ul` `ol` `code` `note` `image` `table` `rule` — and section 1
 * says posts and generic pages render through **one** block kit so they cannot
 * drift. This is that kit, as something David inserts, so writing a page in the
 * house style is a choice from the inserter rather than a thing to remember.
 *
 * The page's own eyebrow and deck are deliberately **not** here. They are meta
 * (`dp_updated`, `dp_lead`) and `templates/page.html` binds them, so putting
 * them in the pattern too would draw each of them twice on a seeded page.
 *
 * Uses is the copy because it is the only one of the three design pages that
 * uses every block. Every word of it is placeholder (CLAUDE.md section 6) and is
 * meant to be replaced; the shape is the deliverable.
 */

?>
<!-- wp:paragraph -->
<p><?php esc_html_e( 'People ask about the setup more than anything else I write about, so it lives here instead of in my replies. I update the page when something changes, not on a schedule.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Desk', 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:image -->
<figure class="wp-block-image"><img alt=""/><figcaption class="wp-element-caption"><?php esc_html_e( 'THE DESK, AUGUST 2026', 'dpaternina' ); ?></figcaption></figure>
<!-- /wp:image -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( '16-inch laptop, docked, lid closed, one 27-inch display above it.', 'dpaternina' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'Mechanical keyboard with tactile switches — quiet enough for calls.', 'dpaternina' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'A notebook, because I still think better with a pen for the first ten minutes.', 'dpaternina' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'What I build with', 'dpaternina' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'The stack has not changed much in three years, which I take as a good sign.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:table {"hasFixedLayout":true} -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th><?php esc_html_e( 'Tool', 'dpaternina' ); ?></th><th><?php esc_html_e( 'For', 'dpaternina' ); ?></th><th><?php esc_html_e( 'Since', 'dpaternina' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'Neovim', 'dpaternina' ); ?></td><td><?php esc_html_e( 'Everything I type into a repo', 'dpaternina' ); ?></td><td>2019</td></tr><tr><td><?php esc_html_e( 'Laravel + Vue', 'dpaternina' ); ?></td><td><?php esc_html_e( 'Client work and internal tools', 'dpaternina' ); ?></td><td>2017</td></tr><tr><td><?php esc_html_e( 'SwiftUI', 'dpaternina' ); ?></td><td><?php esc_html_e( 'The iOS side of my own products', 'dpaternina' ); ?></td><td>2023</td></tr><tr><td><?php esc_html_e( 'Figma', 'dpaternina' ); ?></td><td><?php esc_html_e( 'Interface work before it becomes code', 'dpaternina' ); ?></td><td>2020</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Small utilities that earn their keep', 'dpaternina' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( 'A clipboard history tool — the single biggest time saver on this list.', 'dpaternina' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'A window manager bound to muscle memory.', 'dpaternina' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'One password manager, one terminal, no launchers I have to configure.', 'dpaternina' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><?php esc_html_e( 'A MONO CAPS ASIDE', 'dpaternina' ); ?></h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'The camera is one body and one 35mm prime. The guitar is one guitar. The coffee setup is deliberately unremarkable and produces the same shot every morning.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p><?php esc_html_e( 'A quotation, which the house style allows two of per post and no more.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph --><cite><?php esc_html_e( 'Somebody worth quoting', 'dpaternina' ); ?></cite></blockquote>
<!-- /wp:quote -->

<!-- wp:code {"dpLabel":"ESPRESSO"} -->
<pre class="wp-block-code"><code><?php esc_html_e( '18g in · 36g out · 28s · 93°C', 'dpaternina' ); ?></code></pre>
<!-- /wp:code -->

<!-- wp:dp/callout -->
<div class="wp-block-dp-callout dp-callout"><span class="dp-callout-label"><?php esc_html_e( 'NOTE', 'dpaternina' ); ?></span><p class="dp-callout-text"><?php esc_html_e( 'No affiliate links anywhere on this page. If you want a specific model number, ask me and I will tell you what I paid.', 'dpaternina' ); ?></p></div>
<!-- /wp:dp/callout -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Last thing: none of this matters as much as sitting down and doing the work. The tools are just what happened to be nearby when I did.', 'dpaternina' ); ?></p>
<!-- /wp:paragraph -->
