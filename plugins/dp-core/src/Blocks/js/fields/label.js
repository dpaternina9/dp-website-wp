/**
 * The label in front of a bound paragraph.
 *
 * Seventeen of the thirty-three fields on the three custom post types are edited
 * in a `core/paragraph` bound to post meta through `core/post-meta`. That is
 * core's own mechanism and it needs nothing from us at display time — but a
 * paragraph has no label, and a placeholder disappears the moment there is a
 * value in it.
 *
 * So this block draws one. It is a block rather than a heading with the words
 * typed into it because a heading is editable text: David could type over the
 * name of the field, or delete it, and the form would silently stop saying what
 * it is for. Here the label is an attribute placed by `register_post_type()`'s
 * template, drawn by this component, and not editable at all.
 *
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Draw the label and its help text.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} The editor element tree.
 */
export default function LabelEdit( { attributes } ) {
	const { label, help } = attributes;

	return (
		<div { ...useBlockProps() }>
			<span className="dp-field-label-name">{ label }</span>
			{ help ? (
				<span className="dp-field-label-help">{ help }</span>
			) : null }
		</div>
	);
}
