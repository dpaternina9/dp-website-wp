/**
 * The Watch page's critical path.
 *
 * Three promises, in the order the design makes them.
 *
 * **The grid renders from content.** The featured panel is the newest archived
 * video — this environment is never live, and the live entry the fixture
 * publishes must not render — and every other entry is a card below it.
 *
 * **Nothing loads from Twitch or YouTube before a press.** This is the page's
 * privacy and layout property, not a nicety: the server renders cached
 * thumbnails (or its own glow art) and plain links, so a reader who never
 * presses play is never seen by either host. The spec listens to the page's
 * own network traffic to hold it.
 *
 * **The player is the press.** No iframe exists in the document until a card
 * or the featured button is pressed, and the pressed element is a real link to
 * the video on its host — which is the whole no-JavaScript story, so the
 * no-iframe-on-load assertion covers the scripts-off reader too.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { focusRing, tabTo } from './front-end';
import { SHARED_VIDEOS, sharedWatchPageUrl } from './global-setup';

/** The hosts a Watch page may not touch until a player is asked for. */
const VIDEO_HOSTS =
	/twitch\.tv|ytimg\.com|youtube\.com|youtube-nocookie\.com|jtvnw\.net/;

test.describe( 'the Watch page', () => {
	/** The page's URL, filled in once per worker. */
	let watchPage = '';

	test.beforeAll( async ( { requestUtils } ) => {
		watchPage = await sharedWatchPageUrl( requestUtils );
	} );

	test( 'renders the featured panel, the grid and the gear from content', async ( {
		page,
	} ) => {
		await page.goto( watchPage );

		// The hero is the page's own title and deck.
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Watch.' } )
		).toBeVisible();
		await expect(
			page.getByText( 'Not live at the moment.' ).first()
		).toBeVisible();

		// Not live, so the newest archived video is the panel, wearing LATEST.
		const featured = page.locator( '.dp-watch-featured' );

		await expect( featured ).toHaveCount( 1 );
		await expect( featured ).toContainText( SHARED_VIDEOS.featured.title );
		await expect( featured ).toContainText( 'Latest on Twitch' );
		await expect( featured ).not.toHaveClass( /is-live/ );

		// The featured video is not repeated below; the other entry is a card.
		const cards = page.locator( '.dp-vg-card' );

		await expect( cards ).toHaveCount( 1 );
		await expect( cards ).toContainText( SHARED_VIDEOS.unlinked.title );

		// The live entry renders nowhere while nothing says the channel is live.
		await expect( page.getByText( SHARED_VIDEOS.live.title ) ).toHaveCount(
			0
		);

		// The gear list is the page's own content, from the theme's pattern.
		await expect(
			page.getByRole( 'heading', {
				level: 2,
				name: 'What the stream runs on',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { level: 3, name: 'Desk' } )
		).toBeVisible();
	} );

	/*
	 * The gear list's geometry, which two separate defects reached at once.
	 *
	 * The grid is a `core/group` asking for the default layout, so WordPress
	 * renders it `is-layout-flow` and emits a 24px `margin-block-start` on every
	 * child but the first; only the stylesheet makes the container a grid. In a
	 * grid that margin lands on the items rather than between stacked rows, so
	 * the first column rode 24px above the rest of its row. The same leak inside
	 * `.dp-gear-group` added 24px to a 4px gap between gear items.
	 *
	 * And the design's own `minmax(min(300px, 100%), 1fr)` resolves to three
	 * columns in the 1056px the container gives, which for four groups is a full
	 * row and an orphan. Both are the kind of defect that returns silently, and
	 * both are visible only as a measurement, so both are measured here.
	 */
	test( 'draws the four gear groups two by two, with the row aligned', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1440, height: 1200 } );
		await page.goto( watchPage );

		const groups = page.locator( '.dp-gear > .dp-gear-group' );

		await expect( groups ).toHaveCount( 4 );

		const boxes = await groups.evaluateAll( ( nodes ) =>
			nodes.map( ( node ) => ( {
				top: Math.round( node.getBoundingClientRect().top ),
				left: Math.round( node.getBoundingClientRect().left ),
				margin: window.getComputedStyle( node ).marginBlockStart,
			} ) )
		);

		// Two columns and two rows: the two-by-two block, not three and an orphan.
		expect( new Set( boxes.map( ( box ) => box.left ) ).size ).toBe( 2 );
		expect( new Set( boxes.map( ( box ) => box.top ) ).size ).toBe( 2 );

		// The regression itself: the pair on a row starts at the same height.
		expect( boxes[ 0 ].top ).toBe( boxes[ 1 ].top );
		expect( boxes[ 2 ].top ).toBe( boxes[ 3 ].top );

		// And it stays true because nothing leaks core's flow margin in, at
		// either level — the grid's own `gap` is the only spacing here.
		expect( boxes.map( ( box ) => box.margin ) ).toEqual( [
			'0px',
			'0px',
			'0px',
			'0px',
		] );

		const items = await page
			.locator( '.dp-gear-group > .dp-gear-item' )
			.evaluateAll( ( nodes ) =>
				nodes.map(
					( node ) => getComputedStyle( node ).marginBlockStart
				)
			);

		expect( items.length ).toBeGreaterThan( 0 );
		expect( [ ...new Set( items ) ] ).toEqual( [ '0px' ] );
	} );

	test( 'talks to no video host and holds no player until play is pressed', async ( {
		page,
	} ) => {
		const offsite: string[] = [];

		page.on( 'request', ( request ) => {
			if ( VIDEO_HOSTS.test( request.url() ) ) {
				offsite.push( request.url() );
			}
		} );

		await page.goto( watchPage );

		// The whole document: no player anywhere, not a hidden one either.
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );

		// With the page fully rendered, neither host has seen this reader.
		expect( offsite ).toEqual( [] );

		// The press is a real link to the video on its host — the reader with
		// scripts off follows it, which with the assertions above is the whole
		// no-JavaScript story.
		const play = page.locator( '.dp-watch-play' );

		await expect( play ).toHaveAttribute(
			'href',
			`https://www.twitch.tv/videos/${ SHARED_VIDEOS.featured.ref }`
		);

		// With scripts on, the same press swaps the player in instead.
		await play.click();

		const player = page.locator( 'iframe.dp-vg-player' );

		await expect( player ).toHaveCount( 1 );

		const src = await player.getAttribute( 'src' );

		expect( src ).toContain( 'player.twitch.tv' );
		expect( src ).toContain( `video=${ SHARED_VIDEOS.featured.ref }` );
		expect( src ).toContain( 'parent=localhost' );

		// The player replaces the art; it does not stack a second media area.
		await expect(
			page.locator( '.dp-watch-featured .dp-vg-art' )
		).toHaveCount( 0 );

		// And the player is where focus went, so a keyboard reader is not lost.
		await expect( player ).toBeFocused();
	} );

	test( 'a video with no identifier is visibly unlinked, not clickable', async ( {
		page,
	} ) => {
		await page.goto( watchPage );

		const card = page.locator( '.dp-vg-card', {
			hasText: SHARED_VIDEOS.unlinked.title,
		} );

		await expect(
			card.locator( 'span.dp-vg-link.is-unlinked' )
		).toHaveText( 'Watch on YouTube' );
		await expect( card.locator( 'a' ) ).toHaveCount( 0 );
	} );

	test( 'the press is keyboard operable, and visibly focused', async ( {
		page,
	} ) => {
		await page.goto( watchPage );

		/*
		 * Tabbed to, not `.focus()`ed. The ring in `base.css` is
		 * `:focus-visible` only, which Chromium does not match on a link that
		 * was focused programmatically — so the old `.focus()` here proved the
		 * press worked but could never have caught a missing ring. Tabbing
		 * proves the control is reachable as well.
		 */
		await tabTo( page, '.dp-watch-play' );

		// WCAG 2.4.7. A control that swaps the page's largest element for a
		// player is the worst place to lose the ring.
		expect( await focusRing( page, '.dp-watch-play' ) ).toMatch(
			/^solid [1-9]/
		);

		await page.keyboard.press( 'Enter' );

		await expect( page.locator( 'iframe.dp-vg-player' ) ).toHaveCount( 1 );
	} );
} );
