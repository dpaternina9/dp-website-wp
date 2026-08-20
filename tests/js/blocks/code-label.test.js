/**
 * The label added to core/code.
 *
 * Internal dependencies
 */
import {
	DEFAULT_CODE_LABEL,
	TARGET_BLOCK,
	addLabelAttribute,
} from '../../../plugins/dp-core/src/Blocks/js/house-style/code-label';

describe( 'the code label attribute', () => {
	it( 'defaults to the design’s label', () => {
		expect( DEFAULT_CODE_LABEL ).toBe( 'SHELL' );
	} );

	it( 'is added to core/code', () => {
		const settings = addLabelAttribute(
			{ attributes: { content: { type: 'string' } } },
			TARGET_BLOCK
		);

		expect( settings.attributes.dpLabel ).toEqual( {
			type: 'string',
			default: 'SHELL',
		} );
	} );

	it( 'has no source, so it is stored in the block comment and not in the markup', () => {
		// This is the whole reason the label is safe to add to a core block:
		// core's save() output is untouched, so deactivating dp-core cannot
		// invalidate a single published code block.
		const settings = addLabelAttribute( { attributes: {} }, TARGET_BLOCK );

		expect( settings.attributes.dpLabel.source ).toBeUndefined();
		expect( settings.attributes.dpLabel.selector ).toBeUndefined();
	} );

	it( 'leaves the block’s own attributes alone', () => {
		const original = { content: { type: 'string', source: 'html' } };
		const settings = addLabelAttribute(
			{ attributes: original },
			TARGET_BLOCK
		);

		expect( settings.attributes.content ).toBe( original.content );
	} );

	it( 'touches no other block', () => {
		const settings = { attributes: {} };

		expect( addLabelAttribute( settings, 'core/paragraph' ) ).toBe(
			settings
		);
		expect( addLabelAttribute( settings, 'dp/callout' ) ).toBe( settings );
	} );
} );
