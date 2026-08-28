/**
 * The six controls a field gets when a binding cannot express it.
 *
 * `DP\Core\Editor\Control` decides which field gets which and says why. All six
 * share a shape: they read and write one registered meta key through the entity
 * record, they draw a real `<label>` through `@wordpress/components` — so unlike
 * the bound paragraphs they need no separate label block — and they validate
 * nothing, because every field keeps the `sanitize_callback` it was registered
 * with.
 *
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useMetaField } from './use-meta';
import { MONTHS, toDecimal, toParts } from './year';

/**
 * The months, named by the reader's own locale.
 *
 * `timeZone: 'UTC'` is not decoration. The dates below are built in UTC, and a
 * formatter left to the browser's own zone renders midnight on 1 January as the
 * previous 31 December for every reader west of Greenwich — which put December
 * at the top of the list and named every month as the one before it.
 *
 * @return {Array<{value: string, label: string}>} The twelve months, 1-based.
 */
function monthOptions() {
	const format = new Intl.DateTimeFormat( undefined, {
		month: 'long',
		timeZone: 'UTC',
	} );

	return Array.from( { length: MONTHS }, ( ignored, index ) => ( {
		value: String( index + 1 ),
		label: format.format( new Date( Date.UTC( 2020, index, 1 ) ) ),
	} ) );
}

/**
 * The name a referenced post is offered under.
 *
 * @param {Object} record A post record with `id` and `title.rendered`.
 * @return {string} Its title, or its ID when it has none.
 */
function referenceName( record ) {
	return (
		decodeEntities( record?.title?.rendered ?? '' ) ||
		sprintf(
			/* translators: %d: a post ID, shown when the post has no title. */
			__( 'Untitled (#%d)', 'dp-core' ),
			record?.id ?? 0
		)
	);
}

/**
 * The choices a post picker offers.
 *
 * The post already referenced goes at the front if the search that produced the
 * list did not return it, so a choice made before the list grew is still shown
 * by name rather than dropping back to a number.
 *
 * @param {Array|null}  records The posts the current query returned.
 * @param {Object|null} current The post already referenced, if there is one.
 * @return {Array<{value: string, label: string}>} The options, "none" first.
 */
export function referenceOptions( records, current ) {
	const found = ( records ?? [] ).map( ( record ) => ( {
		value: String( record.id ),
		label: referenceName( record ),
	} ) );

	if (
		current &&
		! found.some( ( option ) => option.value === String( current.id ) )
	) {
		found.unshift( {
			value: String( current.id ),
			label: referenceName( current ),
		} );
	}

	return [ { value: '0', label: __( '— none —', 'dp-core' ) }, ...found ];
}

/**
 * The ID a picker's answer stores.
 *
 * Zero is the field's registered default and its "no reference" value, so
 * anything that is not a post ID becomes zero rather than NaN.
 *
 * @param {string|number} value The picker's value.
 * @return {number} A post ID, or 0.
 */
export function toPostId( value ) {
	return Number( value ) || 0;
}

/**
 * A multi-line field. Line breaks are the value, so this is a textarea.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function TextEdit( { attributes, context } ) {
	const { metaKey, label, help, monospace } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);

	return (
		<div { ...useBlockProps() }>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				rows={ monospace ? 8 : 4 }
				className={ monospace ? 'dp-meta-text-monospace' : undefined }
				value={ 'string' === typeof value ? value : '' }
				onChange={ setValue }
			/>
		</div>
	);
}

/**
 * A point on the timeline, as a year and a month.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function YearEdit( { attributes, context } ) {
	const { metaKey, label, help } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);
	const parts = toParts( value );

	return (
		<div { ...useBlockProps() }>
			<div className="dp-meta-year-parts">
				<TextControl
					__nextHasNoMarginBottom
					type="number"
					step="1"
					label={ label }
					value={ parts.year }
					onChange={ ( year ) =>
						setValue( toDecimal( year, parts.month ) )
					}
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ sprintf(
						/* translators: %s: the name of the field, e.g. "Started". */
						__( '%s — month', 'dp-core' ),
						label
					) }
					value={ String( parts.month ) }
					options={ monthOptions() }
					disabled={ '' === parts.year }
					onChange={ ( month ) =>
						setValue( toDecimal( parts.year, month ) )
					}
				/>
			</div>
			{ help ? (
				<span className="dp-meta-control-help">{ help }</span>
			) : null }
		</div>
	);
}

