<?php
/**
 * One row of the timeline, and everything inside it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

use DP\Core\Content\Timeline\Bar;
use DP\Core\Content\Timeline\Filter;
use DP\Core\Content\Timeline\Lane;
use DP\Core\Content\Timeline\LaneGroup;
use DP\Core\Content\Timeline\Ship;

/**
 * Draws a lane, its shipped things, and the panels behind both.
 *
 * Split out of `Timeline` because the shell — pills, legend, axis — is one
 * shape and a row is another, and because every escaping decision in the chart
 * is in this file.
 *
 * Two rules run through all of it.
 *
 * **A filtered-out row is `hidden`, not absent.** `[hidden]` is honoured by
 * every user agent's own stylesheet, so with the scripts off the three filters
 * show exactly what the design says they show. With the scripts on, the whole
 * record is already in the document, so switching filter is an attribute
 * change rather than a page load — which is what "query-arg links, upgraded to
 * instant" has to mean if the upgraded version is to behave like the plain one.
 * Rendering only the visible rows would make the plain version correct and the
 * upgraded one impossible.
 *
 * **Geometry is the only inline style.** `Bar::style()` writes four numbers that
 * no stylesheet could hold; colour arrives as a class the theme maps to a token,
 * because a hex value written into markup is a value nobody can re-check
 * (CLAUDE.md section 5).
 *
 * **Where markup is added, it is added after the escaping.** The two detail
 * paragraphs are `nl2br( esc_html( … ) )`, in that order: a line break David
 * typed into the field is a break on the page, and the only tags that can reach
 * the document are the `<br />`s `nl2br()` itself put there. Reversing the two
 * would escape those breaks back into text and let anything in the value
 * through, so the order is the whole of the safety. `Maintenance\Screen` writes
 * the same sequence for the same reason. `wpautop()` is deliberately not used:
 * these strings already sit inside a `p.dp-tl-prose`, whose element-qualified
 * selector is what carries the design's declared line-height past `theme.json`'s
 * root one, and nested paragraphs would mean changing that element.
 */
final class TimelineRows {

	/**
	 * The chevron the stack mode draws, from the design's own markup.
	 *
	 * @var string
	 */
	private const CHEVRON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
		. ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="m6 9 6 6 6-6"></path></svg>';

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $open The entry keys to render open, or a single `all`.
	 */
	public function __construct( private readonly array $open ) {}

	/**
	 * One run of lanes: a bare lane, or a company header with lanes under it.
	 *
	 * A run of one is drawn exactly as a lane has always been drawn — the same
	 * element, the same attributes, the same order — because a company with one
	 * role is every company on the chart but two and none of them should change.
	 * `LaneGroup` is what decides which of the two this is; see its class
	 * docblock for why only adjacent lanes ever share a header.
	 *
	 * The header is a plain row and **not** a second `<details>` around the
	 * first. Nesting disclosures would make every role row openable-but-invisible
	 * inside a closed parent, would break the `dp-open` deep links that already
	 * point at those rows, and would put a company name where a heading is
	 * announced twice. So the group is a `role="group"` named by its header, the
	 * role rows stay independently openable, and nothing about `dp-open` changes.
	 *
	 * @param LaneGroup $group  The run.
	 * @param Filter    $filter The filter in force.
	 * @return string
	 */
	public function group( LaneGroup $group, Filter $filter ): string {
		if ( ! $group->is_shared() ) {
			return $this->lane( $group->first, $filter, false );
		}

		$lanes = '';
		$shown = false;

		foreach ( $group->lanes as $lane ) {
			$lanes .= $this->lane( $lane, $filter, true );
			$shown  = $shown || $filter->shows_lane( $lane->has_ships() );
		}

		/*
		 * A header whose every role row is hidden is a company heading nothing,
		 * so it is hidden too — `hidden` rather than absent, for the reason every
		 * other filtered row is: the record stays in the document and the script
		 * switches filter by changing an attribute.
		 */
		return sprintf(
			'<div class="dp-tl-group" role="group" aria-labelledby="%1$s"%2$s>%3$s'
			. '<div class="dp-tl-group-lanes">%4$s</div></div>',
			esc_attr( $group->key ),
			$shown ? '' : ' hidden',
			$this->group_head( $group ),
			$lanes
		);
	}

