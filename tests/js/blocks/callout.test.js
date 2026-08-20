/**
 * The dp/callout block's edit and save trees.
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
 * Internal dependencies
 */
import metadata from '../../../plugins/dp-core/src/Blocks/js/callout/block.json';
import save from '../../../plugins/dp-core/src/Blocks/js/callout/save';
import Edit from '../../../plugins/dp-core/src/Blocks/js/callout/edit';

describe( 'dp/callout metadata', () => {
	it( 'is the note block from the design, under our own prefix', () => {
		expect( metadata.name ).toBe( 'dp/callout' );
		expect( metadata.category ).toBe( 'dp' );
		expect( metadata.textdomain ).toBe( 'dp-core' );
	} );

	it( 'defaults its label to the design’s word', () => {
		expect( metadata.attributes.label.type ).toBe( 'string' );
		expect( metadata.attributes.label.default ).toBe( 'NOTE' );
	} );

	it( 'takes its text out of the markup it saved', () => {
		expect( metadata.attributes.content.source ).toBe( 'html' );
		expect( metadata.attributes.content.selector ).toBe(
			'.dp-callout-text'
		);
	} );

	it( 'does not hard-block a second callout', () => {
		// The house limit is one per post, and docs/plan.md Phase 4 is explicit
		// that it is a warning. `multiple: false` would make it a rule.
		expect( metadata.supports.multiple ).toBe( true );
	} );

	it( 'ships no front-end script', () => {
		expect( metadata.script ).toBeUndefined();
		expect( metadata.viewScript ).toBeUndefined();
		expect( metadata.editorScript ).toBe( 'file:./index.js' );
	} );
} );

describe( 'dp/callout save', () => {
	const render = ( attributes ) =>
		renderToStaticMarkup( save( { attributes } ) );

	it( 'writes a label above a single paragraph', () => {
		const html = render( { label: 'NOTE', content: 'A caveat.' } );

		expect( html ).toBe(
			'<div class="dp-callout">' +
				'<span class="dp-callout-label">NOTE</span>' +
				'<p class="dp-callout-text">A caveat.</p>' +
				'</div>'
		);
	} );

	it( 'keeps the class names the theme’s stylesheet targets', () => {
		const html = render( { label: 'HEADS UP', content: '' } );

		expect( html ).toContain( 'dp-callout-label' );
		expect( html ).toContain( 'dp-callout-text' );
	} );

	it( 'carries inline formatting through', () => {
		const html = render( {
			label: 'NOTE',
			content: 'Figures are <em>mine</em> unless linked.',
		} );

		expect( html ).toContain( '<em>mine</em>' );
	} );
} );

describe( 'dp/callout edit', () => {
	it( 'renders the same shape as save, so the canvas matches the page', () => {
		const setAttributes = jest.fn();
		const html = renderToStaticMarkup(
			<Edit
				attributes={ { label: 'NOTE', content: 'A caveat.' } }
				setAttributes={ setAttributes }
			/>
		);

		expect( html ).toContain( 'class="dp-callout"' );
		expect( html ).toContain( 'dp-callout-label' );
		expect( html ).toContain( 'dp-callout-text' );
		expect( setAttributes ).not.toHaveBeenCalled();
	} );
} );
