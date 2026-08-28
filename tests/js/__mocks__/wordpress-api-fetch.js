/**
 * A stand-in for @wordpress/api-fetch. See wordpress-block-editor.js.
 */
let response = {};

const apiFetch = () => Promise.resolve( response );

apiFetch.__setResponse = ( next ) => {
	response = next;
};

module.exports = apiFetch;
module.exports.default = apiFetch;
