/**
 * The Trip panel on a photo: one trip, chosen from a list.
 *
 * Core draws every taxonomy's sidebar panel through `editor.PostTaxonomyType`.
 * For a flat taxonomy that is a token field, which happily takes any number of
 * terms — and a photo was taken on one trip. This replaces the panel for
 * `dp_trip` alone with a single choice, and leaves every other taxonomy's panel
 * exactly as core draws it.
 *
 * The control is the first of three things that say "one trip"; the server
 * refuses a save carrying two (`DP\Core\Photos\OneTrip`) and the list table's
 * "Set trip…" replaces rather than adds. A photo that arrived with two by some
 * other route shows a note here and the first of them selected, and choosing
 * saves exactly one — nothing is dropped until David picks.
 *
 * A new trip can be added from here, because the moment you notice a trip is
 * missing is the moment you are filing a photo under it.
 *
 * WordPress dependencies
 */
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * The taxonomy this control stands in for.
 *
 * @type {string}
 */
export const TRIP_TAXONOMY = 'dp_trip';

/**
 * The filter namespace, so the replacement can be found and removed.
 *
 * @type {string}
 */
export const TRIP_FILTER = 'dp-core/photos/trip-select';

/**
 * The options the select offers: "no trip", then every trip by name.
 *
 * @param {Array|null} terms The trips, as REST returns them.
 * @return {Array<{value: string, label: string}>} The options.
 */
export function tripOptions( terms ) {
	return [
		{ value: '0', label: __( '— No trip —', 'dp-core' ) },
		...( terms ?? [] ).map( ( term ) => ( {
			value: String( term.id ),
			label: decodeEntities( term.name ?? '' ),
		} ) ),
	];
}

/**
 * What the post's `dp_trip` attribute becomes when an option is chosen.
 *
 * @param {string|number} value The select's value.
 * @return {number[]} One trip, or none.
 */
export function tripValue( value ) {
	const id = Number( value ) || 0;

	return id > 0 ? [ id ] : [];
}

/**
 * The panel body.
 *
 * @return {JSX.Element} The control.
 */
export function TripSelect() {
	const { terms, chosen } = useSelect(
		( select ) => ( {
			terms: select( coreStore ).getEntityRecords(
				'taxonomy',
				TRIP_TAXONOMY,
				{
					per_page: -1,
					orderby: 'name',
					order: 'asc',
					_fields: 'id,name',
					context: 'view',
				}
			),
			chosen:
				select( editorStore ).getEditedPostAttribute( TRIP_TAXONOMY ) ??
				[],
		} ),
		[]
	);

	const { editPost } = useDispatch( editorStore );
	const { saveEntityRecord } = useDispatch( coreStore );
	const [ name, setName ] = useState( '' );
	const [ adding, setAdding ] = useState( false );

	const add = async () => {
		const trimmed = name.trim();

		if ( ! trimmed || ! saveEntityRecord ) {
			return;
		}

		setAdding( true );

		try {
			const term = await saveEntityRecord( 'taxonomy', TRIP_TAXONOMY, {
				name: trimmed,
			} );

			if ( term?.id ) {
				editPost( { [ TRIP_TAXONOMY ]: [ term.id ] } );
				setName( '' );
			}
		} finally {
			setAdding( false );
		}
	};

	return (
		<>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Trip', 'dp-core' ) }
				help={
					chosen.length > 1
						? sprintf(
								/* translators: %d: how many trips the photo is filed under. */
								__(
									'This photo is filed under %d trips. A photo belongs to one — choose it and save.',
									'dp-core'
								),
								chosen.length
						  )
						: __( 'A photo belongs to one trip.', 'dp-core' )
				}
				value={ String( chosen[ 0 ] ?? 0 ) }
				options={ tripOptions( terms ) }
				onChange={ ( value ) =>
					editPost( { [ TRIP_TAXONOMY ]: tripValue( value ) } )
				}
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'New trip', 'dp-core' ) }
				value={ name }
				onChange={ setName }
			/>
			<Button
				variant="secondary"
				onClick={ add }
				disabled={ adding || '' === name.trim() }
			>
				{ __( 'Add and choose it', 'dp-core' ) }
			</Button>
		</>
	);
}

/**
 * Swap core's panel for this one, for trips only.
 *
 * @param {Function} Original The component core would draw.
 * @return {Function} A component that draws one or the other.
 */
export function withTripSelect( Original ) {
	return function TaxonomyPanel( props ) {
		if ( TRIP_TAXONOMY === props.slug ) {
			return <TripSelect />;
		}

		return <Original { ...props } />;
	};
}

/**
 * Attach the replacement.
 *
 * @return {void}
 */
export function registerTripSelect() {
	addFilter( 'editor.PostTaxonomyType', TRIP_FILTER, withTripSelect );
}
