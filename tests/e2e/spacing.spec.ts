/**
 * The home page's spacing, in a browser, in both contexts.
 *
 * This file exists because of one fact that has now bitten this project three
 * times: **the block editor injects WordPress's global styles after the theme's
 * editor styles, and the front end prints them before**. Core's layout rules —
 * `:root :where(.is-layout-flow) > * { margin-block-start: … }` and
 * `:root :where(.is-layout-flex) { gap: … }` — are one class of specificity, the
 * same as a bare `.dp-bento` or `.dp-latest`, so a single-class rule in this
 * theme's stylesheets is the design's value on the site and core's 24px block
 * gap in the canvas. Nothing in the PHP suite can see that, because it is not a
 * property of the markup; it is a property of the cascade, and the cascade only
 * exists in a browser. ADR-0008 named the mechanism, ADR-0011 swept the file.
 *
 * There are two kinds of assertion below and they fail for different reasons.
 *
 * The **parity sweep** compares every `dp-` element's margins and gaps between
 * the rendered page and the site editor's canvas and asserts they are identical.
 * It names no numbers at all, so it cannot go stale when a design value changes;
 * what it catches is a new one-class rule, which is the bug, not the value.
 * Padding is deliberately not compared: `--section-y`, `--gutter` and the CTA
 * band's own padding are `clamp()`s of `vw`, and the canvas is a narrower
 * viewport than the page, so those legitimately differ.
 *
 * The **named values** are the two things David reported by eye — the card grid
 * and the section with no bottom padding — pinned to the number the design
 * gives, so a regression says which one moved.
 *
 * External dependencies
 */
import type { Frame, Page } from '@playwright/test';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** Wide enough that every `clamp()` on the page has reached its ceiling. */
const DESKTOP = { width: 1600, height: 1000 };

/** The home page's template, as the site editor addresses it. */
const FRONT_PAGE = 'dpaternina//front-page';

/** The work page's template, addressed the same way. */
const WORK = 'dpaternina//dp-work';

/**
 * The slugs this file's own work-page fixture owns.
 *
 * Distinct from `timeline.spec.ts`'s, deliberately: the suite is
 * `fullyParallel` against one site, and two specs sweeping the same slugs would
 * delete each other's content mid-run.
 */
const SLUGS = {
	page: 'spacing-fixture-work',
	role: 'spacing-fixture-lab',
	ships: [ 'spacing-fixture-kiveo', 'spacing-fixture-ops' ],
};

/** The work page's URL, filled in by `beforeAll`. */
let workPage = '';

/** The shape of the REST fields this spec reads back. */
type Created = { id: number; link: string };

/** The spacing of one element, keyed so the two contexts can be lined up. */
type Spacing = Record< string, string >;

/**
 * Read every `dp-` element's margins and gaps out of a document.
 *
 * The key is the element's own `dp-` classes plus how many elements with that
 * exact set came before it, which survives the extra wrappers and the extra
 * classes the block editor adds around everything.
 *
 * @param scope The page, or the editor canvas frame.
 */
async function collect( scope: Page | Frame ): Promise< Spacing > {
	return scope.evaluate( () => {
		const seen: Record< string, number > = {};
		const rows: Record< string, string > = {};

		document.querySelectorAll( '[class*="dp-"]' ).forEach( ( el ) => {
			const dp = Array.from( el.classList )
				.filter( ( c ) => c.startsWith( 'dp-' ) )
				.sort()
				.join( '.' );

			if ( ! dp ) {
				return;
			}

			seen[ dp ] = ( seen[ dp ] ?? 0 ) + 1;

			const style = window.getComputedStyle( el );

			rows[ `${ dp }#${ seen[ dp ] }` ] = [
				style.marginBlockStart,
				style.marginBlockEnd,
				style.rowGap,
				style.columnGap,
			].join( ' | ' );
		} );

		return rows;
	} );
}

/**
 * The editor canvas for one template, once it has drawn the chrome.
 *
 * @param page     The Playwright page.
 * @param admin    The suite's admin helper.
 * @param template The template's id.
 */
async function canvasOf(
	page: Page,
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	admin: any,
	template: string
): Promise< Frame > {
	await admin.visitSiteEditor( {
		postId: template,
		postType: 'wp_template',
		canvas: 'edit',
	} );

	await expect
		.poll(
			async () => {
				const frame = page
					.frames()
					.find( ( f ) => f.name() === 'editor-canvas' );

				if ( ! frame ) {
					return 0;
				}

				try {
					return await frame.$$eval(
						'[class*="dp-"]',
						( nodes ) => nodes.length
					);
				} catch {
					return 0;
				}
			},
			{
				timeout: 45_000,
				message: 'The editor canvas never drew the template.',
			}
		)
		.toBeGreaterThan( 10 );

	const frame = page.frames().find( ( f ) => f.name() === 'editor-canvas' );

	if ( ! frame ) {
		throw new Error( 'The editor canvas frame disappeared.' );
	}

	/*
	 * Some blocks draw a placeholder of their own before their data arrives —
	 * `core/categories` fetches its terms over REST — and a placeholder is a
	 * different element with the same class. Measuring one is a false failure,
	 * so the canvas is given a moment to settle before anything is read.
	 */
	await frame.waitForSelector( '.dp-footer-cats li, .dp-footer-cats option', {
		timeout: 15_000,
	} );

	return frame;
}

