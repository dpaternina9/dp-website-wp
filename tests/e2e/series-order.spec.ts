/**
 * Phase C acceptance: a series is put in order by dragging it.
 *
 * The integration suite already asserts what the write path stores and what it
 * refuses to store. Neither of those can answer the question this phase is
 * judged on — whether the row moves when it is dragged — because that is the
 * browser's answer, and the whole feature is a browser interaction over an
 * ordinary form.
 *
 * **The fixture is drafts.** ADR-0013's rule is that content a global query
 * reads belongs to nobody, and a published post is read by the blog index, the
 * feed and the footer's category list. A draft is read by the series screen and
 * by nothing else, so this file can own three of them outright and still make
 * the assertion that matters: published and planned parts share one order, and
 * the ordering of *drafts* against each other is the half no other suite covers.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/*
 * One worker for this file. `fullyParallel` would otherwise split its two tests
 * across workers and run `beforeAll` in each, which means creating a term whose
 * slug already exists and six drafts where there should be three.
 */
test.describe.configure( { mode: 'serial' } );

/** The term this file owns. */
const SERIES = {
	slug: 'e2e-series-order',
	name: 'E2E fixture — a series to put in order',
} as const;

/**
 * The three parts, oldest first.
 *
 * Dated rather than left to the clock, so that the order before anybody drags
 * anything is the same on every run: this is what a series nobody has arranged
 * looks like, and the drag is measured against it.
 */
const PARTS = [
	{ title: 'E2E order — reads first', date: '2026-01-01T09:00:00' },
	{ title: 'E2E order — reads second', date: '2026-01-02T09:00:00' },
	{ title: 'E2E order — reads third', date: '2026-01-03T09:00:00' },
] as const;

/** What the REST API hands back for anything this file creates. */
type Created = { id: number };

/** The term, once it exists. */
let seriesId = 0;

/** The drafts, so that they can be taken away again. */
const drafts: number[] = [];

test.beforeAll( async ( { requestUtils } ) => {
	const term = await requestUtils.rest< Created >( {
		path: '/wp/v2/dp_series',
		method: 'POST',
		data: { name: SERIES.name, slug: SERIES.slug },
	} );

	seriesId = term.id;

	for ( const part of PARTS ) {
		const draft = await requestUtils.rest< Created >( {
			path: '/wp/v2/posts',
			method: 'POST',
			data: {
				title: part.title,
				status: 'draft',
				date: part.date,
				dp_series: [ seriesId ],
			},
		} );

		drafts.push( draft.id );
	}
} );

test.afterAll( async ( { requestUtils } ) => {
	while ( drafts.length > 0 ) {
		await requestUtils.rest( {
			path: `/wp/v2/posts/${ drafts.pop() }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}

	if ( seriesId > 0 ) {
		await requestUtils.rest( {
			path: `/wp/v2/dp_series/${ seriesId }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}
} );

test.describe( 'Ordering a series', () => {
	test( 'the Series list table is how the screen is reached', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/edit-tags.php?taxonomy=dp_series' );

		const row = page.locator( '#the-list tr', { hasText: SERIES.name } );
		const link = row.getByRole( 'link', { name: 'Order parts' } );

		await expect( link ).toHaveCount( 1 );
		await expect( link ).toHaveAttribute(
			'href',
			new RegExp( `page=dp-series-order.*dp_series_id=${ seriesId }` )
		);
	} );

	test( 'dragging a part to the top and saving changes the order', async ( {
		page,
	} ) => {
		await page.goto(
			`/wp-admin/edit.php?page=dp-series-order&dp_series_id=${ seriesId }`
		);

		const titles = page.locator( '.dp-series-order-title' );

		await expect( titles ).toHaveText( [
			PARTS[ 0 ].title,
			PARTS[ 1 ].title,
			PARTS[ 2 ].title,
		] );

		const last = page.locator( '.dp-series-order-item', {
			hasText: PARTS[ 2 ].title,
		} );
		const first = page.locator( '.dp-series-order-item', {
			hasText: PARTS[ 0 ].title,
		} );

		// The top few pixels of the first row, so that "before it" is not a
		// question about which half of the row the pointer landed in.
		await last.dragTo( first, { targetPosition: { x: 20, y: 4 } } );

		await expect( titles ).toHaveText( [
			PARTS[ 2 ].title,
			PARTS[ 0 ].title,
			PARTS[ 1 ].title,
		] );

		await page.getByRole( 'button', { name: 'Save order' } ).click();

		await expect( page.locator( '.notice-success' ) ).toContainText(
			'Order saved'
		);

		await page.reload();

		await expect( titles ).toHaveText( [
			PARTS[ 2 ].title,
			PARTS[ 0 ].title,
			PARTS[ 1 ].title,
		] );
	} );
} );
