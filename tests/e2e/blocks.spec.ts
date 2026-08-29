/**
 * Phase 4 acceptance: the house style, in a browser.
 *
 * The integration suite asserts the markup and the emitted CSS. Neither can
 * answer the question this phase is actually judged on — what the reader sees —
 * because that is the cascade's answer, and the cascade only exists in a
 * browser. Every assertion below is a computed style or a rendered pseudo
 * element.
 *
 * External dependencies
 */
import type { Locator } from '@playwright/test';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** The design's values, written out rather than read from the theme. */
const TEAL = 'rgb(8, 217, 214)'; // --dp-teal, and --accent-text on the dark ground.
const SURFACE = 'rgb(23, 23, 27)'; // --bg-surface.
const MUTED = 'rgb(144, 149, 160)'; // --text-muted.
const SECONDARY = 'rgb(180, 180, 189)'; // --text-secondary.
const BAND = 'rgb(0, 0, 0)'; // --band on the dark ground.

/**
 * The house-style fixture post, block by block.
 *
 * The same sequence as design-source/dpaternina.dc.html's `house-style` entry
 * and as DP\Tests\Integration\Blocks\HouseStyleFixture, trimmed to one of each.
 */
const HOUSE_STYLE_POST = [
	'<!-- wp:paragraph --><p>The rule is that a post is text first.</p><!-- /wp:paragraph -->',
	'<!-- wp:heading --><h2 class="wp-block-heading">Headings run three deep</h2><!-- /wp:heading -->',
	'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Level three groups within a part</h3><!-- /wp:heading -->',
	'<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Level four is for reference material</h4><!-- /wp:heading -->',
	'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One idea per item, no trailing punctuation.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Sentence case, same as everything else.</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
	'<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>Write the thing badly and completely.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Cut every sentence that repeats the one before it.</li><!-- /wp:list-item --></ol><!-- /wp:list -->',
	'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>It&#8217;s better done than perfect.</p><!-- /wp:paragraph --><cite>A lead I had in 2013</cite></blockquote><!-- /wp:quote -->',
	'<!-- wp:code {"dpLabel":"SHELL"} --><pre class="wp-block-code"><code>$ npm run build --silent</code></pre><!-- /wp:code -->',
	'<!-- wp:dp/callout {"label":"NOTE"} --><div class="wp-block-dp-callout dp-callout"><span class="dp-callout-label">NOTE</span><p class="dp-callout-text">Numbers in these posts are from my own projects unless a source is linked.</p></div><!-- /wp:dp/callout -->',
	'<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Block</th><th>Limit</th></tr></thead><tbody><tr><td>Quote</td><td>Two per post</td></tr></tbody></table></figure><!-- /wp:table -->',
	'<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->',
].join( '\n\n' );

/**
 * The pages this worker published, so that it can take them away again.
 *
 * A published page is not a private fixture on this site: the header's
 * navigation is `core/page-list`, so every page this file publishes is a link in
 * everybody's site chrome for as long as it exists. Left behind, they accumulate
 * two per run — and `timeline.spec.ts` tabs from the top of the document to a
 * row on the chart, so a nav that grows by two links a run eventually swallows
 * that budget whole and fails a test that has nothing to do with this file.
 * ADR-0013 made global-query content nobody's; this is the same lesson for
 * content the *chrome* queries.
 */
const published: number[] = [];

/**
 * Publish the fixture on a page that renders post content.
 *
 * Phase 1's only template is `index`, a query loop of titles and excerpts, so a
 * post's body does not reach the front end until Phase 5 ships `single`. The
 * `dp-about` custom template does render `core/post-content` today, so the
 * fixture goes on a page carrying it. The house style is the same either way —
 * it is theme.json and one stylesheet, neither of which knows what is being
 * rendered.
 *
 * @param requestUtils The suite's REST client.
 */
