/**
 * Playwright global setup: the site every spec measures, owned by no spec.
 *
 * Two jobs, and they are the same job.
 *
 * The first is **preconditions**. `composer test:integration` re-installs
 * WordPress into the same database the tests site (:8889) runs on, which resets
 * the active theme and deactivates every plugin. A suite that depended on
 * `wp-env start` having left those switched on would pass or fail depending on
 * what ran before it, so this file switches them on itself.
 *
 * The second is **the shared fixture**, and it is here for a reason worth
 * writing down. Two of the queries this site draws a work page from are
 * *global*: the featured cards are `dpLoop: featured-ships` — three of them,
 * ordered by `dp_end` — and the chart below is every published `dp_role` and
 * `dp_ship` on the site, whichever page you are looking at. So a spec that
 * publishes a role, a ship, or a featured ship is not setting up its own page.
 * It is editing everyone's.
 *
 * Three specs used to do exactly that, each under its own slugs, which stopped
 * them deleting each other's content and did nothing at all about the fact that
 * they were writing to one list. Under several workers that showed up as
 * `timeline.spec.ts` losing its own row somewhere past the fortieth press of
 * Tab, because two other files had pushed it down a chart they shared.
 *
 * So the content is established once, here, and no spec creates or deletes any
 * of it. What a spec owns now is a question, not a fixture. See
 * `docs/adr/0013-one-fixture-nobody-owns.md`.
 *
 * External dependencies
 */
import * as path from 'path';
import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/** The REST fields the suite reads back from anything it establishes. */
export type Established = { id: number; link: string };

/**
 * The published post.
 *
 * It does two jobs, because they want the same object. The footer lists
 * categories through `core/categories`, which hides the empty ones, so on a site
 * with no published posts it renders nothing and `spacing.spec.ts` waits for a
 * list item that never arrives. And every shipped thing below points its
 * `dp_writeup_id` here, so that the chart draws the write-up link that
 * `design-parity.spec.ts` measures.
 */
export const SHARED_POST = {
	slug: 'e2e-shared-post',
	title: 'Shared fixture — a post with a category and a link into it',
} as const;

/** The lane nothing came out of. First on the chart, and the "shipped" filter drops it. */
export const SHARED_BARE_ROLE = {
	slug: 'e2e-shared-backbone',
	title: 'Shared fixture — Backbone',
	entry: 'dp-role-e2e-shared-backbone',
} as const;

/**
 * The lane everything hangs off.
 *
 * `end` is exported because the year axis is measured against it: the chart's
 * last tick follows the site's clock now (ADR-0014), so "the bar reaches its own
 * year's tick" is a statement about *this* end date and the label that names its
 * calendar year, not about a hardcoded 2026 that stops being true in January.
 */
export const SHARED_ROLE = {
	slug: 'e2e-shared-lab',
	title: 'Shared fixture — Fanxie Lab',
	entry: 'dp-role-e2e-shared-lab',
	start: 2016,
	end: 2026.6,
	range: '2016 — now',
} as const;

/**
 * The three featured shipped things, in the order the card grid draws them.
 *
 * `dp_end` is the featured loop's sort key and the three are deliberately
 * distinct, so that "the first card" means one thing rather than whichever row
 * the database felt like returning first out of a tie.
 */
export const SHARED_SHIPS = [
	{
		slug: 'e2e-shared-kiveo',
		title: 'Shared fixture — Kiveo',
		entry: 'dp-ship-e2e-shared-kiveo',
		start: 2023,
		end: 2026.6,
		range: '2023 — now',
	},
	{
		slug: 'e2e-shared-ops',
		title: 'Shared fixture — Agency ops',
		entry: 'dp-ship-e2e-shared-ops',
		start: 2022,
		end: 2025.4,
		range: '2022 — 2025',
	},
	{
		slug: 'e2e-shared-atlas',
		title: 'Shared fixture — Atlas',
		entry: 'dp-ship-e2e-shared-atlas',
		start: 2021,
		end: 2024.2,
		range: '2021 — 2024',
	},
] as const;

