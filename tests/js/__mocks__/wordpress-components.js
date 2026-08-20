/**
 * A stand-in for @wordpress/components. See wordpress-block-editor.js.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- see wordpress-block-editor.js.
const { createElement } = require( 'react' );

const PanelBody = ( { title, children } ) =>
	createElement( 'section', { 'aria-label': title }, children );

const TextControl = ( { label, value, onChange } ) =>
	createElement( 'input', { 'aria-label': label, value, onChange } );

module.exports = { PanelBody, TextControl };
