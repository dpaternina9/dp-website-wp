/**
 * A stand-in for @wordpress/plugins. See wordpress-block-editor.js.
 */
const plugins = new Map();

const registerPlugin = ( name, settings ) => {
	plugins.set( name, settings );

	return settings;
};

const getPlugin = ( name ) => plugins.get( name );

const __reset = () => {
	plugins.clear();
};

module.exports = { registerPlugin, getPlugin, __registered: plugins, __reset };