test.describe( 'the home page spaces itself the same way twice', () => {
	test.use( { viewport: DESKTOP } );

	test( 'every margin and gap is identical on the page and in the canvas', async ( {
		page,
		admin,
	} ) => {
		await page.goto( '/' );

		const front = await collect( page );

		expect(
			Object.keys( front ).length,
			'The page rendered nothing to compare.'
		).toBeGreaterThan( 20 );

		const canvas = await canvasOf( page, admin, FRONT_PAGE );
		const editor = await collect( canvas );

		const divergent: string[] = [];

		for ( const [ key, value ] of Object.entries( front ) ) {
			const other = editor[ key ];

			if ( other !== undefined && other !== value ) {
				divergent.push(
					`${ key } — page: ${ value } / canvas: ${ other }`
				);
			}
		}

		expect(
			divergent,
			'A rule that wins on the page and loses in the canvas is a rule with ' +
				'only one class in its selector. Give it a second class or an ' +
				'element name (ADR-0008, ADR-0011).'
		).toEqual( [] );
	} );

	test( "the bento is the design's 16px grid, with no block gap on the tiles", async ( {
		page,
		admin,
	} ) => {
		const read = async ( scope: Page | Frame ) => ( {
			gap: await scope
				.locator( '.dp-bento' )
				.first()
				.evaluate( ( el ) => window.getComputedStyle( el ).rowGap ),
			tiles: await scope
				.locator( '.dp-bento > .dp-tile' )
				.evaluateAll( ( els ) =>
					els.map(
						( el ) => window.getComputedStyle( el ).marginBlockStart
					)
				),
		} );

		await page.goto( '/' );

		const front = await read( page );

		expect( front.gap ).toBe( '16px' );
		expect( front.tiles.length ).toBeGreaterThan( 3 );
		expect(
			front.tiles.every( ( m ) => m === '0px' ),
			"A tile with a block-gap margin turns the design's 16px row gap into 40."
		).toBe( true );

		const canvas = await canvasOf( page, admin, FRONT_PAGE );
		const editor = await read( canvas );

		expect( editor.gap ).toBe( front.gap );
		expect( editor.tiles ).toEqual( front.tiles );
	} );

	test( "the RIGHT NOW section closes with the design's section padding", async ( {
		page,
	} ) => {
		await page.goto( '/' );

		const padding = await page
			.locator( '.dp-right-now' )
			.first()
			.evaluate( ( el ) => ( {
				top: window.getComputedStyle( el ).paddingBlockStart,
				bottom: window.getComputedStyle( el ).paddingBlockEnd,
			} ) );

		expect( padding.top ).toBe( '24px' );

		// --section-y is clamp(3rem, 8vw, 6rem); the floor is what matters here.
		expect( parseFloat( padding.bottom ) ).toBeGreaterThanOrEqual( 48 );
	} );

	test( 'the shipped rows butt against each other, divided by their borders', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		const items = page.locator( '.dp-shipped-item' );

		await expect( items ).toHaveCount( 3 );

		const boxes = await items.evaluateAll( ( els ) =>
			els.map( ( el ) => {
				const rect = el.getBoundingClientRect();

				return {
					top: Math.round( rect.top ),
					bottom: Math.round( rect.bottom ),
				};
			} )
		);

		expect(
			boxes[ 1 ].top - boxes[ 0 ].bottom,
			'The design stacks these with gap: 0 and separates them with a border.'
		).toBe( 0 );
		expect( boxes[ 2 ].top - boxes[ 1 ].bottom ).toBe( 0 );
	} );
} );

/**
 * Delete everything carrying one of this file's own slugs, and nothing else.
 *
 * @param requestUtils The suite's REST client.
 */
