/**
 * The editor's half of this plugin's server-rendered blocks.
 *
 * `dp/timeline`, `dp/contact-form` and `dp/resume-ledger` are registered in PHP
 * with a render callback and nothing else. That is enough for the front end and
 * not enough for the editor: the block editor only draws a block it has a
 * client-side registration for, so all three arrived in the site editor as
 * core's `core/missing` — "Your site doesn't include support for the
 * dp/timeline block. You can leave it as-is or remove it." — inside a template
 * that renders perfectly on the site.
 *
 * CLAUDE.md §5 says the editor must look like the front end, so each of them
 * gets an `edit` that asks the server for exactly what the page will show.
 * `ServerSideRender` is the right tool precisely because there is nothing to
 * duplicate: none of these blocks has content of its own, and a hand-written
 * editor preview would be a second renderer to keep in step with the first.
 *
 * Everything else — title, icon, category, attributes — comes from the server
 * definition WordPress already bootstraps into `wp.blocks` for every registered
 * block type, so nothing here restates what `block.json` says.
 *
 * WordPress dependencies
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * The blocks this plugin renders on the server and nowhere else.
 *
 * Exported so a test can assert the list is the one the PHP side registers
 * rather than a list that happens to be right today.
 *
 * @type {string[]}
 */
export const SERVER_RENDERED = [
	'dp/timeline',
	'dp/contact-form',
	'dp/resume-ledger',
];

/**
 * Build the `edit` for one block name.
 *
 * @param {string} name The block's name.
 * @return {Function} A block edit component.
 */
export function serverRenderedEdit( name ) {
	return function Edit( { attributes } ) {
		return (
			<div { ...useBlockProps() }>
				<ServerSideRender block={ name } attributes={ attributes } />
			</div>
		);
	};
}

/**
 * Give every server-rendered block an editor preview.
 *
 * A block already registered on the client is skipped rather than registered
 * twice: `registerBlockType()` treats that as an error, and this file is
 * imported by a bundle the editor loads once per screen.
 *
 * @param {string[]} names The block names to register.
 * @return {string[]} The names that were registered.
 */
export function registerServerRenderedBlocks( names = SERVER_RENDERED ) {
	return names.filter( ( name ) => {
		if ( getBlockType( name ) ) {
			return false;
		}

		registerBlockType( name, { edit: serverRenderedEdit( name ) } );

		return true;
	} );
}
