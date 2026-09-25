/**
 * The Photos page, upgraded.
 *
 * Nothing in this file is required for the page to work. `dp/photo-wall`
 * renders an index of links, a wall of links, a "Show more" link and — for a
 * request carrying `?photo=` — the open photo in a panel with previous and next
 * links, and all of it works with scripting off (CLAUDE.md, ADR-0007). What this
 * adds:
 *
 * 1. **The wall, laid out.** Shortest-column masonry (`photo-layout.js`) in place
 *    of CSS columns, so the newest photos are at the top; it reflows with
 *    transform transitions when the width or the filter changes.
 * 2. **The signature moment.** Hovering or focusing an index entry dims every
 *    photo not in it; when none of its photos is on screen a small hint says how
 *    many are above or below.
 * 3. **Filtering without a page load.** An index entry is fetched rather than
 *    followed, and the wall keeps the tiles it already has, so the ones in both
 *    sets glide to their new places. The URL follows with `pushState`, and Back
 *    works.
 * 4. **The lightbox.** One `<dialog>` for the set: the photo grows from its tile,
 *    arrows, keys and swipes step through the filtered set — loading the next
 *    page when they reach the end of this one — and the URL tracks the open
 *    photo with `replaceState`, so any open photo is a link. The text beside it
 *    is the `<template>` the server rendered after its tile.
 * 5. **The index where there is no room for it.** The spine's panel and the
 *    phone's sheet, whose buttons exist only once this has run.
 * 6. **More photos as you scroll.** The next page is fetched a viewport before
 *    the end of the wall until it holds `AUTO_LOAD_CAP` photos; after that
 *    "Show more" is the way on. The address bar keeps the depth
 *    (`?photos-page=N`, which the server draws as pages 1 to N) and the history
 *    entry keeps the scroll position, so reload and Back come back to the same
 *    place.
 *
 * Which of margin, spine and phone the page is in is the stylesheet's decision
 * (container queries on `.dp-photos`); this reads it back from what is drawn,
 * so there is one definition of the three modes, not two.
 *
 * @since 1.2.0
 */

