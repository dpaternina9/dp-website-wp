/**
 * Proves the Jest harness from @wordpress/scripts is really running.
 *
 * A trivial assertion is not the point; the imports are. If the preset, the
 * jsdom environment, or the Babel transform is broken, this file fails to run
 * at all rather than reporting an empty pass.
 */

describe( 'the JS unit harness', () => {
	it( 'runs in a jsdom environment', () => {
		expect( typeof window ).toBe( 'object' );
		expect( typeof document.createElement( 'div' ) ).toBe( 'object' );
	} );

	it( 'transpiles modern syntax', async () => {
		const value = await Promise.resolve( { a: 1, b: 2 } );
		const { a, ...rest } = value;

		expect( a ).toBe( 1 );
		expect( rest ).toEqual( { b: 2 } );
		expect( [ 1, 2, 3 ].at( -1 ) ).toBe( 3 );
	} );

	it( 'loads the @wordpress/jest-preset-default matchers', () => {
		expect( expect.extend ).toBeDefined();
		expect( typeof expect( 1 ).toMatchSnapshot ).toBe( 'function' );
	} );
} );
