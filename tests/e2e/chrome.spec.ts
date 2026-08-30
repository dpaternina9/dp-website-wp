/**
 * Phase 5 acceptance: the chrome, in a browser, twice over.
 *
 * Two promises are made about this site's navigation and neither can be checked
 * anywhere but here.
 *
 * The first is CLAUDE.md §1.7: "every page must be readable and navigable with
 * JS off". The mobile panel is a `<dialog>`, and a dialog that only opens from
 * `showModal()` breaks that promise completely — so one half of this file runs
 * with scripting disabled and opens the menu anyway, through the `:target` rule
 * the hamburger's href exists for. The filter pills are the same promise in a
 * different place: `FilterPills.dc.html` says they are "real links to filtered
 * archive URLs, not JS tabs", and the only way to prove it is to switch the
 * scripts off and click one.
 *
 * The second is the design's own note on `SiteHeader`: "Escape closes the
 * panel; body scroll is locked while the panel is open". With scripting on, the
 * panel is upgraded to a real modal, and the other half of this file drives it
 * from the keyboard alone — no clicks anywhere — because a focus trap is not
 * something a mouse can test.
 *
 * The suite establishes its own content. :8889 is a fixture, not a preview, and
 * `composer test:integration` reinstalls WordPress into the same database.
 *
 * External dependencies
 */
import { AxeBuilder } from '@axe-core/playwright';
import type { Locator, Page } from '@playwright/test';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { unexplainedViolations, WCAG_22_AA } from './axe';
import {
	blockingScripts,
	eventHandlerAttributes,
	inlineScripts,
	visitRecordingOffsite,
} from './front-end';

/** Phones, at the width the design's panel is drawn for. */
const PHONE = { width: 390, height: 844 };

/** Desktop, above the header's 720px container query. */
const DESKTOP = { width: 1440, height: 900 };

/** What David called his blog. Deliberately not `blog`. */
const POSTS_PAGE = 'Field notes';

/** The category the filtering test filters to. */
const CATEGORY = 'Dev';

/** A second category, so filtering has something to filter out. */
const OTHER_CATEGORY = 'Food';

/** The two posts, named so no other spec's content can be mistaken for them. */
const FILED_UNDER_DEV = 'Chrome fixture — filed under Dev';

/** The one that must disappear when the Dev pill is followed. */
const FILED_UNDER_FOOD = 'Chrome fixture — filed under Food';

/**
 * The URLs the fixture produced, filled in by `beforeAll`.
 */
const site: { posts: string; category: string; home: string } = {
	posts: '',
	category: '',
	home: '',
};

/** The slugs this fixture owns. Nothing outside this list is ever deleted. */
const SLUGS = {
	home: 'chrome-fixture-welcome',
	posts: 'chrome-fixture-field-notes',
	contact: 'chrome-fixture-say-hello',
	category: 'chrome-fixture-dev',
	otherCategory: 'chrome-fixture-food',
	postInCategory: 'chrome-fixture-post-dev',
	postInOther: 'chrome-fixture-post-food',
};

/** The shape of the two REST fields this spec reads back. */
type Created = { id: number; link: string };

/** Settings → Reading, as the REST API returns it. */
type Reading = {
	show_on_front: string;
	page_on_front: number;
	page_for_posts: number;
};

