/**
 * The timeline, upgraded.
 *
 * Nothing in this file is required for the chart to work. Every row is a
 * `<details>`, so opening and closing, the disclosure semantics, Enter, Space
 * and the keyboard are the browser's. The filter is three links to the same
 * page with a different query arg, and "expand all" is a fourth. With
 * JavaScript switched off the server reads those args and renders the chart in
 * exactly the state the URL describes, which is CLAUDE.md section 1.7 and is
 * proved in tests/e2e/timeline.spec.ts with scripting disabled.
 *
 * What this adds is four things a round trip does badly:
 *
 * 1. filtering without a page load — the whole record is already in the
 *    document, marked `hidden` where the server filtered it, so switching is an
 *    attribute change;
 * 2. expand and collapse all, in one click rather than one navigation;
 * 3. the URL kept in step with what is open, so the state is copyable;
 * 4. the `WorkCard` above the chart opening its entry in place instead of
 *    reloading the page around it.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const FILTER_ARG = 'dp-filter';
	const OPEN_ARG = 'dp-open';
	const OPEN_ALL = 'all';
	const DEFAULT_FILTER = 'everything';

	const charts = document.querySelectorAll( '.dp-timeline' );

	if ( ! charts.length ) {
		return;
	}

	const quiet = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/**
	 * Show and hide what one filter shows and hides.
	 *
	 * The three rules are the design's own, and DP\Core\Content\Timeline\Filter
	 * is where they are written down: Everything shows all lanes and all ships,
	 * Roles hides the ships, Shipped drops the lanes nothing came out of.
	 *
	 * @param {HTMLElement} chart  The chart's root element.
	 * @param {string}      filter One of everything, roles, shipped.
	 */
	function applyFilter( chart, filter ) {
		chart.querySelectorAll( '.dp-tl-lane' ).forEach( function ( lane ) {
			const shipped = lane.dataset.dpShips === 'yes';
			const rail = lane.querySelector( '.dp-tl-ships' );

			lane.hidden = filter === 'shipped' && ! shipped;

			if ( rail ) {
				rail.hidden = filter === 'roles';
			}
		} );

		/*
		 * A company header whose every role row is hidden is heading nothing, so
		 * it goes with them. Read off the lanes rather than recomputed from the
		 * filter, because the rule for a lane is written once, just above, and
		 * DP\Core\Blocks\TimelineRows::group() does the same on the server.
		 */
		chart.querySelectorAll( '.dp-tl-group' ).forEach( function ( group ) {
			const lanes = Array.from( group.querySelectorAll( '.dp-tl-lane' ) );

			group.hidden =
				lanes.length > 0 &&
				lanes.every( function ( lane ) {
					return lane.hidden;
				} );
		} );

		chart
			.querySelectorAll( '.dp-tl-filter-link' )
			.forEach( function ( link ) {
				if ( link.dataset.dpFilter === filter ) {
					link.setAttribute( 'aria-current', 'page' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );

		chart.dataset.dpFilter = filter;
	}

	/**
	 * Open or close every row at once.
	 *
	 * @param {HTMLElement} chart The chart's root element.
	 * @param {boolean}     open  Whether the rows end up open.
	 */
	function setAll( chart, open ) {
		chart.querySelectorAll( '.dp-tl-row' ).forEach( function ( row ) {
			row.open = open;
		} );
	}

	/**
	 * Put the expand-all control in the state the chart is actually in.
	 *
	 * The two words come from the markup rather than from here, because they
	 * are copy and copy is translated on the server.
	 *
	 * @param {HTMLElement} chart The chart's root element.
	 */
	function refreshToggleAll( chart ) {
		const control = chart.querySelector( '.dp-tl-toggle-all' );

		if ( ! control ) {
			return;
		}

		const rows = chart.querySelectorAll( '.dp-tl-row' );
		const open = chart.querySelectorAll( '.dp-tl-row[open]' );
		const all = rows.length > 0 && rows.length === open.length;

		control.textContent = all
			? control.dataset.dpLabelCollapse
			: control.dataset.dpLabelExpand;
		control.dataset.dpOpen = all ? '' : OPEN_ALL;
	}

	/**
	 * Write the chart's state into the address bar, without a history entry.
	 *
	 * `replaceState` rather than `pushState`: opening a row is not a place, and
	 * the Back button should leave the page rather than close a disclosure.
	 *
	 * @param {HTMLElement} chart The chart's root element.
	 */
	function syncUrl( chart ) {
		const url = new URL( window.location.href );
		const filter = chart.dataset.dpFilter || DEFAULT_FILTER;
		const rows = Array.from( chart.querySelectorAll( '.dp-tl-row' ) );
		const open = rows.filter( function ( row ) {
			return row.open;
		} );

		if ( filter === DEFAULT_FILTER ) {
			url.searchParams.delete( FILTER_ARG );
		} else {
			url.searchParams.set( FILTER_ARG, filter );
		}

		if ( open.length === 0 ) {
			url.searchParams.delete( OPEN_ARG );
		} else if ( open.length === rows.length ) {
			url.searchParams.set( OPEN_ARG, OPEN_ALL );
		} else {
			url.searchParams.set(
				OPEN_ARG,
				open
					.map( function ( row ) {
						return row.id;
					} )
					.join( ',' )
			);
		}

		window.history.replaceState( null, '', url.toString() );
	}

	/**
	 * Open one entry and bring it into view.
	 *
	 * @param {HTMLElement} chart The chart's root element.
	 * @param {string}      id    The entry's element id.
	 * @return {boolean} Whether an entry by that id was found.
	 */
	function openEntry( chart, id ) {
		const row = id ? chart.querySelector( '#' + CSS.escape( id ) ) : null;

		if ( ! row || ! row.classList.contains( 'dp-tl-row' ) ) {
			return false;
		}

		/*
		 * A row hidden by the filter cannot be scrolled to, and a link that
		 * silently did nothing would be worse than one that changed the filter.
		 * Everything is the one filter under which every entry is visible.
		 */
		if ( row.closest( '[hidden]' ) ) {
			applyFilter( chart, DEFAULT_FILTER );
		}

		row.open = true;
		refreshToggleAll( chart );
		syncUrl( chart );

		row.scrollIntoView( {
			behavior: quiet.matches ? 'auto' : 'smooth',
			block: 'center',
		} );

		const summary = row.querySelector( '.dp-tl-summary' );

		if ( summary ) {
			summary.focus( { preventScroll: true } );
		}

		return true;
	}

	charts.forEach( function ( chart ) {
		chart.addEventListener( 'click', function ( event ) {
			const link = event.target.closest( 'a' );

			if ( ! link || ! chart.contains( link ) ) {
				return;
			}

			if ( link.classList.contains( 'dp-tl-filter-link' ) ) {
				event.preventDefault();
				applyFilter( chart, link.dataset.dpFilter || DEFAULT_FILTER );
				syncUrl( chart );

				return;
			}

			if ( link.classList.contains( 'dp-tl-toggle-all' ) ) {
				event.preventDefault();
				setAll( chart, link.dataset.dpOpen === OPEN_ALL );
				refreshToggleAll( chart );
				syncUrl( chart );
			}
		} );

		/*
		 * `toggle` fires for a click, for Enter, for Space and for a row opened
		 * from anywhere else, so the URL is kept in step in one place rather
		 * than in each of the handlers that can open something.
		 */
		chart.addEventListener(
			'toggle',
			function ( event ) {
				if ( ! event.target.classList.contains( 'dp-tl-row' ) ) {
					return;
				}

				refreshToggleAll( chart );
				syncUrl( chart );
			},
			true
		);
	} );

	/*
	 * The design's own note on WorkCard: "clicking a card opens the matching
	 * entry on the timeline below". The card is a link to that entry, carrying
	 * the query arg that opens it on a page load; here it opens it in place.
	 */
	document.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( 'a.dp-card-open' );

		if ( ! link || link.dataset.dpEntry === undefined ) {
			return;
		}

		for ( const chart of charts ) {
			if ( openEntry( chart, link.dataset.dpEntry ) ) {
				event.preventDefault();

				return;
			}
		}
	} );

	/*
	 * A bare fragment — someone's bookmark, or a link from another page — opens
	 * the entry it names. The server already does this for `?dp-open=`; this
	 * covers the plain `#dp-ship-kiveo` form, which no server ever sees.
	 */
	if ( window.location.hash.length > 1 ) {
		const id = window.location.hash.slice( 1 );

		for ( const chart of charts ) {
			if ( openEntry( chart, id ) ) {
				break;
			}
		}
	}

	charts.forEach( refreshToggleAll );
} )();
