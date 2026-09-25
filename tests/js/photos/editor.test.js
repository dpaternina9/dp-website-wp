/**
 * The pure parts of a photo's editing surface.
 *
 * The trip control's two rules — "no trip" is the first option, and choosing
 * one stores exactly one — and the one conversion the Camera panel makes before
 * it offers a taken-at time as the publish date.
 */
import {
	tripOptions,
	tripValue,
	withTripSelect,
	TRIP_TAXONOMY,
} from '../../../plugins/dp-core/src/Blocks/js/photos/trip-select';
import { publishDateFrom } from '../../../plugins/dp-core/src/Blocks/js/photos/photo-panel';

describe( 'the trip control', () => {
	it( 'offers "no trip" first, then every trip by name', () => {
		expect(
			tripOptions( [
				{ id: 4, name: 'Putumayo' },
				{ id: 9, name: 'Duitama &amp; around' },
			] )
		).toEqual( [
			{ value: '0', label: expect.any( String ) },
			{ value: '4', label: 'Putumayo' },
			{ value: '9', label: 'Duitama & around' },
		] );
		expect( tripOptions( null ) ).toHaveLength( 1 );
	} );

	it( 'stores one trip, or none', () => {
		expect( tripValue( '4' ) ).toEqual( [ 4 ] );
		expect( tripValue( '0' ) ).toEqual( [] );
		expect( tripValue( 'nonsense' ) ).toEqual( [] );
	} );

	it( 'replaces the panel for trips and nothing else', () => {
		const Original = () => null;
		const Panel = withTripSelect( Original );

		expect( Panel( { slug: TRIP_TAXONOMY } ).type.name ).toBe(
			'TripSelect'
		);
		expect( Panel( { slug: 'dp_topic' } ).type ).toBe( Original );
	} );
} );

describe( 'the Camera panel', () => {
	it( 'turns a taken-at time into a publish date without moving it', () => {
		expect( publishDateFrom( '2017-12-12 18:04:09' ) ).toBe(
			'2017-12-12T18:04:09'
		);
	} );

	it( 'offers nothing for a missing or malformed time', () => {
		expect( publishDateFrom( null ) ).toBeNull();
		expect( publishDateFrom( '' ) ).toBeNull();
		expect( publishDateFrom( '12/12/2017' ) ).toBeNull();
	} );
} );
