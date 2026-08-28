/**
 * The pager, in a browser, with the scripts switched off.
 *
 * Three things about the design's pager cannot be checked anywhere but here.
 *
 * **It has to work with no JavaScript.** CLAUDE.md §1.7: every page must be
 * readable and navigable with JS off. The pager is plain links to real URLs, and
 * the only way to prove that is to switch scripting off and follow one.
 *
 * **A step with nowhere to go is drawn, not dropped.** `stepStyle(enabled)` in
 * the design's own script block renders PREV on page one at `opacity: 0.45` with
 * `cursor: default`. Core renders nothing there, so the row jumps sideways
 * between page one and page two, and `DP\Theme\Query\Pagination` puts the step
 * back. Whether it is a link or not is a property of the rendered DOM.
 *
 * **The controls are the size their token is named for.** This is the bug David
 * called "huge" on the work page's chips, restated on a second set of controls:
 * the design draws these as `<button>` elements and never declares
 * `box-sizing`, so a `min-height: var(--target-min)` chip rendered as an anchor
 * at `content-box` measures 36 + padding + border. `FilterPills.logic.js`
 * settles it in prose; `design-parity.spec.ts` asserts it for the chart's chips;
 * this asserts it for the pager's, which are a different set of rules.
 *
 * **This file owns no content.** It used to publish a category and twelve posts
 * in `beforeAll` and delete them again in `afterAll`, which is the shared-fixture
 * mutation ADR-0013 rules out: a category is read by global queries — the
 * footer's list and the home page's pill row — so creating one is not setting up
 * this spec's page, it is editing everyone's. It also flaked, as
 * `locator.click: element is not stable`, because two workers racing to create
 * and delete the same twelve posts relaid an archive under a browser mid-click.
 * The fixture is `SHARED_PAGER` in `global-setup.ts` now, established once and
 * deleted by nothing, and this file asks it a question instead.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { SHARED_PAGER, sharedPagerArchiveUrl } from './global-setup';

/** The term archive's URL, looked up once per worker. */
let archive = '';

test.describe( 'The pager', () => {
	test.use( {
		javaScriptEnabled: false,
		// Logged out: the admin bar is chrome no reader sees.
		storageState: { cookies: [], origins: [] },
		viewport: { width: 1440, height: 900 },
		// The same preference `design-parity.spec.ts` measures under (ADR-0013
		// §6). Nothing here samples a colour mid-transition, but a control whose
		// box is still settling is a control Playwright refuses to click, and
		// there is no reason for this file to wait for one.
		reducedMotion: 'reduce',
	} );

	test.beforeAll( async ( { requestUtils } ) => {
		archive = await sharedPagerArchiveUrl( requestUtils );
	} );

	test( 'names where you are, and takes you to the next page without scripts', async ( {
		page,
	} ) => {
		await page.goto( archive );

		const range = page.locator( '.dp-pagination-range' );

		await expect( range ).toHaveText(
			`1–10 of ${ SHARED_PAGER.posts } in ${ SHARED_PAGER.term }`
		);
		await expect( page.locator( '.dp-row' ) ).toHaveCount( 10 );

		// The current page is the one marked, and it is not a link.
		await expect( page.locator( '.page-numbers.current' ) ).toHaveText(
			'1'
		);
		await expect(
			page.locator( '.page-numbers.current' )
		).not.toHaveAttribute( 'href', /./ );

		await page.locator( '.wp-block-query-pagination-next' ).click();

		await expect( range ).toHaveText(
			`11–${ SHARED_PAGER.posts } of ${ SHARED_PAGER.posts } in ${ SHARED_PAGER.term }`
		);
		await expect( page.locator( '.dp-row' ) ).toHaveCount(
			SHARED_PAGER.posts - 10
		);
		await expect( page.locator( '.page-numbers.current' ) ).toHaveText(
			'2'
		);

		// And back, the same way.
		await page.locator( '.wp-block-query-pagination-previous' ).click();

		await expect( range ).toHaveText(
			`1–10 of ${ SHARED_PAGER.posts } in ${ SHARED_PAGER.term }`
		);
	} );

	test( 'draws the step it cannot take, inert rather than absent', async ( {
		page,
	} ) => {
		await page.goto( archive );

		const previous = page.locator( '.wp-block-query-pagination-previous' );

		await expect(
			previous,
			'The design draws PREV on page one, dimmed. Core drops it, which ' +
				'moves the whole row sideways between page one and page two.'
		).toBeVisible();
		await expect( previous ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( previous ).not.toHaveAttribute( 'href', /./ );
		await expect( previous ).toHaveClass( /dp-page-step-disabled/ );

		// It is inert to the keyboard as well: no href, so no tab stop.
		expect(
			await previous.evaluate( ( element ) =>
				element.tagName.toLowerCase()
			)
		).toBe( 'span' );

		// NEXT, on the same page, is a real link.
		await expect(
			page.locator( '.wp-block-query-pagination-next' )
		).toHaveAttribute( 'href', /./ );
	} );

	test( 'every control is the target size its own token is named for', async ( {
		page,
	} ) => {
		await page.goto( archive );

		const measured = await page.evaluate( () => {
			const controls = Array.from(
				document.querySelectorAll< HTMLElement >(
					'.dp-pagination .page-numbers, ' +
						'.dp-pagination .wp-block-query-pagination-previous, ' +
						'.dp-pagination .wp-block-query-pagination-next'
				)
			);

			// The token, resolved by asking the browser rather than by
			// repeating a number this file would then own a copy of.
			const probe = document.createElement( 'div' );
			probe.style.height = 'var(--target-min)';
			probe.style.position = 'absolute';
			document.body.appendChild( probe );
			const target = window.getComputedStyle( probe ).height;
			probe.remove();

			return {
				target,
				heights: controls.map(
					( control ) => window.getComputedStyle( control ).height
				),
				boxes: controls.map(
					( control ) => window.getComputedStyle( control ).boxSizing
				),
			};
		} );

		expect( measured.target ).toBe( '36px' );
		expect( measured.heights.length ).toBeGreaterThan( 3 );

		expect(
			measured.boxes,
			'The design system sizes its controls border-box; see the note at ' +
				'the top of this file.'
		).toEqual( measured.boxes.map( () => 'border-box' ) );

		expect(
			measured.heights,
			'A control taller than --target-min is one whose padding and border ' +
				'were added on top of the height its token declares.'
		).toEqual( measured.heights.map( () => measured.target ) );
	} );
} );
