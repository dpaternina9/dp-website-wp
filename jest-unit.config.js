/**
 * Jest configuration for the JS unit suite (`npm run test:unit`).
 *
 * Playwright owns `tests/e2e`; PHPUnit owns `tests/Unit` and `tests/Integration`.
 * Scoping the roots here keeps the three harnesses from picking up each other's
 * files as the project grows.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	rootDir: __dirname,
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/build/',
		'/artifacts/',
		'<rootDir>/tests/e2e/',
		'<rootDir>/design-source/',
		// Agent worktrees are whole checkouts of this repository living inside
		// it. Without this, one branch's suite runs as part of another's.
		'<rootDir>/.claude/',
	],
	modulePathIgnorePatterns: [ '<rootDir>/.claude/' ],
	collectCoverageFrom: [
		'themes/dpaternina/src/**/*.{js,jsx,ts,tsx}',
		'plugins/dp-core/src/**/*.{js,jsx,ts,tsx}',
	],
	/*
	 * The build externalises every `@wordpress/*` import to the `wp.*` globals
	 * WordPress already loads, so those packages are not — and should not be —
	 * dependencies of this repository. The four below have no npm presence here
	 * at all, so Jest is given a double for each. See tests/js/__mocks__.
	 */
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@wordpress/block-editor$':
			'<rootDir>/tests/js/__mocks__/wordpress-block-editor.js',
		'^@wordpress/blocks$':
			'<rootDir>/tests/js/__mocks__/wordpress-blocks.js',
		'^@wordpress/components$':
			'<rootDir>/tests/js/__mocks__/wordpress-components.js',
		'^@wordpress/data$': '<rootDir>/tests/js/__mocks__/wordpress-data.js',
	},
};
