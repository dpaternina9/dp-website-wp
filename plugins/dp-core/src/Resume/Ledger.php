<?php
/**
 * The `dp/resume-ledger` block: Experience, newest first.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

use DP\Core\Content\Timeline\Chart;
use DP\Core\Content\Timeline\Geometry;
use DP\Core\Content\Timeline\Lane;
use DP\Core\Content\Year;

/**
 * The résumé's ledger, read from the same `dp_role` and `dp_ship` the chart uses.
 *
 * The design's own note is that the résumé and the timeline are two views of
 * one record — "the interactive version of this … is the timeline" — so this
 * reads through `Chart` rather than querying for itself. A second query with a
 * second idea of what "published" means is how a résumé ends up listing a job
 * the timeline has already dropped.
 *
 * The one thing it does not take from the chart is the order. The chart is a
 * chronology and runs oldest first; a résumé runs newest first, which is what
 * `dpaternina.dc.html` does too:
 * `LANES.slice().sort((a, b) => b.start - a.start)`.
 *
 * There is no geometry on this page. `Chart` computes bars because that is what
 * it is for, and they are simply not read here — cheaper than a second reader
 * of the same two post types, and it keeps `Chart` the only thing in the
 * project that knows how the record is assembled.
 */
final class Ledger {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/resume-ledger';

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/resume-ledger';

	/**
	 * Constructor.
	 *
	 * `$today` is passed straight through to `Chart`, which is the only reason
	 * it is here. A role whose `dp_end` is blank is still going and its bar runs
	 * to today; if the two readers of that record answered "today" from two
	 * different clocks, the résumé and the timeline could disagree about whether
	 * the current job has ended. One value, handed to one `Chart`. Null means
	 * "ask WordPress", which is what the plugin does.
	 *
	 * @param string    $plugin_dir Absolute path to the plugin directory.
	 * @param Year|null $today      The point in time an unfinished role runs to, or null to read the clock.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly ?Year $today = null
	) {}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type(
			$this->plugin_dir . self::DEFINITION,
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the ledger.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		$lanes = $this->lanes();

		if ( array() === $lanes ) {
			return '';
		}

		$rows = '';

		foreach ( $lanes as $lane ) {
			$rows .= $this->row( $lane );
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'dp-ledger' ) );

		return '<div ' . $wrapper . '>'
			. $this->head( $attributes )
			. '<div class="dp-ledger-rows">' . $rows . '</div>'
			. '</div>';
	}

	/**
	 * Every published role, newest first.
	 *
	 * @return list<Lane>
	 */
	public function lanes(): array {
		$lanes = ( new Chart( new Geometry( Geometry::DESIGN_FIRST_YEAR, Geometry::DESIGN_LAST_YEAR ), $this->today ) )->lanes();

		usort(
			$lanes,
			static fn ( Lane $one, Lane $two ): int => self::started( $two ) <=> self::started( $one )
		);

		return $lanes;
	}

	/**
	 * The section head above the rows.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	private function head( array $attributes ): string {
		$heading = isset( $attributes['heading'] ) && is_string( $attributes['heading'] )
			? $attributes['heading']
			: __( 'Experience', 'dp-core' );

		$meta = isset( $attributes['meta'] ) && is_string( $attributes['meta'] )
			? $attributes['meta']
			: '';

		return '<div class="dp-section-head">'
			. '<h2 class="dp-section-head-heading">' . esc_html( $heading ) . '</h2>'
			. ( '' === $meta ? '' : '<p class="dp-section-head-meta">' . esc_html( $meta ) . '</p>' )
			. '</div>';
	}

	/**
	 * One role.
	 *
	 * The detail is `nl2br( esc_html( … ) )`, in that order and for the reason
	 * `DP\Core\Blocks\TimelineRows` gives at length: the line breaks David typed
	 * into the field survive, and the only markup that can reach the page is the
	 * `<br />` `nl2br()` added after everything else was escaped. `wpautop()`
	 * would emit paragraphs inside `p.dp-ledger-detail`, whose element-qualified
	 * selector is load-bearing for the type scale.
	 *
	 * @param Lane $lane The role and its shipped things.
	 * @return string
	 */
	private function row( Lane $lane ): string {
		return sprintf(
			'<div class="dp-ledger-row">'
			. '<p class="dp-ledger-range">%1$s</p>'
			. '<div class="dp-ledger-body">'
			. '<h3 class="dp-ledger-title">%2$s</h3>'
			. '<p class="dp-ledger-org">%3$s</p>'
			. '%4$s%5$s%6$s'
			. '</div></div>',
			esc_html( $lane->range ),
			esc_html( '' === $lane->title ? $lane->org : $lane->title ),
			esc_html( $lane->org ),
			'' === $lane->detail ? '' : '<p class="dp-ledger-detail">' . nl2br( esc_html( $lane->detail ) ) . '</p>',
			$this->ships( $lane ),
			'' === $lane->stack ? '' : '<p class="dp-ledger-stack">' . esc_html( $lane->stack ) . '</p>'
		);
	}

	/**
	 * The things that shipped out of one role.
	 *
	 * @param Lane $lane The role.
	 * @return string
	 */
	private function ships( Lane $lane ): string {
		if ( ! $lane->has_ships() ) {
			return '';
		}

		$items = '';

		foreach ( $lane->ships as $ship ) {
			$items .= sprintf(
				'<li class="dp-ledger-ship">%1$s <span class="dp-ledger-ship-range">%2$s</span></li>',
				esc_html( $ship->name ),
				esc_html( $ship->range )
			);
		}

		return '<ul class="dp-ledger-ships" role="list">' . $items . '</ul>';
	}

	/**
	 * When a role began, as a decimal year.
	 *
	 * @param Lane $lane The role.
	 * @return float
	 */
	private static function started( Lane $lane ): float {
		$value = get_post_meta( $lane->id, 'dp_start', true );

		return is_numeric( $value ) ? (float) $value : 0.0;
	}
}
