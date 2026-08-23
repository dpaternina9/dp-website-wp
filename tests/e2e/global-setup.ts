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

	/*
	 * And the plugin, for the same reason. A re-install deactivates every
	 * plugin, so without this the timeline — a dynamic block registered by
	 * `dp-core` — is simply absent from the page, and the specs that read it
	 * fail with a message about a missing element rather than about a missing
	 * plugin.
	 */
	await requestUtils.activatePlugin( 'dp-core' );

	await ensureACategoryHasAPost( requestUtils );
}

/**
 * Make sure at least one category is not empty.
 *
 * The footer lists categories through `core/categories`, which hides the empty
 * ones — so on a site with no published posts it renders nothing at all, and
 * `spacing.spec.ts` waits for a list item that never arrives before it reads the
 * site editor's canvas.
 *
 * That was true before and passed anyway, because the suite ran several workers
 * and some other spec's fixture posts were usually alive at the same moment.
 * One worker removed the coincidence: `composer test` re-installs WordPress into
 * this database and leaves it with no posts, every spec that makes one deletes
 * it again, and the only reason a second run passed was a post the last one
 * happened to leave behind. A precondition that holds by luck is the thing this
 * file exists to replace.
 *
 * The post is created once and never removed. It carries a slug of its own, so a
 * re-run finds it rather than making a second one.
 *
 * @param requestUtils The suite's REST client.
 */
async function ensureACategoryHasAPost(
	requestUtils: RequestUtils
): Promise< void > {
	const slug = 'e2e-precondition-a-category-has-a-post';

	const existing = await requestUtils.rest< Array< { id: number } > >( {
		path: '/wp/v2/posts',
		params: { slug, status: 'any', per_page: 1 },
	} );

	if ( existing.length > 0 ) {
		return;
	}

	await requestUtils.rest( {
		path: '/wp/v2/posts',
		method: 'POST',
		data: {
			title: 'Suite precondition — a category with something in it',
			slug,
			status: 'publish',
			content:
				'Published so that core/categories has a term to list. Deleting this ' +
				'post makes tests/e2e/spacing.spec.ts fail on the site editor canvas.',
		},
	} );
}

export default globalSetup;