( function () {
	'use strict';

	const root = document.querySelector( '.dp-pw[data-dp-photos]' );
	const layoutApi = window.dpPhotoLayout;

	if ( ! root || ! layoutApi || ! window.HTMLDialogElement ) {
		return;
	}

	const quiet = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const body = root.querySelector( '.dp-pw-body' );
	const index = root.querySelector( '.dp-pw-index' );
	const wall = root.querySelector( '.dp-pw-wall' );
	const spineButton = root.querySelector( '.dp-pw-spine-button' );
	const dock = root.querySelector( '.dp-pw-dock' );
	const sheet = root.querySelector( '.dp-pw-sheet' );
	const lightbox = root.querySelector( '.dp-pw-lb' );
	const lbImage = lightbox.querySelector( '.dp-pw-lb-img' );
	const lbInfo = lightbox.querySelector( '.dp-pw-lb-info' );
	const lbCount = lightbox.querySelector( '.dp-pw-count' );
	const lbPrev = lightbox.querySelector( '.dp-pw-prev' );
	const lbNext = lightbox.querySelector( '.dp-pw-next' );
	const status = root.querySelector( '.dp-pw-status' );
	const hint = root.querySelector( '.dp-pw-hint' );
	const floatEdit = root.querySelector( '.dp-pw-edit-float' );
	const lbEdit = lightbox.querySelector( '.dp-pw-lb-edit' );

	/** The tile the floating Edit pill belongs to, and its hide timer. */
	let editFor = null;
	let editTimer = 0;
	const PAGE_ARG = root.dataset.pageArg || 'photos-page';
	const PHOTO_ARG = root.dataset.photoArg || 'photo';
	const EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

	/** Fetched pages of the wall, by URL, so a hover can warm a click. */
	const pages = new Map();

	/** Tiles fading out after a filter, and the timer that removes each. */
	const leaving = new WeakMap();

	/** Whether auto-loading has handed the way on back to the link. */
	let autoStopped = false;

	/** Page URLs that came back without moving the wall on. */
	const spent = new Set();

	/** The last layout, which an append continues from. */
	let lastLayout = null;

	/** The tile the lightbox is showing, or null. */
	let current = null;

	/** Where the index lives while the sheet has borrowed it. */
	const indexHome = index.parentNode;
	const indexNext = index.nextSibling;

	/*
	 * Claiming the block moves the index out of the flow (spine, phone); that
	 * is a change of layout, not a moment to animate, so the panel's own
	 * transitions are held off until the page has painted once.
	 */
	root.classList.add( 'is-enhanced', 'is-starting' );
	window.requestAnimationFrame( function () {
		window.requestAnimationFrame( function () {
			root.classList.remove( 'is-starting' );
		} );
	} );

	/* ------------------------------------------------------------- Modes */

	/**
	 * The mode the stylesheet has drawn: margin, spine or phone.
	 *
	 * @return {string} The mode.
	 */
	function mode() {
		if ( window.getComputedStyle( dock ).display !== 'none' ) {
			return 'phone';
		}

		if (
			window.getComputedStyle( spineButton.parentNode ).display !== 'none'
		) {
			return 'spine';
		}

		return 'margin';
	}

	/* ------------------------------------------------------------- The wall */

	/**
	 * Every tile on the wall, in order.
	 *
	 * @return {HTMLAnchorElement[]} The tiles.
	 */
	function tiles() {
		return Array.from( wall.querySelectorAll( 'a.dp-pw-tile' ) );
	}

	/**
	 * Lay the wall out. Tiles move by transform, so a change animates.
	 *
	 * @param {boolean} [instant] Whether to skip the transition.
	 */
	function layout( instant ) {
		const phone = 'phone' === mode();
		const gap = parseFloat( window.getComputedStyle( wall ).columnGap );
		const all = tiles().filter( function ( tile ) {
			return ! tile.classList.contains( 'is-out' );
		} );
		const result = layoutApi.place(
			all.map( function ( tile ) {
				return {
					w: Number( tile.dataset.w ) || 1,
					h: Number( tile.dataset.h ) || 1,
				};
			} ),
			wall.clientWidth,
			{
				target: 260,
				gap: Number.isFinite( gap ) ? gap : 12,
				columns: phone ? 2 : 0,
			}
		);

		if ( instant ) {
			wall.classList.add( 'is-settling' );
		}

		wall.classList.add( 'is-laid' );

		all.forEach( function ( tile, position ) {
			const box = result.boxes[ position ];

			tile.style.width = box.width + 'px';
			tile.style.height = box.height + 'px';
			tile.style.transform =
				'translate(' + box.x + 'px, ' + box.y + 'px)';
		} );

		wall.style.height = result.height + 'px';
		placeEdit();
		lastLayout = result;

		if ( instant ) {
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( function () {
					wall.classList.remove( 'is-settling' );
				} );
			} );
		}
	}

	/**
	 * Place tiles just appended, under the wall as it stands.
	 *
	 * Continues from the last layout rather than laying out everything again,
	 * so a page of forty-eight costs forty-eight placements and not a jolt of
	 * every tile on the wall.
	 *
	 * @param {HTMLElement[]} added The new tiles, in order.
	 */
	function placeNew( added ) {
		if ( ! lastLayout || ! added.length ) {
			layout( true );

			return;
		}

		const result = layoutApi.place(
			added.map( function ( tile ) {
				return {
					w: Number( tile.dataset.w ) || 1,
					h: Number( tile.dataset.h ) || 1,
				};
			} ),
			wall.clientWidth,
			{ from: lastLayout }
		);

		added.forEach( function ( tile, position ) {
			const box = result.boxes[ position ];

			tile.style.transition = 'none';
			tile.style.width = box.width + 'px';
			tile.style.height = box.height + 'px';
			tile.style.transform =
				'translate(' + box.x + 'px, ' + box.y + 'px)';
		} );

		wall.style.height = result.height + 'px';
		placeEdit();
		lastLayout = result;

		window.requestAnimationFrame( function () {
			added.forEach( function ( tile ) {
				tile.style.transition = '';
			} );
		} );
	}

	let lastWidth = 0;

	new window.ResizeObserver( function () {
		if ( wall.clientWidth !== lastWidth ) {
			lastWidth = wall.clientWidth;
			layout( true );

			if ( 'spine' !== mode() ) {
				setIndexOpen( false );
			}
		}
	} ).observe( wall );

	/* -------------------------------------------------- The lit-up index */

	/**
	 * Light one entry's photos, and dim the rest.
	 *
	 * @param {?HTMLAnchorElement} entry The entry, or null to clear.
	 */
	function highlight( entry ) {
		const kind = entry ? entry.dataset.kind : '';

		if ( ! kind || 'phone' === mode() ) {
			wall.removeAttribute( 'data-hl' );
			tiles().forEach( function ( tile ) {
				tile.classList.remove( 'is-hl' );
			} );
			hint.hidden = true;

			return;
		}

		const slug = entry.dataset.slug;
		const lit = tiles().filter( function ( tile ) {
			const on =
				'trip' === kind
					? tile.dataset.trip === slug
					: ( ' ' + tile.dataset.topics + ' ' ).includes(
							' ' + slug + ' '
					  );

			tile.classList.toggle( 'is-hl', on );

			return on;
		} );

		wall.setAttribute( 'data-hl', '' );
		showHint( lit, Number( entry.dataset.count ) || lit.length );
	}

	/**
	 * Say where the lit photos are, when none of them is on screen.
	 *
	 * Photos in the entry but not yet loaded are on a later page, so they count
	 * as below.
	 *
	 * @param {HTMLElement[]} lit   The lit tiles on the wall.
	 * @param {number}        total How many photos the entry has in all.
	 */
	function showHint( lit, total ) {
		let above = 0;
		let below = total - lit.length;

		for ( const tile of lit ) {
			const rect = tile.getBoundingClientRect();

			if ( rect.bottom > 0 && rect.top < window.innerHeight ) {
				hint.hidden = true;

				return;
			}

			if ( rect.bottom <= 0 ) {
				above++;
			} else {
				below++;
			}
		}

		if ( 0 === above + below ) {
			hint.hidden = true;

			return;
		}

		hint.textContent = (
			below >= above ? hint.dataset.below : hint.dataset.above
		).replace( '%s', String( below >= above ? below : above ) );
		hint.hidden = false;
	}

	index.addEventListener( 'pointerover', function ( event ) {
		highlight( event.target.closest( 'a.dp-pw-entry' ) );
		warm( event.target.closest( 'a.dp-pw-entry' ) );
	} );
	index.addEventListener( 'pointerleave', function () {
		highlight( null );
	} );
	index.addEventListener( 'focusin', function ( event ) {
		highlight( event.target.closest( 'a.dp-pw-entry' ) );
	} );
	index.addEventListener( 'focusout', function ( event ) {
		if ( ! index.contains( event.relatedTarget ) ) {
			highlight( null );
		}
	} );

	/* -------------------------------------------------------- Fetching */

	/**
	 * A page of the wall, parsed, fetched once per URL.
	 *
	 * @param {string} url The page's URL.
	 * @return {Promise<Document>} The parsed page.
	 */
	function fetchPage( url ) {
		const key = url.split( '#' )[ 0 ];

		if ( ! pages.has( key ) ) {
			pages.set(
				key,
				window
					.fetch( key, { credentials: 'same-origin' } )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( String( response.status ) );
						}

						return response.text();
					} )
					.then( function ( html ) {
						return new window.DOMParser().parseFromString(
							html,
							'text/html'
						);
					} )
					.catch( function ( error ) {
						pages.delete( key );
						throw error;
					} )
			);
		}

		return pages.get( key );
	}

	/**
	 * Start fetching an entry's page, so a click on it is instant.
	 *
	 * @param {?HTMLAnchorElement} link The entry.
	 */
	function warm( link ) {
		if ( link && link.href ) {
			fetchPage( link.href ).catch( function () {} );
		}
	}

	/* --------------------------------------------------------- Filtering */

	/**
	 * Replace one element with its counterpart from a fetched page.
	 *
	 * @param {Document} doc      The fetched page.
	 * @param {string}   selector What to replace.
	 * @return {?Element} The new element, or null.
	 */
	function adopt( doc, selector ) {
		const mine = root.querySelector( selector );
		const theirs = doc.querySelector( '.dp-pw ' + selector );

		if ( mine && theirs ) {
			const fresh = document.importNode( theirs, true );

			mine.replaceWith( fresh );

			return fresh;
		}

		if ( mine && ! theirs ) {
			mine.remove();
		}

		if ( ! mine && theirs ) {
			const fresh = document.importNode( theirs, true );

			wall.after( fresh );

			return fresh;
		}

		return null;
	}

	/**
	 * Show the wall a fetched page describes, keeping the tiles already here.
	 *
	 * @param {Document} doc The fetched page.
	 */
	function swapIn( doc ) {
		const next = doc.querySelector( '.dp-pw[data-dp-photos]' );

		if ( ! next ) {
			throw new Error( 'No wall in the response.' );
		}

		[ 'total', 'page', 'pages', 'filter', 'filterSlug' ].forEach(
			function ( key ) {
				root.dataset[ key ] = next.dataset[ key ] || '';
			}
		);

		const kept = new Map(
			tiles().map( function ( tile ) {
				return [ tile.dataset.id, tile ];
			} )
		);
		const incoming = Array.from(
			next.querySelectorAll( '.dp-pw-wall > *' )
		);
		const fragment = document.createDocumentFragment();
		const arriving = [];

		incoming.forEach( function ( node ) {
			const old =
				node.matches( 'a.dp-pw-tile' ) && kept.get( node.dataset.id );

			if ( old ) {
				kept.delete( node.dataset.id );

				// A tile still fading out from the last filter is back in.
				window.clearTimeout( leaving.get( old ) );
				leaving.delete( old );
				old.classList.remove( 'is-out' );
				old.href = node.getAttribute( 'href' );
				old.dataset.position = node.dataset.position;
				fragment.append( old );

				return;
			}

			const fresh = document.importNode( node, true );

			if ( fresh.matches( 'a.dp-pw-tile' ) ) {
				fresh.style.opacity = '0';
				arriving.push( fresh );
			}

			fragment.append( fresh );
		} );

		// Tiles leaving fade where they stand, then go.
		kept.forEach( function ( tile ) {
			tile.classList.add( 'is-out' );
			leaving.set(
				tile,
				window.setTimeout(
					function () {
						leaving.delete( tile );
						tile.remove();
					},
					quiet.matches ? 0 : 200
				)
			);
		} );

		wall.querySelectorAll( 'template' ).forEach( function ( node ) {
			node.remove();
		} );
		wall.prepend( fragment );
		kept.forEach( function ( tile ) {
			wall.append( tile );
		} );

		adopt( doc, '.dp-pw-filter' );
		adopt( doc, '.dp-pw-more' );

		// A new filter is a new wall: auto-loading is back in charge of it.
		autoStopped = false;
		root.dataset.version = next.dataset.version || root.dataset.version;
		syncMore();
		watchMore();
		adopt( doc, '.dp-pw-spine-current' );
		adopt( doc, '.dp-pw-dock-open' );
		adopt( doc, '.dp-pw-dock-clear' );

		index.querySelectorAll( 'a.dp-pw-entry' ).forEach( function ( entry ) {
			const on =
				entry.dataset.kind === ( root.dataset.filter || '' ) &&
				entry.dataset.slug === ( root.dataset.filterSlug || '' );

			if ( on ) {
				entry.setAttribute( 'aria-current', 'page' );
			} else {
				entry.removeAttribute( 'aria-current' );
			}
		} );

		layout( false );

		/*
		 * New tiles were inserted transparent and have just been given their
		 * place; reading a layout value commits that as their first style, so
		 * clearing the opacity now fades them in where they stand rather than
		 * flying them in from the wall's corner.
		 */
		if ( arriving.length ) {
			wall.getBoundingClientRect();
			arriving.forEach( function ( tile ) {
				tile.style.opacity = '';
			} );
		}

		announce();
	}

	/**
	 * Tell a screen reader what the wall now shows.
	 */
	function announce() {
		const line = root.querySelector( '.dp-pw-filter' );
		const all = index.querySelector( 'a.dp-pw-entry-all' );

		status.textContent =
			line && ! line.hidden
				? [
						line.querySelector( '.dp-pw-filter-name' ),
						line.querySelector( '.dp-pw-filter-when' ),
						line.querySelector( '.dp-pw-filter-count' ),
				  ]
						.filter( Boolean )
						.map( function ( part ) {
							return part.textContent;
						} )
						.join( ', ' )
				: all.textContent.replace( /(\D)(\d)/, '$1, $2' );
	}

	/**
	 * Filter the wall to what a link points at.
	 *
	 * @param {string}  url  The link's URL.
	 * @param {boolean} push Whether this is a new history entry.
	 * @return {Promise<void>} Settles when the wall has changed.
	 */
	function filterTo( url, push ) {
		root.setAttribute( 'aria-busy', 'true' );

		return fetchPage( url )
			.then( function ( doc ) {
				swapIn( doc );

				if ( push ) {
					window.history.pushState( { dpPhotos: true }, '', url );
				}

				if ( body.getBoundingClientRect().top < 0 ) {
					body.scrollIntoView( {
						behavior: quiet.matches ? 'auto' : 'smooth',
						block: 'start',
					} );
				}
			} )
			.catch( function () {
				// The upgrade failed; do what the link would have done.
				window.location.assign( url );
			} )
			.finally( function () {
				root.removeAttribute( 'aria-busy' );
			} );
	}

	root.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( 'a[data-kind]' );

		if (
			! link ||
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		highlight( null );

		if ( lightbox.open ) {
			lightbox.close();
		}

		if ( sheet.open ) {
			sheet.close();
		}

		filterTo( link.href, true );
	} );

	window.addEventListener( 'popstate', function () {
		const url = new URL( window.location.href );

		if ( url.searchParams.has( PHOTO_ARG ) ) {
			return;
		}

		filterTo( url.href, false );
	} );

	/* --------------------------------------------------------- Show more */

	/** The page being fetched for the wall, so two triggers share one request. */
	let loading = null;

	/**
	 * The URL that returns one page of the wall and nothing before it.
	 *
	 * The link's own href is a depth — pages 1 to N, which is what a reader
	 * with no script and a reload both want. Appending wants page N alone.
	 *
	 * @param {string} href The "Show more" link's href.
	 * @return {string} The URL to fetch.
	 */
	function partUrl( href ) {
		const url = new URL( href );

		url.searchParams.set( root.dataset.partArg || 'photos-part', '1' );

		// The set's change stamp: a changed set is a URL no cache has seen.
		if ( root.dataset.version ) {
			url.searchParams.set(
				root.dataset.versionArg || 'photos-v',
				root.dataset.version
			);
		}
		url.hash = '';

		return url.href;
	}

	/**
	 * Keep the address bar at the wall's depth, so reload and Back return here.
	 *
	 * @param {number} page The deepest page on the wall.
	 */
	function recordDepth( page ) {
		const url = new URL( window.location.href );

		if ( page > 1 ) {
			url.searchParams.set( PAGE_ARG, String( page ) );
		} else {
			url.searchParams.delete( PAGE_ARG );
		}

		url.hash = '';
		window.history.replaceState( window.history.state, '', url.href );
	}

	/**
	 * Say how many photos arrived.
	 *
	 * @param {number} count How many.
	 */
	function announceLoaded( count ) {
		const phrase =
			1 === count ? status.dataset.loadedOne : status.dataset.loadedMany;

		if ( phrase && count > 0 ) {
			status.textContent = phrase.replace( '%s', String( count ) );
		}
	}

	/**
	 * Hand the way on back to the link, for good, on this wall.
	 *
	 * Auto-loading stops; every busy state is cleared; the link is shown as a
	 * plain link, whose click the script no longer intercepts — it navigates to
	 * its depth URL, which the server draws whole. A new filter is a new wall
	 * and starts auto-loading again.
	 */
	function giveUp() {
		autoStopped = true;
		autoLoader.disconnect();
		syncMore();
	}

	/**
	 * Draw the "Show more" row for who is in charge of it.
	 *
	 * While auto-loading is in charge — scripts on, the wall short of
	 * AUTO_LOAD_CAP, and nothing gone wrong — the link is hidden (it stays in
	 * the document, the path without a script), and while a page is on its way
	 * the quiet loading line shows in its place. Otherwise the link shows.
	 */
	function syncMore() {
		const row = root.querySelector( '.dp-pw-more' );

		if ( ! row ) {
			return;
		}

		const link = row.querySelector( 'a.dp-pw-more-link' );
		const note = row.querySelector( '.dp-pw-loading' );
		const auto =
			! autoStopped &&
			layoutApi.shouldAutoLoad( tiles().length, !! link, false );

		row.classList.toggle( 'is-auto', auto );

		if ( note ) {
			note.hidden = ! ( auto && loading );
		}
	}

	/**
	 * Append the next page of the wall, placing only the new tiles.
	 *
	 * One request at a time, never the same URL twice once it has come back
	 * empty, and on any failure the link is handed back (`giveUp()`) rather
	 * than left waiting.
	 *
	 * @return {Promise<HTMLElement[]>} The tiles added.
	 */
	function loadMore() {
		const more = root.querySelector( 'a.dp-pw-more-link' );

		if ( ! more ) {
			return Promise.resolve( [] );
		}

		if ( loading ) {
			return loading;
		}

		const url = partUrl( more.href );
		const expected = Number( more.dataset.page ) || 0;

		if ( spent.has( url ) ) {
			giveUp();

			return Promise.reject( new Error( 'Already came back empty.' ) );
		}

		wall.setAttribute( 'aria-busy', 'true' );

		loading = fetchPage( url )
			.then( function ( doc ) {
				const next = doc.querySelector( '.dp-pw[data-dp-photos]' );
				const incoming = next
					? Array.from( next.querySelectorAll( '.dp-pw-wall > *' ) )
					: [];
				const fresh = incoming.filter( function ( node ) {
					return (
						node.matches( 'a.dp-pw-tile' ) &&
						! wall.querySelector(
							'a.dp-pw-tile[data-id="' + node.dataset.id + '"]'
						)
					);
				} );
				const returned = next ? Number( next.dataset.page ) : null;

				if (
					! layoutApi.pageLanded( fresh.length, expected, returned )
				) {
					spent.add( url );
					throw new Error( 'The page did not move the wall on.' );
				}

				const added = [];

				incoming.forEach( function ( node ) {
					if (
						node.matches( 'a.dp-pw-tile' ) &&
						! fresh.includes( node )
					) {
						return;
					}

					const imported = document.importNode( node, true );

					if ( imported.matches( 'a.dp-pw-tile' ) ) {
						added.push( imported );
					}

					wall.append( imported );
				} );

				adopt( doc, '.dp-pw-more' );
				root.dataset.page = String( returned );
				placeNew( added );
				recordDepth( returned );
				announceLoaded( added.length );

				return added;
			} )
			.catch( function ( error ) {
				loading = null;
				giveUp();
				throw error;
			} )
			.finally( function () {
				loading = null;
				wall.removeAttribute( 'aria-busy' );
				syncMore();
				watchMore();
			} );

		syncMore();

		return loading;
	}

	root.addEventListener( 'click', function ( event ) {
		const more = event.target.closest( 'a.dp-pw-more-link' );

		if (
			! more ||
			autoStopped ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey
		) {
			// Once auto-loading has given up, the link is just a link.
			return;
		}

		event.preventDefault();

		loadMore()
			.then( function ( added ) {
				if ( added[ 0 ] ) {
					added[ 0 ].focus( { preventScroll: true } );
				}
			} )
			.catch( function () {
				window.location.assign( more.href );
			} );
	} );

	/*
	 * The hybrid: while the wall is short of AUTO_LOAD_CAP photos, the next page
	 * is fetched a viewport before the reader reaches the end of the wall; past
	 * it, the link is the only way on and the footer stays reachable.
	 */
	const autoLoader = new window.IntersectionObserver(
		function ( entries ) {
			const seen = entries.some( function ( entry ) {
				return entry.isIntersecting;
			} );

			if (
				seen &&
				! autoStopped &&
				layoutApi.shouldAutoLoad(
					tiles().length,
					!! root.querySelector( 'a.dp-pw-more-link' ),
					!! loading
				)
			) {
				loadMore().catch( function () {} );
			}
		},
		{ rootMargin: '0px 0px 100% 0px' }
	);

	/**
	 * Watch whichever "Show more" link is on the wall now.
	 */
	function watchMore() {
		autoLoader.disconnect();

		// The row, not the link: while auto-loading, the link is hidden, and a
		// hidden element never intersects anything.
		const row = root.querySelector( '.dp-pw-more' );

		if ( row && ! autoStopped ) {
			autoLoader.observe( row );
		}
	}

	/* ------------------------------------------------------ Spine panel */

	/**
	 * Open or close the index panel in spine mode.
	 *
	 * @param {boolean} open Whether it should be open.
	 */
	function setIndexOpen( open ) {
		root.classList.toggle( 'is-index-open', open );
		spineButton.setAttribute( 'aria-expanded', String( open ) );

		if ( open ) {
			const here =
				index.querySelector( 'a[aria-current="page"]' ) ||
				index.querySelector( 'a' );

			window.setTimeout( function () {
				here.focus( { preventScroll: true } );
			}, 0 );
		}
	}

	spineButton.addEventListener( 'click', function () {
		setIndexOpen( ! root.classList.contains( 'is-index-open' ) );
	} );

	index
		.querySelector( '.dp-pw-index-close' )
		.addEventListener( 'click', function () {
			setIndexOpen( false );
			spineButton.focus();
		} );

	document.addEventListener( 'keydown', function ( event ) {
		if (
			'Escape' === event.key &&
			root.classList.contains( 'is-index-open' ) &&
			! lightbox.open
		) {
			setIndexOpen( false );
			spineButton.focus();
		}
	} );

	document.addEventListener( 'pointerdown', function ( event ) {
		if (
			root.classList.contains( 'is-index-open' ) &&
			! event.target.closest( '.dp-pw-index-inner, .dp-pw-spine' )
		) {
			setIndexOpen( false );
		}
	} );

	/* ------------------------------------------------------ Phone sheet */

	root.querySelector( '.dp-pw-dock' ).addEventListener(
		'click',
		function ( event ) {
			if ( ! event.target.closest( '.dp-pw-dock-open' ) ) {
				return;
			}

			sheet.append( index );
			sheet.showModal();

			const here = index.querySelector( 'a[aria-current="page"]' );

			if ( here ) {
				here.focus( { preventScroll: true } );
			}
		}
	);

	sheet.addEventListener( 'click', function ( event ) {
		if ( event.target === sheet ) {
			sheet.close();
		}
	} );

	sheet.addEventListener( 'close', function () {
		indexHome.insertBefore( index, indexNext );
		root.querySelector( '.dp-pw-dock-open' ).focus( {
			preventScroll: true,
		} );
	} );

	// The pill belongs to the wall: it steps aside once the wall has scrolled
	// away, so it never sits over the footer's links.
	new window.IntersectionObserver( function ( entries ) {
		dock.classList.toggle( 'is-away', ! entries[ 0 ].isIntersecting );
	} ).observe( body );

	/* ------------------------------------------------------ Edit pill */

	/*
	 * One Edit link for the whole wall, printed by the server only for someone
	 * who may edit photos, and floated over whichever tile is hovered or
	 * focused — but only a tile that carries `data-edit`, which the server
	 * gives only to photos this user may edit. The link sits in `.dp-pw-body`,
	 * which also holds the wall, so it scrolls with it and needs no tracking;
	 * it is re-placed after anything that moves tiles.
	 *
	 * Keyboard: Tab from a tile with the pill showing goes to the pill, and Tab
	 * from the pill goes on to the next tile, so the pill is the next stop
	 * after the tile it belongs to. Shift-Tab reverses both.
	 */
	/**
	 * Put the pill over a tile's top-right corner.
	 */
	function placeEdit() {
		if ( ! floatEdit || ! editFor || ! editFor.isConnected ) {
			return;
		}

		const base = body.getBoundingClientRect();
		const box = editFor.getBoundingClientRect();

		floatEdit.style.transform =
			'translate(' +
			( box.right - base.left - 8 ) +
			'px, ' +
			( box.top - base.top + 8 ) +
			'px) translateX(-100%)';
	}

	/**
	 * Show the pill over a tile, or hide it.
	 *
	 * @param {?HTMLElement} tile The tile, or null.
	 */
	function showEdit( tile ) {
		if ( ! floatEdit ) {
			return;
		}

		window.clearTimeout( editTimer );

		if ( editFor && editFor !== tile ) {
			editFor.classList.remove( 'is-pointed' );
		}

		if (
			! tile ||
			! tile.dataset.edit ||
			tile.classList.contains( 'is-out' ) ||
			lightbox.open
		) {
			editFor = null;
			floatEdit.hidden = true;
			floatEdit.removeAttribute( 'href' );

			return;
		}

		editFor = tile;
		floatEdit.href = tile.dataset.edit;
		floatEdit.hidden = false;
		placeEdit();
	}

	/**
	 * Hide the pill after a moment, so the pointer can travel onto it.
	 */
	function leaveEdit() {
		window.clearTimeout( editTimer );
		editTimer = window.setTimeout( function () {
			if (
				floatEdit.matches( ':hover, :focus' ) ||
				( editFor && editFor.matches( ':hover, :focus' ) )
			) {
				return;
			}

			showEdit( null );
		}, 250 );
	}

	if ( floatEdit ) {
		wall.addEventListener( 'pointerover', function ( event ) {
			const tile = event.target.closest( 'a.dp-pw-tile' );

			if ( tile ) {
				showEdit( tile );
			}
		} );
		wall.addEventListener( 'pointerleave', leaveEdit );
		wall.addEventListener( 'focusin', function ( event ) {
			showEdit( event.target.closest( 'a.dp-pw-tile' ) );
		} );
		wall.addEventListener( 'focusout', leaveEdit );

		floatEdit.addEventListener( 'pointerenter', function () {
			window.clearTimeout( editTimer );

			// The tile keeps looking hovered while the pointer is on its pill.
			if ( editFor ) {
				editFor.classList.add( 'is-pointed' );
			}
		} );
		floatEdit.addEventListener( 'pointerleave', function () {
			if ( editFor ) {
				editFor.classList.remove( 'is-pointed' );
			}

			leaveEdit();
		} );
		floatEdit.addEventListener( 'focusout', leaveEdit );

		wall.addEventListener( 'keydown', function ( event ) {
			const tile = event.target.closest( 'a.dp-pw-tile' );

			if (
				'Tab' === event.key &&
				! event.shiftKey &&
				tile &&
				tile === editFor &&
				! floatEdit.hidden
			) {
				event.preventDefault();
				floatEdit.focus();
			}
		} );

		floatEdit.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key || ! editFor ) {
				return;
			}

			const shown = tiles().filter( function ( tile ) {
				return ! tile.classList.contains( 'is-out' );
			} );
			const target = event.shiftKey
				? editFor
				: shown[ shown.indexOf( editFor ) + 1 ];

			if ( target ) {
				event.preventDefault();
				target.focus();
			}
		} );

		wall.addEventListener( 'transitionend', function ( event ) {
			if ( event.target === editFor ) {
				placeEdit();
			}
		} );
		window.addEventListener( 'resize', placeEdit );
	}

	/* --------------------------------------------------------- Lightbox */

	/**
	 * The URL of the page with no photo open, keeping the filter.
	 *
	 * @return {string} The URL.
	 */
	function wallUrl() {
		const url = new URL( window.location.href );

		// The depth stays: closing a photo is not leaving the wall.
		url.searchParams.delete( PHOTO_ARG );
		url.hash = '';

		return url.href;
	}

	/**
	 * The URL of the page with one photo open, keeping the filter and depth.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 * @return {string} The URL.
	 */
	function photoUrl( tile ) {
		const url = new URL( window.location.href );

		url.searchParams.set( PHOTO_ARG, tile.dataset.slug );
		url.hash = '';

		return url.href;
	}

	/**
	 * Size the lightbox photo to the largest box that fits the stage.
	 *
	 * The stylesheet already guarantees containment; this makes the element's
	 * box *be* the photo's box, at full size even while the smaller tile image
	 * is standing in, so the grow-from-tile lands exactly and nothing jumps
	 * when the large image arrives.
	 */
	function fit() {
		if ( ! current || ! lightbox.open ) {
			return;
		}

		const stage = lbImage.parentNode;
		const style = window.getComputedStyle( stage );
		const room = {
			w:
				stage.clientWidth -
				parseFloat( style.paddingLeft ) -
				parseFloat( style.paddingRight ),
			h:
				stage.clientHeight -
				parseFloat( style.paddingTop ) -
				parseFloat( style.paddingBottom ),
		};
		const w = Number( current.dataset.w ) || 1;
		const h = Number( current.dataset.h ) || 1;
		const scale = Math.min( room.w / w, room.h / h, 1 );

		lbImage.style.width = Math.max( 0, Math.floor( w * scale ) ) + 'px';
		lbImage.style.height = Math.max( 0, Math.floor( h * scale ) ) + 'px';
	}

	window.addEventListener( 'resize', fit );

	/**
	 * Show one tile's photo in the lightbox.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 */
	function show( tile ) {
		const position = Number( tile.dataset.position ) || 1;
		const total = Number( root.dataset.total ) || tiles().length;
		const details = wall.querySelector(
			'template[data-for="' + tile.dataset.id + '"]'
		);
		const label = tile.getAttribute( 'aria-label' ) || '';

		current = tile;

		// The tile's own image is already decoded, so it paints at once while
		// the large one loads.
		const small = tile.querySelector( 'img' );

		lbImage.removeAttribute( 'srcset' );
		lbImage.src = small ? small.currentSrc || small.src : '';
		lbImage.alt = label;
		lbImage.width = Number( tile.dataset.w ) || 0;
		lbImage.height = Number( tile.dataset.h ) || 0;

		const large = new window.Image();

		large.sizes = lbImage.sizes =
			'phone' === mode() ? '100vw' : 'calc(100vw - 484px)';
		large.srcset = tile.dataset.srcset || '';
		large.src = tile.dataset.src || '';
		large
			.decode()
			.then( function () {
				if ( current === tile ) {
					lbImage.srcset = large.srcset;
					lbImage.src = large.src;
				}
			} )
			.catch( function () {} );

		lbInfo.replaceChildren();

		if ( details ) {
			lbInfo.append( details.content.cloneNode( true ) );
		}

		lightbox.setAttribute( 'aria-label', label );

		if ( lbEdit ) {
			if ( tile.dataset.edit ) {
				lbEdit.href = tile.dataset.edit;
				lbEdit.hidden = false;
			} else {
				lbEdit.removeAttribute( 'href' );
				lbEdit.hidden = true;
			}
		}
		lbCount.textContent =
			String( position ).padStart( 2, '0' ) +
			' / ' +
			String( total ).padStart( 2, '0' );
		lbPrev.disabled = position <= 1;
		lbNext.disabled = position >= total;

		window.history.replaceState(
			window.history.state,
			'',
			photoUrl( tile )
		);

		fit();

		// Warm the next one.
		const after = neighbour( tile, 1 );

		if ( after && after.dataset.src ) {
			const warmNext = new window.Image();

			warmNext.sizes = lbImage.sizes;
			warmNext.srcset = after.dataset.srcset || '';
			warmNext.src = after.dataset.src;
		}
	}

	/**
	 * The tile before or after one, on the wall as loaded.
	 *
	 * @param {HTMLElement} tile      The tile.
	 * @param {number}      direction -1 or 1.
	 * @return {?HTMLAnchorElement} The neighbour.
	 */
	function neighbour( tile, direction ) {
		const all = tiles();

		return all[ all.indexOf( tile ) + direction ] || null;
	}

	/**
	 * Step through the set, loading the next page when this one runs out.
	 *
	 * @param {number} direction -1 or 1.
	 */
	function step( direction ) {
		if ( ! current ) {
			return;
		}

		const next = neighbour( current, direction );

		if ( next ) {
			show( next );

			return;
		}

		if ( direction > 0 && ! lbNext.disabled ) {
			const from = current;

			loadMore().then( function () {
				const loaded = neighbour( from, 1 );

				if ( loaded && current === from ) {
					show( loaded );
				}
			} );
		}
	}

	/**
	 * Open the lightbox on a tile, growing the photo out of it.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 * @param {boolean}           grow Whether to animate from the tile.
	 */
	function openAt( tile, grow ) {
		showEdit( null );
		show( tile );
		lightbox.showModal();
		fit();

		if ( ! grow || quiet.matches ) {
			return;
		}

		const from = tile.getBoundingClientRect();

		window.requestAnimationFrame( function () {
			const to = lbImage.getBoundingClientRect();

			if ( ! to.width || ! to.height ) {
				return;
			}

			lbImage.animate(
				[
					{
						transform:
							'translate(' +
							( from.left - to.left ) +
							'px, ' +
							( from.top - to.top ) +
							'px) scale(' +
							from.width / to.width +
							', ' +
							from.height / to.height +
							')',
						borderRadius: '8px',
					},
					{ transform: 'none', borderRadius: '4px' },
				],
				{ duration: 380, easing: EASE }
			);
			lightbox.animate( [ { opacity: 0 }, { opacity: 1 } ], {
				duration: 200,
				easing: EASE,
			} );
		} );
	}

	wall.addEventListener( 'click', function ( event ) {
		const tile = event.target.closest( 'a.dp-pw-tile' );

		if (
			! tile ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		openAt( tile, true );
	} );

	lbPrev.addEventListener( 'click', function () {
		step( -1 );
	} );
	lbNext.addEventListener( 'click', function () {
		step( 1 );
	} );
	lightbox
		.querySelector( '.dp-pw-lb-close' )
		.addEventListener( 'click', function () {
			lightbox.close();
		} );

	lightbox.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowRight' === event.key ) {
			step( 1 );
		} else if ( 'ArrowLeft' === event.key ) {
			step( -1 );
		}
	} );

	lightbox.addEventListener( 'close', function () {
		window.history.replaceState( window.history.state, '', wallUrl() );

		if ( current ) {
			current.focus( { preventScroll: false } );
		}

		current = null;
	} );

	let swipeFrom = null;

	lightbox
		.querySelector( '.dp-pw-lb-stage' )
		.addEventListener( 'pointerdown', function ( event ) {
			if ( 'mouse' !== event.pointerType ) {
				swipeFrom = event.clientX;
			}
		} );

	lightbox
		.querySelector( '.dp-pw-lb-stage' )
		.addEventListener( 'pointerup', function ( event ) {
			if ( null === swipeFrom ) {
				return;
			}

			const distance = event.clientX - swipeFrom;

			swipeFrom = null;

			if ( Math.abs( distance ) > 50 ) {
				step( distance < 0 ? 1 : -1 );
			}
		} );

	/* ----------------------------------------------------------- Start */

	layout( true );
	syncMore();
	watchMore();

	/*
	 * Coming back to a deep wall. The server has already drawn it to the depth
	 * in the URL; what the browser cannot restore by itself is the scroll
	 * position, because the wall it measured was the scriptless columns, not
	 * this layout. So the position is kept in the history entry and put back
	 * after the layout, and the browser's own attempt is switched off.
	 */
	if ( 'scrollRestoration' in window.history ) {
		window.history.scrollRestoration = 'manual';
	}

	const saved = window.history.state && window.history.state.dpPhotosScroll;

	if ( typeof saved === 'number' && ! root.querySelector( '.dp-pw-panel' ) ) {
		window.scrollTo( 0, saved );
	}

	let scrollTimer = 0;

	window.addEventListener(
		'scroll',
		function () {
			window.clearTimeout( scrollTimer );
			scrollTimer = window.setTimeout( function () {
				window.history.replaceState(
					Object.assign( {}, window.history.state, {
						dpPhotosScroll: window.scrollY,
					} ),
					''
				);
			}, 150 );
		},
		{ passive: true }
	);

	/*
	 * A `?photo=` URL: the server drew the photo in a panel for a reader with no
	 * script. This one has a script, so the panel becomes the lightbox, over the
	 * page the server already opened at that photo's tile.
	 */
	const panel = root.querySelector( '.dp-pw-panel' );

	if ( panel ) {
		const tile = wall.querySelector(
			'a.dp-pw-tile[data-id="' + panel.dataset.id + '"]'
		);

		if ( tile ) {
			panel.remove();
			layout( true );
			openAt( tile, false );
		}
	}
} )();
