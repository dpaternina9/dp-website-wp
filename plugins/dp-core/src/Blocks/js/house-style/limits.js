/**
 * The house limits, and the counting behind them.
 *
 * design-source/components/PostBlocks.dc.html states them in one line:
 * "House limits: 2 quotes/post, 6 list items, 15 code lines, 1 callout/post."
 *
 * They are editorial guidance, not validation. Nothing here blocks a save, and
 * nothing here runs on the front end — a post that breaks a limit publishes and
 * renders exactly like one that does not. This module is deliberately free of
 * imports so it can be reasoned about, and tested, on its own.
 */

/**
 * The limits, keyed by the finding id they produce.
 *
 * @type {Object<string, number>}
 */
export const HOUSE_LIMITS = {
	quotes: 2,
	'list-items': 6,
	'code-lines': 15,
	callouts: 1,
};

/**
 * Read a rich-text attribute as a plain string.
 *
 * `core/code` hands back a string in some WordPress versions and a RichTextData
 * instance in others. Both are read here rather than at four call sites.
 *
 * @param {unknown} value The attribute value.
 * @return {string} The value as text, or an empty string.
 */
function asText( value ) {
	if ( typeof value === 'string' ) {
		return value;
	}

	if ( value && typeof value.toHTMLString === 'function' ) {
		return value.toHTMLString();
	}

	if ( value === null || value === undefined ) {
		return '';
	}

	return String( value );
}

/**
 * Walk a block tree depth first.
 *
 * @param {Array<Object>} blocks  The blocks to walk.
 * @param {Function}      visitor Called with every block, at every depth.
 * @return {void}
 */
function walk( blocks, visitor ) {
	if ( ! Array.isArray( blocks ) ) {
		return;
	}

	for ( const block of blocks ) {
		if ( ! block || typeof block !== 'object' ) {
			continue;
		}

		visitor( block );
		walk( block.innerBlocks, visitor );
	}
}

/**
 * Count everything the house limits care about.
 *
 * `list-items` and `code-lines` are per-block maximums, not totals: six items
 * is the limit for one list, not for every list in the post added together.
 *
 * @param {Array<Object>} blocks A block tree, as `core/block-editor` stores it.
 * @return {Object<string, number>} Counts keyed by the same ids as HOUSE_LIMITS.
 */
export function countHouseBlocks( blocks ) {
	const counts = {
		quotes: 0,
		'list-items': 0,
		'code-lines': 0,
		callouts: 0,
	};

	walk( blocks, ( block ) => {
		if ( 'core/quote' === block.name ) {
			counts.quotes += 1;
		}

		if ( 'dp/callout' === block.name ) {
			counts.callouts += 1;
		}

		if ( 'core/list' === block.name ) {
			let items = 0;

			walk( block.innerBlocks, ( inner ) => {
				if ( 'core/list-item' === inner.name ) {
					items += 1;
				}
			} );

			counts[ 'list-items' ] = Math.max( counts[ 'list-items' ], items );
		}

		if ( 'core/code' === block.name ) {
			const code = asText( block.attributes?.content ).trimEnd();
			const lines = '' === code ? 0 : code.split( '\n' ).length;

			counts[ 'code-lines' ] = Math.max( counts[ 'code-lines' ], lines );
		}
	} );

	return counts;
}

/**
 * Which limits the current block tree exceeds.
 *
 * @param {Array<Object>} blocks A block tree.
 * @return {Array<{id: string, count: number, limit: number}>} Findings, in a stable order.
 */
export function evaluateHouseLimits( blocks ) {
	const counts = countHouseBlocks( blocks );

	return Object.keys( HOUSE_LIMITS )
		.filter( ( id ) => counts[ id ] > HOUSE_LIMITS[ id ] )
		.map( ( id ) => ( {
			id,
			count: counts[ id ],
			limit: HOUSE_LIMITS[ id ],
		} ) );
}
