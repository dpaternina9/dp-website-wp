/**
 * Playwright configuration.
 *
 * E2E runs against the wp-env `tests` environment (port 8889), never the
 * development site, so a failing run can never leave David's local content in
 * a strange state.
 *
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
import * as path from 'path';

const WP_BASE_URL = process.env.WP_BASE_URL ?? 'http://localhost:8889';
const STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ??
	path.join( process.cwd(), 'artifacts/storage-states/admin.json' );

process.env.WP_BASE_URL = WP_BASE_URL;
process.env.STORAGE_STATE_PATH = STORAGE_STATE_PATH;

export default defineConfig( {
	testDir: './tests/e2e',
	outputDir: './artifacts/test-results',
	globalSetup: require.resolve( './tests/e2e/global-setup.ts' ),
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,

	/*
	 * One worker, because there is one site.
	 *
	 * Every spec here creates its own content under its own slugs, which keeps
	 * them from deleting each other's fixtures — but the work page's featured
	 * cards are a *global* query (`dpLoop: featured-ships`, three of them,
	 * ordered by `dp_end`), so three specs publishing featured `dp_ship` posts
	 * are three specs writing to one list that only holds three. Two of them
	 * running at once means each one's page shows the other's cards. That was
	 * already latent between `timeline.spec.ts` and `spacing.spec.ts`; adding
	 * `design-parity.spec.ts` made it deterministic.
	 *
	 * The parallel-safe answer is one shared fixture in `global-setup.ts` that
	 * nobody owns, which is a refactor across three files and worth doing on its
	 * own. Until then the suite costs about seven seconds more and never lies.
	 *
	 * One worker also removed a coincidence the suite had been living on — see
	 * `ensureACategoryHasAPost` in `global-setup.ts`.
	 */
	workers: 1,
	timeout: 60_000,
	expect: { timeout: 10_000 },
	reporter: process.env.CI
		? [
				[ 'github' ],
				[
					'html',
					{
						outputFolder: './artifacts/playwright-report',
						open: 'never',
					},
				],
		  ]
		: [ [ 'list' ] ],
	use: {
		baseURL: WP_BASE_URL,
		storageState: STORAGE_STATE_PATH,
		actionTimeout: 10_000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
		// CLAUDE.md §1.7: every page must be readable and navigable with JS off.
		// Suites that assert that add their own `javaScriptEnabled: false` context.
		javaScriptEnabled: true,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
