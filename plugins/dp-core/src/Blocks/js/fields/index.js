/**
 * The editing surface for the content model, on the client.
 *
 * `DP\Core\Editor\FieldBlocks` registers all seven of these in PHP, with their
 * titles, their attributes and `inserter: false`; WordPress bootstraps that
 * definition into `wp.blocks` on every editor screen. So this file supplies the
 * one thing the server cannot — how each of them draws — and restates nothing
 * about them.
 *
 * `save` returns null throughout. None of these blocks renders on the front end:
 * `dp_role`, `dp_ship` and `dp_video` are `public => false` and have no single
 * view, so their `post_content` is a form and never a page. What each block
 * leaves behind is a self-closing comment carrying the attributes that say which
 * field it edits.
 *
 * WordPress dependencies
 */
import { getBlockType, registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import {
	ChoiceEdit,
	FlagEdit,
	LinesEdit,
	ReferenceEdit,
	TextEdit,
	YearEdit,
} from './controls';
import LabelEdit from './label';
import { FIELD_BLOCKS } from './names';
import { registerPageFieldsPanel } from './page-panel';

/**
 * The `edit` each block name draws with.
 *
 * @type {Object<string, Function>}
 */
const EDITS = {
	'dp/field-label': LabelEdit,
	'dp/meta-text': TextEdit,
	'dp/meta-year': YearEdit,
	'dp/meta-choice': ChoiceEdit,
	'dp/meta-flag': FlagEdit,
	'dp/meta-lines': LinesEdit,
	'dp/meta-reference': ReferenceEdit,
};

/**
 * Nothing of these reaches `post_content` but the block comment itself.
 *
 * @return {null} No saved markup.
 */
export function saveNothing() {
	return null;
}

/**
 * Give every field block its editor half.
 *
 * A block already registered on the client is skipped rather than registered
 * twice, for the same reason the server-rendered previews are: this bundle is
 * loaded once per editor screen and `registerBlockType()` treats a second
 * registration as an error.
 *
 * @param {string[]} names The block names to register.
 * @return {string[]} The names that were registered.
 */
export function registerFieldBlocks( names = FIELD_BLOCKS ) {
	return names.filter( ( name ) => {
		if ( getBlockType( name ) || ! EDITS[ name ] ) {
			return false;
		}

		registerBlockType( name, { edit: EDITS[ name ], save: saveNothing } );

		return true;
	} );
}

/**
 * Everything the editing surface needs on the client.
 *
 * @return {void}
 */
export function installFieldEditing() {
	registerFieldBlocks();
	registerPageFieldsPanel();
}

export { FIELD_BLOCKS };
