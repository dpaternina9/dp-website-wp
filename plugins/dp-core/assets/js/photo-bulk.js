/**
 * "Add photos in bulk", on the Photos screen.
 *
 * Three steps: core's media modal to choose the images (several at once,
 * uploading allowed), the small dialog `DP\Core\Photos\BulkScreen` printed to
 * choose a trip, topics and a status, then one request to `dp/v1/photos/bulk`.
 * The list reloads afterwards, so the new photos are simply there.
 *
 * Everything this reads is in the markup — the route's path, the words, the
 * terms as real form controls — so there is no inline script and no copy in
 * this file. `wp.apiFetch` adds core's REST nonce; the route checks the
 * capabilities again itself.
 *
 * @since 1.2.0
 */

/*
 * Quick Edit's Trip select (`DP\\Core\\Photos\\AdminList::quick_edit_trip()`).
 * Core fills its own fields from the row's hidden inline data when the panel
 * opens; this does the same for the trip, from the `.dp_trip` value the row
 * carries, so the select starts on the photo's current trip.
 */
( function () {
	'use strict';

	const inlineEdit = window.inlineEditPost;

	if ( ! inlineEdit || 'function' !== typeof inlineEdit.edit ) {
		return;
	}

	const edit = inlineEdit.edit;

	inlineEdit.edit = function ( id ) {
		const opened = edit.apply( this, arguments );
		const postId = 'object' === typeof id ? this.getId( id ) : id;
		const row = document.getElementById( 'inline_' + postId );
		const current = row && row.querySelector( '.dp_trip' );
		const select = document.querySelector(
			'#edit-' + postId + ' select[name="dp_quick_trip"]'
		);

		if ( select ) {
			select.value = current ? current.textContent.trim() : '0';
		}

		return opened;
	};
} )();

( function () {
	'use strict';

	const root = document.querySelector( '[data-dp-photo-bulk]' );

	if ( ! root || ! window.wp || ! window.wp.media || ! window.wp.apiFetch ) {
		return;
	}

	const opener = root.querySelector( '.dp-photo-bulk-open' );
	const dialog = root.querySelector( '.dp-photo-bulk-dialog' );
	const form = root.querySelector( '.dp-photo-bulk-form' );
	const chosen = root.querySelector( '.dp-photo-bulk-chosen' );
	const result = root.querySelector( '.dp-photo-bulk-result' );
	const submit = form.querySelector( 'button[value="create"]' );

	/** The attachment IDs picked in the modal. */
	let picked = [];

	/*
	 * Beside core's "Add Photo". The button is printed in the footer, because
	 * there is no hook in the heading; moving it is the only DOM change here.
	 */
	const anchor = document.querySelector( '.wrap .page-title-action' );

	if ( anchor ) {
		anchor.after( opener );
	} else {
		root.before( opener );
	}

	root.hidden = false;

	const frame = window.wp.media( {
		title: root.dataset.modalTitle,
		button: { text: root.dataset.modalButton },
		library: { type: 'image' },
		multiple: 'add',
	} );

	frame.on( 'select', function () {
		picked = frame
			.state()
			.get( 'selection' )
			.map( function ( attachment ) {
				return attachment.id;
			} );

		if ( ! picked.length ) {
			return;
		}

		chosen.textContent = (
			picked.length === 1
				? root.dataset.chosenOne
				: root.dataset.chosenMany
		).replace( '%d', String( picked.length ) );
		result.textContent = '';
		submit.disabled = false;
		dialog.showModal();
	} );

	opener.addEventListener( 'click', function () {
		frame.open();
	} );

	form.addEventListener( 'submit', function ( event ) {
		if ( ! event.submitter || event.submitter.value !== 'create' ) {
			return;
		}

		// Keep the dialog open while the request runs; it closes on success.
		event.preventDefault();

		const data = new window.FormData( form );

		submit.disabled = true;
		result.textContent = root.dataset.working;

		window.wp
			.apiFetch( {
				path: root.dataset.path,
				method: 'POST',
				data: {
					attachments: picked,
					trip: Number( data.get( 'trip' ) ) || 0,
					topics: data.getAll( 'topics' ).map( Number ),
					status: data.get( 'status' ) || 'draft',
				},
			} )
			.then( function ( response ) {
				result.textContent = response.summary;
				window.setTimeout( function () {
					window.location.reload();
				}, 1200 );
			} )
			.catch( function ( error ) {
				submit.disabled = false;
				result.textContent =
					( error && error.message ) || root.dataset.failed;
			} );
	} );
} )();
