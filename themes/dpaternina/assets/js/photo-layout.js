/**
 * Where each photo goes on the wall. Arithmetic only — no DOM.
 *
 * Shortest-column placement, which is what keeps the wall reading newest first:
 * each photo, in order, goes into whichever column is currently shortest, so the
 * top of the wall is always the newest photos rather than the first column's
 * worth of them (CSS columns, the no-script layout, fill one column at a time).
 *
 * Two rules on top of that, both the prototype's, both in named constants:
 *
 * - A **panorama** (wider than 2.2 : 1) spans two columns, when there are more
 *   than two — on a two-column phone wall it would be a full-width strip.
 * - A **very tall** photo is capped at 1.8 × the column width and cropped with
 *   `object-fit`, in the grid only; the pop-up always shows the whole frame.
 *   `TALL_CAP` is the one place to change it, and `Infinity` switches the cap off.
 *
 * An append continues from the previous result (`options.from`), so a new page
 * is placed under the wall without moving a single tile already on it.
 *
 * Kept apart from `photos.js` so it can be unit-tested without a browser
 * (`tests/js/photos/layout.test.js`). Loaded first; `photos.js` reads it from
 * `window.dpPhotoLayout`.
 *
 * @since 1.2.0
 */

( function () {
	'use strict';

	const scope = typeof window === 'undefined' ? globalThis : window;

	/** Wider than this, a photo spans two columns. */
	const PANORAMA = 2.2;

	/**
	 * Taller than this many column widths, a tile is cropped to it.
	 *
	 * The brief's open question ("tall-photo cap vs never-crop") is answered
	 * here and nowhere else; set it to Infinity to never crop.
	 */
	const TALL_CAP = 1.8;

	/**
	 * How many photos the wall may hold before it stops loading more by itself.
	 *
	 * Below this, reaching the end of the wall fetches the next page before the
	 * reader gets there; at or past it, "Show more" is the only way on, so the
	 * footer can be reached. Counted per filter: a new filter is a new wall.
	 */
	const AUTO_LOAD_CAP = 150;

	/**
	 * Whether the wall should fetch the next page on its own.
	 *
	 * @param {number}  shown   Photos on the wall now.
	 * @param {boolean} hasMore Whether there is a next page.
	 * @param {boolean} busy    Whether a page is already loading.
	 * @param {number}  [cap]   The cap, AUTO_LOAD_CAP unless a test says otherwise.
	 * @return {boolean} Whether to load.
	 */
	function shouldAutoLoad( shown, hasMore, busy, cap ) {
		return hasMore && ! busy && shown < ( cap ?? AUTO_LOAD_CAP );
	}

	/**
	 * How many columns fit.
	 *
	 * @param {number} width   The wall's width, px.
	 * @param {number} target  The column width to aim for, px.
	 * @param {number} gap     The gap between columns, px.
	 * @param {number} minimum The fewest columns to use.
	 * @return {number} The column count.
	 */
	function columnsFor( width, target, gap, minimum ) {
		return Math.max(
			minimum,
			Math.floor( ( width + gap ) / ( target + gap ) )
		);
	}

	/**
	 * Place every photo.
	 *
	 * @param {Array<{w: number, h: number}>} items                The photos' pixel sizes, in order.
	 * @param {number}                        width                The wall's width, px.
	 * @param {Object}                        options              How to place them.
	 * @param {number}                        [options.target=260] Column width to aim for.
	 * @param {number}                        [options.gap=12]     Gap, px.
	 * @param {number}                        [options.columns]    Force a column count.
	 * @param {number}                        [options.minimum=2]  Fewest columns.
	 * @param {Object}                        [options.from]       A previous layout's result to continue
	 *                                                             from: the new items are placed below it
	 *                                                             and nothing already placed moves.
	 * @return {{columns: number, columnWidth: number, gap: number, heights: number[], height: number, boxes: Array<{x: number, y: number, width: number, height: number, span: number}>}} The layout.
	 */
	function place( items, width, options ) {
		const settings = Object.assign(
			{ target: 260, gap: 12, minimum: 2 },
			options || {}
		);
		const from = settings.from;
		const gap = from ? from.gap : settings.gap;
		const columns = from
			? from.columns
			: settings.columns ||
			  columnsFor( width, settings.target, gap, settings.minimum );
		const columnWidth = from
			? from.columnWidth
			: ( width - gap * ( columns - 1 ) ) / columns;
		const heights = from
			? from.heights.slice()
			: new Array( columns ).fill( 0 );

		const boxes = items.map( function ( item ) {
			const ratio = item.w > 0 && item.h > 0 ? item.w / item.h : 1;
			const span = ratio > PANORAMA && columns > 2 ? 2 : 1;

			let best = 0;
			let bestTop = Infinity;

			for ( let column = 0; column <= columns - span; column++ ) {
				const top = Math.max.apply(
					null,
					heights.slice( column, column + span )
				);

				if ( top < bestTop ) {
					bestTop = top;
					best = column;
				}
			}

			const boxWidth = columnWidth * span + gap * ( span - 1 );
			const boxHeight = Math.min(
				boxWidth / ratio,
				columnWidth * TALL_CAP
			);

			for ( let column = best; column < best + span; column++ ) {
				heights[ column ] = bestTop + boxHeight + gap;
			}

			return {
				x: best * ( columnWidth + gap ),
				y: bestTop,
				width: boxWidth,
				height: boxHeight,
				span,
			};
		} );

		return {
			columns,
			columnWidth,
			gap,
			heights,
			height: Math.max( 0, Math.max.apply( null, heights ) - gap ),
			boxes,
		};
	}

	/**
	 * Whether a fetched page actually moved the wall on.
	 *
	 * The guard against the loop the live site hit: a page cache answered the
	 * next-page URL with a stale copy, every tile in it was already on the wall,
	 * the same link came back, it was still in view, and the wall fetched the
	 * same URL again — for ever, with the link unclickable the whole time. A
	 * page that adds nothing, or is not the page asked for, or is not a wall at
	 * all, is a page that did not land; auto-loading stops and the link becomes
	 * a plain link.
	 *
	 * @param {number}  added    New tiles the page brought.
	 * @param {number}  expected The page number asked for.
	 * @param {?number} returned The page number the response says it is, or null.
	 * @return {boolean} Whether to carry on.
	 */
	function pageLanded( added, expected, returned ) {
		return (
			added > 0 && Number.isFinite( returned ) && returned === expected
		);
	}

	const api = {
		pageLanded,
		place,
		columnsFor,
		shouldAutoLoad,
		PANORAMA,
		TALL_CAP,
		AUTO_LOAD_CAP,
	};

	scope.dpPhotoLayout = api;

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
} )();
