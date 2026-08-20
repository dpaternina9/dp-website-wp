/**
 * Phase 6 acceptance: the timeline, in a browser, with and without scripts.
 *
 * Four promises are made about this chart and none of them can be checked
 * anywhere but here.
 *
 * 1. **The three modes come from the component's own width.** digest §5.2 puts
 *    the threshold at 700px of container, not of viewport, and the design's own
 *    note tells the theme to use `@container`. A rendered-markup test cannot
 *    see which mode is drawn, because there is only one markup; a browser can.
 *
 * 2. **It works with JavaScript off.** CLAUDE.md §1.7. Every row is a
 *    `<details>` and the filter is three links, so opening, closing, filtering,
 *    expanding everything and deep-linking all have to work with scripting
 *    disabled — and one half of this file switches it off and does all five.
 *
 * 3. **It works from the keyboard alone.** No clicks in that test: Tab to a
 *    row, Enter to open it, Tab to the next, and the filter after that.
 *
 * 4. **Reduced motion is honoured.** The expanded panel has an entrance. Under
 *    `prefers-reduced-motion: reduce` it has none — not a fast one.
 *
 * The suite establishes its own content. :8889 is a fixture, not a preview, and
 * `composer test:integration` reinstalls WordPress into the same database.
 *
 * External dependencies
 */
import type { Page } from '@playwright/test';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** Desktop, comfortably above the chart's 700px container query. */
const DESKTOP = { width: 1440, height: 900 };

/** Phones, where the Ledger's stacked disclosure list is what ships. */
const PHONE = { width: 390, height: 844 };

/** Logged out: the admin bar is chrome no reader sees. */
const READER = { cookies: [], origins: [] };

/** The slugs this fixture owns. Nothing outside this list is ever deleted. */
const SLUGS = {
	page: 'timeline-fixture-work',
	lab: 'timeline-fixture-lab',
	bare: 'timeline-fixture-backbone',
	kiveo: 'timeline-fixture-kiveo',
	ops: 'timeline-fixture-ops',
};

/** The entry ids the block derives from those slugs. */
const ENTRY = {
	lab: `dp-role-${ SLUGS.lab }`,
	bare: `dp-role-${ SLUGS.bare }`,
	kiveo: `dp-ship-${ SLUGS.kiveo }`,
	ops: `dp-ship-${ SLUGS.ops }`,
};

/** Titles, distinctive enough that no other spec's content can be mistaken for them. */
const TITLE = {
	lab: 'Timeline fixture — Fanxie Lab',
	bare: 'Timeline fixture — Backbone',
	kiveo: 'Timeline fixture — Kiveo',
	ops: 'Timeline fixture — Agency ops',
};

/** The work page's URL, filled in by `beforeAll`. */
let workPage = '';

/** The shape of the REST fields this spec reads back. */
type Created = { id: number; link: string };

/**
 * Delete everything carrying one of this fixture's slugs, and nothing else.
 *
 * @param requestUtils The suite's REST client.
 */