/**
 * The term archive that runs to more than one page.
 *
 * Twelve posts at ten to a page, so `pagination.spec.ts` has a page one, a page
 * two, a step that cannot be taken and a step that can. It is here rather than
 * in that spec for the reason ADR-0013 gives about everything else in this file:
 * a category is read by a **global** query — the footer's category list, and the
 * home page's `dpaternina/filter-pills` row — so a spec that creates and deletes
 * one is not setting up its own page, it is editing everyone's. Two workers
 * racing to create and delete the same twelve posts is also how an archive
 * relaid itself under a browser mid-click, which Playwright reports as
 * `locator.click: element is not stable`.
 *
 * **The posts are dated 2019 on purpose.** Posts on any site's blog index push
 * whatever was there down the list, and `chrome.spec.ts` asserts that its own
 * two posts are visible on the first page of that index. These are old enough
 * that they can never be on the first page of anything but their own archive.
 */
export const SHARED_PAGER = {
	category: 'e2e-shared-pager',
	term: 'Pager fixture',
	posts: 12,
	post: ( index: number ) => `e2e-shared-pager-post-${ index }`,
} as const;

/** The page carrying the `dp-work` template, which every work-page spec reads. */
export const SHARED_WORK_PAGE = {
	slug: 'e2e-shared-work',
	title: 'Shared fixture — where I worked',
} as const;

/** The page carrying the `dp-watch` template, which the watch spec reads. */
export const SHARED_WATCH_PAGE = {
	slug: 'e2e-shared-watch',
	title: 'Watch.',
} as const;

/**
 * The Watch grid's entries.
 *
 * Three deliberate shapes. The Twitch VOD carries an identifier, so it is the
 * featured panel (first by menu order, and the site is never live in this
 * environment) and the one card whose press the click-to-play test can
 * exercise — its embed URL is built client-side and asserted on, never
 * loaded from Twitch by the page itself. The YouTube entry carries none, so
 * the unlinked degradation is on the page, **and** so no render ever asks
 * i.ytimg.com for a thumbnail from the test environment. The live entry
 * exists to prove it does not render while nothing says the channel is live.
 */
export const SHARED_VIDEOS = {
	featured: {
		slug: 'e2e-shared-vod',
		title: 'Watch fixture — the featured VOD',
		ref: '2280918841',
	},
	unlinked: {
		slug: 'e2e-shared-upload',
		title: 'Watch fixture — the upload with no id yet',
	},
	live: {
		slug: 'e2e-shared-live',
		title: 'Watch fixture — the live entry nobody is streaming',
	},
} as const;

/**
 * The work page's URL, for a spec that is about to visit it.
 *
 * A lookup rather than a creation: the page is established once, by this file,
 * before any worker starts. Every spec asks the same question and gets the same
 * answer, which is what lets them all run at once.
 *
 * @param requestUtils The suite's REST client.
 * @return The page's permalink.
 */
export async function sharedWorkPageUrl(
	requestUtils: RequestUtils
): Promise< string > {
	const found = await requestUtils.rest< Established[] >( {
		path: '/wp/v2/pages',
		params: { slug: SHARED_WORK_PAGE.slug, status: 'any', per_page: 1 },
	} );

	const page = found[ 0 ];

	if ( ! page ) {
		throw new Error(
			`The shared work page ("${ SHARED_WORK_PAGE.slug }") is missing. It is ` +
				'established in tests/e2e/global-setup.ts, which runs before every ' +
				'spec; if it is not there, global setup did not finish.'
		);
	}

	return page.link;
}

/**
 * The Watch page's URL, for a spec that is about to visit it.
 *
 * A lookup, like `sharedWorkPageUrl()` and for the same reason.
 *
 * @param requestUtils The suite's REST client.
 * @return The page's permalink.
 */
export async function sharedWatchPageUrl(
	requestUtils: RequestUtils
): Promise< string > {
	const found = await requestUtils.rest< Established[] >( {
		path: '/wp/v2/pages',
		params: { slug: SHARED_WATCH_PAGE.slug, status: 'any', per_page: 1 },
	} );

	const page = found[ 0 ];

	if ( ! page ) {
		throw new Error(
			`The shared watch page ("${ SHARED_WATCH_PAGE.slug }") is missing. It is ` +
				'established in tests/e2e/global-setup.ts, which runs before every ' +
				'spec; if it is not there, global setup did not finish.'
		);
	}

	return page.link;
}

