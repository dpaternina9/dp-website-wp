/**
 * Phase 7 acceptance: the contact form's three states, in a browser.
 *
 * The design draws one card with three faces — `showForm`, `sent`, `failed` —
 * and the only place all three can be seen is here, because which one appears
 * is decided by a POST and rendered by the server.
 *
 * Half of this file runs with **scripting disabled**. CLAUDE.md §1.7: "every
 * page must be readable and navigable with JS off", and a contact form is the
 * page where that promise is easiest to break and hardest to notice. The form
 * has no action attribute and posts to the page it is on, so with no JavaScript
 * at all it still sends, still comes back, and still says what happened. The
 * `fetch` upgrade is tested separately, with scripting on, and the assertion
 * there is not "it worked" but "the document never changed" — because that is
 * the only thing the upgrade adds.
 *
 * Two waits are deliberate and neither is a sleep-until-it-passes. The form
 * carries a signed timestamp and refuses anything submitted inside three
 * seconds, so the successful path has to spend those three seconds, and the
 * refused path has to *not* spend them. That is the gate, seen from outside.
 *
 * The suite establishes its own content. :8889 is a fixture, not a preview, and
 * `composer test:integration` reinstalls WordPress into the same database.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { focusRing, tabTo } from './front-end';

/** The slug this fixture owns. Nothing outside this list is ever deleted. */
const SLUG = 'contact-fixture-say-hello';

/** What David called the page. Deliberately not `contact`. */
const TITLE = 'Contact fixture — say hello';

/**
 * The rate limiter allows three messages per sender per ten minutes, and every
 * Playwright run shares one address. A value that changes per run gives each
 * run its own counter, so the limiter stays live without the third run of the
 * morning failing on a gate that is working. `tests/support/mu-plugins/`
 * explains the other half.
 */
const SENDER = `e2e-${ Date.now() }-${ Math.random()
	.toString( 36 )
	.slice( 2 ) }`;

/** The one header that makes that true, set on every context below. */
const RUN_HEADERS = { 'X-DP-Test-Sender': SENDER };

/** How long the form must be on screen before the server believes a person filled it in. */
const HUMAN_PAUSE = 3500;

/** The URL the fixture produced, filled in by `beforeAll`. */
let contactUrl = '';

/** The shape of the two REST fields this spec reads back. */
type Created = { id: number; link: string };

/**
 * Delete everything carrying this fixture's slug, and nothing else.
 *
 * @param requestUtils The suite's REST client.
 */