	/**
	 * The company header: the name, and the run's whole span on the track.
	 *
	 * The span is `LaneGroup`'s combined bar — earliest start to latest end,
	 * taken from the lanes' own bars — drawn in the group's own track, on the
	 * same grid as every other row. It cannot move or rescale a role bar,
	 * because it is not on a role's row: the role bars are still exactly where
	 * `Geometry` put them, and the axis goes on reading truthfully (ADR-0014).
	 *
	 * No heading element and no `<summary>`: the header names a group, it does
	 * not open one, and a heading here would either be announced twice inside
	 * the disclosure or invent an outline level the page never asked for.
	 *
	 * @param LaneGroup $group The run.
	 * @return string
	 */
	private function group_head( LaneGroup $group ): string {
		$accent = null === $group->accent ? '' : ' is-accent-' . $group->accent->value;

		$label = '<span class="dp-tl-label"><span class="dp-tl-org" id="' . esc_attr( $group->key ) . '">'
			. esc_html( $group->org ) . '</span></span>';

		$track = null === $group->bar
			? ''
			: sprintf(
				'<span class="dp-tl-track" aria-hidden="true"><span class="dp-tl-bar dp-tl-bar-group" style="%s"></span></span>',
				esc_attr( $group->bar->style() )
			);

		return sprintf(
			'<div class="dp-tl-group-head%1$s"><span class="dp-tl-grid">%2$s%3$s</span></div>',
			esc_attr( $accent ),
			$label,
			$track
		);
	}

	/**
	 * One lane: the role's row, then the rail its shipped things hang off.
	 *
	 * @param Lane   $lane    The lane.
	 * @param Filter $filter  The filter in force.
	 * @param bool   $grouped Whether a company header above it already names the organisation.
	 * @return string
	 */
	private function lane( Lane $lane, Filter $filter, bool $grouped ): string {
		$rows = '';

		foreach ( $lane->ships as $ship ) {
			$rows .= $this->ship( $ship );
		}

		$rail = '' === $rows
			? ''
			: sprintf( '<div class="dp-tl-ships"%s>%s</div>', $filter->shows_ships() ? '' : ' hidden', $rows );

		return sprintf(
			'<div class="dp-tl-lane" data-dp-lane="%1$s" data-dp-ships="%2$s"%3$s>%4$s%5$s</div>',
			esc_attr( $lane->key ),
			$lane->has_ships() ? 'yes' : 'no',
			$filter->shows_lane( $lane->has_ships() ) ? '' : ' hidden',
			$this->role_row( $lane, $grouped ),
			$rail
		);
	}

	/**
	 * The role's own disclosure.
	 *
	 * `.dp-tl-org` is the row's *name*, which is why a shipped thing already
	 * uses it for a project. Under a company header the row's name is the job
	 * title, and the organisation is not repeated — that repetition is the whole
	 * defect this closes. A lane with no job title keeps the organisation, so a
	 * half-finished post is a row with a name rather than a row with none.
	 *
	 * @param Lane $lane    The lane.
	 * @param bool $grouped Whether a company header above it already names the organisation.
	 * @return string
	 */
	private function role_row( Lane $lane, bool $grouped ): string {
		$named = $grouped && '' !== $lane->title ? $lane->title : $lane->org;
		$title = $grouped ? '' : $lane->title;

		$label = '<span class="dp-tl-org">' . esc_html( $named ) . '</span>'
			. ( '' === $title ? '' : '<span class="dp-tl-role">' . esc_html( $title ) . '</span>' )
			. ( '' === $lane->range ? '' : '<span class="dp-tl-range">' . esc_html( $lane->range ) . '</span>' )
			. '<span class="dp-tl-chevron" aria-hidden="true">' . self::CHEVRON . '</span>';

		$detail = '<div class="dp-tl-detail"><div class="dp-tl-kind">' . esc_html__( 'Role', 'dp-core' ) . '</div>'
			. '<div class="dp-tl-detail-body">'
			. ( '' === $lane->detail ? '' : '<p class="dp-tl-prose">' . nl2br( esc_html( $lane->detail ) ) . '</p>' )
			. ( '' === $lane->stack ? '' : '<div class="dp-tl-stack">' . esc_html( $lane->stack ) . '</div>' )
			. '</div></div>';

		$accent = null === $lane->accent ? '' : ' is-accent-' . $lane->accent->value;

		return sprintf(
			'<details class="dp-tl-row dp-tl-row-role%1$s" id="%2$s"%3$s>%4$s%5$s</details>',
			esc_attr( $accent ),
			esc_attr( $lane->key ),
			$this->is_open( $lane->key ) ? ' open' : '',
			$this->summary( $label, $lane->bar ),
			$detail
		);
	}

