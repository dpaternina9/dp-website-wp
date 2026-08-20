/**
 * The mobile menu panel.
 *
 * Everything this file does is an upgrade. The panel is a `<dialog>` that the
 * stylesheet already opens from `:target` — the hamburger is a real link to the
 * panel's id — so with JavaScript switched off the menu still opens, still
 * closes, and still navigates, which is what CLAUDE.md section 1.7 requires of
 * every page on this site.
 *
 * What `showModal()` adds is the three things the design's own note asks for
 * and CSS cannot do: Escape closes the panel, focus is trapped inside it while
 * it is open, and the page behind it does not scroll. The first two are the top
 * layer's, for free, and are the reason the panel is a dialog rather than a
 * `<details>`; the third is one class, because whether a modal blocks scrolling
 * of the page behind it is still left to the browser.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const PANEL_ID = 'dp-nav-panel';
	const SCROLL_LOCK = 'dp-panel-open';

	const panel = document.getElementById( PANEL_ID );

	if ( ! panel || typeof panel.showModal !== 'function' ) {
		return;
	}

	const opener = document.querySelector( '.dp-menu-open a[href$="#' + PANEL_ID + '"]' );
	const closer = panel.querySelector( '.dp-menu-close a' );

	if ( ! opener ) {
		return;
	}

	/*
	 * The dialog has no heading of its own — the design's panel opens straight
	 * into the mark and the menu — so it borrows the control's name. Without
	 * this a screen reader announces an unnamed dialog.
	 */
	if ( ! panel.hasAttribute( 'aria-label' ) ) {
		panel.setAttribute( 'aria-label', opener.textContent.trim() );
	}

	opener.setAttribute( 'aria-haspopup', 'dialog' );
	opener.setAttribute( 'aria-expanded', 'false' );

	function open( event ) {
		event.preventDefault();

		/*
		 * The fragment is deliberately not put in the URL. With the dialog open
		 * as a real modal the `:target` rule would be a second, redundant way of
		 * showing the same element, and a history entry the Back button would
		 * spend on closing a menu.
		 */
		panel.showModal();
		document.documentElement.classList.add( SCROLL_LOCK );
		opener.setAttribute( 'aria-expanded', 'true' );
	}

	function close( event ) {
		if ( event ) {
			event.preventDefault();
		}

		panel.close();
	}

	opener.addEventListener( 'click', open );

	if ( closer ) {
		closer.addEventListener( 'click', close );
	}

	/*
	 * Fires for the close button, for Escape, and for a form submission inside
	 * the dialog, so the tidying up happens in one place whatever closed it.
	 */
	panel.addEventListener( 'close', function () {
		document.documentElement.classList.remove( SCROLL_LOCK );
		opener.setAttribute( 'aria-expanded', 'false' );
		opener.focus();
	} );

	/*
	 * Following a link closes the panel first. The navigation would replace the
	 * document anyway; closing keeps the scroll lock from being the last thing
	 * the browser saw if the target turns out to be a fragment on this page.
	 */
	panel.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( 'a' );

		if ( link && link !== closer && ! link.hasAttribute( 'aria-disabled' ) ) {
			panel.close();
		}
	} );

	/*
	 * The panel belongs to widths below the header's 720px container query. If
	 * the window grows while it is open the wide navigation is back and the
	 * panel is covering it, so it goes. `offsetParent` is null exactly when the
	 * hamburger's own container query has hidden it, which is the same
	 * threshold without this file having to know the number.
	 */
	window.addEventListener( 'resize', function () {
		if ( panel.open && opener.offsetParent === null ) {
			panel.close();
		}
	} );
}() );
