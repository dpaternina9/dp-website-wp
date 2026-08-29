/**
 * A stand-in for @wordpress/core-data. See wordpress-block-editor.js.
 *
 * The real `useEntityProp` reads and writes the post being edited through the
 * data registry. Here it is a pair of values a test sets, so a control can be
 * rendered and its writes observed without a store.
 */
const store = { name: 'core' };

let entityProp = [ {}, () => {} ];

const useEntityProp = () => entityProp;

const __setEntityProp = ( value, setter = () => {} ) => {
	entityProp = [ value, setter ];
};

const __reset = () => {
	entityProp = [ {}, () => {} ];
};

module.exports = { store, useEntityProp, __setEntityProp, __reset };
