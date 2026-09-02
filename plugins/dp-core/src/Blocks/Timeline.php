<?php
/**
 * The `dp/timeline` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

use DP\Core\Content\Timeline\Chart;
use DP\Core\Content\Timeline\Filter;
use DP\Core\Content\Timeline\Geometry;
use DP\Core\Content\Timeline\Lane;
use DP\Core\Content\Timeline\LaneGroup;
use DP\Core\Content\Year;

/**
 * The design's `TimelineChart`, rendered on the server from `dp_role` and `dp_ship`.
 *
 * Three things about this block are deliberate and none of them is obvious.
 *
 * **It is a dynamic block in the plugin, and its stylesheet is in the theme.**
 * That is CLAUDE.md section 2.1's table read literally: a render callback over
 * content is the plugin's, presentation is the theme's. `dp/callout` splits the
 * same way (ADR-0005 section 5) and for the same reason — deactivating the
 * theme must not take the record away, and deactivating the plugin must not
 * leave a page full of blocks WordPress no longer recognises.
 *
 * **The three modes are CSS, not PHP and not JavaScript.** The design measures
 * its own width with a `ResizeObserver` because a design tool cannot express a
 * media query; its own closing note says so and tells the theme to use
 * `@container` instead. So the server renders one markup for all three modes —
 * the year track included, and hidden rather than omitted below 700px — and the
 * stylesheet decides which of them the reader is looking at. A server render
 * cannot know a container's width, and a render that guessed from a user agent
 * string would be wrong on the first resize.
 *
 * **Every row is a `<details>`.** Many open at once is the design's requirement,
 * and it is what `<details>` does natively; so is the disclosure button, its
 * expanded state, and Enter and Space. The whole chart therefore opens, closes
 * and deep-links with JavaScript switched off, which is CLAUDE.md section 1.7,
 * and the script the theme adds is only ever an upgrade: expand-all in one
 * click instead of one navigation, filtering without a round trip, and the URL
 * kept in step.
 *
 * The state a URL can carry is two query args, both read here and both
 * reproduced by the script:
 *
 * - `dp-filter` — `everything`, `roles` or `shipped`.
 * - `dp-open`   — `all`, or a comma-separated list of entry keys.
 */
final class Timeline {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/timeline';

	/**
	 * The query arg carrying the filter.
	 *
	 * @var string
	 */
	public const FILTER_ARG = 'dp-filter';

	/**
	 * The query arg carrying which entries are open.
	 *
	 * @var string
	 */
	public const OPEN_ARG = 'dp-open';

	/**
	 * The `dp-open` value meaning "every entry".
	 *
	 * @var string
	 */
	public const OPEN_ALL = 'all';

	/**
	 * The id the chart carries, and what every link into it points at.
	 *
	 * A constant rather than a generated one, and `block.json` says
	 * `multiple: false` so it stays unique. The design has one chart; a second
	 * one would need a second id, and an id that changed with the number of
	 * charts on a page is an id no link could be written against — including the
	 * ones on the work cards above it.
	 *
	 * @var string
	 */
	public const ROOT_ID = 'dp-timeline';

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * There is no build step: the block has no editor script and no view script
	 * of its own, so `block.json` is checked in where it is registered from
	 * rather than copied through webpack the way `dp/callout` has to be.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/timeline';

	/**
	 * Constructor.
	 *
	 * `$current_year` exists so a test can say what year it is without a clock.
	 * `Geometry` is deliberately WordPress-free and unit-tested, so it may not
	 * call one; the merge queue already records that Brain Monkey cannot stand
	 * in for `time()`. Passing the year in is what makes "the track ends at the
	 * current year" assertable at a year boundary, in both directions, without
	 * anything being mocked. `null` means "ask WordPress", which is what the
	 * plugin does.
	 *
	 * `$today` is the same idea one resolution finer, and it answers a different
	 * question: `$current_year` says where the *track* ends, `$today` says where
	 * an **ongoing** role's bar ends. A role whose `dp_end` is blank runs to
	 * today — which is what the field's own description in the editor has
	 * promised since Phase 2 — and "today" has to carry the month, because
	 * `Year` encodes months as twelfths and stopping at January of the current
	 * year would draw a bar months short of the truth. Null means "ask
	 * WordPress", which is what the plugin does; `Chart` is the one that asks.
	 *
	 * @param string    $plugin_dir   Absolute path to the plugin directory, without a trailing slash.
	 * @param int|null  $current_year The year to treat as now, or null to read the site's clock.
	 * @param Year|null $today        The point in time an unfinished role runs to, or null to read the clock.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly ?int $current_year = null,
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
	 * Render the chart.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		$geometry = $this->geometry( $attributes );
		$lanes    = ( new Chart( $geometry, $this->today ) )->lanes();

		if ( array() === $lanes ) {
			return '';
		}

		$filter = $this->requested_filter();
		$open   = $this->requested_open();
		$rows   = new TimelineRows( $open );

		$body = '';

		/*
		 * Consecutive lanes at one company share a header, so the chart is drawn
		 * as runs rather than as lanes. A run of one is a lane and is drawn as
		 * one; `LaneGroup` decides which is which, and says why only adjacency
		 * counts.
		 */
		foreach ( LaneGroup::consecutive( $lanes ) as $group ) {
			$body .= $rows->group( $group, $filter );
		}

