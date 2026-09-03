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
 * The fourth detail arrived with Cloudflare Turnstile, and it is a consequence
 * of the first. `api.js` renders widgets **implicitly, once, on load**: it scans
 * for `.cf-turnstile` when it starts and never again. Replacing the panel's
 * innerHTML therefore throws away a rendered widget and puts an unrendered div
 * in its place, so a retry would carry no token and be refused by the gate it
 * is retrying past — a form that fails once and then fails forever, which is
 * worse than not having the upgrade. So every replacement explicitly renders
 * whatever `.cf-turnstile` arrived in the new markup, and the widget it was
 * given first is removed, because a token is spent when it is redeemed and the
 * dead widget's is worth nothing to anybody.
 *
 * None of that runs on a site with no challenge configured: there is no
 * `.cf-turnstile` in the markup and no `window.turnstile` to call, and both are
 * checked rather than assumed.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const PANEL = '.dp-contact-panel';
	const CARD = '[data-dp-contact-state]';
	const CHALLENGE = '.cf-turnstile';
	const HEADER = 'X-DP-Contact';

	const panel = document.querySelector( PANEL );

	if ( ! panel || typeof window.fetch !== 'function' ) {
		return;
	}

	/**
	 * The id of the widget this file rendered, so it can be taken back down.
	 *
	 * Null covers three cases that behave identically: no challenge on this
	 * site, `api.js` never arrived, and nothing has been replaced yet.
	 *
	 * @type {string|undefined|null}
	 */
	let widget = null;

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
	 * Cloudflare's widget API, once it has loaded, or null.
	 *
	 * Read on every call rather than captured once: this file is deferred and
	 * `api.js` is async, so neither is guaranteed to have run first.
	 *
	 * @return {object|null} The API, or null when there is no challenge here.
	 */
	function challengeApi() {
		const api = window.turnstile;

		return api && typeof api.render === 'function' ? api : null;
	}

	/**
	 * Draw a widget into the panel that has just replaced the old one.
	 *
	 * A no-op when there is no challenge on this site, and when `api.js` has not
	 * arrived — in which case the first submission had no token either, so
	 * there is nothing this could rescue.
	 */
	function drawChallenge() {
		const api = challengeApi();
		const element = panel.querySelector( CHALLENGE );

		widget = null;

		if ( ! api || ! element ) {
			return;
		}

		try {
			widget = api.render( element );
		} catch ( failure ) {
			/*
			 * A widget that will not draw is a submission that will be refused,
			 * which the panel is already equipped to say. Letting this throw
			 * would instead land in the submit handler's catch and re-submit a
			 * form that is no longer in the document.
			 */
			widget = null;
		}
	}

	/**
	 * Take down the widget this file drew, if it drew one.
	 *
	 * Called before the markup holding it is thrown away: `api.js` keeps its own
	 * reference to every widget it renders, and dropping the element without
	 * saying so leaves that reference pointing at a node no longer in the
	 * document.
	 *
	 * The very first widget on the page is the exception, and it cannot be
	 * helped: `api.js` rendered it implicitly and returned no id to give back.
	 * That one reference is left behind on the first replacement, and every
	 * widget after it is this file's to clean up.
	 */
	function clearChallenge() {
		const api = challengeApi();

		if ( api && widget && typeof api.remove === 'function' ) {
			api.remove( widget );
		}

		widget = null;
	}

	/**
	 * Replace the panel's contents and put focus on what replaced them.
	 *
	 * @param {string} html The rendered panel.
	 */
	function show( html ) {
		clearChallenge();

		panel.innerHTML = html;

		drawChallenge();

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
