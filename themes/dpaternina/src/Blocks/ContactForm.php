<?php
/**
 * What the theme adds to `dp/contact-form`.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Contact\ContactForm as ContactFormBlock;
use DP\Theme\Theme;

/**
 * One script, loaded only on the page the form is actually on.
 *
 * The block belongs to `dp-core`, because it is a write path and a theme that
 * owned it would take the contact form away with it (CLAUDE.md section 2.1).
 * The front-end JavaScript is the other half of that same table, and it is the
 * theme's: switching themes should take the `fetch` upgrade with it and leave a
 * form that still posts.
 *
 * **Enqueued from the render, not from `wp_enqueue_scripts`.** The form is on
 * one page. Loading its controller on every page of the site to save a
 * conditional is the pitfall CLAUDE.md names, and `render_block` is the only
 * hook that knows whether the block is on the page being drawn. It fires while
 * the template renders, which is before `wp_footer`, so a footer script still
 * has somewhere to be printed. This is the pattern `DP\Theme\Blocks\Timeline`
 * already established.
 *
 * **The plugin's class name is behind a guard.** Naming it unguarded fatals the
 * theme on a site where `dp-core` is deactivated, which is what a fresh
 * `composer test:integration` leaves behind — it shows up as a 500 on the tests
 * site and an "Unexpected end of JSON input" from `requestUtils.rest`, which
 * names neither cause (ADR-0006 section 5).
 */
final class ContactForm {

	/**
	 * The script handle.
	 */
	public const SCRIPT_HANDLE = 'dpaternina-contact-form';

	/**
	 * The script, relative to the theme root.
	 */
	private const SCRIPT_PATH = 'assets/js/contact-form.js';

	/**
	 * Constructor.
	 *
	 * @param Theme $theme The booted theme, for URLs and cache-busting versions.
	 */
	public function __construct( private readonly Theme $theme ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( class_exists( ContactFormBlock::class ) ) {
			add_filter( 'render_block_' . ContactFormBlock::BLOCK_NAME, $this->enqueue_controller( ... ) );
		}
	}

	/**
	 * Load the controller, because the form is on this page.
	 *
	 * A closed form renders nothing at all, so an empty render is a page with no
	 * form on it and gets no script.
	 *
	 * @param string $content The block's rendered HTML.
	 * @return string The HTML, untouched.
	 */
	public function enqueue_controller( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->theme->url( self::SCRIPT_PATH ),
			array(),
			$this->theme->asset_version( self::SCRIPT_PATH ),
			array(
				// Deferred rather than async: the controller upgrades markup it
				// has to be able to find. CLAUDE.md section 1.7: no render-blocking JS.
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		return $content;
	}
}
