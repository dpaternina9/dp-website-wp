/**
 * Conventional Commits, enforced on commit-msg by husky.
 *
 * One phase, one branch, one PR (CLAUDE.md §6). The scope vocabulary below is
 * the repository's own shape, so `git log --oneline` reads as a change log.
 */
module.exports = {
	extends: [ '@commitlint/config-conventional' ],
	rules: {
		'scope-enum': [
			2,
			'always',
			[
				'theme',
				'core',
				'build',
				'ci',
				'env',
				'tests',
				'docs',
				'deps',
				'release',
			],
		],
		'body-max-line-length': [ 1, 'always', 100 ],
		'subject-case': [ 2, 'never', [ 'pascal-case', 'upper-case' ] ],
	},
};
