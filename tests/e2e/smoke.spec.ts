/**
 * Phase 0 smoke tests.
 *
 * These exist to prove the e2e harness is real: a live site, a real browser,
 * and an authenticated REST client. They assert nothing about the design.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Phase 0 floor', () => {
	test( 'the site responds and renders the block theme', async ( {
		page,
	} ) => {
		const response = await page.goto( '/' );

		expect( response?.status() ).toBe( 200 );

		// A block theme always emits the global-styles stylesheet.
		await expect(
			page
				.locator( '#wp-block-library-css, #global-styles-inline-css' )
				.first()
		).toBeAttached();

		await expect( page.locator( 'body' ) ).toHaveClass(
			/wp-embed-responsive|wp-singular|home|blog/
		);
	} );

	test( 'the REST client is authenticated as an administrator', async ( {
		requestUtils,
	} ) => {
		const me = await requestUtils.rest< { slug: string } >( {
			path: '/wp/v2/users/me',
		} );

		expect( me.slug ).toBe( 'admin' );
	} );

	test( 'the front end is served by the dpaternina theme', async ( {
		page,
		requestUtils,
	} ) => {
		const themes = await requestUtils.rest<
			Array< { stylesheet: string; status: string } >
		>( { path: '/wp/v2/themes', params: { status: 'active' } } );

		expect( themes.map( ( theme ) => theme.stylesheet ) ).toContain(
			'dpaternina'
		);

		await page.goto( '/' );

		// Global styles are emitted per theme, so this is the front end
		// agreeing with the REST API rather than a second look at the same fact.
		const globalStyles = await page
			.locator( '#global-styles-inline-css' )
			.innerText();

		expect( globalStyles.length ).toBeGreaterThan( 0 );
	} );
} );
