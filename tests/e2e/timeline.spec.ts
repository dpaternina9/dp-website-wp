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
 * The content is not this file's. The chart is drawn from a global query, so a
 * role published here would be a row on every other spec's work page as well;
 * `tests/e2e/global-setup.ts` establishes one set for the whole suite and no
 * spec creates or deletes any of it. ADR-0013.
 *
 * External dependencies
 */
import type { Page } from '@playwright/test';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	SHARED_BARE_ROLE,
	SHARED_ROLE,
	SHARED_SHIPS,
	sharedWorkPageUrl,
} from './global-setup';

/** Desktop, comfortably above the chart's 700px container query. */
const DESKTOP = { width: 1440, height: 900 };

/** Phones, where the Ledger's stacked disclosure list is what ships. */
const PHONE = { width: 390, height: 844 };

/** Logged out: the admin bar is chrome no reader sees. */
const READER = { cookies: [], origins: [] };

/**
 * The chart this file drives, which is the site's and not this file's.
 *
 * The block draws every published `dp_role` and `dp_ship` there is, on whatever
 * page it sits on, so a role published here is a row on every other spec's work
 * page too. This file used to publish four of them and then tab towards its own
 * — a distance that quietly depended on how many fixtures the rest of the suite
 * happened to have alive, and that ran out of Tab presses once three files were
 * doing it at once. The content now comes from `tests/e2e/global-setup.ts`,
 * where it is established before any worker starts and deleted by nothing, so
 * the chart is the same chart in every test in the run. ADR-0013.
 */
const LAB = SHARED_ROLE;
const BARE = SHARED_BARE_ROLE;
const [ KIVEO, OPS ] = SHARED_SHIPS;

