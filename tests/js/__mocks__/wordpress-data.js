/**
 * A stand-in for @wordpress/data. See wordpress-block-editor.js.
 */
module.exports = {
	select: () => null,
	dispatch: () => ( {} ),
	subscribe: () => () => {},
};
