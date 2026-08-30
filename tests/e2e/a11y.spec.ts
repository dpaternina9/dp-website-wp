/**
 * Phase 10 acceptance: every template, measured — not opined about.
 *
 * Four sweeps share one navigation per template:
 *
 * 1. **axe, at WCAG 2.2 AA.** The bar CLAUDE.md §1.7 sets, applied to the
 *    front end only — admin screens are out of scope by recorded decision.
 * 2. **Zero third-party requests on first paint.** `watch.spec.ts` already
 *    proves the Watch page never talks to a video host before a click; this
 *    generalises the fact to every template, because "no off-origin request"
 *    is the posture David's CSP relies on (docs/plan.md Phase 10), not a
 *    property of one page.
 * 3. **No parser-blocking scripts.** A `<script src>` in `<head>` without
 *    `defer`/`async`/`type=module` stops the parser; the theme promises none.
 * 4. **Nothing a strict CSP would refuse.** No inline `<script>` we wrote, no
 *    `on*` handler attribute. CLAUDE.md §1.4 says the headers are David's and
 *    our side of the bargain is to need no exception from them; Phase 10 says
 *    "there is an audit for it", and the inline-`style=` half of that claim was
 *    the only half a test actually held (`TimelineTest`). This is the rest.
 *
 * The keyboard runs live where their fixtures live: the mobile panel's trap in
 * `chrome.spec.ts`, the timeline in `timeline.spec.ts`, click-to-play in
 * `watch.spec.ts`, the contact form's focus handoff in `contact.spec.ts`. What
 * this file adds is the header itself: every stop paints the `--focus-ring`,
 * and focus passes through rather than around or into a trap.
 *
 * The whole file runs logged out. The admin bar is core UI a reader never
 * sees, and auditing it would fail the sweep on markup this repo cannot fix.
 *
 * The fixture follows ADR-0013's grain: established idempotently under slugs
 * this file owns, deleted by nothing. The blog index (home.html) is the one
 * template not swept here — it only exists once Settings → Reading points at a
 * posts page, and `chrome.spec.ts` owns that flip, so the axe pass on it lives
 * there.
 *
 * External dependencies
 */
import * as path from 'path';
import { AxeBuilder } from '@axe-core/playwright';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { unexplainedViolations, WCAG_22_AA } from './axe';
import {
	blockingScripts,
	eventHandlerAttributes,
	inlineScripts,
	visitRecordingOffsite,
} from './front-end';
import {
	sharedPagerArchiveUrl,
	sharedWatchPageUrl,
	sharedWorkPageUrl,
} from './global-setup';
import type { Established } from './global-setup';

/** The slugs this fixture owns. Nothing outside this list is ever written. */
const SLUGS = {
	post: 'a11y-fixture-post',
	page: 'a11y-fixture-page',
	about: 'a11y-fixture-about',
	contact: 'a11y-fixture-contact',
	resume: 'a11y-fixture-resume',
	series: 'a11y-fixture-series',
	seriesPartOne: 'a11y-fixture-series-part-1',
	seriesPartTwo: 'a11y-fixture-series-part-2',
} as const;

/** The templates this file sweeps, filled in by `beforeAll`. */
const swept: Array< { template: string; url: () => string } > = [];

/** URL registry the sweep list reads from after `beforeAll` ran. */
const urls: Record< string, string > = {};

/**
 * Publish one thing under a slug this file owns, or bring it up to date.
 *
 * The same establish() shape as global-setup.ts, for the same reason: a POST
 * to a single-post route is an update, so creating and correcting are one
 * request, and a re-run repairs drift instead of racing a delete.
 *
 * @param requestUtils The suite's REST client.
 * @param endpoint     The REST route segment, e.g. `pages`.
 * @param slug         The slug this file owns.
 * @param data         Everything else the post carries.
 * @return The post as the REST API describes it.
 */
async function establish(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any,
	endpoint: string,
	slug: string,
	data: Record< string, unknown >
): Promise< Established > {
	const existing: Established[] = await requestUtils.rest( {
		path: `/wp/v2/${ endpoint }`,
		params: { slug, status: 'any', per_page: 1 },
	} );

	const id = existing[ 0 ]?.id;

	return requestUtils.rest( {
		path: id ? `/wp/v2/${ endpoint }/${ id }` : `/wp/v2/${ endpoint }`,
		method: 'POST',
		data: { ...data, slug, status: 'publish' },
	} );
}