async function publishTheFixture(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< number > {
	const created = await requestUtils.rest( {
		path: '/wp/v2/pages',
		method: 'POST',
		data: {
			title: 'The house style, and every piece of it',
			content: HOUSE_STYLE_POST,
			status: 'publish',
			template: 'dp-about',
		},
	} );

	published.push( created.id );

	return created.id;
}

/*
 * Only what this worker published, by id. A sweep by title or slug would be the
 * wrong shape: the two tests that publish can run in two workers at once, and a
 * sweep in one of them would delete the other's page out from under it.
 */
test.afterAll( async ( { requestUtils } ) => {
	while ( published.length > 0 ) {
		const id = published.pop();

		await requestUtils.rest( {
			path: `/wp/v2/pages/${ id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}
} );

/**
 * The rendered `content` of an element's ::before box.
 *
 * @param locator The element whose marker is wanted.
 */
async function markerOf( locator: Locator ): Promise< string > {
	return locator.evaluate(
		( element ) => getComputedStyle( element, '::before' ).content
	);
}

test.describe( 'the house style on the page', () => {
	test( 'every block in the vocabulary renders as the design draws it', async ( {
		page,
		requestUtils,
	} ) => {
		const id = await publishTheFixture( requestUtils );

		await page.goto( `/?page_id=${ id }` );
		await page.evaluate( () => document.fonts.ready );

		/*
		 * Scoped to the post's content. Phase 5 wrapped every template in the
		 * site chrome, and the CTA band that closes each one carries a heading
		 * of its own — correctly, and with the house style applied to it. What
		 * this test is about is the vocabulary a *post* is written in, so it
		 * looks inside the article rather than at the page.
		 */
		const content = page.locator( '.wp-block-post-content' );

		await test.step( 'h4 is mono caps in the accent colour, not the display face', async () => {
			const h4 = content.locator( 'h4.wp-block-heading' );

			await expect( h4 ).toHaveCSS( 'font-family', /JetBrains Mono/ );
			await expect( h4 ).not.toHaveCSS(
				'font-family',
				/Bricolage Grotesque/
			);
			await expect( h4 ).toHaveCSS( 'text-transform', 'uppercase' );
			await expect( h4 ).toHaveCSS( 'color', TEAL );
			await expect( h4 ).toHaveCSS( 'letter-spacing', /^1\.68px$/ );
			await expect( h4 ).toHaveCSS( 'margin-top', '32px' );
		} );

		await test.step( 'h2 and h3 keep the display face and their own rhythm', async () => {
			const h2 = content.locator( 'h2.wp-block-heading' );

			await expect( h2 ).toHaveCSS(
				'font-family',
				/Bricolage Grotesque/
			);
			await expect( h2 ).toHaveCSS( 'font-size', '30px' );
			await expect( h2 ).toHaveCSS( 'margin-top', '48px' );
			await expect( content.locator( 'h3.wp-block-heading' ) ).toHaveCSS(
				'margin-top',
				'36px'
			);
		} );

		await test.step( 'list markers are rendered, in a 28px column', async () => {
			const ul = content.locator( 'ul.wp-block-list' );
			const ol = content.locator( 'ol.wp-block-list' );

			await expect( ul ).toHaveAttribute( 'role', 'list' );
			await expect( ol ).toHaveAttribute( 'role', 'list' );
			await expect( ul ).toHaveCSS( 'list-style-type', 'none' );

			const item = ul.locator( 'li' ).first();

			expect( await markerOf( item ) ).toBe( '"—"' );
			await expect( item ).toHaveCSS( 'grid-template-columns', /^28px / );

			const numbered = ol.locator( 'li' );

			/*
			 * A browser reports `content` on a counter as the function, not as
			 * the string it paints — there is no API that hands back "01". What
			 * can be asserted is the whole mechanism that produces it: the list
			 * has no native marker, each item increments the counter, and the
			 * marker is drawn from it zero-padded.
			 */
			await expect( ol ).toHaveCSS( 'list-style-type', 'none' );
			await expect( numbered.nth( 0 ) ).toHaveCSS(
				'counter-increment',
				'dp-list-item 1'
			);
			expect( await markerOf( numbered.nth( 0 ) ) ).toBe(
				'counter(dp-list-item, decimal-leading-zero)'
			);

			const marker = await numbered.nth( 0 ).evaluate( ( element ) => {
				const style = getComputedStyle( element, '::before' );

				return {
					family: style.fontFamily,
					size: style.fontSize,
					color: style.color,
				};
			} );

			expect( marker.family ).toContain( 'JetBrains Mono' );
			expect( marker.size ).toBe( '12px' );
			expect( marker.color ).toBe( TEAL );
		} );

		await test.step( 'the quote is the pull quote', async () => {
			const quote = content.locator( 'blockquote.wp-block-quote' );

			await expect( quote ).toHaveCSS( 'border-left-width', '2px' );
			await expect( quote ).toHaveCSS( 'border-left-color', TEAL );
			await expect( quote ).toHaveCSS( 'background-color', SURFACE );
			await expect( quote ).toHaveCSS( 'margin-top', '32px' );

			await expect( quote.locator( 'p' ) ).toHaveCSS(
				'font-family',
				/Bricolage Grotesque/
			);
			await expect( quote.locator( 'p' ) ).toHaveCSS(
				'font-size',
				'24px'
			);
			await expect( quote.locator( 'cite' ) ).toHaveCSS(
				'font-family',
				/JetBrains Mono/
			);
			await expect( quote.locator( 'cite' ) ).toHaveCSS( 'color', MUTED );
		} );

		await test.step( 'the code block is labelled and forced dark', async () => {
			const code = content.locator( 'pre.wp-block-code' );

			await expect( code ).toHaveClass( /dp-dark/ );
			await expect( code ).toHaveCSS( 'background-color', BAND );
			expect( await markerOf( code ) ).toBe( '"SHELL"' );

			await expect( code.locator( 'code' ) ).toHaveCSS( 'color', TEAL );
			await expect( code.locator( 'code' ) ).toHaveCSS(
				'font-family',
				/JetBrains Mono/
			);
		} );

		await test.step( 'the callout renders without the plugin registering anything', async () => {
			const callout = content.locator( '.wp-block-dp-callout' );

			await expect( callout ).toBeVisible();
			await expect( callout ).toHaveCSS( 'display', 'flex' );
			await expect( callout.locator( '.dp-callout-label' ) ).toHaveCSS(
				'color',
				TEAL
			);
			await expect( callout.locator( '.dp-callout-text' ) ).toHaveCSS(
				'font-size',
				'14px'
			);
		} );

		await test.step( 'the separator is a 1px spectrum line at 60%', async () => {
			const rule = content.locator( 'hr.wp-block-separator' );

			await expect( rule ).toHaveCSS( 'height', '1px' );
			await expect( rule ).toHaveCSS( 'opacity', '0.6' );
			await expect( rule ).toHaveCSS(
				'background-image',
				/linear-gradient/
			);
			await expect( rule ).toHaveCSS( 'border-top-width', '0px' );
		} );

		await test.step( 'the table scrolls rather than squeezing', async () => {
			const table = content.locator( 'figure.wp-block-table' );

			await expect( table ).toHaveCSS( 'overflow-x', 'auto' );
			await expect( table.locator( 'th' ).first() ).toHaveCSS(
				'font-family',
				/JetBrains Mono/
			);
			await expect( table.locator( 'td' ).first() ).toHaveCSS(
				'color',
				SECONDARY
			);
		} );

		await test.step( 'body copy sits on the design’s measure', async () => {
			const paragraph = content.locator( 'p' ).first();

			await expect( paragraph ).toHaveCSS( 'color', SECONDARY );
			await expect( paragraph ).toHaveCSS( 'font-size', '16px' );
		} );
	} );

	test( 'the page needs no JavaScript to read', async ( {
		browser,
		requestUtils,
		baseURL,
	} ) => {
		const id = await publishTheFixture( requestUtils );

		const context = await browser.newContext( {
			javaScriptEnabled: false,
			baseURL,
		} );
		const page = await context.newPage();

		await page.goto( `/?page_id=${ id }` );

		const content = page.locator( '.wp-block-post-content' );

		await expect( content.locator( 'h4.wp-block-heading' ) ).toBeVisible();
		await expect( content.locator( 'pre.wp-block-code' ) ).toBeVisible();
		await expect( content.locator( '.wp-block-dp-callout' ) ).toBeVisible();

		await context.close();
	} );
} );

test.describe( 'the house style in the editor', () => {
	test( 'a post is offered the vocabulary and nothing else', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost();

		const allowed = await page.evaluate(
			() =>
				// eslint-disable-next-line @typescript-eslint/no-explicit-any
				( window as any ).wp.data
					.select( 'core/block-editor' )
					.getSettings().allowedBlockTypes
		);

		expect( Array.isArray( allowed ) ).toBe( true );

		for ( const name of [
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/list-item',
			'core/quote',
			'core/code',
			'core/image',
			'core/table',
			'core/separator',
		] ) {
			expect( allowed ).toContain( name );
		}

		for ( const name of [
			'core/buttons',
			'core/columns',
			'core/html',
			'core/cover',
			'core/group',
		] ) {
			expect( allowed ).not.toContain( name );
		}
	} );

	test( 'a page is left alone, because pages are David’s', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost( { postType: 'page' } );

		const allowed = await page.evaluate(
			() =>
				// eslint-disable-next-line @typescript-eslint/no-explicit-any
				( window as any ).wp.data
					.select( 'core/block-editor' )
					.getSettings().allowedBlockTypes
		);

		expect( allowed ).toBe( true );
	} );

	test( 'the canvas draws the same list marker the page does', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();

		await editor.insertBlock( {
			name: 'core/list',
			innerBlocks: [
				{
					name: 'core/list-item',
					attributes: { content: 'One idea per item.' },
				},
			],
		} );

		const item = editor.canvas.locator( 'ul.wp-block-list li' ).first();

		await expect( item ).toBeVisible();
		expect( await markerOf( item ) ).toBe( '"—"' );
		await expect( item ).toHaveCSS( 'grid-template-columns', /^28px / );
	} );

	test( 'core’s alternative styles are not on offer', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost();

		const styles = await page.evaluate( () =>
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			( window as any ).wp.blocks
				.getBlockType( 'core/separator' )
				.styles.map( ( style: { name: string } ) => style.name )
		);

		expect( styles ).toEqual( [] );
	} );

	/*
	 * The three links this theme computes are registered in PHP with a render
	 * callback and nothing else, which is enough for the front end and not for
	 * the editor: a block the server knows about and the client does not draws
	 * as core’s `core/missing` — "Your site doesn't include support for the
	 * dpaternina/feed-link block" — inside a template that renders perfectly on
	 * the site (ADR-0009). That failure exists only in the canvas, so it can
	 * only be caught in a browser, which is why this assertion is here rather
	 * than in the integration suite.
	 */
	test( 'the theme’s computed links exist in the editor’s registry', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost();

		const registered = await page.evaluate( () =>
			[
				'dpaternina/series-parts-link',
				'dpaternina/resume-download',
				'dpaternina/feed-link',
			].map( ( name ) => ( {
				name,
				// eslint-disable-next-line @typescript-eslint/no-explicit-any
				edit: !! ( window as any ).wp.blocks.getBlockType( name )?.edit,
			} ) )
		);

		for ( const block of registered ) {
			expect(
				block.edit,
				`${ block.name } has no client-side edit, so the site editor draws it as core/missing.`
			).toBe( true );
		}
	} );
} );
