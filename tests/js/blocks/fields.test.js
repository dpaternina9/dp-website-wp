/**
 * The editing form's client half.
 *
 * The three assertions worth making without a browser are here: that the list of
 * blocks the bundle draws is a list and not a guess, that none of them saves
 * anything into `post_content`, and that the decimal-year arithmetic agrees with
 * `DP\Core\Content\Year` — which is the one piece of logic in this phase written
 * twice in two languages.
 *
 * WordPress dependencies
 */
import { getBlockType, __reset } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import {
	FIELD_BLOCKS,
	registerFieldBlocks,
	saveNothing,
} from '../../../plugins/dp-core/src/Blocks/js/fields';
import {
	MONTHS,
	toDecimal,
	toParts,
} from '../../../plugins/dp-core/src/Blocks/js/fields/year';
import { fieldsFromSchema } from '../../../plugins/dp-core/src/Blocks/js/fields/page-panel';

describe( 'the field blocks', () => {
	beforeEach( () => {
		__reset();
	} );

	it( 'names one label block and one control per shape', () => {
		expect( FIELD_BLOCKS ).toEqual( [
			'dp/field-label',
			'dp/meta-text',
			'dp/meta-year',
			'dp/meta-choice',
			'dp/meta-flag',
			'dp/meta-lines',
			'dp/meta-reference',
		] );
	} );

	it( 'gives every one of them an edit', () => {
		expect( registerFieldBlocks() ).toEqual( FIELD_BLOCKS );

		FIELD_BLOCKS.forEach( ( name ) => {
			expect( typeof getBlockType( name ).edit ).toBe( 'function' );
		} );
	} );

	it( 'registers none of them twice', () => {
		registerFieldBlocks();

		expect( registerFieldBlocks() ).toEqual( [] );
	} );

	it( 'saves no markup, because none of these post types has a page', () => {
		expect( saveNothing() ).toBeNull();

		registerFieldBlocks();

		FIELD_BLOCKS.forEach( ( name ) => {
			expect( getBlockType( name ).save() ).toBeNull();
		} );
	} );

	it( 'ignores a name it has no edit for', () => {
		expect( registerFieldBlocks( [ 'dp/not-a-field' ] ) ).toEqual( [] );
	} );
} );

describe( 'the decimal year', () => {
	it( 'reads the fraction as twelfths, the way Year does', () => {
		// The three rows of the table in DP\Core\Content\Year's docblock.
		expect( toParts( 2026 ) ).toEqual( { year: '2026', month: 1 } );
		expect( toParts( 2026.4 ) ).toEqual( { year: '2026', month: 5 } );
		expect( toParts( 2026.6 ) ).toEqual( { year: '2026', month: 8 } );
	} );

	it( 'treats zero as no date yet, which is the registered default', () => {
		expect( toParts( 0 ) ).toEqual( { year: '', month: 1 } );
		expect( toParts( '' ) ).toEqual( { year: '', month: 1 } );
		expect( toDecimal( '', 5 ) ).toBe( 0 );
	} );

	it( 'round-trips every month of a year', () => {
		for ( let month = 1; month <= MONTHS; month++ ) {
			expect( toParts( toDecimal( 2024, month ) ) ).toEqual( {
				year: '2024',
				month,
			} );
		}
	} );

	it( 'holds the month inside the year', () => {
		expect( toDecimal( 2024, 0 ) ).toBe( 2024 );
		expect( toParts( toDecimal( 2024, 99 ) ).month ).toBe( MONTHS );
	} );
} );

describe( 'the page panel', () => {
	const schema = {
		properties: {
			dp_lead: {
				type: 'string',
				title: 'Deck',
				description: 'The deck under the page title.',
			},
			dp_updated: { type: 'string', title: 'Updated stamp' },
			dp_tone: { type: 'string', title: 'Tone', enum: [ '', 'teal' ] },
			footnotes: { type: 'string', title: 'Footnotes' },
		},
	};

	it( 'takes its labels from the schema rather than restating them', () => {
		expect( fieldsFromSchema( schema ) ).toEqual( [
			{
				key: 'dp_lead',
				title: 'Deck',
				description: 'The deck under the page title.',
				type: 'string',
				options: [],
			},
			{
				key: 'dp_updated',
				title: 'Updated stamp',
				description: '',
				type: 'string',
				options: [],
			},
			{
				key: 'dp_tone',
				title: 'Tone',
				description: '',
				type: 'string',
				options: [ '', 'teal' ],
			},
		] );
	} );

	it( 'draws nothing for a field that is not ours', () => {
		expect(
			fieldsFromSchema( schema ).some(
				( field ) => 'footnotes' === field.key
			)
		).toBe( false );
	} );

	it( 'copes with a schema that has not arrived yet', () => {
		expect( fieldsFromSchema( undefined ) ).toEqual( [] );
		expect( fieldsFromSchema( {} ) ).toEqual( [] );
	} );
} );
