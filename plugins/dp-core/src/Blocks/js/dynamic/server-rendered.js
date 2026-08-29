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
 * CLAUDE.md says the editor must look like the front end, so each of them gets
 * an `edit` that asks the server for exactly what the page will show.
 * `ServerSideRender` is the right tool precisely because there is nothing to
 * duplicate: none of these blocks has content of its own, and a hand-written
 * editor preview would be a second renderer to keep in step with the first.
 *
 * The contact form's copy is the one exception to "nothing to edit": every
 * visible string on its three panels is a block attribute (CLAUDE.md rule 2 —
 * copy is David's to change from the admin), so its `edit` also draws the
 * inspector panels that let him change them. The controls read and write the
 * attributes `block.json` declares; the labels here are the only thing this
 * file adds.
 *
 * Everything else — title, icon, category, attributes — comes from the server
 * definition WordPress already bootstraps into `wp.blocks` for every registered
 * block type, so nothing here restates what `block.json` says.
 *
 * WordPress dependencies
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
	'dp/watch-featured',
	'dp/video-grid',
];

/**
 * The inspector panels a server-rendered block offers, by block name.
 *
 * Each field names one attribute from the block's own `block.json`; `area`
 * marks the ones long enough to want a textarea. A block with no entry here
 * gets no inspector, which is every block whose attributes are not copy.
 *
 * Exported so a test can hold this list to the attributes the server declares.
 *
 * @type {Object.<string, Array.<{title: string, fields: Array.<{key: string, label: string, area?: boolean}>}>>}
 */
export const COPY_PANELS = {
	'dp/contact-form': [
		{
			title: __( 'Form copy', 'dp-core' ),
			fields: [
				{ key: 'heading', label: __( 'Heading', 'dp-core' ) },
				{ key: 'nameLabel', label: __( 'Name label', 'dp-core' ) },
				{
					key: 'namePlaceholder',
					label: __( 'Name placeholder', 'dp-core' ),
				},
				{ key: 'emailLabel', label: __( 'Email label', 'dp-core' ) },
				{
					key: 'emailPlaceholder',
					label: __( 'Email placeholder', 'dp-core' ),
				},
				{
					key: 'messageLabel',
					label: __( 'Message label', 'dp-core' ),
				},
				{
					key: 'messagePlaceholder',
					label: __( 'Message placeholder', 'dp-core' ),
					area: true,
				},
				{ key: 'submitLabel', label: __( 'Send button', 'dp-core' ) },
				{
					key: 'note',
					label: __( 'Note under the form', 'dp-core' ),
					area: true,
				},
			],
		},
		{
			title: __( 'After it sends', 'dp-core' ),
			fields: [
				{ key: 'sentHeading', label: __( 'Heading', 'dp-core' ) },
				{
					key: 'sentLine',
					label: __( 'Line', 'dp-core' ),
					area: true,
				},
				{
					key: 'sendAnotherLabel',
					label: __( '"Send another" link', 'dp-core' ),
				},
				{
					key: 'readSomethingLabel',
					label: __( '"Read something" link', 'dp-core' ),
				},
			],
		},
		{
			title: __( 'When it fails', 'dp-core' ),
			fields: [
				{ key: 'failedHeading', label: __( 'Heading', 'dp-core' ) },
				{
					key: 'failedLine',
					label: __( 'Line', 'dp-core' ),
					area: true,
				},
				{
					key: 'rateLimitedLine',
					label: __( 'Line when rate limited', 'dp-core' ),
					area: true,
				},
				{
					key: 'tryAgainLabel',
					label: __( '"Try again" button', 'dp-core' ),
				},
				{
					key: 'emailInsteadLabel',
					label: __( '"Email instead" link', 'dp-core' ),
				},
			],
		},
	],
};

/**
 * The inspector for one block, or nothing when it has no copy to edit.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.name          The block's name.
 * @param {Object}   props.attributes    The block's attributes.
 * @param {Function} props.setAttributes The attribute setter.
 * @return {?JSX.Element} The inspector controls.
 */
function CopyInspector( { name, attributes, setAttributes } ) {
	const panels = COPY_PANELS[ name ] ?? [];

	if ( 0 === panels.length ) {
		return null;
	}

	return (
		<InspectorControls>
			{ panels.map( ( panel ) => (
				<PanelBody
					key={ panel.title }
					title={ panel.title }
					initialOpen={ false }
				>
					{ panel.fields.map( ( field ) => {
						const Control = field.area
							? TextareaControl
							: TextControl;

						return (
							<Control
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								key={ field.key }
								label={ field.label }
								value={ attributes[ field.key ] ?? '' }
								onChange={ ( value ) =>
									setAttributes( { [ field.key ]: value } )
								}
							/>
						);
					} ) }
				</PanelBody>
			) ) }
		</InspectorControls>
	);
}

/**
 * Build the `edit` for one block name.
 *
 * @param {string} name The block's name.
 * @return {Function} A block edit component.
 */
export function serverRenderedEdit( name ) {
	return function Edit( { attributes, setAttributes } ) {
		return (
			<>
				<CopyInspector
					name={ name }
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
				<div { ...useBlockProps() }>
					<ServerSideRender
						block={ name }
						attributes={ attributes }
					/>
				</div>
			</>
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
