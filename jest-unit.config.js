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
	],
	collectCoverageFrom: [
		'themes/dpaternina/src/**/*.{js,jsx,ts,tsx}',
		'plugins/dp-core/src/**/*.{js,jsx,ts,tsx}',
	],
};
