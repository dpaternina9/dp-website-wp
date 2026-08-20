/**
 * The saved markup for `dp/callout`.
 *
 * The structure mirrors design-source/components/PostBlocks.dc.html: a label
 * span above a single paragraph, both inside one element. Everything visual
 * lives in the theme's assets/css/blocks.css, so switching themes leaves the
 * content intact and only changes how it looks.
 *
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * Serialise a callout.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} The saved element tree.
 */
export default function save( { attributes } ) {
	const { label, content } = attributes;
	const blockProps = useBlockProps.save( { className: 'dp-callout' } );

	return (
		<div { ...blockProps }>
			<RichText.Content
				tagName="span"
				className="dp-callout-label"
				value={ label }
			/>
			<RichText.Content
				tagName="p"
				className="dp-callout-text"
				value={ content }
			/>
		</div>
	);
}
