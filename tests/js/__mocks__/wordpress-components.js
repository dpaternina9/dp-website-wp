/**
 * A stand-in for @wordpress/components. See wordpress-block-editor.js.
 *
 * Each double renders the one thing the tests read: a real control, labelled the
 * way the real component labels it, so an assertion about a field's label is an
 * assertion about what a screen reader would be told.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- see wordpress-block-editor.js.
const { createElement } = require( 'react' );

const PanelBody = ( { title, children } ) =>
	createElement( 'section', { 'aria-label': title }, children );

const TextControl = ( { label, value, onChange, type } ) =>
	createElement( 'input', { 'aria-label': label, type, value, onChange } );

const TextareaControl = ( { label, value, onChange, rows } ) =>
	createElement( 'textarea', { 'aria-label': label, rows, value, onChange } );

const ToggleControl = ( { label, checked, onChange } ) =>
	createElement( 'input', {
		'aria-label': label,
		type: 'checkbox',
		checked,
		onChange,
	} );

const optionElements = ( options = [] ) =>
	options.map( ( option ) =>
		createElement(
			'option',
			{ key: option.value, value: option.value },
			option.label
		)
	);

const SelectControl = ( { label, value, options, onChange } ) =>
	createElement(
		'select',
		{ 'aria-label': label, value, onChange },
		optionElements( options )
	);

const ComboboxControl = ( { label, value, options, onChange } ) =>
	createElement(
		'select',
		{ 'aria-label': label, value, onChange, 'data-combobox': 'true' },
		optionElements( options )
	);

const Button = ( { children, onClick } ) =>
	createElement( 'button', { type: 'button', onClick }, children );

module.exports = {
	Button,
	ComboboxControl,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
};
