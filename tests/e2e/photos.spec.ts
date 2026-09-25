/**
 * The Photos page's critical path.
 *
 * Four promises, each at the width it belongs to.
 *
 * **The index filters, through real links.** Hovering an entry lights its
 * photos and dims the rest; pressing it narrows the wall without a page load,
 * moves the URL, marks the entry current and says so to a screen reader.
 *
 * **One lightbox for the set.** A tile opens it, the arrow keys step through the
 * filtered set, the URL follows the open photo, and Escape closes it and puts
 * focus back on the tile it came from.
 *
 * **On a phone the index is a sheet behind a docked pill.**
 *
 * **Axe finds nothing it does not already know about**, and the whole thing
 * works with scripts off: a `?photo=` URL is the open photo, drawn by the server.
 *
 * External dependencies
 */
import { AxeBuilder } from '@axe-core/playwright';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { unexplainedViolations, WCAG_22_AA } from './axe';
import { SHARED_PHOTOS, sharedPhotosPageUrl } from './global-setup';

const NORTH = SHARED_PHOTOS.trips.north;

/**
 * The page's URL with query args added.
 *
 * The permalink may already carry a query string — under plain permalinks it
 * is `?page_id=N` — so the args are merged, never concatenated.
 *
 * @param url  The page's URL.
 * @param args The args to add.
 * @return The URL.
 */
function withArgs( url: string, args: Record< string, string > ): string {
	const next = new URL( url );

	for ( const [ key, value ] of Object.entries( args ) ) {
		next.searchParams.set( key, value );
	}

	return next.href;
}
const SOUTH = SHARED_PHOTOS.trips.south;

