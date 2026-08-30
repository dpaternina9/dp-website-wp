/**
 * The four facts Phase 10 holds every front-end template to, as probes.
 *
 * `a11y.spec.ts` sweeps twelve templates and `chrome.spec.ts` audits the
 * thirteenth (the blog index, which only exists behind that file's Settings →
 * Reading flip). Both must measure the *same* four things or the thirteenth
 * template quietly drops to a lower bar, so the probes live here rather than in
 * either spec.
 *
 * Three of the four are CSP posture, not accessibility. CLAUDE.md §1.4 is blunt
 * about it: the headers are David's security plugin's, and what this repo owes
 * that policy is to need no exceptions from it — no inline `<script>`, no
 * authored inline `style=`, no `onclick`, no off-origin request. `docs/plan.md`
 * Phase 10 says "there is an audit for it"; before this file there was not one,
 * only the inline-style half pinned in `TimelineTest`. This is the rest.
 *
 * External dependencies
 */
import type { Page } from '@playwright/test';

/**
 * Inline `<script>` types that are not executable code.
 *
 * `speculationrules` is core's own prefetch declaration (`wp_speculation_rules`),
 * it is JSON rather than script, and a policy that refuses it costs a
 * prefetch hint and nothing else. Nothing in this repo emits either type; they
 * are listed so the probe fails on *our* inline script rather than on core's
 * data blocks.
 */
const DATA_SCRIPT_TYPES = [ 'speculationrules', 'application/ld+json' ];

/**
 * Visit one URL while recording every request that leaves the origin.
 *
 * @param page The browser page.
 * @param url  Where to go.
 * @return The requests that went somewhere other than the site itself.
 */
export async function visitRecordingOffsite(
	page: Page,
	url: string
): Promise< string[] > {
	const origin = new URL( process.env.WP_BASE_URL ?? 'http://localhost:8889' )
		.host;
	const offsite: string[] = [];

	page.on( 'request', ( request ) => {
		if ( new URL( request.url() ).host !== origin ) {
			offsite.push( request.url() );
		}
	} );

	await page.goto( url );
	await page.waitForLoadState( 'networkidle' );

	return offsite;
}

/**
 * Scripts in `<head>` that stop the parser.
 *
 * A `<script src>` without `defer`, `async` or `type=module` blocks rendering
 * until it has been fetched and run. CLAUDE.md §1.7: "No render-blocking JS."
 *
 * @param page The browser page, already navigated.
 * @return The `src` of every parser-blocking script.
 */
export async function blockingScripts( page: Page ): Promise< string[] > {
	return page.$$eval( 'head script[src]', ( nodes ) =>
		nodes
			.filter(
				( node ): node is HTMLScriptElement =>
					node instanceof HTMLScriptElement &&
					! node.defer &&
					! node.async &&
					node.type !== 'module'
			)
			.map( ( node ) => node.src )
	);
}

/**
 * Executable `<script>` elements with no `src`, which a strict CSP refuses.
 *
 * @param page The browser page, already navigated.
 * @return A readable description of each, empty when there are none.
 */
export async function inlineScripts( page: Page ): Promise< string[] > {
	return page.$$eval(
		'script:not([src])',
		( nodes, dataTypes ) =>
			nodes
				.filter(
					( node ) =>
						! dataTypes.includes(
							( node.getAttribute( 'type' ) ?? '' ).toLowerCase()
						)
				)
				.map(
					( node ) =>
						`<script${ node.id ? ` id="${ node.id }"` : '' }> ${ (
							node.textContent ?? ''
						)
							.trim()
							.slice( 0, 80 ) }`
				),
		DATA_SCRIPT_TYPES
	);
}

/**
 * Tab forward from wherever focus is until one control has it.
 *
 * `.focus()` is not a substitute. The ring in `base.css` is `:focus-visible`,
 * which Chromium only matches on a link or a button when the last input was a
 * key — so a test that focuses programmatically and then asserts an outline is
 * asserting nothing, and would keep passing after the ring was deleted. Tabbing
 * also checks the half of WCAG 2.1.1 that `.focus()` cannot: that the control
 * is reachable at all.
 *
 * A control nobody can reach by keyboard is the failure worth having, so this
 * throws rather than returning a flag.
 *
 * @param page     The page.
 * @param selector What we are tabbing towards.
 * @param limit    How many presses to allow before giving up.
 * @return How many presses it took.
 */
export async function tabTo(
	page: Page,
	selector: string,
	limit = 60
): Promise< number > {
	const target = page.locator( selector );

	for ( let presses = 1; presses <= limit; presses++ ) {
		await page.keyboard.press( 'Tab' );

		const focused = await target.evaluate(
			( element ) => element === element.ownerDocument.activeElement
		);

		if ( focused ) {
			return presses;
		}
	}

	throw new Error(
		`"${ selector }" was not reachable within ${ limit } presses of Tab.`
	);
}

/**
 * The outline the browser is actually painting on one element.
 *
 * @param page     The page.
 * @param selector The element to measure.
 * @return `style width` of the outline, e.g. `solid 2px`.
 */
export async function focusRing(
	page: Page,
	selector: string
): Promise< string > {
	return page.locator( selector ).evaluate( ( element ) => {
		const style = window.getComputedStyle( element );

		return `${ style.outlineStyle } ${ style.outlineWidth }`;
	} );
}

/**
 * Every `on*` event-handler attribute in the document.
 *
 * An `onclick=` is inline script under another name, and a CSP without
 * `'unsafe-inline'` silently drops it — so a control wired that way stops
 * working with no error anyone would see.
 *
 * @param page The browser page, already navigated.
 * @return `tag[attribute]` for each, empty when there are none.
 */
export async function eventHandlerAttributes(
	page: Page
): Promise< string[] > {
	return page.$$eval( '*', ( nodes ) =>
		nodes.flatMap( ( node ) =>
			Array.from( node.attributes )
				.filter( ( attribute ) =>
					/^on[a-z]+$/.test( attribute.name.toLowerCase() )
				)
				.map(
					( attribute ) =>
						`${ node.tagName.toLowerCase() }[${ attribute.name }]`
				)
		)
	);
}
