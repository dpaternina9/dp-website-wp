/**
 * Drag a series' parts into the order they read in.
 *
 * The whole of the interaction is `insertBefore`. The list is already a form:
 * every row carries a hidden input with its post ID, so moving a row in the DOM
 * moves its input, and submitting posts the IDs in the order the list is now in.
 * Nothing here fetches, nothing here holds a nonce, and nothing here needs to
 * know a URL — which is why the screen ships no inline script and needs no
 * exception from the site's content-security policy (CLAUDE.md section 1.4).
 *
 * Native HTML5 drag and drop rather than pointer maths, and no library. A
 * sortable-list dependency would replace about sixty lines with a build step, a
 * bundle in the release zip and a second thing to keep current; the burden of
 * proof in CLAUDE.md section 6 is not met by "it would also work".
 *
 * The part numbers are redrawn as rows move, because the number is the position
 * of a *published* row and the screen would otherwise show the order it had
 * before the drag. Drafts have no number, so they are skipped in the count —
 * exactly as `SeriesParts::part_of()` skips them.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const list = document.querySelector( '[data-dp-series-order]' );

	if ( ! list ) {
		return;
	}

	/** The row being dragged, or null between drags. */
	let dragging = null;

	/**
	 * Every row, in the order the list currently holds them.
	 *
	 * @return {Element[]} The rows.
	 */
	function rows() {
		return Array.prototype.slice.call(
			list.querySelectorAll( '.dp-series-order-item' )
		);
	}

	/**
	 * Redraw the part numbers: published rows counted from the top.
	 *
	 * @return {void}
	 */
	function renumber() {
		let part = 0;

		rows().forEach( function ( row ) {
			const badge = row.querySelector( '[data-dp-part]' );

			if ( ! badge ) {
				return;
			}

			if ( row.dataset.dpPublished === '1' ) {
				part += 1;
				badge.textContent = String( part );
			} else {
				badge.textContent = '—';
			}
		} );
	}

	rows().forEach( function ( row ) {
		row.setAttribute( 'draggable', 'true' );
	} );

	list.classList.add( 'is-draggable' );

	list.addEventListener( 'dragstart', function ( event ) {
		const row = event.target.closest( '.dp-series-order-item' );

		if ( ! row ) {
			return;
		}

		dragging = row;
		row.classList.add( 'is-dragging' );

		if ( event.dataTransfer ) {
			event.dataTransfer.effectAllowed = 'move';
			// Firefox refuses to begin a drag until something is on the
			// transfer. The value is never read back.
			event.dataTransfer.setData(
				'text/plain',
				row.dataset.dpPostId || ''
			);
		}
	} );

	list.addEventListener( 'dragover', function ( event ) {
		if ( ! dragging ) {
			return;
		}

		event.preventDefault();

		if ( event.dataTransfer ) {
			event.dataTransfer.dropEffect = 'move';
		}

		const over = event.target.closest( '.dp-series-order-item' );

		if ( ! over || over === dragging ) {
			return;
		}

		// Past the middle of the row it is over, the dragged row goes after it.
		// Anything else makes the last position in the list unreachable.
		const box = over.getBoundingClientRect();
		const after = event.clientY > box.top + box.height / 2;

		list.insertBefore( dragging, after ? over.nextSibling : over );
		renumber();
	} );

	list.addEventListener( 'drop', function ( event ) {
		event.preventDefault();
	} );

	list.addEventListener( 'dragend', function () {
		if ( dragging ) {
			dragging.classList.remove( 'is-dragging' );
		}

		dragging = null;
		renumber();
	} );
} )();
