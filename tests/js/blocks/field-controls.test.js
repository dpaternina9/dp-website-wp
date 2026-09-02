/**
 * The controls that stand in where a binding cannot go.
 *
 * The picker is the one worth the most here. `dp_ship` attaches to the role it
 * came from through `dp_role_id`, and before this phase the only way to set it
 * was to find a role's post ID and type the number into WordPress's raw custom
 * fields table. What these assert is that the list is names and the value is an
 * ID — and that a role chosen before the list was searched is still shown by
 * name rather than falling back to a number.
 *
 * External dependencies
 */
import { TextDecoder, TextEncoder } from 'util';

/*
 * react-dom/server resolves to its browser build under jsdom, and that build
 * reaches for TextEncoder, which jsdom does not provide. Node's own are
 * identical for this purpose. The require below is deliberate: an import would
 * be hoisted above these two assignments and fail.
 */
global.TextEncoder = global.TextEncoder ?? TextEncoder;
global.TextDecoder = global.TextDecoder ?? TextDecoder;

// eslint-disable-next-line import/no-extraneous-dependencies -- see tests/js/__mocks__/wordpress-block-editor.js.
const { renderToStaticMarkup } = require( 'react-dom/server' );

/**
 * WordPress dependencies
 */
import { __setEntityProp, __reset } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import {
	ChoiceEdit,
	FlagEdit,
	TextEdit,
	referenceOptions,
	toPostId,
} from '../../../plugins/dp-core/src/Blocks/js/fields/controls';

const context = { postType: 'dp_ship', postId: 7 };

describe( 'a post picker', () => {
	const roles = [
		{ id: 12, title: { rendered: 'Backbone Technology' } },
		{ id: 34, title: { rendered: 'Kiveo &amp; Co' } },
	];

	it( 'offers posts by name and stores their ID', () => {
		expect( referenceOptions( roles, null ) ).toEqual( [
			{ value: '0', label: '— none —' },
			{ value: '12', label: 'Backbone Technology' },
			{ value: '34', label: 'Kiveo & Co' },
		] );
	} );

	it( 'keeps the post already chosen in the list when a search hides it', () => {
		const options = referenceOptions( [ roles[ 0 ] ], {
			id: 34,
			title: { rendered: 'Kiveo &amp; Co' },
		} );

		expect( options[ 1 ] ).toEqual( { value: '34', label: 'Kiveo & Co' } );
	} );

	it( 'does not offer the chosen post twice', () => {
		const options = referenceOptions( roles, roles[ 1 ] );

		expect( options.filter( ( o ) => '34' === o.value ) ).toHaveLength( 1 );
	} );

	it( 'names a post that has no title', () => {
		expect( referenceOptions( [ { id: 9 } ], null )[ 1 ].label ).toBe(
			'Untitled (#9)'
		);
	} );

	it( 'tells two roles at one company apart', () => {
		const aplyca = [
			{
				id: 51,
				title: { rendered: 'Aplyca' },
				meta: {
					dp_role_title: 'Full-Stack Developer',
					dp_range: 'July — Dec 2019',
				},
			},
			{
				id: 52,
				title: { rendered: 'Aplyca' },
				meta: {
					dp_role_title: 'Solutions Architect',
					dp_range: '2019 — 2021',
				},
			},
		];

		expect(
			referenceOptions( aplyca, null, 'dp_role' ).map( ( o ) => o.label )
		).toEqual( [
			'— none —',
			'Aplyca — Full-Stack Developer · July — Dec 2019',
			'Aplyca — Solutions Architect · 2019 — 2021',
		] );
	} );

	it( 'adds nothing for a post type that has no distinguishing meta', () => {
		const writeup = [
			{
				id: 61,
				title: { rendered: 'A write-up' },
				meta: { dp_role_title: 'ignored off dp_role' },
			},
		];

		expect( referenceOptions( writeup, null, 'post' )[ 1 ].label ).toBe(
			'A write-up'
		);
	} );

	it( 'falls back to the title when the meta is empty or missing', () => {
		const bare = [
			{ id: 71, title: { rendered: 'Backbone Technology' }, meta: {} },
			{
				id: 72,
				title: { rendered: 'Imaginamos' },
				meta: { dp_role_title: '   ', dp_range: '' },
			},
		];

		expect(
			referenceOptions( bare, null, 'dp_role' ).map( ( o ) => o.label )
		).toEqual( [ '— none —', 'Backbone Technology', 'Imaginamos' ] );
	} );

	it( 'keeps the detail on the chosen role a search has hidden', () => {
		const options = referenceOptions(
			[],
			{
				id: 52,
				title: { rendered: 'Aplyca' },
				meta: {
					dp_role_title: 'Solutions Architect',
					dp_range: '2019 — 2021',
				},
			},
			'dp_role'
		);

		expect( options[ 1 ].label ).toBe(
			'Aplyca — Solutions Architect · 2019 — 2021'
		);
	} );

	it( 'offers nothing but "none" before the list arrives', () => {
		expect( referenceOptions( null, null ) ).toEqual( [
			{ value: '0', label: '— none —' },
		] );
	} );

	it( 'stores 0 rather than NaN for no reference', () => {
		expect( toPostId( '0' ) ).toBe( 0 );
		expect( toPostId( '' ) ).toBe( 0 );
		expect( toPostId( null ) ).toBe( 0 );
		expect( toPostId( '34' ) ).toBe( 34 );
	} );
} );

describe( 'the controls that draw their own label', () => {
	beforeEach( () => {
		__reset();
	} );

	it( 'labels an enum with the field name and offers its registered values', () => {
		__setEntityProp( { dp_tone: 'pink' } );

		const html = renderToStaticMarkup(
			<ChoiceEdit
				attributes={ {
					metaKey: 'dp_tone',
					label: 'Tone',
					help: 'Which hue the card takes.',
					options: [
						{ value: '', label: '— not set —' },
						{ value: 'teal', label: 'teal' },
						{ value: 'pink', label: 'pink' },
					],
				} }
				context={ context }
			/>
		);

		expect( html ).toContain( 'aria-label="Tone"' );
		expect( html ).toContain( '>teal</option>' );
		expect( html ).toContain( '>pink</option>' );
	} );

	it( 'labels a boolean and reflects what is stored', () => {
		__setEntityProp( { dp_featured: true } );

		const html = renderToStaticMarkup(
			<FlagEdit
				attributes={ {
					metaKey: 'dp_featured',
					label: 'Featured on a work card',
					help: '',
				} }
				context={ context }
			/>
		);

		expect( html ).toContain( 'aria-label="Featured on a work card"' );
		expect( html ).toContain( 'checked=""' );
	} );

	it( 'gives a preformatted field room and the value it holds', () => {
		__setEntityProp( { dp_artifact: 'wp dp seed --fresh\nDone.' } );

		const html = renderToStaticMarkup(
			<TextEdit
				attributes={ {
					metaKey: 'dp_artifact',
					label: 'Artifact',
					help: '',
					monospace: true,
				} }
				context={ context }
			/>
		);

		expect( html ).toContain( 'aria-label="Artifact"' );
		expect( html ).toContain( 'rows="8"' );
		expect( html ).toContain( 'wp dp seed --fresh' );
	} );
} );
