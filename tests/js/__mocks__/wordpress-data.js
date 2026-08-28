/**
 * A stand-in for @wordpress/data. See wordpress-block-editor.js.
 *
 * `useSelect` is the real thing's shape without the registry: it calls the
 * mapping function with a `select` that answers for whatever a test registered
 * against a store descriptor. That is enough to render a control that reads
 * entity records, and it keeps the test asserting on the control rather than on
 * a store implementation.
 */
const stores = new Map();

const select = ( store ) => stores.get( store ) ?? {};

const useSelect = ( mapper ) => mapper( select );

const __setStore = ( store, api ) => {
	stores.set( store, api );
};

const __reset = () => {
	stores.clear();
};

module.exports = {
	select,
	useSelect,
	dispatch: () => ( {} ),
	subscribe: () => () => {},
	__setStore,
	__reset,
};
