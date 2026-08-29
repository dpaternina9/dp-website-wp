/**
 * Click-to-play for the Watch page.
 *
 * Everything this file does is an upgrade. Every card and the featured panel
 * ship as plain links to the video on Twitch or YouTube, so with JavaScript
 * switched off a press still watches the video — on its host, in this tab —
 * which is what CLAUDE.md section 1.7 requires. What the script adds is the
 * design's promise: "players load only when you press play". No iframe exists
 * anywhere in the document until a press, and the press that creates one is
 * the same press that would have navigated.
 *
 * The embed URL is built here rather than on the server for one honest
 * reason: Twitch's player refuses to load without a `parent` naming the
 * embedding hostname, and the page's hostname is the browser's to know.
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	/**
	 * The embed URL for one pressed link.
	 *
	 * @param {string} kind What to embed: twitch-vod, twitch-live, or youtube.
	 * @param {string} ref  The VOD id, video id, or channel login.
	 * @return {string} The URL, or '' when the link should just navigate.
	 */
	function embedUrl( kind, ref ) {
		/*
		 * Autoplay is the point of press-to-play — the reader pressed play —
		 * but under prefers-reduced-motion the player waits for a second,
		 * in-player press instead of bursting into motion on the first.
		 */
		const still = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;
		const parent = window.location.hostname;

		if ( 'youtube' === kind ) {
			return (
				'https://www.youtube-nocookie.com/embed/' +
				encodeURIComponent( ref ) +
				( still ? '' : '?autoplay=1' )
			);
		}

		if ( 'twitch-vod' === kind ) {
			return (
				'https://player.twitch.tv/?video=' +
				encodeURIComponent( ref ) +
				'&parent=' +
				encodeURIComponent( parent ) +
				'&autoplay=' +
				( still ? 'false' : 'true' )
			);
		}

		if ( 'twitch-live' === kind ) {
			return (
				'https://player.twitch.tv/?channel=' +
				encodeURIComponent( ref ) +
				'&parent=' +
				encodeURIComponent( parent ) +
				'&autoplay=' +
				( still ? 'false' : 'true' )
			);
		}

		return '';
	}

	/**
	 * Swap a pressed card's media area for its player.
	 *
	 * @param {HTMLAnchorElement} link The pressed play link.
	 * @return {boolean} Whether the player took over.
	 */
	function play( link ) {
		const kind = link.getAttribute( 'data-dp-embed' ) || '';
		const ref = link.getAttribute( 'data-dp-ref' ) || '';
		const url = embedUrl( kind, ref );

		const host = link.closest( '.dp-vg-card, .dp-watch-featured' );
		const media = host ? host.querySelector( '.dp-vg-media' ) : null;

		if ( ! url || ! media ) {
			return false;
		}

		const frame = document.createElement( 'iframe' );

		frame.className = 'dp-vg-player';
		frame.src = url;
		frame.title = link.getAttribute( 'data-dp-title' ) || link.textContent;
		frame.allow =
			'autoplay; fullscreen; encrypted-media; picture-in-picture';
		frame.setAttribute( 'allowfullscreen', '' );

		media.replaceChildren( frame );
		media.classList.add( 'is-playing' );

		// The control the reader pressed is gone from under them; the player
		// is where their focus belongs now.
		frame.focus();

		return true;
	}

	document.addEventListener( 'click', function ( event ) {
		const link =
			event.target instanceof Element
				? event.target.closest( 'a[data-dp-embed]' )
				: null;

		// Modified presses keep their meaning: open on the host, in a new tab.
		if (
			! link ||
			event.defaultPrevented ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey ||
			0 !== event.button
		) {
			return;
		}

		if ( play( link ) ) {
			event.preventDefault();
		}
	} );
} )();
