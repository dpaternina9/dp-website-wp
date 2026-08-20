/**
 * The editor UI for `dp/callout`.
 *
 * The edit tree is deliberately the same shape as save(), with the same class
 * names, so the theme's one stylesheet renders the canvas and the front end
 * identically (CLAUDE.md §5).
 *
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Edit a callout.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} The editor element tree.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { label, content } = attributes;
	const blockProps = useBlockProps( { className: 'dp-callout' } );

	return (
		<div { ...blockProps }>
			<RichText
				identifier="label"
				tagName="span"
				className="dp-callout-label"
				value={ label }
				allowedFormats={ [] }
				withoutInteractiveFormatting
				onChange={ ( value ) => setAttributes( { label: value } ) }
				placeholder={ __( 'NOTE', 'dp-core' ) }
				aria-label={ __( 'Callout label', 'dp-core' ) }
			/>
			<RichText
				identifier="content"
				tagName="p"
				className="dp-callout-text"
				value={ content }
				onChange={ ( value ) => setAttributes( { content: value } ) }
				placeholder={ __(
					'The caveat the reader will actually hit.',
					'dp-core'
				) }
				aria-label={ __( 'Callout text', 'dp-core' ) }
			/>
		</div>
	);
}
