/**
 * The photo's own panels in the document sidebar: Related post and Camera.
 *
 * A photo is the one custom type whose canvas is prose — its story — so like a
 * page (`fields/page-panel.js`) its fields go in the sidebar rather than in a
 * locked form. There are two, and only one of them is a field:
 *
 * - **Related post** writes `dp_photo_related_post`, with the same searchable
 *   post picker a shipped thing's write-up uses (`referenceOptions`,
 *   `toPostId`). The page prints "Read the story →" only while the post it
 *   names is published.
 * - **Camera** writes nothing. It shows what the page will print under the
 *   photo — worked out from the featured image's EXIF, so ADR-0018 requires it
 *   be visible here — read from the attachment's `dp_camera` property, which the
 *   server formats with the same code the page uses. When the image records
 *   when it was taken, the panel offers that as the publish date. It never
 *   applies it: the date changes only when David presses the button.
 *
 * **The save notice.** WordPress will not save a post whose title, excerpt
 * and content are all empty — `isEditedPostSaveable()` — and an imported photo
 * is exactly that, by design: no title is shown as no title. So on a photo with
 * unsaved changes and nothing WordPress counts as content, the notice bar above
 * the canvas says so and offers to save or publish it directly through the
 * entity record.
 *
 * WordPress dependencies
 */
import { Button, ComboboxControl } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { getPlugin, registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import { referenceOptions, toPostId } from '../fields/controls';
import { useMetaField } from '../fields/use-meta';

/**
 * The plugin name the panels are registered under.
 *
 * @type {string}
 */
export const PHOTO_PANEL_NAME = 'dp-core-photo-fields';

/**
 * The post type the panels belong to.
 *
 * @type {string}
 */
export const PHOTO_POST_TYPE = 'dp_photo';

/**
 * The related-post field.
 *
 * @type {string}
 */
export const RELATED_POST_KEY = 'dp_photo_related_post';

/**
 * The labels the Camera panel lists, in the order the page prints them.
 *
 * @type {Array<{key: string, label: string}>}
 */
export const CAMERA_PARTS = [
	{ key: 'camera', label: __( 'Camera', 'dp-core' ) },
	{ key: 'focal_length', label: __( 'Focal length', 'dp-core' ) },
	{ key: 'aperture', label: __( 'Aperture', 'dp-core' ) },
	{ key: 'shutter', label: __( 'Shutter', 'dp-core' ) },
	{ key: 'iso', label: __( 'ISO', 'dp-core' ) },
];

/**
 * The value the editor's `date` attribute takes for a taken-at time.
 *
 * `dp_camera.taken` is `Y-m-d H:i:s` in the camera's own wall-clock time, and
 * a post's `date` is the site's wall-clock time with a `T` in it — so this is
 * a change of separator and nothing else. No time zone arithmetic: the camera
 * did not record a zone.
 *
 * @param {?string} taken The `taken` value, or null.
 * @return {?string} The date, or null.
 */
export function publishDateFrom( taken ) {
	if ( ! taken || ! /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test( taken ) ) {
		return null;
	}

	return taken.replace( ' ', 'T' );
}

/**
 * A taken-at time, as the panel says it.
 *
 * @param {string} taken `Y-m-d H:i:s`.
 * @return {string} A readable date and time.
 */
function readable( taken ) {
	const [ day, time ] = taken.split( ' ' );
	const [ year, month, date ] = day.split( '-' ).map( Number );
	const [ hours, minutes ] = time.split( ':' ).map( Number );

	return new Intl.DateTimeFormat( undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
		timeZone: 'UTC',
	} ).format( new Date( Date.UTC( year, month - 1, date, hours, minutes ) ) );
}

/**
 * The Related post panel body.
 *
 * @param {Object} props          Component props.
 * @param {string} props.postType The post type being edited.
 * @param {number} props.postId   The post being edited.
 * @return {JSX.Element} The picker.
 */
function RelatedPost( { postType, postId } ) {
	const [ value, setValue ] = useMetaField(
		RELATED_POST_KEY,
		postType,
		postId
	);
	const [ search, setSearch ] = useState( '' );
	const chosen = Number( value ) || 0;

	const { records, current } = useSelect(
		( select ) => {
			const { getEntityRecords, getEntityRecord } = select( coreStore );
			const query = {
				per_page: 50,
				orderby: 'date',
				order: 'desc',
				status: 'publish',
				_fields: 'id,title',
			};

			if ( search ) {
				query.search = search;
			}

			return {
				records: getEntityRecords( 'postType', 'post', query ),
				current:
					chosen > 0
						? getEntityRecord( 'postType', 'post', chosen, {
								_fields: 'id,title,status',
						  } )
						: null,
			};
		},
		[ search, chosen ]
	);

	const options = useMemo(
		() => referenceOptions( records, current, 'post' ),
		[ records, current ]
	);

	return (
		<ComboboxControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Related post', 'dp-core' ) }
			help={ __(
				'The pop-up links to it as "Read the story". Only a published post is linked.',
				'dp-core'
			) }
			value={ String( chosen ) }
			options={ options }
			onFilterValueChange={ setSearch }
			onChange={ ( next ) => setValue( toPostId( next ) ) }
		/>
	);
}

/**
 * The Camera panel body.
 *
 * @return {JSX.Element} What the camera recorded, and the date offer.
 */