/**
 * The single post the sweep reads, carrying a featured image large enough
 * that core's loading optimisation treats it as the LCP candidate it is.
 *
 * Core only adds `fetchpriority="high"` above `wp_min_priority_img_pixels`
 * (50 000 px²), so the fixture uploads the 2000×2000 source mark rather than
 * the 128px favicon — a small image would make the LCP assertion below pass
 * vacuously false. Uploaded once: a post that already carries a featured
 * image keeps it.
 *
 * @param requestUtils The suite's REST client.
 * @return The post as the REST API describes it.
 */
async function establishPost(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< Established > {
	const post: Established & { featured_media?: number } = await establish(
		requestUtils,
		'posts',
		SLUGS.post,
		{
			title: 'A11y fixture — a post with a lead image',
			content:
				'<!-- wp:paragraph --><p>Published so the single template has ' +
				'a body, a caption-less lead image and a date to draw. ' +
				'Established in tests/e2e/a11y.spec.ts and deleted by ' +
				'nothing.</p><!-- /wp:paragraph -->',
			// 2018: old enough to never sit on the first page of any index
			// another spec measures (the global-setup pager rule).
			date_gmt: '2018-03-01T09:00:00',
		}
	);

	if ( ! post.featured_media ) {
		const media = await requestUtils.uploadMedia(
			path.join(
				process.cwd(),
				'themes/dpaternina/assets/img/dp-mark-gradient.src.png'
			)
		);

		await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			method: 'POST',
			data: { featured_media: media.id },
		} );
	}

	return post;
}

/**
 * The series archive: a term and two old-dated parts filed under it.
 *
 * @param requestUtils The suite's REST client.
 * @return The term's archive link.
 */
