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
 * **The fixture is dated 2019 on purpose.** Twelve posts is enough to paginate,
 * and posts on any site's blog index push whatever was there down the list. The
 * suite runs fully parallel against one site (ADR-0013), and `chrome.spec.ts`
 * asserts that its own two posts are visible on the first page of that index —
 * so this fixture is old enough that it can never be on the first page of
 * anything but its own term archive.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** How many posts, and therefore how many pages at ten to a page. */
const POSTS = 12;

/** The slugs this fixture owns. Nothing outside this list is ever deleted. */
const SLUGS = {
	category: 'pager-fixture-term',
	post: ( index: number ) => `pager-fixture-post-${ index }`,
};

/** What the term is called, so its name can be read out of the range line. */
const TERM = 'Pager fixture';

/** The shape of the REST fields this spec reads back. */
type Created = { id: number; link: string };

/** The term archive's URL, filled in by `beforeAll`. */
let archive = '';

/**
 * Delete everything carrying one of this fixture's slugs, and nothing else.
 *
 * @param requestUtils The suite's REST client.
 */
async function removeFixture(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< void > {
	const posts = Array.from( { length: POSTS }, ( _unused, index ) =>
		SLUGS.post( index + 1 )
	);

	const sweep: Array< [ string, string[] ] > = [
		[ 'posts', posts ],
		[ 'categories', [ SLUGS.category ] ],
	];

	for ( const [ endpoint, slugs ] of sweep ) {
		const found: Created[] = await requestUtils.rest( {
			path: `/wp/v2/${ endpoint }`,
			params:
				endpoint === 'categories'
					? { slug: slugs.join( ',' ), per_page: 100 }
					: { slug: slugs.join( ',' ), per_page: 100, status: 'any' },
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

test.describe( 'The pager', () => {
	/*
	 * Serial, like `chrome.spec.ts` and for the same reason: every test here
	 * shares one fixture, `beforeAll` runs once per worker, and under
	 * `fullyParallel` two workers would race to create the same slugs.
	 */
	test.describe.configure( { mode: 'serial' } );

	test.use( {
		javaScriptEnabled: false,
		// Logged out: the admin bar is chrome no reader sees.
		storageState: { cookies: [], origins: [] },
		viewport: { width: 1440, height: 900 },
	} );

	test.beforeAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );

		const term = await requestUtils.rest< Created >( {
			path: '/wp/v2/categories',
			method: 'POST',
			data: { name: TERM, slug: SLUGS.category },
		} );

		for ( let index = 1; index <= POSTS; index++ ) {
			await requestUtils.rest( {
				path: '/wp/v2/posts',
				method: 'POST',
				data: {
					title: `Pager fixture — post ${ index }`,
					slug: SLUGS.post( index ),
					status: 'publish',
					categories: [ term.id ],
					// Old enough never to reach the first page of the site's
					// own index, which other specs assert about.
					date_gmt: `2019-01-${ String( index ).padStart(
						2,
						'0'
					) }T09:00:00`,
				},
			} );
		}

		archive = term.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );
	} );

	test( 'names where you are, and takes you to the next page without scripts', async ( {
		page,
	} ) => {
		await page.goto( archive );

		const range = page.locator( '.dp-pagination-range' );

		await expect( range ).toHaveText( `1–10 of ${ POSTS } in ${ TERM }` );
		await expect( page.locator( '.dp-row' ) ).toHaveCount( 10 );

		// The current page is the one marked, and it is not a link.
		await expect( page.locator( '.page-numbers.current' ) ).toHaveText(
			'1'
		);
		await expect(
			page.locator( '.page-numbers.current' )
		).not.toHaveAttribute( 'href', /./ );

		await page.locator( '.wp-block-query-pagination-next' ).click();

		await expect( range ).toHaveText( `11–12 of ${ POSTS } in ${ TERM }` );
		await expect( page.locator( '.dp-row' ) ).toHaveCount( 2 );
		await expect( page.locator( '.page-numbers.current' ) ).toHaveText(
			'2'
		);

		// And back, the same way.
		await page.locator( '.wp-block-query-pagination-previous' ).click();

		await expect( range ).toHaveText( `1–10 of ${ POSTS } in ${ TERM }` );
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