	/**
	 * One shipped thing's disclosure, with the expanded panel behind it.
	 *
	 * @param Ship $ship The shipped thing.
	 * @return string
	 */
	private function ship( Ship $ship ): string {
		$label = '<span class="dp-tl-org">' . esc_html( $ship->name ) . '</span>'
			. ( '' === $ship->range ? '' : '<span class="dp-tl-range">' . esc_html( $ship->range ) . '</span>' )
			. '<span class="dp-tl-chevron" aria-hidden="true">' . self::CHEVRON . '</span>';

		return sprintf(
			'<details class="dp-tl-row dp-tl-row-ship" id="%1$s"%2$s>%3$s%4$s</details>',
			esc_attr( $ship->key ),
			$this->is_open( $ship->key ) ? ' open' : '',
			$this->summary( $label, $ship->bar ),
			$this->panel( $ship )
		);
	}

	/**
	 * The clickable half of a row: the label column and the bar beside it.
	 *
	 * The design gives the label cell and the bar the same click handler, so
	 * both live inside the `<summary>` and both toggle the row. Everything in
	 * here is phrasing content — spans laid out as a grid by the stylesheet —
	 * because a `<summary>` is not a place for flow content, and because a
	 * heading inside a disclosure button is announced twice.
	 *
	 * @param string   $label The label column's inner markup, already escaped.
	 * @param Bar|null $bar   The computed bar, or null when the dates are unusable.
	 * @return string
	 */
	private function summary( string $label, ?Bar $bar ): string {
		$track = null === $bar
			? ''
			: sprintf(
				'<span class="dp-tl-track" aria-hidden="true"><span class="dp-tl-bar" style="%s"></span></span>',
				esc_attr( $bar->style() )
			);

		return '<summary class="dp-tl-summary"><span class="dp-tl-grid">'
			. '<span class="dp-tl-label">' . $label . '</span>'
			. $track
			. '</span></summary>';
	}

	/**
	 * The expanded panel for a shipped thing.
	 *
	 * @param Ship $ship The shipped thing.
	 * @return string
	 */
	private function panel( Ship $ship ): string {
		$main = '';

		if ( '' !== $ship->range ) {
			$main .= '<div class="dp-tl-shipped">'
				/* translators: %s: the range a piece of work covers, e.g. "2023 — now". */
				. esc_html( sprintf( __( 'Shipped · %s', 'dp-core' ), $ship->range ) )
				. '</div>';
		}

		if ( '' !== $ship->headline ) {
			$main .= '<h3 class="dp-tl-headline">' . esc_html( $ship->headline ) . '</h3>';
		}

		if ( '' !== $ship->detail ) {
			$main .= '<p class="dp-tl-prose">' . nl2br( esc_html( $ship->detail ) ) . '</p>';
		}

		$main .= $this->bullets( $ship->bullets ) . $this->facts( $ship );

		$aside = $this->artifact( $ship ) . $this->stats( $ship );

		return '<div class="dp-tl-panel"><div class="dp-tl-panel-cols">'
			. '<div class="dp-tl-panel-main">' . $main . '</div>'
			. ( '' === $aside ? '' : '<div class="dp-tl-panel-aside">' . $aside . '</div>' )
			. '</div></div>';
	}

	/**
	 * The constraints that shaped a piece of work.
	 *
	 * @param array<int, string> $bullets The lines.
	 * @return string
	 */
	private function bullets( array $bullets ): string {
		if ( array() === $bullets ) {
			return '';
		}

		$items = '';

		foreach ( $bullets as $bullet ) {
			$items .= '<li class="dp-tl-bullet">' . esc_html( $bullet ) . '</li>';
		}

		/*
		 * `role="list"` is stated rather than inherited. The stylesheet draws the
		 * design's em dash instead of a bullet, which needs `list-style: none`,
		 * and removing a list's markers is exactly what stops Safari and
		 * VoiceOver announcing it as a list. ADR-0005 section 7 hit this in the
		 * house style and put the role back through a filter; a list this
		 * package writes itself can simply say so.
		 */
		return '<ul class="dp-tl-bullets" role="list">' . $items . '</ul>';
	}

