/**
 * The contact form's `fetch` upgrade.
 *
 * Everything here is an upgrade to something that already works. The form is a
 * plain `<form method="post">` with no action, so with JavaScript switched off
 * it posts to the page it is on, the handler decides, and the page comes back
 * carrying the answer — which is what CLAUDE.md section 1.7 requires and what
 * `tests/e2e/contact.spec.ts` proves with scripting disabled.
 *
 * What this adds is that the answer arrives without the page moving. It is
 * deliberately **the same request**: the same body, to the same URL, with one
 * extra header. `DP\Core\Contact\Handler` reads that header and answers with the
 * rendered panel as JSON instead of with a whole document, so the enhanced path
 * and the plain one cannot disagree about six security gates — there is only
 * one of them.
 *
 * Three details are load-bearing.
 *
 * **Everything is delegated from the panel**, which is the element that never
 * gets replaced. The card inside it does, on every answer, so a listener bound
 * to the form would be bound to a form that is no longer in the document.
 *
 * **A failed fetch falls back to the real submit.** A network error mid-upgrade
 * must not be worse than not having the upgrade, and `form.submit()` does what
 * pressing the button would have done.
 *
 * **Focus moves to the answer.** The card is `tabindex="-1"` and carries
 * `role="status"` or `role="alert"`, so the panel is announced; without moving
 * focus, a keyboard user is left on a button that no longer exists.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const PANEL = '.dp-contact-panel';
	const CARD = '[data-dp-contact-state]';
	const HEADER = 'X-DP-Contact';

	const panel = document.querySelector( PANEL );

	if ( ! panel || typeof window.fetch !== 'function' ) {
		return;
	}

	/**
	 * Whether a submit event is one this file should take over.
	 *
	 * @param {Event} event The submit event.
	 * @return {HTMLFormElement|null} The form, or null to leave it alone.
	 */
	function formFrom( event ) {
		const form = event.target;

		if (
			! ( form instanceof HTMLFormElement ) ||
			! panel.contains( form )
		) {
			return null;
		}

		return form;
	}

	/**
	 * Replace the panel's contents and put focus on what replaced them.
	 *
	 * @param {string} html The rendered panel.
	 */
	function show( html ) {
		panel.innerHTML = html;

		const card = panel.querySelector( CARD );

		if ( card ) {
			card.focus();
		}
	}

	/**
	 * Mark the form as working, or not.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {boolean}         working Whether a request is in flight.
	 */
	function busy( form, working ) {
		const submit = form.querySelector( 'button[type="submit"]' );

		if ( ! submit ) {
			return;
		}

		submit.disabled = working;
		submit.setAttribute( 'aria-busy', working ? 'true' : 'false' );
	}

	panel.addEventListener( 'submit', function ( event ) {
		const form = formFrom( event );

		if ( ! form ) {
			return;
		}

		event.preventDefault();
		busy( form, true );

		window
			.fetch( window.location.href, {
				method: 'POST',
				body: new FormData( form ),
				credentials: 'same-origin',
				headers: {
					[ HEADER ]: '1',
					Accept: 'application/json',
				},
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( answer ) {
				if (
					! answer ||
					typeof answer.html !== 'string' ||
					'' === answer.html
				) {
					throw new Error( 'the answer carried no panel' );
				}

				show( answer.html );
			} )
			.catch( function () {
				/*
				 * The upgrade failed, so fall back to the thing it was an
				 * upgrade to. `submit()` bypasses this listener, which is what
				 * is wanted: one more attempt, the plain way.
				 */
				busy( form, false );
				form.submit();
			} );
	} );
} )();
