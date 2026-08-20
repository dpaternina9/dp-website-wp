/**
 * A stand-in for @wordpress/blocks. See wordpress-block-editor.js.
 */
const registered = [];

const registerBlockType = ( name, settings ) => {
	registered.push( { name, settings } );

	return { name, ...settings };
};

module.exports = { registerBlockType, __registered: registered };
