/**
 * The axe sweep's shared vocabulary: the bar, and the two debts.
 *
 * `a11y.spec.ts` sweeps twelve templates and `chrome.spec.ts` audits the
 * thirteenth (the blog index, which only exists behind that file's Settings →
 * Reading flip), so the ruleset and the known-issue ledger live here — one
 * definition, or the two files' bars drift apart.
 *
 * External dependencies
 */

/**
 * The axe rulesets that add up to WCAG 2.2 AA.
 *
 * Axe tags rules by the standard that introduced them, so 2.2 AA is the union
 * of every A and AA tag up to 2.2 — not the `wcag22aa` tag alone, which holds
 * only the criteria 2.2 added.
 */
export const WCAG_22_AA = [
	'wcag2a',
	'wcag2aa',
	'wcag21a',
	'wcag21aa',
	'wcag22a',
	'wcag22aa',
];

/** The slice of an axe violation a failure message needs. */
export type Violation = {
	id: string;
	impact?: string | null;
	help: string;
	nodes: Array< {
		target: Array< unknown >;
		any?: Array< { data?: { fgColor?: string } | null } >;
	} >;
};

/**
 * The dark scope's `--hue-purple`, verbatim (`design-source/theme.css`).
 *
 * The one brand hue whose raw value fails AA as text on the dark ground —
 * 2.80:1 on `--bg-page`, the number the design's own token comment measured.
 */
const RAW_PURPLE = '#6b479c';

/**
 * The two violations Phase 10 measured, understood, and could not fix here.
 *
 * Anything else axe reports still fails the sweep — these are named nodes on
 * named rules, not disabled rules.
 *
 * 1. **`color-contrast` on `*tone-purple*` elements.** On the dark ground
 *    `--hue-purple` is the raw brand hue (`#6b479c`), which measures 2.80:1
 *    on `--bg-page` — the exact number the design's own token comment gives
 *    for why hue-as-text must go through the tone-mix rule. Teal, gold and
 *    pink clear AA raw; purple does not, and the design's dark scope ships it
 *    raw anyway (`design-source/theme.css`). The fix is a design-source
 *    correction (tone-mixed purple toward white measures ~5.0:1) and a
 *    re-import — design-source/ is read-only here, and TokenParityTest pins
 *    the theme to it, so this ledger records the debt instead of hiding it.
 *
 * 2. **`list` on `.wp-block-navigation__container`.** WordPress 7.1 renders
 *    `core/page-list` inside `core/navigation` — which is what the menu
 *    fallback serves until David's one-time link pass (ADR-0018) — as a
 *    `<ul>` directly inside the container `<ul>`. Core's markup, not this
 *    repo's: a navigation post of explicit `core/navigation-link` items
 *    renders a valid single list, which is what the curated site will have.
 *
 * @param violation One axe violation.
 * @return The violation with its known nodes removed, or null when nothing
 *         unexplained is left of it.
 */
export function withoutKnownIssues( violation: Violation ): Violation | null {
	let nodes = violation.nodes;

	if ( 'color-contrast' === violation.id ) {
		// The debt is exactly "the raw purple hue as text", so the filter
		// reads the foreground colour axe itself measured rather than
		// guessing from selectors — a second failing colour still fails.
		nodes = nodes.filter(
			( node ) =>
				! ( node.any ?? [] ).some(
					( check ) =>
						check.data?.fgColor?.toLowerCase() === RAW_PURPLE
				)
		);
	}

	if ( 'list' === violation.id ) {
		nodes = nodes.filter(
			( node ) =>
				! node.target.some(
					( target ) =>
						'string' === typeof target &&
						target.includes( 'wp-block-navigation__container' )
				)
		);
	}

	return nodes.length > 0 ? { ...violation, nodes } : null;
}

/**
 * Format axe violations so a failure names the rule, the impact and the node.
 *
 * @param violations What `analyze()` reported.
 * @return One readable line per violation.
 */
export function describeViolations( violations: Violation[] ): string[] {
	return violations.map(
		( violation ) =>
			`${ violation.id } (${ violation.impact ?? 'n/a' }): ${
				violation.help
			} — ${ violation.nodes
				.slice( 0, 3 )
				.map( ( node ) => node.target.join( ' ' ) )
				.join( ' | ' ) }`
	);
}

/**
 * What the sweep holds a page to: everything axe found, minus the ledger.
 *
 * @param violations What `analyze()` reported.
 * @return Readable lines for every violation the ledger does not explain.
 */
export function unexplainedViolations( violations: Violation[] ): string[] {
	return describeViolations(
		violations
			.map( withoutKnownIssues )
			.filter( ( violation ): violation is Violation =>
				Boolean( violation )
			)
	);
}
