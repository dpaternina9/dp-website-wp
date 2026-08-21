/**
 * A stand-in for @wordpress/blocks. See wordpress-block-editor.js.
 */
const registered = [];

const registerBlockType = ( name, settings ) => {
	registered.push( { name, settings } );

	return { name, ...settings };
};

/*
 * The real `getBlockType()` answers for a block registered on the *client*, and
 * returns undefined for one the server has merely bootstrapped a definition
 * for. That distinction is the whole point of the guard in
 * src/Blocks/js/dynamic/server-rendered.js, so the double keeps it.
 */
const getBlockType = ( name ) => {
	const found = registered.find( ( block ) => block.name === name );

	return found ? { name, ...found.settings } : undefined;
};

const __reset = () => {
	registered.length = 0;
};

module.exports = {
	registerBlockType,
	getBlockType,
	__registered: registered,
	__reset,
};
