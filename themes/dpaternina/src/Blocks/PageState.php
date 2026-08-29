<?php
/**
 * The container that only belongs on some pages of an archive.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Theme\Query\Pagination;

/**
 * Two of the design's containers exist only in a page state, and now say so.
 *
 * `pager.show` is `matching.length > PER_PAGE` and `pager.atEnd` adds a closing
 * panel on the last page only. Neither is a thing a template can express and
 * neither is a thing the block editor can know, because the canvas has no page
 * number — so something has to decide at render time.
 *
 * **What changed is how it is asked for.** It used to be a bare CSS class: a
 * `core/group` carrying `dp-when-paginated` or `dp-when-last-page` had its
 * rendered output replaced with the empty string by a `render_block_core/group`
 * filter. That is ADR-0018 rule 2 exactly — nothing about the class said that
 * PHP would delete the element, it could not be seen in the inspector, and it
 * could not be found by grepping for the symptom. The two classes also read as
 * styling hooks, which is what a class is for; the stylesheet never used either.
 *
 * A named block says it out loud. The state is an attribute with an `enum`, the
 * two states are inserter variations with their own titles, and a template that
 * uses one is readable from the block's name. Nothing is spliced and nothing is
 * parsed: the render callback either returns the inner blocks or does not.
 *
 * **The editor still draws both, always, and that is not a divergence this can
 * close.** ADR-0021 named it and ADR-0008 before that: the canvas has no page
 * number, so there is no supported hook that removes a static block from it for
 * a reason only the server knows. It is the same asymmetry
 * `core/query-no-results` has had since it shipped. What a block buys over a
 * class is that the canvas now *says* why — the block is named "Only on the last
 * page" in the list view — rather than drawing an unexplained container.
 *
 * The wrapper is written here rather than saved, so the saved markup is the
 * inner blocks and nothing else. That is what keeps the block out of the
 * editor's validation path entirely: there is no static wrapper for a future
 * WordPress to disagree with us about.
 */
final class PageState {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/page-state';

	/**
	 * Renders only when the archive runs to more than one page.
	 */
	public const PAGINATED = 'paginated';

	/**
	 * Renders only on the last page of an archive that has more than one.
	 */
	public const LAST_PAGE = 'last-page';

	/**
	 * The last-page state as it appears in block markup.
	 *
	 * Exposed so a test can assert which pattern carries the closing panel
	 * without restating the JSON in two places.
	 */
	public const LAST_PAGE_VARIATION = '"state":"' . self::LAST_PAGE . '"';

	/**
	 * Constructor.
	 *
	 * @param Pagination $pagination Answers what state the archive is in.
	 */
	public function __construct( private readonly Pagination $pagination ) {}

	/**
	 * Attach the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_block( ... ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type(
			get_theme_file_path( 'blocks/page-state' ),
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Draw the container, or nothing.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $content    The inner blocks, already rendered.
	 * @return string
	 */
	public function render( array $attributes = array(), string $content = '' ): string {
		if ( ! $this->holds( $attributes ) ) {
			return '';
		}

		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $content );
	}

	/**
	 * Whether the page being rendered is in the state the block names.
	 *
	 * An unknown state is not a state this page is in, so the container is
	 * dropped. `block.json` declares the attribute with an `enum`, which is what
	 * stops one being saved; this is the same check for markup that was parsed
	 * rather than validated.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return bool
	 */
	private function holds( array $attributes ): bool {
		$state = $attributes['state'] ?? self::PAGINATED;

		return match ( is_string( $state ) ? $state : '' ) {
			self::PAGINATED => $this->pagination->is_paginated(),
			self::LAST_PAGE => $this->pagination->is_last_page(),
			default         => false,
		};
	}
}
