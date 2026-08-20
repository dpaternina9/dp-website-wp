/**
 * Playwright global setup.
 *
 * Establishes the preconditions the suite needs instead of assuming them.
 * That matters because `composer test:integration` re-installs WordPress into
 * the same database the tests site (:8889) runs on, which resets the active
 * theme. A suite that depended on `wp-env start` having activated the theme
 * would therefore pass or fail depending on what ran before it.
 *
 * External dependencies
 */
import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

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
}

export default globalSetup;
