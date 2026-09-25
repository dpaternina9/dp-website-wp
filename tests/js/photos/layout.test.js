/**
 * The wall's placement arithmetic.
 *
 * `photo-layout.js` is the one piece of the Photos page's script with a rule
 * worth holding in a test: shortest-column placement is what keeps the wall
 * reading newest first, and the panorama and tall-photo rules are the design's.
 * It is loaded as a plain browser script on the page, and exports itself for
 * CommonJS so this can require it without a browser.
 */
const {
	place,
	columnsFor,
	shouldAutoLoad,
	PANORAMA,
	TALL_CAP,
	AUTO_LOAD_CAP,
} = require( '../../../themes/dpaternina/assets/js/photo-layout' );

const square = { w: 1000, h: 1000 };
const landscape = { w: 1500, h: 1000 };
const portrait = { w: 1000, h: 1500 };

describe( 'the photo wall layout', () => {
	it( 'fits as many ~260px columns as the width allows, never fewer than two', () => {
		expect( columnsFor( 1120, 260, 12, 2 ) ).toBe( 4 );
		expect( columnsFor( 1600, 260, 12, 2 ) ).toBe( 5 );
		expect( columnsFor( 300, 260, 12, 2 ) ).toBe( 2 );
	} );

	it( 'fills the top row left to right, newest first', () => {
		const { boxes, columnWidth } = place(
			[ square, square, square, square ],
			1120,
			{ gap: 12 }
		);

		expect( boxes.map( ( box ) => box.y ) ).toEqual( [ 0, 0, 0, 0 ] );
		expect( boxes.map( ( box ) => Math.round( box.x ) ) ).toEqual( [
			0,
			Math.round( columnWidth + 12 ),
			Math.round( 2 * ( columnWidth + 12 ) ),
			Math.round( 3 * ( columnWidth + 12 ) ),
		] );
	} );

	it( 'puts each next photo in the shortest column', () => {
		// Two columns: a tall one on the left, a short one on the right, so the
		// third photo goes under the short one rather than back to column one.
		const { boxes } = place( [ portrait, landscape, square ], 512, {
			gap: 12,
			columns: 2,
		} );

		expect( boxes[ 2 ].x ).toBe( boxes[ 1 ].x );
		expect( boxes[ 2 ].y ).toBeCloseTo( boxes[ 1 ].height + 12 );
	} );

	it( 'spans a panorama across two columns when there are more than two', () => {
		const panorama = { w: 3400, h: 1000 };

		expect( panorama.w / panorama.h ).toBeGreaterThan( PANORAMA );

		const wide = place( [ panorama ], 1120, { gap: 12 } );

		expect( wide.boxes[ 0 ].span ).toBe( 2 );
		expect( wide.boxes[ 0 ].width ).toBeCloseTo(
			wide.columnWidth * 2 + 12
		);

		const phone = place( [ panorama ], 360, { gap: 6, columns: 2 } );

		expect( phone.boxes[ 0 ].span ).toBe( 1 );
	} );

	it( 'caps a very tall photo at TALL_CAP column widths', () => {
		const { boxes, columnWidth } = place( [ { w: 500, h: 2000 } ], 1120, {
			gap: 12,
		} );

		expect( boxes[ 0 ].height ).toBeCloseTo( columnWidth * TALL_CAP );
	} );

	it( 'keeps an ordinary photo at its own ratio', () => {
		const { boxes, columnWidth } = place( [ landscape ], 1120, {
			gap: 12,
		} );

		expect( boxes[ 0 ].height ).toBeCloseTo( columnWidth / 1.5 );
	} );

	it( 'reports the wall height without a trailing gap', () => {
		const { height, boxes } = place( [ square ], 1120, { gap: 12 } );

		expect( height ).toBeCloseTo( boxes[ 0 ].height );
		expect( place( [], 1120, { gap: 12 } ).height ).toBe( 0 );
	} );

	it( 'places an appended page exactly where a full layout would, moving nothing', () => {
		const first = [ portrait, landscape, square, { w: 3400, h: 1000 } ];
		const second = [ square, portrait, landscape, { w: 500, h: 2000 } ];
		const whole = place( [ ...first, ...second ], 1120, { gap: 12 } );
		const before = place( first, 1120, { gap: 12 } );
		const after = place( second, 9999, { from: before } );

		expect( after.boxes ).toEqual( whole.boxes.slice( first.length ) );
		expect( after.height ).toBeCloseTo( whole.height );
		expect( before.heights ).not.toBe( after.heights );
	} );
} );

describe( 'loading more by itself', () => {
	it( 'loads while the wall is short of the cap, and not after', () => {
		expect( AUTO_LOAD_CAP ).toBe( 150 );
		expect( shouldAutoLoad( 48, true, false ) ).toBe( true );
		expect( shouldAutoLoad( 144, true, false ) ).toBe( true );
		expect( shouldAutoLoad( 150, true, false ) ).toBe( false );
		expect( shouldAutoLoad( 192, true, false ) ).toBe( false );
	} );

	it( 'never loads with nothing left, or while a page is already coming', () => {
		expect( shouldAutoLoad( 48, false, false ) ).toBe( false );
		expect( shouldAutoLoad( 48, true, true ) ).toBe( false );
	} );

	it( 'takes a different cap when asked', () => {
		expect( shouldAutoLoad( 10, true, false, 8 ) ).toBe( false );
	} );
} );
