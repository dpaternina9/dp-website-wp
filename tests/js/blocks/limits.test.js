/**
 * The house limits.
 *
 * Internal dependencies
 */
import {
	HOUSE_LIMITS,
	countHouseBlocks,
	evaluateHouseLimits,
} from '../../../plugins/dp-core/src/Blocks/js/house-style/limits';
import {
	messageFor,
	syncHouseLimitNotices,
} from '../../../plugins/dp-core/src/Blocks/js/house-style/limits-notices';

const block = ( name, attributes = {}, innerBlocks = [] ) => ( {
	name,
	attributes,
	innerBlocks,
} );

const list = ( items ) =>
	block(
		'core/list',
		{},
		Array.from( { length: items }, () => block( 'core/list-item' ) )
	);

describe( 'the limits themselves', () => {
	it( 'are the ones the design states', () => {
		// design-source/components/PostBlocks.dc.html:
		// "2 quotes/post, 6 list items, 15 code lines, 1 callout/post".
		expect( HOUSE_LIMITS ).toEqual( {
			quotes: 2,
			'list-items': 6,
			'code-lines': 15,
			callouts: 1,
		} );
	} );
} );

describe( 'countHouseBlocks', () => {
	it( 'counts nothing in an empty post', () => {
		expect( countHouseBlocks( [] ) ).toEqual( {
			quotes: 0,
			'list-items': 0,
			'code-lines': 0,
			callouts: 0,
		} );
	} );

	it( 'survives a missing or malformed tree', () => {
		expect( countHouseBlocks( undefined ).quotes ).toBe( 0 );
		expect( countHouseBlocks( [ null, 'nonsense' ] ).quotes ).toBe( 0 );
	} );

	it( 'finds blocks nested inside other blocks', () => {
		const tree = [
			block( 'core/group', {}, [
				block( 'core/quote' ),
				block( 'core/group', {}, [ block( 'dp/callout' ) ] ),
			] ),
		];

		expect( countHouseBlocks( tree ).quotes ).toBe( 1 );
		expect( countHouseBlocks( tree ).callouts ).toBe( 1 );
	} );

	it( 'takes list items per list, not per post', () => {
		const counts = countHouseBlocks( [ list( 4 ), list( 5 ) ] );

		expect( counts[ 'list-items' ] ).toBe( 5 );
	} );

	it( 'counts code lines in the longest block', () => {
		const counts = countHouseBlocks( [
			block( 'core/code', { content: 'a\nb' } ),
			block( 'core/code', { content: 'a\nb\nc\nd' } ),
		] );

		expect( counts[ 'code-lines' ] ).toBe( 4 );
	} );

	it( 'does not count a trailing newline as a line', () => {
		expect(
			countHouseBlocks( [ block( 'core/code', { content: 'a\nb\n' } ) ] )[
				'code-lines'
			]
		).toBe( 2 );
	} );

	it( 'reads a rich-text value as well as a string', () => {
		const richText = { toHTMLString: () => 'a\nb\nc' };

		expect(
			countHouseBlocks( [ block( 'core/code', { content: richText } ) ] )[
				'code-lines'
			]
		).toBe( 3 );
	} );
} );

describe( 'evaluateHouseLimits', () => {
	it( 'says nothing about a post that keeps to the house style', () => {
		const tree = [
			block( 'core/quote' ),
			block( 'core/quote' ),
			list( 6 ),
			block( 'core/code', { content: 'one line' } ),
			block( 'dp/callout' ),
		];

		expect( evaluateHouseLimits( tree ) ).toEqual( [] );
	} );

	it( 'reports each limit that is exceeded, with the numbers', () => {
		const tree = [
			block( 'core/quote' ),
			block( 'core/quote' ),
			block( 'core/quote' ),
			list( 7 ),
		];

		expect( evaluateHouseLimits( tree ) ).toEqual( [
			{ id: 'quotes', count: 3, limit: 2 },
			{ id: 'list-items', count: 7, limit: 6 },
		] );
	} );

	it( 'reports in a stable order, so notices do not shuffle', () => {
		const tree = [
			block( 'dp/callout' ),
			block( 'dp/callout' ),
			block( 'core/quote' ),
			block( 'core/quote' ),
			block( 'core/quote' ),
		];

		expect( evaluateHouseLimits( tree ).map( ( f ) => f.id ) ).toEqual( [
			'quotes',
			'callouts',
		] );
	} );
} );

describe( 'the warnings', () => {
	const notices = () => ( {
		createWarningNotice: jest.fn(),
		removeNotice: jest.fn(),
	} );

	it( 'says which limit, by how much, in words', () => {
		const message = messageFor( {
			id: 'code-lines',
			count: 40,
			limit: 15,
		} );

		expect( message ).toContain( '40' );
		expect( message ).toContain( '15' );
	} );

	it( 'has wording for every limit', () => {
		for ( const id of Object.keys( HOUSE_LIMITS ) ) {
			expect(
				messageFor( { id, count: 99, limit: HOUSE_LIMITS[ id ] } )
			).not.toBe( '' );
		}
	} );

	it( 'raises a warning once, not on every keystroke', () => {
		const shown = new Set();
		const store = notices();
		const findings = [ { id: 'quotes', count: 3, limit: 2 } ];

		syncHouseLimitNotices( findings, shown, store );
		syncHouseLimitNotices( findings, shown, store );

		expect( store.createWarningNotice ).toHaveBeenCalledTimes( 1 );
		expect( store.createWarningNotice.mock.calls[ 0 ][ 1 ].id ).toBe(
			'dp-house-limit-quotes'
		);
		expect(
			store.createWarningNotice.mock.calls[ 0 ][ 1 ].isDismissible
		).toBe( true );
	} );

	it( 'takes the warning away when the post comes back inside the limit', () => {
		const shown = new Set();
		const store = notices();

		syncHouseLimitNotices(
			[ { id: 'quotes', count: 3, limit: 2 } ],
			shown,
			store
		);
		syncHouseLimitNotices( [], shown, store );

		expect( store.removeNotice ).toHaveBeenCalledWith(
			'dp-house-limit-quotes'
		);
		expect( shown.size ).toBe( 0 );
	} );

	it( 'never removes a notice it does not own', () => {
		const shown = new Set();
		const store = notices();

		syncHouseLimitNotices( [], shown, store );

		expect( store.removeNotice ).not.toHaveBeenCalled();
	} );
} );
