/**
 * Surfaces the house limits in the editor as dismissible warnings.
 *
 * Warnings, never blocks: nothing here prevents inserting a block, saving, or
 * publishing. A notice appears when a limit is first exceeded and disappears
 * when it stops being exceeded, so dismissing one does not put it back on the
 * next keystroke.
 *
 * WordPress dependencies
 */
import { dispatch, select, subscribe } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Prefix for every notice this module owns, so it only ever removes its own.
 *
 * @type {string}
 */
const NOTICE_PREFIX = 'dp-house-limit-';

/**
 * The wording for one finding.
 *
 * @param {{id: string, count: number, limit: number}} finding One exceeded limit.
 * @return {string} A translated sentence.
 */
export function messageFor( finding ) {
	const { id, count, limit } = finding;

	switch ( id ) {
		case 'quotes':
			return sprintf(
				/* translators: 1: number of quotes in the post, 2: the house limit. */
				__(
					'The house style allows %2$d quotes per post; this one has %1$d. Nothing stops you — but a third quote usually wants to be a paragraph.',
					'dp-core'
				),
				count,
				limit
			);
		case 'list-items':
			return sprintf(
				/* translators: 1: number of items in the longest list, 2: the house limit. */
				__(
					'A list runs to %1$d items. The house style stops at %2$d — longer than that is a table.',
					'dp-core'
				),
				count,
				limit
			);
		case 'code-lines':
			return sprintf(
				/* translators: 1: number of lines in the longest code block, 2: the house limit. */
				__(
					'A code block runs to %1$d lines. The house style stops at %2$d — anything longer goes in a repo and gets a link.',
					'dp-core'
				),
				count,
				limit
			);
		case 'callouts':
			return sprintf(
				/* translators: 1: number of callouts in the post, 2: the house limit. */
				__(
					'This post has %1$d callouts. The house style allows %2$d — a callout is for a caveat, not for emphasis.',
					'dp-core'
				),
				count,
				limit
			);
		default:
			return sprintf(
				/* translators: 1: the limit name, 2: the current count, 3: the house limit. */
				__( '%1$s: %2$d, over the house limit of %3$d.', 'dp-core' ),
				id,
				count,
				limit
			);
	}
}

/**
 * Bring the editor's notices in line with the current findings.
 *
 * Exported for its own sake: it takes the registry it talks to, so a test can
 * hand it a double instead of a running editor.
 *
 * @param {Array<{id: string, count: number, limit: number}>}       findings Exceeded limits.
 * @param {Set<string>}                                             shown    Ids currently notified. Mutated.
 * @param {{createWarningNotice: Function, removeNotice: Function}} notices  The notices store actions.
 * @return {void}
 */
export function syncHouseLimitNotices( findings, shown, notices ) {
	const current = new Set( findings.map( ( finding ) => finding.id ) );

	for ( const id of Array.from( shown ) ) {
		if ( ! current.has( id ) ) {
			notices.removeNotice( NOTICE_PREFIX + id );
			shown.delete( id );
		}
	}

	for ( const finding of findings ) {
		if ( shown.has( finding.id ) ) {
			continue;
		}

		notices.createWarningNotice( messageFor( finding ), {
			id: NOTICE_PREFIX + finding.id,
			isDismissible: true,
		} );

		shown.add( finding.id );
	}
}

/**
 * Watch the editor and keep the warnings current.
 *
 * @param {Function} evaluate Takes a block tree, returns findings.
 * @return {Function} An unsubscribe callback.
 */
export function installHouseLimitWarnings( evaluate ) {
	const shown = new Set();
	let last = null;

	return subscribe( () => {
		const editor = select( 'core/block-editor' );

		if ( ! editor ) {
			return;
		}

		const blocks = editor.getBlocks();

		if ( blocks === last ) {
			return;
		}

		last = blocks;

		syncHouseLimitNotices(
			evaluate( blocks ),
			shown,
			dispatch( 'core/notices' )
		);
	} );
}
