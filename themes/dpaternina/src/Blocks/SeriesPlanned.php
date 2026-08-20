<?php
/**
 * "Still to come" — the parts of a series that are not written yet.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Blocks;

use DP\Core\Content\PlannedPart;
use DP\Core\Content\SeriesParts;
use WP_Term;

/**
 * The one section of the series archive no core block can render.
 *
 * A planned part is a **draft post** carrying the series term — `docs/plan.md`
 * section 3.1 — and no block in core queries drafts. `core/query` has no
 * `postStatus` attribute at all, and giving it one through
 * `query_loop_block_query_vars` would hand a `core/post-template` a set of draft
 * posts, at which point `core/post-title` links them and `core/post-excerpt`
 * prints the opening of an unfinished piece of writing. Phase 3 built
 * `SeriesParts` precisely so that cannot happen: `planned()` returns
 * `PlannedPart` objects, which carry a title, a year range, a note and a number,
 * and hold **no post ID**, so there is nothing here that could resolve to a
 * permalink or a body even by accident.
 *
 * Registering this in the theme rather than in `dp-core` follows CLAUDE.md
 * section 2.1's own rule of thumb — "if switching themes would destroy content
 * or break a URL, it is not theme code". Nothing here is content: the drafts,
 * the term and the meta are the plugin's and stay put. What disappears with the
 * theme is one arrangement of them, used by one of this theme's templates.
 * ADR-0005 section 5's objection to theme-registered blocks — that they
 * invalidate saved content — does not reach a block that never appears in any.
 * Hence `inserter => false`: it is chrome, not something to write with.
 *
 * With `dp-core` deactivated the block renders nothing rather than fatalling,
 * which is the same promise the theme makes about Stackable.
 */
final class SeriesPlanned {

	/**
	 * The block name.
	 */
	public const NAME = 'dpaternina/series-planned';

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
			self::NAME,
			array(
				'api_version'     => 3,
				'title'           => __( 'Series — still to come', 'dpaternina' ),
				'category'        => 'theme',
				'description'     => __( "The parts of the series being viewed that have not been published yet. Titles, years and notes only — never a draft's body or its link.", 'dpaternina' ),
				'supports'        => array(
					'inserter'  => false,
					'html'      => false,
					'reusable'  => false,
					'multiple'  => false,
					'className' => false,
				),
				'render_callback' => $this->render( ... ),
			)
		);
	}

	/**
	 * Render the list.
	 *
	 * @return string
	 */
	public function render(): string {
		$parts = $this->parts();

		if ( array() === $parts ) {
			return '';
		}

		$rows = '';

		foreach ( $parts as $part ) {
			$rows .= $this->row( $part );
		}

		return '<div class="dp-planned">' . $rows . '</div>';
	}

	/**
	 * One planned part.
	 *
	 * @param PlannedPart $part The part.
	 * @return string
	 */
	private function row( PlannedPart $part ): string {
		$number = null === $part->part
			/* translators: shown in place of a part number for a part that has not been given one yet. */
			? __( 'Soon', 'dpaternina' )
			/* translators: %d: the part's number within its series. */
			: sprintf( __( 'Part %d', 'dpaternina' ), $part->part );

		return sprintf(
			'<div class="dp-planned__row">'
				. '<p class="dp-planned__part">%1$s</p>'
				. '<div class="dp-planned__body"><h3 class="dp-planned__title">%2$s</h3>%3$s</div>'
				. '%4$s'
			. '</div>',
			esc_html( $number ),
			esc_html( $part->title ),
			'' === trim( $part->note ) ? '' : '<p class="dp-planned__note">' . esc_html( $part->note ) . '</p>',
			'' === trim( $part->years ) ? '' : '<p class="dp-planned__years">' . esc_html( $part->years ) . '</p>'
		);
	}

	/**
	 * The planned parts of the series being viewed.
	 *
	 * @return list<PlannedPart>
	 */
	private function parts(): array {
		if ( ! class_exists( SeriesParts::class ) ) {
			return array();
		}

		$term = get_queried_object();

		if ( ! $term instanceof WP_Term || ! $this->orders_its_posts( $term ) ) {
			return array();
		}

		return ( new SeriesParts() )->planned( $term->term_id );
	}

	/**
	 * Whether a term belongs to a taxonomy whose archive is a reading order.
	 *
	 * The same description `DP\Theme\Query\QueryLoops` uses, and for the same
	 * reason: the theme never repeats a taxonomy slug that `dp-core` owns.
	 *
	 * @param WP_Term $term The queried term.
	 * @return bool
	 */
	private function orders_its_posts( WP_Term $term ): bool {
		$taxonomy = get_taxonomy( $term->taxonomy );

		return false !== $taxonomy
			&& in_array( 'post', (array) $taxonomy->object_type, true )
			&& ! $taxonomy->_builtin
			&& ! $taxonomy->hierarchical;
	}
}