test.describe( 'the Photos page', () => {
	/** The page's URL, filled in once per worker. */
	let photosPage = '';

	test.beforeAll( async ( { requestUtils } ) => {
		photosPage = await sharedPhotosPageUrl( requestUtils );
	} );

	test( 'filters the wall from the index', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( photosPage );

		const wall = page.locator( '.dp-pw-wall' );
		const north = page.locator(
			`a.dp-pw-entry[data-slug="${ NORTH.slug }"]`
		);

		await expect( wall ).toHaveClass( /is-laid/ );
		await expect(
			page.getByRole( 'navigation', { name: 'Index' } )
		).toBeVisible();

		// The signature moment: its photos stay lit, the rest step back.
		await north.hover();
		await expect( wall ).toHaveAttribute( 'data-hl', '' );
		await expect(
			wall.locator( `a.dp-pw-tile[data-trip="${ NORTH.slug }"].is-hl` )
		).toHaveCount( 2 );

		await north.click();

		await expect( page ).toHaveURL( new RegExp( `trip=${ NORTH.slug }` ) );
		await expect( north ).toHaveAttribute( 'aria-current', 'page' );
		await expect( wall.locator( 'a.dp-pw-tile:not(.is-out)' ) ).toHaveCount(
			2
		);
		await expect( page.locator( '.dp-pw-filter-name' ) ).toHaveText(
			NORTH.name
		);
		await expect( page.locator( '.dp-pw-status' ) ).toContainText(
			NORTH.name
		);

		// Back is the unfiltered wall again.
		await page.goBack();
		await expect(
			wall.locator( `a.dp-pw-tile[data-slug^="e2e-photo-"]:not(.is-out)` )
		).toHaveCount( SHARED_PHOTOS.photos.length );
	} );

	test( 'steps through the filtered set in one lightbox and gives focus back', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( withArgs( photosPage, { trip: NORTH.slug } ) );

		const first = page.locator(
			`a.dp-pw-tile[data-slug="${ SHARED_PHOTOS.photos[ 0 ].slug }"]`
		);
		const lightbox = page.locator( 'dialog.dp-pw-lb' );

		await first.focus();

		// The suite is logged in as an administrator, so focusing a tile
		// shows its Edit pill, and it is the next tab stop.
		const pill = page.locator( 'a.dp-pw-edit-float' );
		const editHref = await first.getAttribute( 'data-edit' );

		expect( editHref ).toMatch( /post\.php\?post=\d+&action=edit/ );
		await expect( pill ).toBeVisible();
		await expect( pill ).toHaveAttribute( 'href', editHref! );
		await page.keyboard.press( 'Tab' );
		await expect( pill ).toBeFocused();
		await page.keyboard.press( 'Shift+Tab' );
		await expect( first ).toBeFocused();

		await page.keyboard.press( 'Enter' );

		await expect( lightbox ).toHaveAttribute( 'open', '' );
		await expect( pill ).toBeHidden();
		await expect( lightbox.locator( 'a.dp-pw-lb-edit' ) ).toHaveAttribute(
			'href',
			editHref!
		);
		await expect( lightbox.locator( '.dp-pw-count' ) ).toHaveText(
			'01 / 02'
		);
		await expect( lightbox.locator( '.dp-pw-d-title' ) ).toHaveText(
			SHARED_PHOTOS.photos[ 0 ].title
		);
		await expect( page ).toHaveURL(
			new RegExp( `photo=${ SHARED_PHOTOS.photos[ 0 ].slug }` )
		);

		await page.keyboard.press( 'ArrowRight' );
		await expect( lightbox.locator( '.dp-pw-count' ) ).toHaveText(
			'02 / 02'
		);
		await expect( lightbox.locator( '.dp-pw-next' ) ).toBeDisabled();
		await expect( page ).toHaveURL(
			new RegExp( `photo=${ SHARED_PHOTOS.photos[ 1 ].slug }` )
		);

		await page.keyboard.press( 'Escape' );

		await expect( lightbox ).not.toHaveAttribute( 'open', '' );
		await expect(
			page.locator(
				`a.dp-pw-tile[data-slug="${ SHARED_PHOTOS.photos[ 1 ].slug }"]`
			)
		).toBeFocused();
		await expect( page ).not.toHaveURL( /photo=/ );
	} );

	test( 'opens the index from the docked pill on a phone', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( photosPage );

		const dock = page.locator( '.dp-pw-dock-open' );
		const sheet = page.locator( 'dialog.dp-pw-sheet' );

		await expect( dock ).toBeVisible();
		await expect(
			page.locator( '.dp-pw-body > .dp-pw-index' )
		).toBeHidden();

		await dock.click();
		await expect( sheet ).toHaveAttribute( 'open', '' );

		await sheet
			.locator( `a.dp-pw-entry[data-slug="${ SOUTH.slug }"]` )
			.click();

		await expect( sheet ).not.toHaveAttribute( 'open', '' );
		await expect( page ).toHaveURL( new RegExp( `trip=${ SOUTH.slug }` ) );
		await expect( dock ).toContainText( SOUTH.name );
		await expect( page.locator( '.dp-pw-dock-clear' ) ).toBeVisible();
	} );

	test( 'loads more as it scrolls, up to the cap, then leaves it to the link', async ( {
		page,
	} ) => {
		const { bulk } = SHARED_PHOTOS;
		const tiles = page.locator( 'a.dp-pw-tile' );
		const more = page.locator( 'a.dp-pw-more-link' );

		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( withArgs( photosPage, { trip: bulk.trip.slug } ) );
		await expect( tiles ).toHaveCount( 48 );

		// While the wall loads by itself, the link is not on screen.
		await expect( more ).toBeHidden();

		// Each approach to the end fetches the next page, until the cap.
		for ( const expected of [ 96, 144, 192 ] ) {
			await page.evaluate( () =>
				window.scrollTo( 0, document.body.scrollHeight )
			);
			await expect( tiles ).toHaveCount( expected );
		}

		await expect( page.locator( '.dp-pw-status' ) ).toHaveText(
			'48 more photos loaded'
		);
		await expect( page ).toHaveURL( /photos-page=4/ );
		await expect( page ).toHaveURL(
			new RegExp( `trip=${ bulk.trip.slug }` )
		);

		// Past the cap nothing loads by itself, however far the reader goes.
		await page.evaluate( () =>
			window.scrollTo( 0, document.body.scrollHeight )
		);
		await page.waitForTimeout( 800 );
		await expect( tiles ).toHaveCount( 192 );
		await expect( more ).toBeVisible();
		await expect( page.locator( '.dp-footer' ) ).toBeInViewport();

		await more.click();
		await expect( tiles ).toHaveCount( bulk.count );
		await expect( more ).toHaveCount( 0 );
	} );

	test( 'loads a wall under the cap to its end without ever showing the link', async ( {
		page,
	} ) => {
		const { some } = SHARED_PHOTOS.bulk;
		const tiles = page.locator( 'a.dp-pw-tile' );
		const more = page.locator( 'a.dp-pw-more-link' );
		let seen = false;

		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( withArgs( photosPage, { topic: some.topic.slug } ) );
		await expect( tiles ).toHaveCount( 48 );

		// Every part fetch carries the set's change stamp.
		const part = page.waitForRequest( /photos-part=1/ );

		while ( ( await tiles.count() ) < some.count ) {
			seen = seen || ( await more.isVisible() );
			await page.evaluate( () =>
				window.scrollTo( 0, document.body.scrollHeight )
			);
			await page.waitForTimeout( 150 );
		}

		expect( ( await part ).url() ).toMatch( /photos-v=[0-9a-f]{12}/ );
		await expect( tiles ).toHaveCount( some.count );
		await expect( more ).toHaveCount( 0 );
		await expect( page.locator( '.dp-pw-loading' ) ).toHaveCount( 0 );
		expect( seen ).toBe( false );
	} );

	test( 'hands the way on back to the link when a page brings nothing new', async ( {
		page,
	} ) => {
		const { some } = SHARED_PHOTOS.bulk;
		const tiles = page.locator( 'a.dp-pw-tile' );
		const more = page.locator( 'a.dp-pw-more-link' );
		const url = withArgs( photosPage, { topic: some.topic.slug } );
		let parts = 0;

		// A stale cache: the next page answers with the first page again.
		const stale = await ( await page.request.get( url ) ).text();

		await page.route( /photos-part=1/, ( route ) => {
			parts++;

			return route.fulfill( {
				status: 200,
				contentType: 'text/html',
				body: stale,
			} );
		} );

		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( url );
		await expect( tiles ).toHaveCount( 48 );

		for ( let i = 0; i < 4; i++ ) {
			await page.evaluate( () =>
				window.scrollTo( 0, document.body.scrollHeight )
			);
			await page.waitForTimeout( 250 );
		}

		// One request, no loop; the link is back, plain and clickable.
		expect( parts ).toBe( 1 );
		await expect( tiles ).toHaveCount( 48 );
		await expect( more ).toBeVisible();
		await expect( more ).not.toHaveClass( /is-loading/ );
		await expect( page.locator( '.dp-pw-loading' ) ).toBeHidden();
		await expect( page.locator( '.dp-pw-wall' ) ).not.toHaveAttribute(
			'aria-busy',
			'true'
		);

		await more.click();
		await expect( page ).toHaveURL( /photos-page=2/ );
		await expect( tiles ).toHaveCount( some.count );
		expect( parts ).toBe( 1 );
	} );

	test( 'comes back to the same depth and place on reload', async ( {
		page,
	} ) => {
		const tiles = page.locator( 'a.dp-pw-tile' );

		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto(
			withArgs( photosPage, { trip: SHARED_PHOTOS.bulk.trip.slug } )
		);

		for ( const expected of [ 96, 144 ] ) {
			await page.evaluate( () =>
				window.scrollTo( 0, document.body.scrollHeight )
			);
			await expect( tiles ).toHaveCount( expected );
		}

		await page.evaluate( () => window.scrollTo( 0, 5000 ) );
		await page.waitForTimeout( 400 );

		// Opening and closing a photo keeps the depth in the address bar.
		await page
			.locator( 'a.dp-pw-tile' )
			.nth( 100 )
			.evaluate( ( tile: HTMLElement ) => tile.click() );
		await expect( page.locator( 'dialog.dp-pw-lb' ) ).toHaveAttribute(
			'open',
			''
		);
		await page.keyboard.press( 'Escape' );
		await expect( page ).toHaveURL( /photos-page=3/ );
		await expect( page ).not.toHaveURL( /photo=/ );

		await page.evaluate( () => window.scrollTo( 0, 5000 ) );
		await page.waitForTimeout( 400 );
		await page.reload();

		await expect( tiles ).toHaveCount( 144 );
		await expect
			.poll( () => page.evaluate( () => window.scrollY ) )
			.toBeGreaterThan( 4800 );
		expect( await page.evaluate( () => window.scrollY ) ).toBeLessThan(
			5200
		);
	} );

	test( 'contains a very tall photo and a very wide one in the lightbox', async ( {
		page,
	} ) => {
		for ( const width of [ 1440, 390 ] ) {
			await page.setViewportSize( { width, height: 844 } );
			await page.goto( photosPage );

			// The fixture's image is square; give two tiles extreme shapes
			// and check the box the lightbox draws them in.
			for ( const [ w, h ] of [
				[ 1000, 4000 ],
				[ 6000, 1000 ],
			] ) {
				const tile = page.locator( 'a.dp-pw-tile' ).first();

				await tile.evaluate(
					( node: HTMLElement, size: number[] ) => {
						node.dataset.w = String( size[ 0 ] );
						node.dataset.h = String( size[ 1 ] );
					},
					[ w, h ]
				);
				await tile.click();

				const lightbox = page.locator( 'dialog.dp-pw-lb' );
				const image = lightbox.locator( '.dp-pw-lb-img' );

				await expect( lightbox ).toHaveAttribute( 'open', '' );

				// Measure where the photo lands, not a frame of its growing.
				await image.evaluate( ( node ) =>
					Promise.all(
						node
							.getAnimations()
							.map( ( motion ) => motion.finished )
					)
				);

				const box = await image.boundingBox();
				const scroll = await lightbox.evaluate( ( node ) => ( {
					overflow: node.scrollHeight - node.clientHeight,
					page: document.documentElement.scrollHeight,
				} ) );

				expect( box ).not.toBeNull();
				expect( box!.y ).toBeGreaterThanOrEqual( 0 );
				expect( box!.y + box!.height ).toBeLessThanOrEqual( 844 + 1 );
				expect( box!.x + box!.width ).toBeLessThanOrEqual( width + 1 );
				expect( box!.width / box!.height ).toBeCloseTo( w / h, 1 );
				expect( scroll.overflow ).toBe( 0 );

				await page.keyboard.press( 'Escape' );
			}
		}
	} );

	test( 'passes axe at every width', async ( { page } ) => {
		for ( const width of [ 1440, 1024, 390 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await page.goto( photosPage );
			await expect( page.locator( '.dp-pw-wall' ) ).toHaveClass(
				/is-laid/
			);

			const results = await new AxeBuilder( { page } )
				.withTags( WCAG_22_AA )
				.analyze();

			expect(
				unexplainedViolations( results.violations ),
				`at ${ width }px`
			).toEqual( [] );
		}
	} );
} );