async function removeFixture(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< void > {
	const sweep: Array< [ string, string[] ] > = [
		[ 'pages', [ SLUGS.page ] ],
		[ 'dp_role', [ SLUGS.role ] ],
		[ 'dp_ship', SLUGS.ships ],
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
 * Read one element's computed spacing and type out of a document.
 *
 * @param scope    The page, or the editor canvas frame.
 * @param selector What to measure.
 */
async function one(
	scope: Page | Frame,
	selector: string
): Promise< Record< string, string > > {
	return scope
		.locator( selector )
		.first()
		.evaluate( ( el ) => {
			const style = window.getComputedStyle( el );

			return {
				marginBlockStart: style.marginBlockStart,
				marginBlockEnd: style.marginBlockEnd,
				fontSize: style.fontSize,
			};
		} );
}

test.describe( 'the work page spaces itself the same way twice', () => {
	/*
	 * Serial, for the same reason `timeline.spec.ts` is: one fixture, one
	 * `beforeAll` per worker, and `fullyParallel` would otherwise race two
	 * workers to create the same slugs.
	 */
	test.describe.configure( { mode: 'serial' } );
	test.use( { viewport: DESKTOP } );

	test.beforeAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );

		const role = await requestUtils.rest< Created >( {
			path: '/wp/v2/dp_role',
			method: 'POST',
			data: {
				title: 'Spacing fixture — Fanxie Lab',
				slug: SLUGS.role,
				status: 'publish',
				meta: {
					dp_role_title: 'CTO & founder',
					dp_start: 2016,
					dp_end: 2026.6,
					dp_range: '2016 — now',
					dp_detail: 'The thread running under everything else.',
					dp_stack: 'LARAVEL · NESTJS',
				},
			},
		} );

		for ( const slug of SLUGS.ships ) {
			await requestUtils.rest< Created >( {
				path: '/wp/v2/dp_ship',
				method: 'POST',
				data: {
					title: `Spacing fixture — ${ slug }`,
					slug,
					status: 'publish',
					meta: {
						dp_role_id: role.id,
						dp_start: 2023,
						dp_end: 2026.6,
						dp_range: '2023 — now',
						dp_headline: 'The one line.',
						dp_detail: 'What it is and who it is for.',
						dp_line: 'The sentence written for the card.',
						dp_stack: 'SWIFT · SWIFTUI',
						dp_featured: true,
					},
				},
			} );
		}

		const created = await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Spacing fixture — what I have worked on',
				slug: SLUGS.page,
				status: 'publish',
				template: 'dp-work',
			},
		} );

		workPage = created.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );
	} );

	test( 'every margin and gap is identical on the page and in the canvas', async ( {
		page,
		admin,
	} ) => {
		await page.goto( workPage );

		const front = await collect( page );

		expect(
			Object.keys( front ).length,
			'The page rendered nothing to compare.'
		).toBeGreaterThan( 20 );

		const canvas = await canvasOf( page, admin, WORK );
		const editor = await collect( canvas );

		const divergent: string[] = [];

		for ( const [ key, value ] of Object.entries( front ) ) {
			const other = editor[ key ];

			if ( other !== undefined && other !== value ) {
				divergent.push(
					`${ key } — page: ${ value } / canvas: ${ other }`
				);
			}
		}

		expect(
			divergent,
			'A rule that wins on the page and loses in the canvas is a rule with ' +
				'only one class in its selector. Give it a second class or an ' +
				'element name (ADR-0008, ADR-0011).'
		).toEqual( [] );
	} );

	test( 'each section head declares the whole gap under it, and core adds nothing', async ( {
		page,
		admin,
	} ) => {
		const read = async ( scope: Page | Frame ) => ( {
			featuredHead: await one(
				scope,
				'.dp-work-featured .dp-section-head'
			),
			cards: await one( scope, '.dp-work-featured .dp-query' ),
			recordHead: await one( scope, '.dp-work-record .dp-section-head' ),
			lede: await one( scope, '.dp-work-lede' ),
			chart: await one( scope, '.dp-timeline' ),
		} );

		await page.goto( workPage );

		const front = await read( page );

		// dpaternina.dc.html, isTimeline: the SectionHead wrapper above the
		// cards carries `margin-bottom: 32px` and the one above the chart 24,
		// and neither the grid nor the chart carries a margin of its own.
		expect( front.featuredHead.marginBlockEnd ).toBe( '32px' );
		expect(
			front.cards.marginBlockStart,
			"Core's block gap is a second gap on top of the head's own."
		).toBe( '0px' );

		expect( front.recordHead.marginBlockEnd ).toBe( '24px' );
		expect( front.lede.marginBlockStart ).toBe( '0px' );
		expect( front.lede.marginBlockEnd ).toBe( '24px' );
		expect( front.chart.marginBlockStart ).toBe( '0px' );

		const canvas = await canvasOf( page, admin, WORK );

		expect( await read( canvas ) ).toEqual( front );
	} );

	test( "the card's meta line is the design's 12px mono, not a paragraph", async ( {
		page,
		admin,
	} ) => {
		const read = async ( scope: Page | Frame ) => ( {
			meta: await one( scope, '.dp-card-meta' ),
			year: await one( scope, '.dp-card-year' ),
			org: await one( scope, '.dp-card-org' ),
			line: await one( scope, '.dp-card-line' ),
			title: await one( scope, '.dp-card-title' ),
		} );

		await page.goto( workPage );

		const front = await read( page );

		// WorkCard.dc.html: the meta row is --fs-xs, the title --fs-md and the
		// line --fs-sm. theme.json's core/paragraph style is `:root :where(p)`,
		// which outranks the container's inherited size on the two paragraphs
		// inside the row and left both at 16px.
		expect( front.meta.fontSize ).toBe( '12px' );
		expect( front.year.fontSize ).toBe( '12px' );
		expect( front.org.fontSize ).toBe( '12px' );
		expect( front.title.fontSize ).toBe( '20px' );
		expect( front.line.fontSize ).toBe( '14px' );

		const canvas = await canvasOf( page, admin, WORK );

		expect( await read( canvas ) ).toEqual( front );
	} );
} );