async function removeFixture(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	requestUtils: any
): Promise< void > {
	const found: Created[] = await requestUtils.rest( {
		path: '/wp/v2/pages',
		params: { slug: SLUG, per_page: 100, status: 'any' },
	} );

	for ( const page of found ) {
		await requestUtils.rest( {
			path: `/wp/v2/pages/${ page.id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}
}

test.describe( 'The contact form', () => {
	/*
	 * Serial, against the grain of the rest of the suite. Every test here
	 * shares one page, and `beforeAll` runs once per worker, so under
	 * `fullyParallel` two workers would race to create the same slug.
	 */
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );

		const page = await requestUtils.rest< Created >( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: TITLE,
				slug: SLUG,
				status: 'publish',
				// The admin stores a block theme's custom template under its
				// slug, without the extension. Assigning it the way the admin
				// does is the whole point of doing it through REST.
				template: 'dp-contact',
				content:
					'<!-- wp:paragraph --><p>Agency work, product conversations, or a note about espresso.</p><!-- /wp:paragraph -->',
			},
		} );

		contactUrl = page.link;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await removeFixture( requestUtils );
	} );

	test.describe( 'with JavaScript switched off', () => {
		test.use( {
			javaScriptEnabled: false,
			extraHTTPHeaders: RUN_HEADERS,
		} );

		test( 'draws the form, with the honeypot out of sight', async ( {
			page,
		} ) => {
			await page.goto( contactUrl );

			const card = page.locator( '[data-dp-contact-state]' );

			await expect( card ).toHaveAttribute(
				'data-dp-contact-state',
				'form'
			);
			await expect(
				page.getByRole( 'heading', { name: 'Send a note' } )
			).toBeVisible();

			/*
			 * The trap has to be in the document — that is what a form filler
			 * fills — and out of sight of anybody using the form as intended.
			 * `aria-hidden` and `tabindex="-1"` are in the markup; being off
			 * screen is the stylesheet's job, and this is the assertion that
			 * catches its going missing.
			 */
			const honeypot = page.locator( '.dp-hp input' );

			await expect( honeypot ).toHaveCount( 1 );
			await expect( honeypot ).not.toBeInViewport();
		} );

		test( 'sends a message and says it landed', async ( { page } ) => {
			await page.goto( contactUrl );

			// The form has to have been on screen long enough to have been typed into.
			await page.waitForTimeout( HUMAN_PAUSE );

			await page
				.locator( '#dp-contact-form-name' )
				.fill( 'Someone Reading' );
			await page
				.locator( '#dp-contact-form-email' )
				.fill( 'someone@example.com' );
			await page
				.locator( '#dp-contact-form-message' )
				.fill(
					'A note about espresso, sent with the scripts switched off.'
				);

			await page.getByRole( 'button', { name: 'Send it' } ).click();

			const card = page.locator( '[data-dp-contact-state]' );

			await expect( card ).toHaveAttribute(
				'data-dp-contact-state',
				'sent'
			);
			await expect(
				page.getByText( 'It landed. Thanks.' )
			).toBeVisible();
			await expect( card ).toHaveAttribute( 'role', 'status' );
		} );

		test( 'refuses a form submitted faster than a person could type it', async ( {
			page,
		} ) => {
			await page.goto( contactUrl );

			// Deliberately no pause: this is the timing gate, seen from outside.
			await page
				.locator( '#dp-contact-form-name' )
				.fill( 'Not A Person' );
			await page
				.locator( '#dp-contact-form-email' )
				.fill( 'bot@example.com' );
			await page.locator( '#dp-contact-form-message' ).fill( 'Instant.' );

			await page.getByRole( 'button', { name: 'Send it' } ).click();

			const card = page.locator( '[data-dp-contact-state]' );

			await expect( card ).toHaveAttribute(
				'data-dp-contact-state',
				'failed'
			);
			await expect(
				page.getByText( 'That did not send.' )
			).toBeVisible();
			await expect( card ).toHaveAttribute( 'role', 'alert' );
		} );

		test( 'keeps the message in the form after a refusal', async ( {
			page,
		} ) => {
			await page.goto( contactUrl );

			await page.locator( '#dp-contact-form-name' ).fill( 'Still Here' );
			await page
				.locator( '#dp-contact-form-email' )
				.fill( 'still@example.com' );
			await page
				.locator( '#dp-contact-form-message' )
				.fill( 'This sentence must survive.' );

			await page.getByRole( 'button', { name: 'Send it' } ).click();

			/*
			 * The design's failure copy promises "your message is still in the
			 * form". The retry carries the three typed fields as hidden inputs
			 * behind a fresh nonce and a fresh stamp.
			 */
			await expect(
				page.locator( 'input[name="dp_contact_message"]' )
			).toHaveValue( 'This sentence must survive.' );
			await expect(
				page.locator( 'input[name="dp_contact_name"]' )
			).toHaveValue( 'Still Here' );
			await expect(
				page.getByRole( 'button', { name: 'Try again' } )
			).toBeVisible();
		} );
	} );

	test.describe( 'with JavaScript on', () => {
		test.use( { extraHTTPHeaders: RUN_HEADERS } );

		test( 'answers in place, without the document changing', async ( {
			page,
		} ) => {
			await page.goto( contactUrl );
			await page.waitForTimeout( HUMAN_PAUSE );

			/*
			 * A value on `window` is the cheapest possible proof that the
			 * upgrade did what it claims. A full page load — which is what the
			 * plain path does, and what a broken `fetch` would fall back to —
			 * takes it with it.
			 */
			await page.evaluate( () => {
				( window as unknown as Record< string, unknown > ).dpStayed =
					true;
			} );

			await page
				.locator( '#dp-contact-form-name' )
				.fill( 'Someone Reading' );
			await page
				.locator( '#dp-contact-form-email' )
				.fill( 'someone@example.com' );
			await page
				.locator( '#dp-contact-form-message' )
				.fill( 'A note about espresso, sent the enhanced way.' );

			await page.getByRole( 'button', { name: 'Send it' } ).click();

			await expect(
				page.locator( '[data-dp-contact-state="sent"]' )
			).toBeVisible();

			expect(
				await page.evaluate(
					() =>
						( window as unknown as Record< string, unknown > )
							.dpStayed
				)
			).toBe( true );
		} );

		test( 'moves focus to the answer', async ( { page } ) => {
			await page.goto( contactUrl );

			// No pause, so this one is refused — the panel still has to be announced.
			await page
				.locator( '#dp-contact-form-name' )
				.fill( 'Not A Person' );
			await page
				.locator( '#dp-contact-form-email' )
				.fill( 'bot@example.com' );
			await page.locator( '#dp-contact-form-message' ).fill( 'Instant.' );

			await page.getByRole( 'button', { name: 'Send it' } ).click();

			const card = page.locator( '[data-dp-contact-state="failed"]' );

			await expect( card ).toBeVisible();
			expect(
				await card.evaluate(
					( element ) =>
						element === element.ownerDocument.activeElement
				)
			).toBe( true );
		} );
	} );

	/*
	 * Its own sender, not `RUN_HEADERS`. This test spends a successful send,
	 * the limiter allows three per sender per ten minutes, and two of those are
	 * already spoken for above — sharing the counter would leave the suite with
	 * no margin at all, which is how a green gate becomes an intermittent one.
	 */
	test.describe( 'from the keyboard alone', () => {
		test.use( {
			extraHTTPHeaders: { 'X-DP-Test-Sender': `${ SENDER }-keyboard` },
		} );

		test( 'sends, and rings every stop on the way', async ( { page } ) => {
			await page.goto( contactUrl );
			await page.waitForTimeout( HUMAN_PAUSE );

			/*
			 * No clicks anywhere, and every stop reached by Tab rather than by
			 * `.focus()`. The form is the site's one write path a reader can
			 * reach, so "operable without a pointer" (WCAG 2.1.1) and "the
			 * focus is visible" (2.4.7) are checked on the way through rather
			 * than asserted about the stylesheet. Each `tabTo` carries on from
			 * where the last one stopped, so this also proves the three fields
			 * and the button come in that order.
			 */
			const stops: Array< [ string, string ] > = [
				[ '#dp-contact-form-name', 'Typed By Keyboard' ],
				[ '#dp-contact-form-email', 'keys@example.com' ],
				[ '#dp-contact-form-message', 'Sent without a mouse.' ],
				[ 'button.dp-contact-submit', '' ],
			];

			for ( const [ field, value ] of stops ) {
				await tabTo( page, field );

				// `--border-width-strong solid --focus-ring`, as base.css draws
				// it. A width of 0 or a style of none is a stop with no ring.
				expect(
					await focusRing( page, field ),
					`"${ field }" is focused but paints no ring`
				).toMatch( /^solid [1-9]/ );

				if ( '' !== value ) {
					await page.keyboard.type( value );
				}
			}

			await page.keyboard.press( 'Enter' );

			await expect(
				page.locator( '[data-dp-contact-state="sent"]' )
			).toBeVisible();
		} );
	} );
} );
