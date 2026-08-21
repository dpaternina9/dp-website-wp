/**
 * The editor previews for this plugin's server-rendered blocks.
 *
 * `dp/timeline`, `dp/contact-form` and `dp/resume-ledger` are PHP render
 * callbacks and nothing else, so before this existed all three arrived in the
 * site editor as `core/missing` — "your site doesn't include support for the
 * dp/timeline block" — inside a template that renders perfectly on the site.
 * CLAUDE.md §5 says the editor must look like the front end, and what these
 * tests hold is the two properties that makes true: every one of them gets an
 * `edit`, and that `edit` asks the server for the block it is standing in for
 * rather than drawing an approximation of it.
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
import { registerBlockType, getBlockType, __reset } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import {
	SERVER_RENDERED,
	serverRenderedEdit,
	registerServerRenderedBlocks,
} from '../../../plugins/dp-core/src/Blocks/js/dynamic/server-rendered';

describe( 'the server-rendered blocks', () => {
	beforeEach( () => {
		__reset();
	} );

	it( 'names the three blocks the plugin renders in PHP', () => {
		expect( SERVER_RENDERED ).toEqual( [
			'dp/timeline',
			'dp/contact-form',
			'dp/resume-ledger',
		] );
	} );

	it( 'gives every one of them an editor preview', () => {
		expect( registerServerRenderedBlocks() ).toEqual( SERVER_RENDERED );

		SERVER_RENDERED.forEach( ( name ) => {
			expect( getBlockType( name ) ).toBeDefined();
			expect( typeof getBlockType( name ).edit ).toBe( 'function' );
		} );
	} );

	it( 'restates nothing block.json already says', () => {
		registerServerRenderedBlocks( [ 'dp/timeline' ] );

		/*
		 * Title, icon, category and attributes come from the server definition
		 * WordPress bootstraps into wp.blocks. A settings object carrying its
		 * own copy of any of them is a second place to keep them in step.
		 */
		expect( Object.keys( getBlockType( 'dp/timeline' ) ) ).toEqual( [
			'name',
			'edit',
		] );
	} );

	it( 'does not register a block twice', () => {
		registerBlockType( 'dp/timeline', { edit: () => null } );

		expect( registerServerRenderedBlocks() ).toEqual( [
			'dp/contact-form',
			'dp/resume-ledger',
		] );
	} );

	it( 'asks the server for the block it is standing in for', () => {
		const Edit = serverRenderedEdit( 'dp/resume-ledger' );

		const markup = renderToStaticMarkup(
			<Edit attributes={ { heading: 'Experience' } } />
		);

		expect( markup ).toContain(
			'data-server-side-render="dp/resume-ledger"'
		);
		expect( markup ).toContain( 'Experience' );
	} );
} );
