/**
 * Reading and writing one registered meta field from a block in the canvas.
 *
 * WordPress dependencies
 */
import { useEntityProp } from '@wordpress/core-data';

/**
 * One field of the post being edited.
 *
 * The write goes through the entity record, which is what makes it part of the
 * post's own unsaved state: the field turns the Save button on, it is written in
 * the same request as the title, and undo covers it. A direct REST call would do
 * none of that.
 *
 * Nothing here validates. Every field keeps the `sanitize_callback` and the
 * `auth_callback` it was registered with (`DP\Core\Content\Meta`), and a control
 * that also decided what was acceptable would be a second gate to disagree with
 * the first.
 *
 * @param {string} metaKey  The registered meta key.
 * @param {string} postType The post type being edited.
 * @param {number} postId   The post being edited.
 * @return {[*, Function]} The value and a setter for it.
 */
export function useMetaField( metaKey, postType, postId ) {
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	const setValue = ( value ) => setMeta( { ...meta, [ metaKey ]: value } );

	return [ meta?.[ metaKey ], setValue ];
}