/**
 * The paginated term archive's URL, for a spec that is about to visit it.
 *
 * A lookup, like `sharedWorkPageUrl()` and for the same reason.
 *
 * @param requestUtils The suite's REST client.
 * @return The term archive's permalink.
 */
export async function sharedPagerArchiveUrl(
	requestUtils: RequestUtils
): Promise< string > {
	const found = await requestUtils.rest< Established[] >( {
		path: '/wp/v2/categories',
		params: { slug: SHARED_PAGER.category, per_page: 1 },
	} );

	const term = found[ 0 ];

	if ( ! term ) {
		throw new Error(
			`The shared pager category ("${ SHARED_PAGER.category }") is missing. It is ` +
				'established in tests/e2e/global-setup.ts, which runs before every ' +
				'spec; if it is not there, global setup did not finish.'
		);
	}

	return term.link;
}

/**
 * Authenticate as the wp-env administrator and put the site in a known state.
 *
 * @param config The resolved Playwright configuration.
 */
async function globalSetup( config: FullConfig ): Promise< void > {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestUtils = await RequestUtils.setup( {
		baseURL,
		storageStatePath,
	} );

	await requestUtils.setupRest();
	await requestUtils.activateTheme( 'dpaternina' );
	await requestUtils.activatePlugin( 'dp-core' );

	await establishBrandMark( requestUtils );
	await establishSharedContent( requestUtils );
}

/**
 * Put the theme's own mark in Site Identity, if nothing is there.
 *
 * The design draws the monogram in three places — the header, the footer and
 * the top of the home page — and all three render `core/site-logo`, which
 * renders *nothing at all* when `site_logo` is unset (ADR-0011). So on a site
 * with no logo those three elements are simply absent, and a sweep measuring
 * them reports "the design draws this and the selector matched nothing", which
 * is true and is about the fixture rather than about the theme.
 *
 * `dp-core`'s seeder does this on a real site. The suite does not run the
 * seeder, so it does the same thing here, and it does it the same way the
 * seeder does: never replacing a mark that is already set.
 *
 * @param requestUtils The suite's REST client.
 */
async function establishBrandMark(
	requestUtils: RequestUtils
): Promise< void > {
	const settings = await requestUtils.rest< { site_logo?: number } >( {
		path: '/wp/v2/settings',
	} );

	if ( settings.site_logo ) {
		return;
	}

	const media = await requestUtils.uploadMedia(
		path.join(
			process.cwd(),
			'themes/dpaternina/assets/img/dp-mark-gradient-128.png'
		)
	);

	await requestUtils.rest( {
		path: '/wp/v2/settings',
		method: 'POST',
		data: { site_logo: media.id },
	} );
}

/**
 * Publish one thing under a slug this file owns, or bring the existing one up to date.
 *
 * WordPress treats a POST to a single-post route as an update, so creating and
 * correcting are one request and one code path. Nothing here is ever deleted:
 * a fixture that a spec can remove is a fixture the next spec can lose, which
 * is the whole failure this file replaces. Re-running writes the same values
 * over themselves, so a fixture that drifted after an edit to this file is
 * repaired by the next run rather than by a database reset.
 *
 * @param requestUtils The suite's REST client.
 * @param endpoint     The REST route segment, e.g. `pages` or `dp_ship`.
 * @param slug         The slug this file owns.
 * @param data         Everything else the post carries.
 * @return The post as the REST API describes it.
 */
async function establish(
	requestUtils: RequestUtils,
	endpoint: string,
	slug: string,
	data: Record< string, unknown >
): Promise< Established > {
	const existing = await requestUtils.rest< Established[] >( {
		path: `/wp/v2/${ endpoint }`,
		params: { slug, status: 'any', per_page: 1 },
	} );

	const id = existing[ 0 ]?.id;

	return requestUtils.rest< Established >( {
		path: id ? `/wp/v2/${ endpoint }/${ id }` : `/wp/v2/${ endpoint }`,
		method: 'POST',
		data: { ...data, slug, status: 'publish' },
	} );
}