function Camera() {
	const { camera, date, hasImage } = useSelect( ( select ) => {
		const media =
			select( editorStore ).getEditedPostAttribute( 'featured_media' );

		return {
			hasImage: media > 0,
			camera:
				media > 0
					? select( coreStore ).getEntityRecord(
							'postType',
							'attachment',
							media
					  )?.dp_camera
					: null,
			date: select( editorStore ).getEditedPostAttribute( 'date' ),
		};
	}, [] );

	const { editPost } = useDispatch( editorStore );

	if ( ! hasImage ) {
		return (
			<p>
				{ __( 'Set the photo (the featured image) first.', 'dp-core' ) }
			</p>
		);
	}

	const parts = CAMERA_PARTS.filter(
		( part ) => '' !== ( camera?.parts?.[ part.key ] ?? '' )
	);
	const taken = publishDateFrom( camera?.taken ?? null );

	return (
		<>
			{ parts.length === 0 ? (
				<p>
					{ __(
						'This image carries no camera data, so the pop-up prints no camera line.',
						'dp-core'
					) }
				</p>
			) : (
				<>
					<dl className="dp-photo-camera">
						{ parts.map( ( part ) => (
							<div key={ part.key }>
								<dt>{ part.label }</dt>
								<dd>{ camera.parts[ part.key ] }</dd>
							</div>
						) ) }
					</dl>
					<p>
						{ __( 'The pop-up prints:', 'dp-core' ) }{ ' ' }
						<code>{ camera.line }</code>
					</p>
				</>
			) }
			{ taken && (
				<>
					<p>
						{ sprintf(
							/* translators: %s: when the photo was taken. */
							__( 'Taken %s.', 'dp-core' ),
							readable( camera.taken )
						) }
					</p>
					{ date?.slice( 0, 19 ) === taken ? (
						<p>{ __( 'This is the publish date.', 'dp-core' ) }</p>
					) : (
						<Button
							variant="secondary"
							onClick={ () => editPost( { date: taken } ) }
						>
							{ __( 'Use as publish date', 'dp-core' ) }
						</Button>
					) }
				</>
			) }
		</>
	);
}

/**
 * The id of the save notice, so there is only ever one.
 *
 * @type {string}
 */
const SAVE_NOTICE_ID = 'dp-photo-save';

/**
 * The save notice, for a photo WordPress's own Save button refuses.
 *
 * It lives in the editor's notice bar above the canvas rather than in a sidebar
 * panel: a panel can be collapsed, or hidden behind the Block tab or a closed
 * sidebar, and then a photo has no way to save and nothing saying why. The
 * notice is there whenever the photo has unsaved changes and nothing WordPress
 * counts as content, and goes as soon as either stops being true.
 *
 * @param {Object} props          Component props.
 * @param {string} props.postType The post type being edited.
 * @param {number} props.postId   The post being edited.
 * @return {null} Renders nothing itself.
 */
function SaveNotice( { postType, postId } ) {
	const { refused, status } = useSelect( ( select ) => {
		const editor = select( editorStore );

		return {
			refused:
				! editor.isEditedPostSaveable() &&
				editor.isEditedPostDirty() &&
				! editor.isSavingPost(),
			status: editor.getEditedPostAttribute( 'status' ),
		};
	}, [] );

	const { editPost } = useDispatch( editorStore );
	const { saveEditedEntityRecord } = useDispatch( coreStore );
	const { createNotice, removeNotice } = useDispatch( noticesStore );

	useEffect( () => {
		if ( ! refused ) {
			removeNotice( SAVE_NOTICE_ID );
			return;
		}

		const save = () =>
			saveEditedEntityRecord( 'postType', postType, postId );
		const actions = [
			{ label: __( 'Save photo', 'dp-core' ), onClick: save },
		];

		if ( 'publish' !== status ) {
			actions.push( {
				label: __( 'Publish photo', 'dp-core' ),
				onClick: () => {
					editPost( { status: 'publish' } );
					save();
				},
			} );
		}

		createNotice(
			'info',
			__(
				'This photo has no title, excerpt or story, so WordPress switches its own Save button off. That is fine — the page prints nothing for them. Save it here instead.',
				'dp-core'
			),
			{ id: SAVE_NOTICE_ID, isDismissible: false, actions }
		);
	}, [
		refused,
		status,
		postType,
		postId,
		createNotice,
		removeNotice,
		editPost,
		saveEditedEntityRecord,
	] );

	useEffect( () => () => removeNotice( SAVE_NOTICE_ID ), [ removeNotice ] );

	return null;
}

/**
 * The panels, on a photo and nowhere else.
 *
 * @return {?JSX.Element} The panels, or null on any other screen.
 */
export function PhotoPanels() {
	const { postType, postId } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			postId: select( editorStore ).getCurrentPostId(),
		} ),
		[]
	);

	if ( PHOTO_POST_TYPE !== postType ) {
		return null;
	}

	return (
		<>
			<SaveNotice postType={ postType } postId={ postId } />
			<PluginDocumentSettingPanel
				name={ `${ PHOTO_PANEL_NAME }-photo` }
				title={ __( 'Photo', 'dp-core' ) }
			>
				<RelatedPost postType={ postType } postId={ postId } />
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name={ `${ PHOTO_PANEL_NAME }-camera` }
				title={ __( 'Camera', 'dp-core' ) }
			>
				<Camera />
			</PluginDocumentSettingPanel>
		</>
	);
}

/**
 * Attach the panels to the post editor.
 *
 * @return {boolean} Whether they were registered by this call.
 */
export function registerPhotoPanels() {
	if ( getPlugin( PHOTO_PANEL_NAME ) ) {
		return false;
	}

	registerPlugin( PHOTO_PANEL_NAME, { render: PhotoPanels } );

	return true;
}