test.describe( 'the Photos page for a visitor', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'carries no Edit link and no edit URL', async ( {
		page,
		requestUtils,
	} ) => {
		const url = await sharedPhotosPageUrl( requestUtils );

		await page.goto( url );
		await expect( page.locator( 'a.dp-pw-tile' ).first() ).toBeVisible();
		await expect( page.locator( '.dp-pw-edit, [data-edit]' ) ).toHaveCount(
			0
		);
		expect( await page.content() ).not.toContain( 'action=edit' );
	} );
} );

test.describe( 'the Photos page with scripts off', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'draws an open photo in the page', async ( {
		page,
		requestUtils,
	} ) => {
		const url = await sharedPhotosPageUrl( requestUtils );
		const photo = SHARED_PHOTOS.photos[ 2 ];

		await page.goto( withArgs( url, { photo: photo.slug } ) );

		const panel = page.locator( 'section.dp-pw-panel' );

		await expect( panel ).toBeVisible();
		await expect( panel.locator( '.dp-pw-d-title' ) ).toHaveText(
			photo.title
		);
		await expect( panel.locator( '.dp-pw-chip' ).first() ).toHaveText(
			SOUTH.name
		);
		await expect( panel.locator( 'a.dp-pw-next' ) ).toHaveAttribute(
			'href',
			new RegExp( `photo=${ SHARED_PHOTOS.photos[ 3 ].slug }` )
		);
	} );
} );