/** Settings → Reading as it was before this spec touched it. */
let previousReading: Reading | null = null;

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
		[ 'pages', [ SLUGS.home, SLUGS.posts, SLUGS.contact ] ],
		[ 'posts', [ SLUGS.postInCategory, SLUGS.postInOther ] ],
		[ 'categories', [ SLUGS.category, SLUGS.otherCategory ] ],
	];

	for ( const [ endpoint, slugs ] of sweep ) {
		const found: Created[] = await requestUtils.rest( {
			path: `/wp/v2/${ endpoint }`,
			params:
				endpoint === 'categories'
					? { slug: slugs.join( ',' ), per_page: 100 }
					: {
							slug: slugs.join( ',' ),
							per_page: 100,
							status: 'any',
					  },
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
 * Tab until an element matching a selector has focus.
 *
 * Returns the number of presses it took, so a test can assert the control is
 * reachable rather than merely present. Throws if it never arrives, which is
 * the failure worth having: a control nobody can reach by keyboard.
 *
 * @param page     The page.
 * @param selector What we are tabbing towards.
 * @param limit    How many presses to allow before giving up.
 */
async function tabTo(
	page: Page,
	selector: string,
	limit = 20
): Promise< number > {
	const target = page.locator( selector );

	for ( let presses = 1; presses <= limit; presses++ ) {
		await page.keyboard.press( 'Tab' );

		if ( await hasFocus( target ) ) {
			return presses;
		}
	}

	throw new Error(
		`"${ selector }" was not reachable within ${ limit } presses of Tab.`
	);
}

/**
 * Whether an element is the one with focus.
 *
 * @param target The element.
 */
function hasFocus( target: Locator ): Promise< boolean > {
	return target.evaluate(
		( element ) => element === element.ownerDocument.activeElement
	);
}

/**
 * Where focus is, relative to the panel.
 *
 * Three answers rather than two, because a modal dialog's focus cycle has a
 * seam in it: when Tab passes the last control, the browser parks focus on the
 * document before wrapping round to the first — in a real browser that is the
 * moment focus would be in the address bar. `body` is therefore not an escape,
 * and `escaped` means what it says: a focusable control outside the dialog,
 * which is the thing `showModal()` is supposed to make unreachable.
 *
 * @param panel The panel.
 */
function focusRelativeTo(
	panel: Locator
): Promise< 'inside' | 'document' | 'escaped' > {
	return panel.evaluate( ( element ) => {
		const focused = element.ownerDocument.activeElement;

		if ( ! focused || focused === element.ownerDocument.body ) {
			return 'document';
		}

		return element.contains( focused ) ? 'inside' : 'escaped';
	} );
}

test.describe( 'Site chrome', () => {
	/*
	 * Serial, against the grain of the rest of the suite. Every test here shares
	 * one fixture — a front page, a posts page and two categories — and
	 * `beforeAll` runs once per worker, so under `fullyParallel` two workers
	 * would race to create the same slugs and one of them would lose.
	 */
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { requestUtils } ) => {
		/*
		 * Nothing outside this fixture is deleted. The suite runs fully
		 * parallel against one site, so a spec that cleared the content would
		 * pull the fixture out from under whichever other spec happened to be
		 * mid-run. Everything below carries a slug no other spec uses, and the
		 * same list is swept before creating and after finishing — so a run
		 * that died half way through does not poison the next one.
		 */
		await removeFixture( requestUtils );

		const reading = await requestUtils.rest< Reading >( {
			path: '/wp/v2/settings',
		} );

		previousReading = {
			show_on_front: reading.show_on_front,
			page_on_front: reading.page_on_front,
			page_for_posts: reading.page_for_posts,
		};

		const home = await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Chrome fixture — welcome',
				slug: SLUGS.home,
				status: 'publish',
			},
		} );

		const posts = await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: { title: POSTS_PAGE, slug: SLUGS.posts, status: 'publish' },
		} );

		await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Chrome fixture — say hello',
				slug: SLUGS.contact,
				status: 'publish',
				// The admin stores a block theme's custom template under its
				// slug, without the extension. Assigning it the way the admin
				// does is the whole point of doing it through REST.
				template: 'dp-contact',
			},
		} );

		await requestUtils.rest( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: {
				show_on_front: 'page',
				page_on_front: home.id,
				page_for_posts: posts.id,
			},
		} );

		const category = await requestUtils.rest< Created >( {
			path: '/wp/v2/categories',
			method: 'POST',
			data: { name: CATEGORY, slug: SLUGS.category },
		} );

		const other = await requestUtils.rest< Created >( {
			path: '/wp/v2/categories',
			method: 'POST',
			data: { name: OTHER_CATEGORY, slug: SLUGS.otherCategory },
		} );

		await requestUtils.rest( {
			path: '/wp/v2/posts',
			method: 'POST',
			data: {
				title: FILED_UNDER_DEV,
				slug: SLUGS.postInCategory,
				status: 'publish',
				categories: [ category.id ],
			},
		} );

		await requestUtils.rest( {
			path: '/wp/v2/posts',
			method: 'POST',
			data: {
				title: FILED_UNDER_FOOD,
				slug: SLUGS.postInOther,
				status: 'publish',
				categories: [ other.id ],
			},
		} );

		site.home = home.link;
		site.posts = posts.link;
		site.category = category.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( previousReading ) {
			await requestUtils.rest( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: previousReading,
			} );
		}

		await removeFixture( requestUtils );
	} );

	test.describe( 'the mobile panel, keyboard only', () => {
		test.use( {
			viewport: PHONE,
			// Logged out: the admin bar is 32px of fixed chrome that nothing in
			// the design accounts for, and it is not what a reader sees.
			storageState: { cookies: [], origins: [] },
		} );

		test( 'opens, traps focus, and closes on Escape', async ( {
			page,
		} ) => {
			await page.goto( site.home );

			const panel = page.locator( '#dp-nav-panel' );
			const opener = page.locator( '.dp-menu-open a' );

			await expect( opener ).toBeVisible();
			await expect( panel ).toBeHidden();

			// Reaching the control counts: no clicks anywhere in this test.
			await tabTo( page, '.dp-menu-open a' );
			await page.keyboard.press( 'Enter' );

			await expect( panel ).toBeVisible();
			await expect( panel ).toHaveAttribute( 'open', '' );
			await expect( opener ).toHaveAttribute( 'aria-expanded', 'true' );

			// Scroll lock, which is the one part of a modal dialog browsers
			// still leave to the page.
			await expect( page.locator( 'html' ) ).toHaveClass(
				/dp-panel-open/
			);

			// The top layer's focus trap. Tabbing well past the number of
			// controls the panel has is the point: focus has to wrap round,
			// not walk out into the page behind.
			expect( await focusRelativeTo( panel ) ).toBe( 'inside' );

			let landedInside = 0;

			for ( let presses = 0; presses < 24; presses++ ) {
				await page.keyboard.press( 'Tab' );

				const where = await focusRelativeTo( panel );

				expect(
					where,
					`focus left the panel after ${ presses + 1 } presses of Tab`
				).not.toBe( 'escaped' );

				if ( where === 'inside' ) {
					landedInside++;
				}
			}

			// And it really did keep moving through the panel, rather than
			// sitting on the document for twenty-four presses.
			expect( landedInside ).toBeGreaterThan( 10 );

			await page.keyboard.press( 'Escape' );

			await expect( panel ).toBeHidden();
			await expect( opener ).toHaveAttribute( 'aria-expanded', 'false' );
			await expect( page.locator( 'html' ) ).not.toHaveClass(
				/dp-panel-open/
			);

			// Focus goes back where it came from, or the reader is lost.
			expect( await hasFocus( opener ) ).toBe( true );
		} );

		test( 'marks where the reader is', async ( { page } ) => {
			await page.goto( site.home );

			await tabTo( page, '.dp-menu-open a' );
			await page.keyboard.press( 'Enter' );

			/*
			 * Scoped to the panel's navigation. The brand is a `core/site-title`
			 * and core marks that `aria-current` on the front page too, which is
			 * correct and is not the marker being asserted here.
			 */
			const current = page.locator(
				'#dp-nav-panel .dp-panel-nav [aria-current="page"]'
			);

			await expect( current ).toHaveCount( 1 );

			// The design's HERE marker is drawn, not written: `aria-current`
			// already tells a screen reader which item this is.
			const marker = await current.evaluate(
				( element ) =>
					window.getComputedStyle( element, '::after' ).content
			);

			expect( marker ).toBe( '"HERE"' );
		} );

		test( "is not reachable above the header's 720px switch", async ( {
			page,
		} ) => {
			await page.setViewportSize( DESKTOP );
			await page.goto( site.home );

			await expect( page.locator( '.dp-menu-open a' ) ).toBeHidden();
			await expect( page.locator( '.dp-header-wide' ) ).toBeVisible();
			await expect(
				page
					.locator(
						'.dp-header-wide .wp-block-navigation-item__content'
					)
					.first()
			).toBeVisible();
		} );
	} );

	test.describe( 'the blog index, audited', () => {
		// Logged out: the audit is of the page a reader sees, not the admin bar.
		test.use( { storageState: { cookies: [], origins: [] } } );

		/*
		 * The one template a11y.spec.ts cannot reach: home.html only renders
		 * once Settings → Reading points at a posts page, and this file owns
		 * that flip. The sweep on it therefore lives here, held to all four of
		 * that file's facts rather than only the first — a thirteenth template
		 * measured on a shorter ruler is a thirteenth template nobody measured.
		 */
		test( 'WCAG 2.2 AA, one origin, nothing a CSP refuses', async ( {
			page,
		} ) => {
			const offsite = await visitRecordingOffsite( page, site.posts );

			expect( offsite ).toEqual( [] );
			expect( await blockingScripts( page ) ).toEqual( [] );
			expect( await inlineScripts( page ) ).toEqual( [] );
			expect( await eventHandlerAttributes( page ) ).toEqual( [] );

			const results = await new AxeBuilder( { page } )
				.withTags( WCAG_22_AA )
				.analyze();

			expect( unexplainedViolations( results.violations ) ).toEqual( [] );
		} );
	} );

	test.describe( 'with JavaScript switched off', () => {
		test.use( {
			javaScriptEnabled: false,
			storageState: { cookies: [], origins: [] },
		} );

		test( 'the filter pills are links that filter', async ( { page } ) => {
			await page.goto( site.posts );

			const pills = page.locator( '.dp-filter-pills a' );

			await expect( pills.first() ).toBeVisible();

			// Every pill is an anchor with a real href — not a button, not a tab.
			const hrefs = await pills.evaluateAll( ( links ) =>
				links.map( ( link ) => link.getAttribute( 'href' ) )
			);

			expect( hrefs.length ).toBeGreaterThan( 1 );
			expect( hrefs.every( ( href ) => !! href && href !== '#' ) ).toBe(
				true
			);

			await expect( page.getByText( FILED_UNDER_FOOD ) ).toBeVisible();

			await page
				.locator( '.dp-filter-pills a', { hasText: CATEGORY } )
				.click();

			await expect( page.getByText( FILED_UNDER_DEV ) ).toBeVisible();
			await expect( page.getByText( FILED_UNDER_FOOD ) ).toHaveCount( 0 );

			// And back, through the All pill, to everything.
			await page.goto( site.posts );
			await page.locator( '.dp-pill-all a' ).click();

			await expect( page.getByText( FILED_UNDER_FOOD ) ).toBeVisible();
		} );

		test( 'the mobile panel still opens', async ( { page } ) => {
			await page.setViewportSize( PHONE );
			await page.goto( site.home );

			const panel = page.locator( '#dp-nav-panel' );

			await expect( panel ).toBeHidden();

			// No script ran, so nothing intercepted this: the browser follows
			// the href, the panel becomes `:target`, and the stylesheet opens it.
			await page.locator( '.dp-menu-open a' ).click();

			await expect( panel ).toBeVisible();
			await expect( page ).toHaveURL( /#dp-nav-panel$/ );

			const links = panel.locator( 'a' );

			expect( await links.count() ).toBeGreaterThan( 1 );

			await panel.locator( '.dp-menu-close a' ).click();

			await expect( panel ).toBeHidden();
		} );
	} );
} );