	/**
	 * The three-column footer: what David did, the stack, and the write-up.
	 *
	 * @param Ship $ship The shipped thing.
	 * @return string
	 */
	private function facts( Ship $ship ): string {
		$facts = '';

		if ( '' !== $ship->role ) {
			$facts .= $this->fact( __( 'My role', 'dp-core' ), '<span>' . esc_html( $ship->role ) . '</span>', '' );
		}

		if ( '' !== $ship->stack ) {
			$facts .= $this->fact( __( 'Stack', 'dp-core' ), '<span>' . esc_html( $ship->stack ) . '</span>', 'dp-tl-fact-stack' );
		}

		if ( $ship->has_writeup() ) {
			$facts .= $this->fact(
				__( 'Write-up', 'dp-core' ),
				sprintf(
					'<a class="dp-tl-writeup" href="%1$s">%2$s</a>',
					esc_url( $ship->writeup_url ),
					esc_html__( 'Read the post →', 'dp-core' )
				),
				''
			);
		}

		return '' === $facts ? '' : '<div class="dp-tl-facts">' . $facts . '</div>';
	}

	/**
	 * One labelled fact.
	 *
	 * @param string $label   The mono caps label.
	 * @param string $value   The value's markup, already escaped.
	 * @param string $variant An extra class, or ''.
	 * @return string
	 */
	private function fact( string $label, string $value, string $variant ): string {
		return sprintf(
			'<div class="dp-tl-fact"><div class="dp-tl-fact-label">%1$s</div><div class="dp-tl-fact-value%2$s">%3$s</div></div>',
			esc_html( $label ),
			esc_attr( '' === $variant ? '' : ' ' . $variant ),
			$value
		);
	}

	/**
	 * The preformatted sample beside the copy.
	 *
	 * @param Ship $ship The shipped thing.
	 * @return string
	 */
	private function artifact( Ship $ship ): string {
		if ( '' === trim( $ship->artifact ) ) {
			return '';
		}

		return sprintf(
			'<div class="dp-tl-artifact"><div class="dp-tl-artifact-head">'
			. '<span class="dp-tl-artifact-label">%1$s</span>'
			. '<span class="dp-tl-artifact-tag">%2$s</span></div>'
			. '<pre class="dp-tl-artifact-body">%3$s</pre></div>',
			esc_html( $ship->artifact_label ),
			esc_html__( 'Example', 'dp-core' ),
			esc_html( $ship->artifact )
		);
	}

	/**
	 * The two statistics.
	 *
	 * @param Ship $ship The shipped thing.
	 * @return string
	 */
	private function stats( Ship $ship ): string {
		$tiles = $this->stat( $ship->stat1, $ship->stat1_label, 'dp-tl-stat-loud' )
			. $this->stat( $ship->stat2, $ship->stat2_label, 'dp-tl-stat-quiet' );

		return '' === $tiles ? '' : '<div class="dp-tl-stats">' . $tiles . '</div>';
	}

	/**
	 * One statistic tile.
	 *
	 * @param string $value   The figure, which the fixture often leaves as an em dash.
	 * @param string $label   What it counts.
	 * @param string $variant The tile's class.
	 * @return string
	 */
	private function stat( string $value, string $label, string $variant ): string {
		if ( '' === trim( $value ) && '' === trim( $label ) ) {
			return '';
		}

		return sprintf(
			'<div class="dp-tl-stat %1$s"><div class="dp-tl-stat-value">%2$s</div><div class="dp-tl-stat-label">%3$s</div></div>',
			esc_attr( $variant ),
			esc_html( $value ),
			esc_html( $label )
		);
	}

	/**
	 * Whether one entry renders open.
	 *
	 * @param string $key The entry key.
	 * @return bool
	 */
	private function is_open( string $key ): bool {
		return in_array( Timeline::OPEN_ALL, $this->open, true ) || in_array( $key, $this->open, true );
	}
}