async function removeFixture(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< void > {
	const sweep: Array< [ string, string[] ] > = [
		[ 'pages', [ SLUGS.page ] ],
		[ 'dp_role', [ SLUGS.lab, SLUGS.bare ] ],
		[ 'dp_ship', [ SLUGS.kiveo, SLUGS.ops ] ],
	];

	for ( const [ endpoint, slugs ] of sweep ) {
		const found: Created[] = await requestUtils.rest( {
			path: `/wp/v2/${ endpoint }`,
			params: { slug: slugs.join( ',' ), per_page: 100, status: 'any' },
		} );

		for ( const item of found ) {
			await requestUtils.rest( {
				path: `/wp/v2/${ endpoint }/${ item.id }`,
				method: 'DELETE',
				params: { force: true },
			} );
		}
	}
}

/**
 * Whether a `<details>` is open.
 *
 * @param page The page.
 * @param id   The entry's element id.
 */
function isOpen( page: Page, id: string ): Promise< boolean > {
	return page.locator( `#${ id }` ).evaluate( ( row ) => {
		return ( row as HTMLDetailsElement ).open;
	} );
}

/**
 * The work page's URL with query args added, and optionally a fragment.
 *
 * Built through `URL` rather than by concatenation, because the site under test
 * has plain permalinks: the page's own link already carries a query string, and
 * `link + '?dp-open=…'` produces a second `?` that WordPress never parses. The
 * first version of this file did exactly that, and one test passed anyway —
 * through the fragment, which the controller acts on — which is the failure
 * mode a helper exists to remove.
 *
 * @param args     Query args to set.
 * @param fragment An element id to jump to, without the `#`.
 */
function workUrl( args: Record< string, string > = {}, fragment = '' ): string {
	const url = new URL( workPage );

	for ( const [ name, value ] of Object.entries( args ) ) {
		url.searchParams.set( name, value );
	}

	url.hash = fragment;

	return url.toString();
}

/**
 * Whether the document actually sees the reduced-motion preference.
 *
 * @param page The page.
 */
function prefersReducedMotion( page: Page ): Promise< boolean > {
	return page.evaluate(
		() => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * Whether the page really is running without scripts.
 *
 * `javaScriptEnabled: false` is a context option, and a context option that
 * silently failed to apply would turn this file's whole second half into a
 * duplicate of its first — passing, and proving nothing about CLAUDE.md §1.7.
 * So the precondition is measured: a script element is appended and asked
 * whether it ran. Playwright's own evaluation runs in an isolated world and is
 * unaffected, which is what makes the question askable at all.
 *
 * @param page The page.
 */
async function scriptsAreOff( page: Page ): Promise< boolean > {
	return page.evaluate( () => {
		const probe = document.createElement( 'script' );

		probe.textContent = 'window.dpScriptsRan = true;';
		document.body.appendChild( probe );

		const ran = ( window as unknown as Record< string, boolean > )
			.dpScriptsRan;

		probe.remove();

		return ran !== true;
	} );
}

/**
 * Tab until an element has focus, and say how many presses it took.
 *
 * A control nobody can reach by keyboard is the failure worth having, so this
 * throws rather than returning a flag.
 *
 * @param page     The page.
 * @param selector What we are tabbing towards.
 * @param limit    How many presses to allow before giving up.
 */
async function tabTo(
	page: Page,
	selector: string,
	limit = 40
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

test.describe( 'The timeline', () => {
	/*
	 * Serial, against the grain of the rest of the suite: every test shares one
	 * fixture, `beforeAll` runs once per worker, and under `fullyParallel` two
	 * workers would race to create the same slugs.
	 */
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );

		const lab = await requestUtils.rest< Created >( {
			path: '/wp/v2/dp_role',
			method: 'POST',
			data: {
				title: TITLE.lab,
				slug: SLUGS.lab,
				status: 'publish',
				menu_order: 2,
				meta: {
					dp_role_title: 'CTO & founder',
					dp_start: 2016,
					dp_end: 2026.6,
					dp_range: '2016 — now',
					dp_detail: 'The thread running under everything else.',
					dp_stack: 'LARAVEL · NESTJS',
					dp_accent: 'pink',
				},
			},
		} );

		await requestUtils.rest< Created >( {
			path: '/wp/v2/dp_role',
			method: 'POST',
			data: {
				title: TITLE.bare,
				slug: SLUGS.bare,
				status: 'publish',
				menu_order: 1,
				meta: {
					dp_role_title: 'Developer',
					dp_start: 2014,
					dp_end: 2016,
					dp_range: '2014 — 2016',
					dp_detail: 'Placeholder role description.',
					dp_stack: 'STACK · PLACEHOLDER',
				},
			},
		} );

		for ( const [ slug, title, order ] of [
			[ SLUGS.kiveo, TITLE.kiveo, 1 ],
			[ SLUGS.ops, TITLE.ops, 2 ],
		] as Array< [ string, string, number ] > ) {
			await requestUtils.rest< Created >( {
				path: '/wp/v2/dp_ship',
				method: 'POST',
				data: {
					title,
					slug,
					status: 'publish',
					menu_order: order,
					meta: {
						dp_role_id: lab.id,
						dp_start: 2023,
						dp_end: 2026.6,
						dp_range: '2023 — now',
						dp_headline: `${ title } — the one line.`,
						dp_detail: `What ${ title } is and who it is for.`,
						dp_bullets: [ 'One constraint.', 'Another.' ],
						dp_ship_role: 'Everything',
						dp_stack: 'SWIFT · SWIFTUI',
						dp_artifact_label: 'SWIFTUI',
						dp_artifact: 'struct EntryList: View { }',
						dp_stat1: '0',
						dp_stat1_label: 'TRACKERS',
						dp_stat2: '—',
						dp_stat2_label: 'APPS SHIPPED',
						dp_featured: true,
					},
				},
			} );
		}

		const page = await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Timeline fixture — what I have worked on',
				slug: SLUGS.page,
				status: 'publish',
				// The admin stores a block theme's custom template under its
				// slug, without the extension.
				template: 'dp-work',
			},
		} );

		workPage = page.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );
	} );

	test.describe( 'the three modes', () => {
		test.use( { storageState: READER } );

		test( 'draws bars when the component is wide enough', async ( {
			page,
		} ) => {
			await page.setViewportSize( DESKTOP );
			await page.goto( workPage );

			const row = page.locator( `#${ ENTRY.lab }` );

			await expect( row.locator( '.dp-tl-track' ) ).toBeVisible();
			await expect( row.locator( '.dp-tl-chevron' ) ).toBeHidden();
			await expect(
				page.locator( '.dp-timeline .dp-tl-years' )
			).toBeVisible();

			// The label column is the design's 200px, measured rather than assumed.
			const label = await row.locator( '.dp-tl-label' ).boundingBox();

			expect( label?.width ).toBeCloseTo( 200, 0 );
		} );

		test( 'stacks when it is not, and never scrolls the page sideways', async ( {
			page,
		} ) => {
			await page.setViewportSize( PHONE );
			await page.goto( workPage );

			const row = page.locator( `#${ ENTRY.lab }` );

			await expect( row.locator( '.dp-tl-track' ) ).toBeHidden();
			await expect( row.locator( '.dp-tl-chevron' ) ).toBeVisible();
			await expect(
				page.locator( '.dp-timeline .dp-tl-years' )
			).toBeHidden();
			await expect(
				page.locator( '.dp-timeline .dp-tl-swipe' )
			).toBeHidden();

			const overflow = await page.evaluate( () => {
				const root = document.documentElement;

				return root.scrollWidth - root.clientWidth;
			} );

			expect( overflow ).toBeLessThanOrEqual( 0 );
		} );

		test( 'the threshold is the component, not the window', async ( {
			page,
		} ) => {
			/*
			 * digest §5.2, and the reason this assertion is worth its own test:
			 * at a 760px window the chart is inside a gutter and a container,
			 * so the component is under 700px and stacks — even though the
			 * viewport is over it. A media query would get this wrong.
			 */
			await page.setViewportSize( { width: 760, height: 900 } );
			await page.goto( workPage );

			const width = await page
				.locator( '.dp-timeline' )
				.evaluate( ( chart ) => chart.getBoundingClientRect().width );

			expect( width ).toBeLessThan( 700 );

			await expect(
				page.locator( `#${ ENTRY.lab } .dp-tl-track` )
			).toBeHidden();
		} );
	} );

	test.describe( 'with JavaScript', () => {
		test.use( { viewport: DESKTOP, storageState: READER } );

		test( 'a row opens and closes, and several stay open at once', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			// The counterpart of the assertion in the JS-off group below. One
			// of the two would fail if the context option stopped applying.
			expect( await scriptsAreOff( page ) ).toBe( false );

			expect( await isOpen( page, ENTRY.lab ) ).toBe( false );

			await page.locator( `#${ ENTRY.lab } .dp-tl-summary` ).click();
			await expect(
				page.locator( `#${ ENTRY.lab } .dp-tl-detail` )
			).toBeVisible();

			await page.locator( `#${ ENTRY.kiveo } .dp-tl-summary` ).click();
			await expect(
				page.locator( `#${ ENTRY.kiveo } .dp-tl-panel` )
			).toBeVisible();

			// The design's whole point: the second one does not close the first.
			expect( await isOpen( page, ENTRY.lab ) ).toBe( true );
			expect( await isOpen( page, ENTRY.kiveo ) ).toBe( true );

			await page.locator( `#${ ENTRY.lab } .dp-tl-summary` ).click();

			expect( await isOpen( page, ENTRY.lab ) ).toBe( false );
			expect( await isOpen( page, ENTRY.kiveo ) ).toBe( true );

			// What is open is in the URL, so the state is copyable.
			expect( page.url() ).toContain( `dp-open=${ ENTRY.kiveo }` );
		} );

		test( 'expand all opens everything, and then offers the opposite', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			const control = page.locator( '.dp-tl-toggle-all' );
			const rows = page.locator( '.dp-timeline .dp-tl-row' );

			await expect( control ).toHaveText( 'Expand all' );

			await control.click();

			const total = await rows.count();

			expect( total ).toBeGreaterThan( 2 );
			expect(
				await rows.evaluateAll( ( all ) =>
					all.every( ( row ) => ( row as HTMLDetailsElement ).open )
				)
			).toBe( true );

			await expect( control ).toHaveText( 'Collapse all' );
			expect( page.url() ).toContain( 'dp-open=all' );

			await control.click();

			expect(
				await rows.evaluateAll( ( all ) =>
					all.some( ( row ) => ( row as HTMLDetailsElement ).open )
				)
			).toBe( false );

			await expect( control ).toHaveText( 'Expand all' );
			expect( page.url() ).not.toContain( 'dp-open' );
		} );

		test( 'the filter switches without a page load', async ( { page } ) => {
			await page.goto( workPage );

			/*
			 * A marker on `window`, rather than watching for a navigation:
			 * `waitForNavigation` also resolves for a same-document history
			 * change, and the controller makes one of those on every switch.
			 * What has to be true is that the *document* never went away.
			 */
			await page.evaluate( () => {
				( window as unknown as Record< string, number > ).dpAlive = 1;
			} );

			const ships = page.locator(
				`[data-dp-lane="${ ENTRY.lab }"] .dp-tl-ships`
			);
			const bare = page.locator( `[data-dp-lane="${ ENTRY.bare }"]` );

			await expect( ships ).toBeVisible();

			await page
				.locator( '.dp-tl-filter-link[data-dp-filter="roles"]' )
				.click();

			await expect( ships ).toBeHidden();
			await expect( bare ).toBeVisible();
			expect( page.url() ).toContain( 'dp-filter=roles' );

			await page
				.locator( '.dp-tl-filter-link[data-dp-filter="shipped"]' )
				.click();

			await expect( ships ).toBeVisible();
			await expect( bare ).toBeHidden();

			await page
				.locator( '.dp-tl-filter-link[data-dp-filter="everything"]' )
				.click();

			await expect( ships ).toBeVisible();
			await expect( bare ).toBeVisible();
			expect( page.url() ).not.toContain( 'dp-filter' );

			// The document never reloaded: that is what "upgraded to instant" means.
			expect(
				await page.evaluate(
					() =>
						( window as unknown as Record< string, number > )
							.dpAlive
				)
			).toBe( 1 );
		} );

		test( 'a work card opens its entry on the chart below', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			const card = page.locator(
				`.dp-cards a.dp-card-open[data-dp-entry="${ ENTRY.kiveo }"]`
			);

			await expect( card ).toBeVisible();

			await card.click();

			await expect(
				page.locator( `#${ ENTRY.kiveo } .dp-tl-panel` )
			).toBeVisible();

			expect( page.url() ).toContain( `dp-open=${ ENTRY.kiveo }` );
		} );

		test( 'a deep link arrives with the entry already open', async ( {
			page,
		} ) => {
			/*
			 * No fragment. With one, the controller would open the entry on
			 * arrival and the test would pass whether or not the server had
			 * done anything — which is the opposite of what it is for.
			 */
			await page.goto( workUrl( { 'dp-open': ENTRY.ops } ) );

			await expect(
				page.locator( `#${ ENTRY.ops } .dp-tl-panel` )
			).toBeVisible();

			expect( await isOpen( page, ENTRY.kiveo ) ).toBe( false );
		} );

		test( 'the whole chart is operable from the keyboard alone', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			// No clicks anywhere in this test.
			await tabTo( page, `#${ ENTRY.bare } .dp-tl-summary` );
			await page.keyboard.press( 'Enter' );

			expect( await isOpen( page, ENTRY.bare ) ).toBe( true );

			await page.keyboard.press( 'Enter' );

			expect( await isOpen( page, ENTRY.bare ) ).toBe( false );

			// Space is the other disclosure key, and it is not the same code path.
			await page.keyboard.press( 'Space' );

			expect( await isOpen( page, ENTRY.bare ) ).toBe( true );

			// The focused summary shows a ring rather than nothing.
			const outline = await page
				.locator( `#${ ENTRY.bare } .dp-tl-summary` )
				.evaluate(
					( element ) =>
						window.getComputedStyle( element ).outlineStyle
				);

			expect( outline ).not.toBe( 'none' );

			await page.keyboard.press( 'Home' );
			await page.locator( '.dp-tl-toggle-all' ).focus();
			await page.keyboard.press( 'Enter' );

			await expect( page.locator( '.dp-tl-toggle-all' ) ).toHaveText(
				'Collapse all'
			);
		} );
	} );

	test.describe( 'with reduced motion', () => {
		test.use( { viewport: DESKTOP, storageState: READER } );

		/*
		 * `emulateMedia()` on the page rather than `reducedMotion` on the
		 * context: the context option did not reach the document under this
		 * project's fixtures, and a preference that is not actually set makes a
		 * reduced-motion test that can only pass. Every test below asserts the
		 * media query matches before it asserts anything about the animation,
		 * so it can never silently become a test of nothing.
		 */
		test.beforeEach( async ( { page } ) => {
			await page.emulateMedia( { reducedMotion: 'reduce' } );
		} );

		test( 'the panel has no entrance at all', async ( { page } ) => {
			await page.goto( workUrl( { 'dp-open': ENTRY.kiveo } ) );

			expect( await prefersReducedMotion( page ) ).toBe( true );

			const panel = page.locator( `#${ ENTRY.kiveo } .dp-tl-panel` );

			await expect( panel ).toBeVisible();

			const motion = await panel.evaluate( ( element ) => {
				const style = window.getComputedStyle( element );

				return {
					name: style.animationName,
					opacity: style.opacity,
					transform: style.transform,
				};
			} );

			expect( motion.name ).toBe( 'none' );
			expect( motion.opacity ).toBe( '1' );
			expect( [ 'none', 'matrix(1, 0, 0, 1, 0, 0)' ] ).toContain(
				motion.transform
			);
		} );

		test( 'and the bar does not animate into its open state', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			expect( await prefersReducedMotion( page ) ).toBe( true );

			const bar = page.locator( `#${ ENTRY.lab } .dp-tl-bar` );

			const duration = await bar.evaluate(
				( element ) =>
					window.getComputedStyle( element ).transitionDuration
			);

			/*
			 * base.css blunts every transition on the site to 0.01ms with
			 * `!important`, and the component says `none` for its own. Which of
			 * the two a browser reports is not the point and is not worth
			 * pinning; that the bar does not spend 300ms sliding into its open
			 * state is.
			 */
			const seconds =
				duration === 'none' ? 0 : Number.parseFloat( duration );

			expect( seconds ).toBeLessThan( 0.01 );
		} );
	} );

	test.describe( 'with JavaScript switched off', () => {
		test.use( {
			viewport: DESKTOP,
			storageState: READER,
			javaScriptEnabled: false,
		} );

		test( 'rows still open and close', async ( { page } ) => {
			await page.goto( workPage );

			expect( await scriptsAreOff( page ) ).toBe( true );

			const detail = page.locator( `#${ ENTRY.lab } .dp-tl-detail` );

			await expect( detail ).toBeHidden();

			await page.locator( `#${ ENTRY.lab } .dp-tl-summary` ).click();

			await expect( detail ).toBeVisible();

			await page.locator( `#${ ENTRY.lab } .dp-tl-summary` ).click();

			await expect( detail ).toBeHidden();
		} );

		test( 'the filter is three links that still filter', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			expect( await scriptsAreOff( page ) ).toBe( true );

			const ships = page.locator(
				`[data-dp-lane="${ ENTRY.lab }"] .dp-tl-ships`
			);
			const bare = page.locator( `[data-dp-lane="${ ENTRY.bare }"]` );

			await page
				.locator( '.dp-tl-filter-link[data-dp-filter="roles"]' )
				.click();
			await page.waitForURL( /dp-filter=roles/ );

			await expect( ships ).toBeHidden();
			await expect( bare ).toBeVisible();

			await page
				.locator( '.dp-tl-filter-link[data-dp-filter="shipped"]' )
				.click();
			await page.waitForURL( /dp-filter=shipped/ );

			await expect( bare ).toBeHidden();
			await expect( page.locator( `#${ ENTRY.kiveo }` ) ).toBeVisible();
		} );

		test( 'expand all still expands everything', async ( { page } ) => {
			await page.goto( workPage );

			await page.locator( '.dp-tl-toggle-all' ).click();
			await page.waitForURL( /dp-open=all/ );

			const rows = page.locator( '.dp-timeline .dp-tl-row' );

			expect(
				await rows.evaluateAll( ( all ) =>
					all.every( ( row ) => ( row as HTMLDetailsElement ).open )
				)
			).toBe( true );

			await expect( page.locator( '.dp-tl-toggle-all' ) ).toHaveText(
				'Collapse all'
			);
		} );

		test( 'a work card still opens its entry', async ( { page } ) => {
			await page.goto( workPage );

			await page
				.locator(
					`.dp-cards a.dp-card-open[data-dp-entry="${ ENTRY.kiveo }"]`
				)
				.click();

			await page.waitForURL( new RegExp( `dp-open=${ ENTRY.kiveo }` ) );

			await expect(
				page.locator( `#${ ENTRY.kiveo } .dp-tl-panel` )
			).toBeVisible();
		} );

		test( 'and the chart is readable end to end', async ( { page } ) => {
			await page.goto( workUrl( { 'dp-open': 'all' } ) );

			await expect(
				page.locator( `#${ ENTRY.kiveo } .dp-tl-headline` )
			).toHaveText( `${ TITLE.kiveo } — the one line.` );

			await expect(
				page.locator( '.dp-timeline .dp-tl-legend' )
			).toContainText( TITLE.lab );

			await expect(
				page.locator( `#${ ENTRY.lab } .dp-tl-bar` )
			).toBeVisible();
		} );
	} );
} );