async function establishSeries(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< string > {
	const existing: Established[] = await requestUtils.rest( {
		path: '/wp/v2/dp_series',
		params: { slug: SLUGS.series, per_page: 1 },
	} );

	const term: Established = await requestUtils.rest( {
		path: existing[ 0 ]
			? `/wp/v2/dp_series/${ existing[ 0 ].id }`
			: '/wp/v2/dp_series',
		method: 'POST',
		data: {
			name: 'A11y fixture — a series',
			slug: SLUGS.series,
			description: 'Two parts, so the archive has a list to order.',
		},
	} );

	for ( const [ index, slug ] of [
		SLUGS.seriesPartOne,
		SLUGS.seriesPartTwo,
	].entries() ) {
		await establish( requestUtils, 'posts', slug, {
			title: `A11y fixture — part ${ index + 1 }`,
			content: 'A part of the series the sweep reads.',
			dp_series: [ term.id ],
			date_gmt: `2018-04-0${ index + 1 }T09:00:00`,
		} );
	}

	return term.link;
}

test.describe( 'Phase 10 — the front end, measured', () => {
	/*
	 * Serial: `beforeAll` runs once per worker, and two workers establishing
	 * the same slugs would race. The same reasoning as contact.spec.ts.
	 */
	test.describe.configure( { mode: 'serial' } );

	// Logged out: what a reader sees, without the admin bar's core UI.
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		const post = await establishPost( requestUtils );

		const pages: Array< [ keyof typeof urls, string, string, string ] > = [
			[ 'page', SLUGS.page, '', 'A11y fixture — a plain page' ],
			[ 'about', SLUGS.about, 'dp-about', 'A11y fixture — about' ],
			[ 'contact', SLUGS.contact, 'dp-contact', 'A11y fixture — hello' ],
			[ 'resume', SLUGS.resume, 'dp-resume', 'A11y fixture — résumé' ],
		];

		for ( const [ key, slug, template, title ] of pages ) {
			const established = await establish( requestUtils, 'pages', slug, {
				title,
				template,
				content:
					'<!-- wp:paragraph --><p>Established in ' +
					'tests/e2e/a11y.spec.ts and deleted by nothing.</p>' +
					'<!-- /wp:paragraph -->',
			} );
			urls[ key ] = established.link;
		}

		urls.post = post.link;
		urls.series = await establishSeries( requestUtils );
		urls.front = '/';
		urls.work = await sharedWorkPageUrl( requestUtils );
		urls.watch = await sharedWatchPageUrl( requestUtils );
		urls.category = await sharedPagerArchiveUrl( requestUtils );
		urls.search = '/?s=fixture';
		urls[ '404' ] = '/there-is-no-page-at-this-path/';
	} );

	for ( const template of [
		'front',
		'post',
		'page',
		'work',
		'watch',
		'about',
		'contact',
		'resume',
		'category',
		'series',
		'search',
		'404',
	] ) {
		swept.push( { template, url: () => urls[ template ] as string } );
	}

	for ( const { template, url } of swept ) {
		test( `${ template }: WCAG 2.2 AA, one origin, nothing a CSP refuses`, async ( {
			page,
		} ) => {
			const offsite = await visitRecordingOffsite( page, url() );

			// Sweep 2: first paint asked nothing of any other host.
			expect( offsite ).toEqual( [] );

			// Sweep 3: nothing in <head> stops the parser.
			expect( await blockingScripts( page ) ).toEqual( [] );

			// Sweep 4: nothing a `script-src` without 'unsafe-inline' drops.
			expect( await inlineScripts( page ) ).toEqual( [] );
			expect( await eventHandlerAttributes( page ) ).toEqual( [] );

			// Sweep 1: axe, at the bar §1.7 sets.
			const results = await new AxeBuilder( { page } )
				.withTags( WCAG_22_AA )
				.analyze();

			expect( unexplainedViolations( results.violations ) ).toEqual( [] );
		} );
	}

	test( 'the single post treats its lead image as the LCP candidate', async ( {
		page,
	} ) => {
		await page.goto( urls.post as string );

		const lead = page.locator( '.dp-post-lead-image img' );

		await expect( lead ).toHaveAttribute( 'fetchpriority', 'high' );
		await expect( lead ).not.toHaveAttribute( 'loading', 'lazy' );
	} );

	test( 'the first paint is served by few stylesheets and two font preloads', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		// The theme's five sheets plus whatever core inlines; a regression
		// that starts enqueuing per-block files would blow through this.
		const stylesheets = await page
			.locator( 'link[rel="stylesheet"]' )
			.count();
		expect( stylesheets ).toBeLessThanOrEqual( 8 );

		// The two faces the first paint needs (Assets::PRELOADED_FONTS).
		const preloads = await page
			.locator( 'link[rel="preload"][as="font"]' )
			.count();
		expect( preloads ).toBe( 2 );
	} );

	test( 'the header is keyboard traversable, ringed at every stop', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		const ring = await page.evaluate( () =>
			getComputedStyle( document.documentElement )
				.getPropertyValue( '--focus-ring' )
				.trim()
		);
		expect( ring ).not.toBe( '' );

		const stops: string[] = [];
		let visitedHeader = false;

		// Tab from the top of the page — through the skip link core puts
		// first — into the header, and out the other side. A trap never
		// leaves, and a stop without a visible ring fails WCAG 2.4.7 no
		// matter what the stylesheet promises.
		for ( let press = 0; press < 30; press++ ) {
			await page.keyboard.press( 'Tab' );

			const stop = await page.evaluate( () => {
				const active = document.body.ownerDocument.activeElement;

				if ( ! ( active instanceof HTMLElement ) ) {
					return null;
				}

				const style = getComputedStyle( active );

				return {
					inHeader: null !== active.closest( 'header' ),
					label: active.textContent?.trim() ?? '',
					outlineStyle: style.outlineStyle,
					outlineWidth: style.outlineWidth,
				};
			} );

			if ( ! stop ) {
				continue;
			}

			if ( ! stop.inHeader ) {
				if ( visitedHeader ) {
					// Through and out: the traversal is over, without a trap.
					break;
				}
				continue;
			}

			visitedHeader = true;

			expect(
				stop.outlineStyle,
				`"${ stop.label }" is focused but paints no ring`
			).toBe( 'solid' );
			expect( stop.outlineWidth ).not.toBe( '0px' );

			stops.push( stop.label );
		}

		// It was a traversal, not a bounce: the header's stops were visited
		// and then focus moved on into the document.
		expect( stops.length ).toBeGreaterThan( 0 );
		expect( stops.length ).toBeLessThan( 30 );
	} );
} );
