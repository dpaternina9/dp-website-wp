/**
 * A stand-in for @wordpress/editor. See wordpress-block-editor.js.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- see wordpress-block-editor.js.
const { createElement } = require( 'react' );

const store = { name: 'core/editor' };

const PluginDocumentSettingPanel = ( { title, children } ) =>
	createElement( 'section', { 'aria-label': title }, children );

module.exports = { store, PluginDocumentSettingPanel };
