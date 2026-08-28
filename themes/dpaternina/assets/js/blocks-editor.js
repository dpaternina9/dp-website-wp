/**
 * The editor's half of every block this theme renders on the server.
 *
 * Every name in `SERVER_RENDERED` below is registered in PHP with a render
 * callback and nothing else. That is enough for the front end and not enough
 * for the editor: the block editor draws a block only from a client-side
 * registration, so a block the server knows about and the client does not
 * arrives in the canvas as core's `core/missing` — "Your site doesn't include
 * support for the dpaternina/feed-link block" — inside a template that renders
 * perfectly on the site. ADR-0009 has the reasoning; this is the theme's
 * application of it.
 *
 * `ServerSideRender` is the right tool precisely because there is nothing to
 * duplicate. None of these blocks has content of its own: each is a label, a
 * URL or a list nobody can type, and a hand-written editor preview would be a
 * second implementation of the same derivation kept in step with the first by
 * nobody.
 *
 * Everything else — title, description, category, keywords — comes from the
 * server definition WordPress already bootstraps into `wp.blocks` for every
 * registered block type, so nothing here restates what `block.json` says.
 *
 * It is written against the `wp` globals rather than as ES modules on purpose.
 * The theme ships no JavaScript build (`package.json` builds `dp-core` only),
 * and giving it one to register three previews would be a build, a bundle and a
 * compiled artefact in the release zip for about forty lines of code. Every
 * dependency below is a core-provided script handle, declared in PHP by
 * `DP\Theme\Blocks\EditorScript`.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const wp = window.wp;

	if (
		! wp ||
		! wp.blocks ||
		! wp.element ||
		! wp.blockEditor ||
		! wp.serverSideRender
	) {
		return;
	}

	const createElement = wp.element.createElement;
	const useBlockProps = wp.blockEditor.useBlockProps;
	const ServerSideRender = wp.serverSideRender;

	/** The blocks this theme renders on the server and nowhere else. */
	const SERVER_RENDERED = [
		'dpaternina/series-parts-link',
		'dpaternina/resume-download',
		'dpaternina/feed-link',
		'dpaternina/filter-pills',
		'dpaternina/series-planned',
	];

	/**
	 * Build the `edit` for one block name.
	 *
	 * @param {string} name The block's name.
	 * @return {Function} A block edit component.
	 */
	function serverRenderedEdit( name ) {
		return function Edit( props ) {
			return createElement(
				'div',
				useBlockProps(),
				createElement( ServerSideRender, {
					block: name,
					attributes: props.attributes,
				} )
			);
		};
	}

	SERVER_RENDERED.forEach( function ( name ) {
		// A block already registered on the client is skipped rather than
		// registered twice: `registerBlockType()` treats that as an error, and
		// this file is enqueued once per block-editor screen.
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		wp.blocks.registerBlockType( name, {
			edit: serverRenderedEdit( name ),
		} );
	} );
} )();
