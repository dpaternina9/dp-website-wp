/**
 * Phase 1 acceptance, as a test rather than a screenshot.
 *
 * Two things this phase promises that only a real browser can confirm: a page
 * lands on the design's ground with the design's type, and nothing is fetched
 * from another origin to make that happen.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * The design's own values, from design-source/_ds/tokens/.
 *
 * Written out rather than derived, on purpose: this is the browser's opinion
 * being compared with the design's, and reading both from the same place would
 * make the comparison circular. TokenParityTest owns the source-level check.
 */
const GROUND = 'rgb(12, 12, 14)'; // --dp-ink, via --bg-page.
const BODY_TEXT = 'rgb(255, 255, 255)'; // --dp-white, via --text-primary.
const BODY_SIZE = '16px'; // --fs-base.
const BODY_LEADING = '26.4px'; // --lh-relaxed (1.65) at 16px.

/**
 * The posts this worker published, so that it can take them away again.
 *
 * A published post is not a private fixture: the index template is a query loop
 * over every published post, so one left behind is a row on the home page and in
 * the editor's canvas of it for good — and `spacing.spec.ts` renders that canvas
 * and waits 15 seconds for it. Left uncleaned this file adds one post a run, the
 * canvas gets slower every time, and eventually a spec that has nothing to do
 * with this one starts timing out. ADR-0013 settled who owns content a global
 * query reads; this is the other half of it — what a spec makes, a spec unmakes.
 */
const published: number[] = [];

test.afterAll( async ( { requestUtils } ) => {
	while ( published.length > 0 ) {
		const id = published.pop();

		await requestUtils.rest( {
			path: `/wp/v2/posts/${ id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}
} );

test.describe( 'Phase 1 tokens and skeleton', () => {
	test( 'a page renders on the design ground, in the design type', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Résumé — Medellín',
			content:
				'<!-- wp:paragraph --><p>Ground truth.</p><!-- /wp:paragraph -->',
			status: 'publish',
		} );

		published.push( post.id );

		await page.goto( `/?p=${ post.id }` );
		await page.evaluate( () => document.fonts.ready );

		const body = page.locator( 'body' );

		await expect( body ).toHaveCSS( 'background-color', GROUND );
		await expect( body ).toHaveCSS( 'color', BODY_TEXT );
		await expect( body ).toHaveCSS( 'font-size', BODY_SIZE );
		await expect( body ).toHaveCSS( 'line-height', BODY_LEADING );
		await expect( body ).toHaveCSS( 'font-family', /Manrope/ );

		// Phase 1 ships one template, so the post title is the only heading on the
		// page and the query loop renders it as an h2. The h1-per-page rule is
		// Phase 5's, when the real templates and PageHero arrive.
		const heading = page.locator( '.wp-block-post-title' ).first();

		await expect( heading ).toHaveCSS(
			'font-family',
			/Bricolage Grotesque/
		);

		// The tokens are on :root under their own names, not only under
		// WordPress's generated ones. That is the whole point of the bridge.
		const tokens = await page.evaluate( () => {
			const styles = getComputedStyle( document.documentElement );

			return {
				teal: styles.getPropertyValue( '--dp-teal' ).trim(),
				space5: styles.getPropertyValue( '--space-5' ).trim(),
				radiusLg: styles.getPropertyValue( '--radius-lg' ).trim(),
				measure: styles.getPropertyValue( '--measure' ).trim(),
			};
		} );

		expect( tokens ).toEqual( {
			teal: '#08d9d6',
			space5: '1.5rem',
			radiusLg: '16px',
			measure: '68ch',
		} );

		// Both faces really loaded, rather than falling back to a system stack.
		const loaded = await page.evaluate( () => ( {
			display: document.fonts.check( '700 48px "Bricolage Grotesque"' ),
			body: document.fonts.check( '400 16px "Manrope"' ),
		} ) );

		expect( loaded ).toEqual( { display: true, body: true } );
	} );

	/*
	 * As a visitor, not as David. The logged-in admin bar loads an avatar from
	 * secure.gravatar.com, which is a genuine off-origin request — but it is
	 * WordPress's admin chrome, shown only to a logged-in user, and no visitor
	 * ever sees it. The promise in CLAUDE.md §1.4 is about the site, so the test
	 * is run against the site.
	 */
	test.describe( 'as a logged-out visitor', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'the page fetches nothing from another origin', async ( {
			page,
			baseURL,
		} ) => {
			const origin = new URL( baseURL as string ).origin;
			const offOrigin: string[] = [];

			page.on( 'request', ( request ) => {
				if ( ! request.url().startsWith( origin ) ) {
					offOrigin.push( request.url() );
				}
			} );

			await page.goto( '/' );
			await page.evaluate( () => document.fonts.ready );

			expect(
				offOrigin,
				'CLAUDE.md §1.4: nothing enqueues from a CDN, and the fonts are self-hosted.'
			).toEqual( [] );
		} );
	} );

	test( 'the font preloads are same-origin woff2 with crossorigin set', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		const preloads = await page
			.locator( 'link[rel="preload"][as="font"]' )
			.evaluateAll( ( links ) =>
				links.map( ( link ) => ( {
					href: link.getAttribute( 'href' ) ?? '',
					type: link.getAttribute( 'type' ),
					crossOrigin: link.getAttribute( 'crossorigin' ),
				} ) )
			);

		expect( preloads ).toHaveLength( 2 );

		for ( const preload of preloads ) {
			expect( preload.type ).toBe( 'font/woff2' );
			expect( preload.crossOrigin ).not.toBeNull();
			expect( preload.href ).toContain(
				'/wp-content/themes/dpaternina/assets/fonts/'
			);
			expect( preload.href ).toContain( '-latin.woff2' );
		}
	} );
} );