/** The work page's URL, looked up once per worker. */
let workPage = '';

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
	 * Nothing is created here. The chart is a global query and the shared
	 * fixture is established once in `tests/e2e/global-setup.ts`; this is a
	 * lookup, so two workers doing it at the same time is two reads.
	 */
	test.beforeAll( async ( { requestUtils } ) => {
		workPage = await sharedWorkPageUrl( requestUtils );
	} );

	test.describe( 'the three modes', () => {
		test.use( { storageState: READER } );

		test( 'draws bars when the component is wide enough', async ( {
			page,
		} ) => {
			await page.setViewportSize( DESKTOP );
			await page.goto( workPage );

			const row = page.locator( `#${ LAB.entry }` );

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

			const row = page.locator( `#${ LAB.entry }` );

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
				page.locator( `#${ LAB.entry } .dp-tl-track` )
			).toBeHidden();
		} );
	} );

	/*
	 * The year axis, which is the one place the theme deliberately disagrees
	 * with `design-source/`.
	 *
	 * The design lays the labels out as a flex row with
	 * `justify-content: space-between`. Its bars are on a different scale —
	 * `pos(y) = ((y - start) / (end - start + 1)) * 100`, so a year owns 1/13 of
	 * a thirteen-year track and 2026 begins at 92.3% — while thirteen labels
	 * spread between two edges advance 1/12 and put 2026 at 100%. Seven points
	 * seven percent apart by the right-hand edge, which is why a role running to
	 * mid-2026 landed visibly left of its own label and read as ending in 2025.
	 * David reported exactly that. ADR-0014.
	 *
	 * The theme draws the row as a grid of equal columns instead, so label `n`
	 * begins at `n / span` — which *is* `Geometry::position()` of the year it
	 * names. `design-parity.spec.ts` records that divergence and stops asserting
	 * those two properties; these are the assertions that take their place, and
	 * they are here rather than there because what has to hold is a rendered
	 * position, not a declaration.
	 */
	test.describe( 'the year axis', () => {
		test.use( { storageState: READER } );

		/**
		 * Wait until the chart has stopped moving.
		 *
		 * `page.goto()` resolves on `load`, which is not the same event as "this
		 * component has finished deciding what it is". Bars mode is a
		 * `@container` rule and a container query resolves against a laid-out
		 * container rather than at parse time; the labels are set in a web font;
		 * and a style change that lands after the row already has a computed
		 * style starts a transition on a page nobody has touched — ADR-0013
		 * recorded that last one against the open rows' tint, and the axis met
		 * it too. Half a pixel is the whole point of the assertions below, so
		 * measuring whatever exists the instant `goto()` returns is a race by
		 * construction.
		 *
		 * "Settled" is expressed as agreement rather than as a duration: the
		 * same geometry, read twice, in two consecutive frames.
		 * `waitForFunction` polls on `requestAnimationFrame`, so a page that is
		 * already still costs two frames and a page that is not waits exactly as
		 * long as it needs to.
		 *
		 * @param page The page, already navigated.
		 */
		async function hasSettled( page: Page ): Promise< void > {
			await page
				.locator( '.dp-tl-years' )
				.waitFor( { state: 'attached' } );
			await page.evaluate( () => document.fonts.ready );

			await page.waitForFunction( () => {
				const years = document.querySelector( '.dp-tl-years' );
				const track = document.querySelector(
					'.dp-tl-row-role .dp-tl-track'
				);

				if ( ! years || ! track ) {
					return false;
				}

				const box = ( element: Element ): string => {
					const rect = element.getBoundingClientRect();

					return `${ rect.left }:${ rect.width }`;
				};

				const frame = [
					window.getComputedStyle( years ).display,
					box( years ),
					box( track ),
					...Array.from( years.children ).map( box ),
				].join( '|' );

				const memo = window as unknown as { dpAxisFrame?: string };
				const agrees = memo.dpAxisFrame === frame;

				memo.dpAxisFrame = frame;

				return agrees;
			} );
		}

		/**
		 * The axis, its track, and where every label sits inside it.
		 *
		 * @param page The page, already on the work page in bars mode.
		 */
		async function axis( page: Page ) {
			return page.evaluate( () => {
				const years = document.querySelector( '.dp-tl-years' );
				const track = document.querySelector(
					'.dp-tl-row-role .dp-tl-track'
				);

				if ( ! years || ! track ) {
					return null;
				}

				const box = years.getBoundingClientRect();

				return {
					left: box.left,
					width: box.width,
					track: {
						left: track.getBoundingClientRect().left,
						width: track.getBoundingClientRect().width,
					},
					labels: Array.from( years.children ).map( ( label ) => {
						const own = label.getBoundingClientRect();

						return {
							year: ( label.textContent ?? '' ).trim(),
							left: own.left,
							right: own.left + own.width,
							ink: ( label as HTMLElement ).scrollWidth,
						};
					} ),
				};
			} );
		}

		test( 'every label begins exactly where its own year does', async ( {
			page,
		} ) => {
			await page.setViewportSize( DESKTOP );
			await page.goto( workPage );

			/*
			 * Bars mode is what is being measured, and it is a container query,
			 * so asserting it makes "the track has a width" true rather than
			 * assumed. `.first()` because `axis()` reads the document's first
			 * role track, and this has to be about the same element.
			 */
			await expect(
				page.locator( '.dp-tl-row-role .dp-tl-track' ).first()
			).toBeVisible();
			await hasSettled( page );

			const measured = await axis( page );

			expect( measured, 'The axis is not on the page.' ).toBeTruthy();

			const { left, width, labels, track } = measured!;

			// The axis and the bars share a column, which is the premise of
			// everything below: a percentage of one is a percentage of the other.
			expect( track.left ).toBeCloseTo( left, 0 );
			expect( track.width ).toBeCloseTo( width, 0 );

			expect(
				labels.length,
				'Thirteen years from 2014 to 2026, or one more per year since.'
			).toBeGreaterThanOrEqual( 13 );

			const span = labels.length;

			for ( const [ index, label ] of labels.entries() ) {
				/*
				 * `pos(year)` for the `index`-th labelled year, said the way the
				 * grid says it. Under the design's `space-between` this would be
				 * `index / (span - 1)`, and the last label would be 7.7% out.
				 */
				expect(
					label.left - left,
					`"${ label.year }" is label ${ index } of ${ span }; ` +
						`Geometry::position() puts it at ${ (
							( index / span ) *
							100
						).toFixed( 2 ) }% of the track.`
				).toBeCloseTo( ( index / span ) * width, 0 );
			}
		} );

		test( 'the last label stays inside the track and is not clipped', async ( {
			page,
		} ) => {
			/*
			 * The cost of the change, measured rather than assumed. On the
			 * design's scale the last label ended at the right-hand edge; on the
			 * bars' scale it begins at 92.3% and has the final thirteenth to sit
			 * in — about 36px at the narrowest width bars mode is ever drawn at,
			 * against four mono characters of roughly 32. That is a real margin
			 * and a small one, so it is checked at both ends of the range: the
			 * text must fit its own column, and nothing may overflow the track.
			 */
			for ( const width of [ 1440, 760 ] ) {
				await page.setViewportSize( { width, height: 900 } );
				await page.goto( workPage );
				await hasSettled( page );

				// 760 stacks (the container is under 700), so the axis is only
				// drawn at widths where the track exists. Which mode is drawn is
				// itself a container query, so the question is put to a settled
				// page — an `isVisible()` the instant `goto()` returns can be
				// asking before the answer exists.
				if (
					! ( await page
						.locator( '.dp-tl-track' )
						.first()
						.isVisible() )
				) {
					continue;
				}

				const measured = await axis( page );

				expect( measured ).toBeTruthy();

				const last = measured!.labels[ measured!.labels.length - 1 ];
				const column = measured!.width / measured!.labels.length;

				expect(
					last.ink,
					`At ${ width }px the last column is ${ column.toFixed(
						1
					) }px and "${ last.year }" needs ${ last.ink }px.`
				).toBeLessThanOrEqual( Math.ceil( column ) );

				expect(
					last.right,
					'The axis may not run past the track it labels.'
				).toBeLessThanOrEqual(
					measured!.track.left + measured!.track.width + 1
				);
			}
		} );

		test( 'a role that runs to now reaches its own tick', async ( {
			page,
		} ) => {
			await page.setViewportSize( DESKTOP );
			await page.goto( workPage );

			/*
			 * The bar and the tick are two different elements, so this
			 * comparison is exactly as sensitive to an unsettled layout as the
			 * two above it. `.first()` for the same reason as there.
			 */
			await expect(
				page.locator( '.dp-tl-row-role .dp-tl-track' ).first()
			).toBeVisible();
			await hasSettled( page );

			const measured = await axis( page );

			expect( measured ).toBeTruthy();

			// The label naming the calendar year the shared role ends in. Read
			// from the fixture rather than typed, so this goes on meaning the
			// same thing once the axis has grown a column past it.
			const ends = String( Math.floor( SHARED_ROLE.end ) );
			const tick = measured!.labels.find(
				( label ) => label.year === ends
			);

			expect(
				tick,
				`The axis has no "${ ends }" label, so the fixture's role ends off the track.`
			).toBeTruthy();

			const bar = await page
				.locator( `#${ LAB.entry } .dp-tl-bar` )
				.evaluate( ( element ) => {
					const box = element.getBoundingClientRect();

					return box.left + box.width;
				} );

			expect(
				bar,
				'This is the whole of what David reported: a role running to the ' +
					'present drew short of the label for its own year, because the ' +
					'axis was on a different scale from the bars. ADR-0014.'
			).toBeGreaterThan( tick!.left );
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

			expect( await isOpen( page, LAB.entry ) ).toBe( false );

			await page.locator( `#${ LAB.entry } .dp-tl-summary` ).click();
			await expect(
				page.locator( `#${ LAB.entry } .dp-tl-detail` )
			).toBeVisible();

			await page.locator( `#${ KIVEO.entry } .dp-tl-summary` ).click();
			await expect(
				page.locator( `#${ KIVEO.entry } .dp-tl-panel` )
			).toBeVisible();

			// The design's whole point: the second one does not close the first.
			expect( await isOpen( page, LAB.entry ) ).toBe( true );
			expect( await isOpen( page, KIVEO.entry ) ).toBe( true );

			await page.locator( `#${ LAB.entry } .dp-tl-summary` ).click();

			expect( await isOpen( page, LAB.entry ) ).toBe( false );
			expect( await isOpen( page, KIVEO.entry ) ).toBe( true );

			// What is open is in the URL, so the state is copyable.
			expect( page.url() ).toContain( `dp-open=${ KIVEO.entry }` );
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
				`[data-dp-lane="${ LAB.entry }"] .dp-tl-ships`
			);
			const bare = page.locator( `[data-dp-lane="${ BARE.entry }"]` );

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
				`.dp-cards a.dp-card-open[data-dp-entry="${ KIVEO.entry }"]`
			);

			await expect( card ).toBeVisible();

			await card.click();

			await expect(
				page.locator( `#${ KIVEO.entry } .dp-tl-panel` )
			).toBeVisible();

			expect( page.url() ).toContain( `dp-open=${ KIVEO.entry }` );
		} );

		test( 'a deep link arrives with the entry already open', async ( {
			page,
		} ) => {
			/*
			 * No fragment. With one, the controller would open the entry on
			 * arrival and the test would pass whether or not the server had
			 * done anything — which is the opposite of what it is for.
			 */
			await page.goto( workUrl( { 'dp-open': OPS.entry } ) );

			await expect(
				page.locator( `#${ OPS.entry } .dp-tl-panel` )
			).toBeVisible();

			expect( await isOpen( page, KIVEO.entry ) ).toBe( false );
		} );

		test( 'the whole chart is operable from the keyboard alone', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			// No clicks anywhere in this test.
			await tabTo( page, `#${ BARE.entry } .dp-tl-summary` );
			await page.keyboard.press( 'Enter' );

			expect( await isOpen( page, BARE.entry ) ).toBe( true );

			await page.keyboard.press( 'Enter' );

			expect( await isOpen( page, BARE.entry ) ).toBe( false );

			// Space is the other disclosure key, and it is not the same code path.
			await page.keyboard.press( 'Space' );

			expect( await isOpen( page, BARE.entry ) ).toBe( true );

			// The focused summary shows a ring rather than nothing.
			const outline = await page
				.locator( `#${ BARE.entry } .dp-tl-summary` )
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
			await page.goto( workUrl( { 'dp-open': KIVEO.entry } ) );

			expect( await prefersReducedMotion( page ) ).toBe( true );

			const panel = page.locator( `#${ KIVEO.entry } .dp-tl-panel` );

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

			const bar = page.locator( `#${ LAB.entry } .dp-tl-bar` );

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
		/*
		 * Motion off as well, and for a reason that is about the test rather than
		 * about the browser. `rowStyle` transitions `padding` as well as
		 * `background` — an open row is taller than a closed one — so for 200ms
		 * after the first click the `<summary>` is still easing into place, and
		 * Playwright will not click an element whose box moved between two
		 * frames. What this block asserts is that a disclosure works with no
		 * script at all; how long its padding takes to settle is not part of it,
		 * and `components.css` already takes the transition away under this
		 * preference. Same tool, same reasoning as `design-parity.spec.ts`
		 * (ADR-0013).
		 */
		test.use( {
			viewport: DESKTOP,
			storageState: READER,
			javaScriptEnabled: false,
		} );

		test.beforeEach( async ( { page } ) => {
			await page.emulateMedia( { reducedMotion: 'reduce' } );
		} );

		test( 'rows still open and close', async ( { page } ) => {
			await page.goto( workPage );

			expect( await scriptsAreOff( page ) ).toBe( true );

			const detail = page.locator( `#${ LAB.entry } .dp-tl-detail` );

			await expect( detail ).toBeHidden();

			await page.locator( `#${ LAB.entry } .dp-tl-summary` ).click();

			await expect( detail ).toBeVisible();

			await page.locator( `#${ LAB.entry } .dp-tl-summary` ).click();

			await expect( detail ).toBeHidden();
		} );

		test( 'the filter is three links that still filter', async ( {
			page,
		} ) => {
			await page.goto( workPage );

			expect( await scriptsAreOff( page ) ).toBe( true );

			const ships = page.locator(
				`[data-dp-lane="${ LAB.entry }"] .dp-tl-ships`
			);
			const bare = page.locator( `[data-dp-lane="${ BARE.entry }"]` );

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
			await expect( page.locator( `#${ KIVEO.entry }` ) ).toBeVisible();
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
					`.dp-cards a.dp-card-open[data-dp-entry="${ KIVEO.entry }"]`
				)
				.click();

			await page.waitForURL( new RegExp( `dp-open=${ KIVEO.entry }` ) );

			await expect(
				page.locator( `#${ KIVEO.entry } .dp-tl-panel` )
			).toBeVisible();
		} );

		test( 'and the chart is readable end to end', async ( { page } ) => {
			await page.goto( workUrl( { 'dp-open': 'all' } ) );

			await expect(
				page.locator( `#${ KIVEO.entry } .dp-tl-headline` )
			).toHaveText( `${ KIVEO.title } — the one line.` );

			await expect(
				page.locator( '.dp-timeline .dp-tl-legend' )
			).toContainText( LAB.title );

			await expect(
				page.locator( `#${ LAB.entry } .dp-tl-bar` )
			).toBeVisible();
		} );
	} );
} );