		$card = '<div class="dp-tl-card"><div class="dp-tl-scroller"><div class="dp-tl-inner">'
			. '<div class="dp-tl-head">'
			. $this->legend( $lanes )
			. $this->years( $geometry )
			. '</div>'
			. '<div class="dp-tl-lanes">' . $body . '</div>'
			. '</div></div>'
			. '<div class="dp-tl-swipe">' . esc_html__( 'Swipe the track →', 'dp-core' ) . '</div>'
			. '</div>';

		$wrapper = get_block_wrapper_attributes(
			array(
				'id'             => self::ROOT_ID,
				'class'          => 'dp-timeline ' . $this->mobile_class( $attributes ),
				'data-dp-filter' => $filter->value,
			)
		);

		return '<div ' . $wrapper . '>' . $this->pills( self::ROOT_ID, $filter, $open ) . $card . '</div>';
	}

	/**
	 * The filter pills and the expand-all control, as real links.
	 *
	 * `FilterPills.dc.html` settles the question for the whole site in its own
	 * note — "these are real links to filtered archive URLs, not JS tabs" — so
	 * the timeline's filter is the same thing the category filter already is,
	 * wearing the class the theme's stylesheet already draws. What the design
	 * calls the trailing "extra" button is a link too, for exactly one reason:
	 * with the scripts off it still has to expand every row, and a `<button>`
	 * with no form behind it cannot.
	 *
	 * @param string             $root   The chart's element id, so a click returns to it.
	 * @param Filter             $filter The filter in force.
	 * @param array<int, string> $open The entry keys currently open, or `all`.
	 * @return string
	 */
	private function pills( string $root, Filter $filter, array $open ): string {
		$labels = array(
			Filter::Everything->value => __( 'Everything', 'dp-core' ),
			Filter::Roles->value      => __( 'Roles', 'dp-core' ),
			Filter::Shipped->value    => __( 'Shipped', 'dp-core' ),
		);

		$items = '';

		foreach ( Filter::pills() as $pill ) {
			$href = $pill->is_default()
				? remove_query_arg( self::FILTER_ARG )
				: add_query_arg( self::FILTER_ARG, $pill->value );

			$items .= sprintf(
				'<li class="dp-tl-pill"><a class="dp-tl-filter-link" data-dp-filter="%1$s" href="%2$s"%3$s>%4$s</a></li>',
				esc_attr( $pill->value ),
				esc_url( $href . '#' . $root ),
				$pill === $filter ? ' aria-current="page"' : '',
				esc_html( $labels[ $pill->value ] )
			);
		}

		$all_open = in_array( self::OPEN_ALL, $open, true );
		$expand   = __( 'Expand all', 'dp-core' );
		$collapse = __( 'Collapse all', 'dp-core' );

		/*
		 * The two words are carried on the element rather than repeated in the
		 * script, because they are copy: a translated site has to be able to
		 * swap them without a build, and a script with English in it cannot.
		 */
		$items .= sprintf(
			'<li class="dp-tl-pill dp-tl-pill-extra">'
			. '<a class="dp-tl-toggle-all" data-dp-open="%1$s" data-dp-label-expand="%2$s" data-dp-label-collapse="%3$s" href="%4$s">%5$s</a>'
			. '</li>',
			$all_open ? '' : esc_attr( self::OPEN_ALL ),
			esc_attr( $expand ),
			esc_attr( $collapse ),
			esc_url(
				( $all_open ? remove_query_arg( self::OPEN_ARG ) : add_query_arg( self::OPEN_ARG, self::OPEN_ALL ) ) . '#' . $root
			),
			esc_html( $all_open ? $collapse : $expand )
		);

		return '<ul class="dp-filter-pills dp-tl-pills">' . $items . '</ul>';
	}

	/**
	 * The legend, including a swatch for every lane that carries its own accent.
	 *
	 * @param array<int, Lane> $lanes Every lane on the chart.
	 * @return string
	 */
	private function legend( array $lanes ): string {
		$keys = sprintf(
			'<span class="dp-tl-key"><span class="dp-tl-swatch dp-tl-swatch-role" aria-hidden="true"></span>%s</span>'
			. '<span class="dp-tl-key"><span class="dp-tl-swatch dp-tl-swatch-ship" aria-hidden="true"></span>%s</span>',
			esc_html__( 'Roles', 'dp-core' ),
			esc_html__( 'Shipped', 'dp-core' )
		);

		$seen = array();

		foreach ( $lanes as $lane ) {
			if ( ! $lane->earns_a_swatch() || null === $lane->accent ) {
				continue;
			}

			if ( in_array( $lane->accent->value, $seen, true ) ) {
				continue;
			}

			$seen[] = $lane->accent->value;

			$keys .= sprintf(
				'<span class="dp-tl-key"><span class="dp-tl-swatch dp-tl-swatch-role is-accent-%1$s" aria-hidden="true"></span>%2$s</span>',
				esc_attr( $lane->accent->value ),
				esc_html( $lane->org )
			);
		}

		return '<div class="dp-tl-legend">' . $keys . '</div>';
	}

	/**
	 * The year labels along the top of the track.
	 *
	 * Hidden from assistive technology on purpose. It is an axis, and every row
	 * already prints its own range as text — "2016 — now" — which is the same
	 * information said properly rather than as thirteen loose numbers.
	 *
	 * @param Geometry $geometry The track.
	 * @return string
	 */
	private function years( Geometry $geometry ): string {
		$labels = '';

		foreach ( $geometry->year_labels() as $year ) {
			$labels .= '<span>' . esc_html( (string) $year ) . '</span>';
		}

		return '<div class="dp-tl-years" aria-hidden="true">' . $labels . '</div>';
	}

	/**
	 * The track this chart is drawn against.
	 *
	 * `firstYear` is the design's 2014 and stays declared in `block.json`.
	 * `lastYear` has **no** default there, deliberately: a declared default is
	 * sent on every render, so a `lastYear` of 2026 in `block.json` would have
	 * been indistinguishable from David pinning 2026 and the track could never
	 * have moved on its own. Absent, it means "wherever we are now"; present, it
	 * means David pinned the track — to hold it still, or to run it past the
	 * present for something already scheduled.
	 *
	 * `Geometry::through()` resolves every degenerate pair rather than throwing,
	 * because this runs on a public page. It is also pure, so the year has to
	 * arrive from here.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return Geometry
	 */
	private function geometry( array $attributes ): Geometry {
		return Geometry::through(
			$this->pinned_year( $attributes, 'firstYear' ),
			$this->pinned_year( $attributes, 'lastYear' ),
			$this->this_year()
		);
	}

	/**
	 * One year attribute, or null when the block did not set it.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $name       Which attribute.
	 * @return int|null
	 */
	private function pinned_year( array $attributes, string $name ): ?int {
		$value = $attributes[ $name ] ?? null;

		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * The year it is now, in the site's timezone.
	 *
	 * `wp_date()` rather than `gmdate()` or `date()`: the year the axis ends on
	 * is the year David is living in, and on 31 December a site in Bogotá is
	 * still five hours short of the UTC one. `date()` would read the container's
	 * clock instead, which is a third answer nobody chose.
	 *
	 * The fallback is UTC rather than `Geometry::DESIGN_LAST_YEAR`, which is the
	 * whole point of this change: a wrong-by-hours year is a rounding error one
	 * day a year, and a hardcoded 2026 is a chart that is wrong forever.
	 *
	 * @return int
	 */
	private function this_year(): int {
		if ( null !== $this->current_year ) {
			return $this->current_year;
		}

		$year = wp_date( 'Y' );

		return is_string( $year ) && is_numeric( $year ) ? (int) $year : (int) gmdate( 'Y' );
	}

	/**
	 * The class naming what happens below 700px.
	 *
	 * The Ledger chose stack, so stack is the default and scroll is the
	 * variant — implemented, styled and reachable, but never what a reader gets
	 * unless the block says so.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	private function mobile_class( array $attributes ): string {
		$mode = isset( $attributes['mobileMode'] ) && is_string( $attributes['mobileMode'] )
			? $attributes['mobileMode']
			: 'stack';

		return 'scroll' === $mode ? 'is-mobile-scroll' : 'is-mobile-stack';
	}

	/**
	 * The filter this request asked for.
	 *
	 * @return Filter
	 */
	private function requested_filter(): Filter {
		/*
		 * Read-only, on a public page, with nothing to forge: the value selects
		 * which of three views of already-public content is drawn, and is
		 * checked against a closed enum before anything is done with it. A
		 * nonce here would protect nothing and would break every link somebody
		 * bookmarked.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; see above.
		$raw = isset( $_GET[ self::FILTER_ARG ] ) && is_string( $_GET[ self::FILTER_ARG ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; see above.
			? sanitize_key( wp_unslash( $_GET[ self::FILTER_ARG ] ) )
			: '';

		return Filter::from_request( $raw );
	}

	/**
	 * The entry keys this request asked to be open.
	 *
	 * @return list<string> Entry keys, or a single `all`.
	 */
	private function requested_open(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; see requested_filter().
		$raw = isset( $_GET[ self::OPEN_ARG ] ) && is_string( $_GET[ self::OPEN_ARG ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; see requested_filter().
			? sanitize_text_field( wp_unslash( $_GET[ self::OPEN_ARG ] ) )
			: '';

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$keys = array();

		foreach ( explode( ',', $raw ) as $candidate ) {
			$key = sanitize_key( $candidate );

			if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}

		return in_array( self::OPEN_ALL, $keys, true ) ? array( self::OPEN_ALL ) : $keys;
	}
}
