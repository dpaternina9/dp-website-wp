/**
 * The label on a code block.
 *
 * design-source/components/PostBlocks.dc.html wraps every code block in a
 * labelled dark surface, with the label defaulting to "SHELL". The label is
 * therefore data, not decoration — but core/code has nowhere to put it.
 *
 * It is added here as an attribute with no `source`, which means WordPress
 * serialises it into the block's HTML comment and never into the block's
 * markup:
 *
 *     <!-- wp:code {"dpLabel":"WP-CLI"} -->
 *     <pre class="wp-block-code"><code>…</code></pre>
 *     <!-- /wp:code -->
 *
 * That distinction is the whole reason this is safe. The saved markup is byte
 * for byte what core's own save() produces, so deactivating this plugin cannot
 * invalidate a single existing code block — it only stops the label being
 * shown, and DP\Core\Blocks\CodeLabel stops adding the attribute the theme's
 * CSS reads. The theme falls back to "SHELL" on its own.
 *
 * WordPress dependencies
 */
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * The block the label belongs to.
 *
 * @type {string}
 */
export const TARGET_BLOCK = 'core/code';

/**
 * The label a code block carries when nobody has set one.
 *
 * Kept in step with the same default in DP\Core\Blocks\CodeLabel and with the
 * theme's assets/css/blocks.css fallback.
 *
 * @type {string}
 */
export const DEFAULT_CODE_LABEL = 'SHELL';

/**
 * Declare the attribute on core/code.
 *
 * @param {Object} settings The block's settings.
 * @param {string} name     The block's name.
 * @return {Object} The settings, with the attribute added for core/code.
 */
export function addLabelAttribute( settings, name ) {
	if ( TARGET_BLOCK !== name ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dpLabel: {
				type: 'string',
				default: DEFAULT_CODE_LABEL,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'dp/code-label/attribute',
	addLabelAttribute
);

addFilter(
	'editor.BlockEdit',
	'dp/code-label/control',
	createHigherOrderComponent(
		( BlockEdit ) => ( props ) => {
			if ( TARGET_BLOCK !== props.name ) {
				return <BlockEdit { ...props } />;
			}

			return (
				<Fragment>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __( 'Label', 'dp-core' ) }
							initialOpen
						>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Code label', 'dp-core' ) }
								help={ __(
									'Shown above the code, in mono caps. SHELL, WP-CLI, SWIFTUI.',
									'dp-core'
								) }
								value={ props.attributes.dpLabel ?? '' }
								onChange={ ( dpLabel ) =>
									props.setAttributes( { dpLabel } )
								}
							/>
						</PanelBody>
					</InspectorControls>
				</Fragment>
			);
		},
		'withDpCodeLabelControl'
	)
);

addFilter(
	'editor.BlockListBlock',
	'dp/code-label/preview',
	createHigherOrderComponent(
		( BlockListBlock ) => ( props ) => {
			if ( TARGET_BLOCK !== props.name ) {
				return <BlockListBlock { ...props } />;
			}

			return (
				<BlockListBlock
					{ ...props }
					wrapperProps={ {
						...props.wrapperProps,
						'data-dp-label':
							props.attributes.dpLabel ?? DEFAULT_CODE_LABEL,
					} }
				/>
			);
		},
		'withDpCodeLabelPreview'
	)
);