/**
 * Publish one term under a slug this file owns, or bring the existing one up to date.
 *
 * Terms have no status and no `establish()` shape, so they get their own two
 * requests. Nothing is deleted here either.
 *
 * @param requestUtils The suite's REST client.
 * @param slug         The slug this file owns.
 * @param name         What the term is called.
 * @return The term as the REST API describes it.
 */
async function establishTerm(
	requestUtils: RequestUtils,
	slug: string,
	name: string
): Promise< Established > {
	const existing = await requestUtils.rest< Established[] >( {
		path: '/wp/v2/categories',
		params: { slug, per_page: 1 },
	} );

	const id = existing[ 0 ]?.id;

	return requestUtils.rest< Established >( {
		path: id ? `/wp/v2/categories/${ id }` : '/wp/v2/categories',
		method: 'POST',
		data: { name, slug },
	} );
}

/**
 * Establish the twelve posts and the term that paginate.
 *
 * @param requestUtils The suite's REST client.
 */
async function establishPagerArchive(
	requestUtils: RequestUtils
): Promise< void > {
	const term = await establishTerm(
		requestUtils,
		SHARED_PAGER.category,
		SHARED_PAGER.term
	);

	for ( let index = 1; index <= SHARED_PAGER.posts; index++ ) {
		await establish( requestUtils, 'posts', SHARED_PAGER.post( index ), {
			title: `Pager fixture — post ${ index }`,
			categories: [ term.id ],
			content:
				'Published so that one term archive on this site runs to more ' +
				'than one page. Established in tests/e2e/global-setup.ts and ' +
				'deleted by nothing.',
			date_gmt: `2019-01-${ String( index ).padStart(
				2,
				'0'
			) }T09:00:00`,
		} );
	}
}

/**
 * Establish the content the whole suite reads.
 *
 * The shape is the union of what the three work-page specs used to publish for
 * themselves: a lane nothing came out of, a lane everything hangs off, three
 * featured shipped things, a post to link to, and one page carrying the
 * `dp-work` template. Every shipped thing carries the *whole* meta vocabulary,
 * because `design-parity.spec.ts` measures elements — the artifact block, the
 * bullets, the two stat tiles, the write-up link — that only exist when the
 * field behind them does.
 *
 * @param requestUtils The suite's REST client.
 */
async function establishSharedContent(
	requestUtils: RequestUtils
): Promise< void > {
	const post = await establish( requestUtils, 'posts', SHARED_POST.slug, {
		title: SHARED_POST.title,
		content:
			'Published so that core/categories has a term to list, and so that ' +
			'every shared dp_ship has a write-up to point at. Established in ' +
			'tests/e2e/global-setup.ts and deleted by nothing.',
	} );

	await establish( requestUtils, 'dp_role', SHARED_BARE_ROLE.slug, {
		title: SHARED_BARE_ROLE.title,
		menu_order: 1,
		meta: {
			dp_role_title: 'Developer',
			dp_start: 2014,
			dp_end: 2016,
			dp_range: '2014 — 2016',
			dp_detail:
				'A lane with nothing hanging off it, which is what the "shipped" ' +
				'filter has to be able to drop. Placeholder copy, at enough length ' +
				'that the paragraph wraps onto a second line.',
			dp_stack: 'STACK · PLACEHOLDER',
		},
	} );

	const role = await establish( requestUtils, 'dp_role', SHARED_ROLE.slug, {
		title: SHARED_ROLE.title,
		menu_order: 2,
		meta: {
			dp_role_title: 'CTO & founder',
			dp_start: SHARED_ROLE.start,
			dp_end: SHARED_ROLE.end,
			dp_range: SHARED_ROLE.range,
			dp_detail:
				'The thread running under everything else, said at enough length ' +
				'that the paragraph wraps onto a second line.',
			dp_stack: 'LARAVEL · NESTJS',
			dp_accent: 'pink',
		},
	} );

	for ( const [ index, ship ] of SHARED_SHIPS.entries() ) {
		await establish( requestUtils, 'dp_ship', ship.slug, {
			title: ship.title,
			menu_order: index + 1,
			meta: {
				dp_role_id: role.id,
				dp_start: ship.start,
				dp_end: ship.end,
				dp_range: ship.range,
				dp_headline: `${ ship.title } — the one line.`,
				dp_detail: `What ${ ship.title } is and who it is for, at enough length that the paragraph wraps onto a second line.`,
				dp_line: 'The sentence written for the card.',
				dp_bullets: [ 'One constraint.', 'Another one.' ],
				dp_ship_role: 'Everything',
				dp_stack: 'SWIFT · SWIFTUI',
				dp_artifact_label: 'SWIFTUI',
				dp_artifact: 'struct EntryList: View { }',
				dp_stat1: '0',
				dp_stat1_label: 'TRACKERS',
				dp_stat2: '—',
				dp_stat2_label: 'APPS SHIPPED',
				dp_writeup_id: post.id,
				dp_featured: true,
			},
		} );
	}

	await establishPagerArchive( requestUtils );
	await establishWatchContent( requestUtils );

	await establish( requestUtils, 'pages', SHARED_WORK_PAGE.slug, {
		title: SHARED_WORK_PAGE.title,
		// The admin stores a block theme's custom template under its slug,
		// without the extension.
		template: 'dp-work',
		meta: {
			// The design's own placeholder, verbatim. CLAUDE.md section 6: seed
			// the copy as it is written and invent nothing that reads like a fact.
			dp_lead:
				"There's no separate portfolio here. Three projects I'd show first, " +
				"then every role I've held and everything that came out of each one.",
		},
	} );
}

