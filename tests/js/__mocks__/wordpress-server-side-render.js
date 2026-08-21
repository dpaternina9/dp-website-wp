/**
 * A stand-in for @wordpress/server-side-render.
 *
 * Same reasoning as the other doubles in this directory: the build externalises
 * every `@wordpress/*` import to a `wp.*` global, so the package is not a
 * dependency of this repository and installing it would add a tree that ships
 * nowhere. The real component fetches `/wp/v2/block-renderer/{name}` and prints
 * what comes back; what a test can usefully assert is that it was asked for the
 * right block with the right attributes, so this renders those as data.
 *
 * External dependencies
 */
// eslint-disable-next-line import/no-extraneous-dependencies
const { createElement } = require( 'react' );

const ServerSideRender = ( { block, attributes = {} } ) =>
	createElement( 'div', {
		'data-server-side-render': block,
		'data-attributes': JSON.stringify( attributes ),
	} );

module.exports = ServerSideRender;
module.exports.default = ServerSideRender;
