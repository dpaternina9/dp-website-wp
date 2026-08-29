/**
 * The two fields a page carries, in the document sidebar.
 *
 * A page is the one type in this content model that is not solved by a locked
 * form in the canvas. The other three carry no prose at all — a Role is its
 * fields — so a form is the whole of what their canvas can be. A page has real
 * body content, and a locked template would take that away; an unlocked one
 * would leave two fields floating in the middle of the page David is writing,
 * which the first Return key would break apart.
 *
 * So the two fields go in the sidebar, which is where a document-level property
 * of a page belongs and is what the sidebar is for. David's objection to the
 * sidebar was eighteen fields in a column that narrow. Two is not eighteen.
 *
 * **The panel is generated from the REST schema**, not written out. The labels
 * and the help text are the `title` and `description` `DP\Core\Content\Meta`
 * registered, fetched the same way `core-data` fetches them for the bindings UI:
 * an OPTIONS request against the post type's own route. A third field added to
 * `page_fields()` therefore appears here with no change to this file, and there
 * is no second copy of a label to go stale.
 *
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	SelectControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getPlugin, registerPlugin } from '@wordpress/plugins';

/**
 * The plugin name the panel is registered under.
 *
 * @type {string}
 */
export const PANEL_NAME = 'dp-core-page-fields';

/**
 * The post type whose fields this panel draws.
 *
 * @type {string}
 */
export const PANEL_POST_TYPE = 'page';

/**
 * The prefix every field this plugin registers carries.
 *
 * @type {string}
 */
const PREFIX = 'dp_';

/**
 * Pull this plugin's fields out of a post type's REST schema.
 *
 * @param {Object} schema The `meta` property of a post type's item schema.
 * @return {Array<{key: string, title: string, description: string, type: string, options: string[]}>} The fields, in declaration order.
 */
export function fieldsFromSchema( schema ) {
	return Object.entries( schema?.properties ?? {} )
		.filter( ( [ key ] ) => key.startsWith( PREFIX ) )
		.map( ( [ key, property ] ) => ( {
			key,
			title: property.title || key,
			description: property.description || '',
			type: property.type || 'string',
			options: Array.isArray( property.enum ) ? property.enum : [],
		} ) );
}

/**
 * One field's control.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    The field, as `fieldsFromSchema` describes it.
 * @param {*}        props.value    The current value.
 * @param {Function} props.onChange Setter for it.
 * @return {JSX.Element} The control.
 */
function FieldControl( { field, value, onChange } ) {
	if ( 'boolean' === field.type ) {
		return (
			<ToggleControl
				__nextHasNoMarginBottom
				label={ field.title }
				help={ field.description }
				checked={ Boolean( value ) }
				onChange={ onChange }
			/>
		);
	}

	if ( field.options.length > 0 ) {
		return (
			<SelectControl
				__nextHasNoMarginBottom
				label={ field.title }
				help={ field.description }
				value={ 'string' === typeof value ? value : '' }
				options={ field.options.map( ( option ) => ( {
					value: option,
					label:
						'' === option ? __( '— not set —', 'dp-core' ) : option,
				} ) ) }
				onChange={ onChange }
			/>
		);
	}

	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.title }
			help={ field.description }
			rows={ 3 }
			value={ 'string' === typeof value ? value : '' }
			onChange={ onChange }
		/>
	);
}

/**
 * The panel itself.
 *
 * @return {JSX.Element|null} The panel, or null on any screen that is not a page.
 */
export function PageFieldsPanel() {
	const { postType, postId, restBase, restNamespace } = useSelect(
		( select ) => {
			const type = select( editorStore ).getCurrentPostType();
			const definition = type
				? select( coreStore ).getPostType( type )
				: null;

			return {
				postType: type,
				postId: select( editorStore ).getCurrentPostId(),
				restBase: definition?.rest_base ?? '',
				restNamespace: definition?.rest_namespace ?? 'wp/v2',
			};
		},
		[]
	);

	const [ fields, setFields ] = useState( [] );

	useEffect( () => {
		if ( PANEL_POST_TYPE !== postType || '' === restBase ) {
			return;
		}

		let live = true;

		apiFetch( {
			path: `${ restNamespace }/${ restBase }/?context=edit`,
			method: 'OPTIONS',
		} )
			.then( ( response ) => {
				if ( live ) {
					setFields(
						fieldsFromSchema(
							response?.schema?.properties?.meta ?? {}
						)
					);
				}
			} )
			.catch( () => {} );

		return () => {
			live = false;
		};
	}, [ postType, restBase, restNamespace ] );

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	if ( PANEL_POST_TYPE !== postType || 0 === fields.length ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name={ PANEL_NAME }
			title={ __( 'Page fields', 'dp-core' ) }
		>
			{ fields.map( ( field ) => (
				<FieldControl
					key={ field.key }
					field={ field }
					value={ meta?.[ field.key ] }
					onChange={ ( next ) =>
						setMeta( { ...meta, [ field.key ]: next } )
					}
				/>
			) ) }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Attach the panel to the post editor.
 *
 * Registering twice is an error, and the bundle this lives in is loaded on every
 * block editor screen, so the guard is not decoration.
 *
 * @return {boolean} Whether it was registered by this call.
 */
export function registerPageFieldsPanel() {
	if ( getPlugin( PANEL_NAME ) ) {
		return false;
	}

	registerPlugin( PANEL_NAME, { render: PageFieldsPanel } );

	return true;
}
