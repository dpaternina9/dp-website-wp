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

/** The page carrying the `dp-work` template, which every work-page spec reads. */
export const SHARED_WORK_PAGE = {
	slug: 'e2e-shared-work',
	title: 'Shared fixture — where I worked',
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

export default globalSetup;
