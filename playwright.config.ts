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
	workers: process.env.CI ? 2 : undefined,
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
