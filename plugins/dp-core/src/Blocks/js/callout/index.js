/**
 * The dP editor bundle.
 *
 * It is registered as `dp/callout`'s editorScript, which is what gets it
 * enqueued: WordPress loads every registered block's editor script on every
 * block-editor screen, so one entry point covers the block, the two house style
 * extensions that have no block of their own, and the editor previews for the
 * three blocks this plugin renders entirely on the server.
 *
 * Nothing in here runs on the front end. The callout is a static block and the
 * house style is CSS, so a published page loads no JavaScript from this plugin
 * at all (CLAUDE.md §1.7).
 *
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import '../house-style/code-label';
import { evaluateHouseLimits } from '../house-style/limits';
import { installHouseLimitWarnings } from '../house-style/limits-notices';
import { registerServerRenderedBlocks } from '../dynamic/server-rendered';

registerBlockType( metadata.name, { edit, save } );

registerServerRenderedBlocks();

installHouseLimitWarnings( evaluateHouseLimits );
