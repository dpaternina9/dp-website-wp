/**
 * A stand-in for @wordpress/block-editor.
 *
 * The package is not a dependency of this repository and never will be: the
 * build externalises every `@wordpress/*` import to the `wp.*` globals
 * WordPress already loads, so installing it would add a large tree that ships
 * nowhere. Jest needs *something* to resolve, and a double that behaves like
 * the two APIs the callout actually uses is more honest than a copy of the real
 * package that the browser never runs.
 *
 * External dependencies
 */
/*
 * React is not — and must not be — a dependency of this repository: WordPress
 * externalises it, and the build emits `react-jsx-runtime` and `wp-element` as
 * globals rather than bundling anything. Under Jest it resolves through
 * @wordpress/element's own tree, which is the same React the editor runs.
 */
// eslint-disable-next-line import/no-extraneous-dependencies
const { createElement } = require( 'react' );

/**
 * The props WordPress would put on a block's root element.
 *
 * The real implementation also adds `wp-block-{name}`, which it derives from
 * editor context this double has no access to. Tests assert on the class names
 * the theme's CSS targets, which are the ones passed in here.
 *
 * @param {Object} props Extra props for the block element.
 * @return {Object} The props to spread.
 */
const useBlockProps = ( props = {} ) => ( { ...props } );

useBlockProps.save = ( props = {} ) => ( { ...props } );

// The editable component. Renders as its tag, with the value inside it.
const RichText = ( { tagName = 'div', value = '', className, placeholder } ) =>
	createElement(
		tagName,
		{
			className,
			'data-placeholder': placeholder,
			contentEditable: true,
			suppressContentEditableWarning: true,
		},
		value
	);

RichText.Content = ( { tagName = 'div', value = '', className } ) =>
	createElement( tagName, {
		className,
		dangerouslySetInnerHTML: { __html: value },
	} );

const InspectorControls = ( { children } ) =>
	createElement( 'div', { className: 'inspector' }, children );

module.exports = { useBlockProps, RichText, InspectorControls };