/**
 * A field that accepts a closed set of values.
 *
 * The options are the field's own registered `enum`, handed down from
 * `Tone::meta_values()` or `VideoSource::meta_values()` through the post type's
 * template. Nothing here restates them.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function ChoiceEdit( { attributes, context } ) {
	const { metaKey, label, help, options } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);

	return (
		<div { ...useBlockProps() }>
			<SelectControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				value={ 'string' === typeof value ? value : '' }
				options={ Array.isArray( options ) ? options : [] }
				onChange={ setValue }
			/>
		</div>
	);
}

/**
 * A field that is either on or off.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function FlagEdit( { attributes, context } ) {
	const { metaKey, label, help } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);

	return (
		<div { ...useBlockProps() }>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				checked={ Boolean( value ) }
				onChange={ setValue }
			/>
		</div>
	);
}

/**
 * A field holding several short strings, in order.
 *
 * A fieldset rather than a set of loose inputs, so the field's name is announced
 * once and each row is numbered inside it.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function LinesEdit( { attributes, context } ) {
	const { metaKey, label, help } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);
	const lines = Array.isArray( value ) ? value : [];

	return (
		<div { ...useBlockProps() }>
			<fieldset>
				<legend className="dp-field-label-name">{ label }</legend>
				{ lines.map( ( line, index ) => (
					<div
						className="dp-meta-lines-row"
						key={ `${ metaKey }-${ index }` }
					>
						<TextControl
							__nextHasNoMarginBottom
							hideLabelFromVision
							label={ sprintf(
								/* translators: 1: the name of the field, 2: the row's position in the list. */
								__( '%1$s, item %2$d', 'dp-core' ),
								label,
								index + 1
							) }
							value={ line }
							onChange={ ( next ) =>
								setValue(
									lines.map( ( existing, at ) =>
										at === index ? next : existing
									)
								)
							}
						/>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								setValue(
									lines.filter(
										( ignored, at ) => at !== index
									)
								)
							}
						>
							{ sprintf(
								/* translators: %d: the row's position in the list. */
								__( 'Remove item %d', 'dp-core' ),
								index + 1
							) }
						</Button>
					</div>
				) ) }
				<Button
					variant="secondary"
					onClick={ () => setValue( [ ...lines, '' ] ) }
				>
					{ __( 'Add an item', 'dp-core' ) }
				</Button>
				{ help ? (
					<span className="dp-meta-control-help">{ help }</span>
				) : null }
			</fieldset>
		</div>
	);
}

/**
 * A field holding the ID of another post, chosen by name.
 *
 * This is the control the phase exists for. `dp_ship` attaches to the role it
 * came from through `dp_role_id`, and until now the only way to set it was to
 * find a role's post ID and type the number into WordPress's raw custom fields
 * table.
 *
 * The list is fetched by name as it is typed rather than all at once, and the
 * post already referenced is fetched separately and put at the front, so a
 * choice made before the list grew is still shown by name rather than as a
 * number.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    The post the block is in.
 * @return {JSX.Element} The editor element tree.
 */
export function ReferenceEdit( { attributes, context } ) {
	const { metaKey, label, help, reference } = attributes;
	const [ value, setValue ] = useMetaField(
		metaKey,
		context?.postType,
		context?.postId
	);
	const [ search, setSearch ] = useState( '' );
	const chosen = Number( value ) || 0;

	const { records, current } = useSelect(
		( select ) => {
			const { getEntityRecords, getEntityRecord } = select( coreStore );

			if ( ! reference ) {
				return { records: null, current: null };
			}

			const query = {
				per_page: 50,
				orderby: 'title',
				order: 'asc',
				_fields: 'id,title',
			};

			if ( search ) {
				query.search = search;
			}

			return {
				records: getEntityRecords( 'postType', reference, query ),
				current:
					chosen > 0
						? getEntityRecord( 'postType', reference, chosen, {
								_fields: 'id,title',
						  } )
						: null,
			};
		},
		[ reference, search, chosen ]
	);

	const options = useMemo(
		() => referenceOptions( records, current ),
		[ records, current ]
	);

	return (
		<div { ...useBlockProps() }>
			<ComboboxControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				help={ help }
				value={ String( chosen ) }
				options={ options }
				onFilterValueChange={ setSearch }
				onChange={ ( next ) => setValue( toPostId( next ) ) }
			/>
		</div>
	);
}