/**
 * Establish the Watch page and the three video entries the watch spec reads.
 *
 * The page's body carries the theme's gear pattern by reference rather than a
 * copy of its markup, so the fixture cannot drift from the pattern it stands
 * for; core inlines a `core/pattern` at render time.
 *
 * @param requestUtils The suite's REST client.
 */
async function establishWatchContent(
	requestUtils: RequestUtils
): Promise< void > {
	await establish( requestUtils, 'dp_video', SHARED_VIDEOS.featured.slug, {
		title: SHARED_VIDEOS.featured.title,
		menu_order: 1,
		meta: {
			dp_video_source: 'twitch',
			dp_video_ref: SHARED_VIDEOS.featured.ref,
			dp_tone: 'purple',
			dp_duration: '2H 41M',
			dp_when: 'AUG 2026',
			dp_note: 'The card whose press the click-to-play test presses.',
		},
	} );

	await establish( requestUtils, 'dp_video', SHARED_VIDEOS.unlinked.slug, {
		title: SHARED_VIDEOS.unlinked.title,
		menu_order: 2,
		meta: {
			dp_video_source: 'youtube',
			dp_video_ref: '',
			dp_tone: 'teal',
			dp_duration: '18 MIN',
			dp_when: 'JUL 2026',
			dp_note:
				'No identifier yet, so the card is visibly unlinked (ADR-0008).',
		},
	} );

	await establish( requestUtils, 'dp_video', SHARED_VIDEOS.live.slug, {
		title: SHARED_VIDEOS.live.title,
		menu_order: 3,
		meta: {
			dp_video_source: 'twitch',
			dp_tone: 'pink',
			dp_live: true,
			dp_live_meta: 'STREAMING NOW · 1H 12M IN',
			dp_note:
				'Renders only while Twitch says the channel is live, which this environment never is.',
		},
	} );

	await establish( requestUtils, 'pages', SHARED_WATCH_PAGE.slug, {
		title: SHARED_WATCH_PAGE.title,
		// The admin stores a block theme's custom template under its slug,
		// without the extension.
		template: 'dp-watch',
		content: '<!-- wp:pattern {"slug":"dpaternina/watch-gear"} /-->',
		meta: {
			// The design's own placeholder, verbatim. CLAUDE.md section 6: seed
			// the copy as it is written and invent nothing that reads like a fact.
			dp_lead:
				'Not live at the moment. Long unedited streams live on Twitch, ' +
				'shorter edited pieces on YouTube, and both end up here.',
		},
	} );
}

export default globalSetup;
