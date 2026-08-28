/**
 * Phase E acceptance: every registered field has a control, in a browser.
 *
 * The integration suite proves the form covers every registered field and that
 * a binding resolves. Neither can answer the question the phase is judged on —
 * whether David can open a Shipped thing and fill it in — because that is the
 * editor's answer, and the editor only exists in a browser. Three things are
 * only true here:
 *
 * - `register_post_type()`'s `template` is applied by JavaScript, and only while
 *   the post is an `auto-draft`. Nothing in PHP can tell you it arrived.
 * - A `core/post-meta` binding is editable only if core says it is, and core
 *   says no for reasons that are invisible from the server — a query context, a
 *   template, or the Custom Fields panel being switched on.
 * - `inserter: false` is enforced by the inserter.
 *
 * External dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/*
 * One worker for this file. Both tests publish a `dp_ship`, which is a row on
 * the chart every other spec shares (ADR-0013), so they are created and removed
 * here rather than left behind — and serially, so the removal in `afterAll`
 * cannot run while the other test is still writing.
 */
test.describe.configure( { mode: 'serial' } );

/** What the REST API hands back for anything this file creates. */
type Created = { id: number };

/**
 * The bound paragraph a label names.
 *
 * @param editor The suite's editor utils.
 * @param label  The label's text.
 * @return The paragraph immediately after that label.
 */
function theFieldUnder(
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	editor: any,
	label: string
) {
	return editor.canvas
		.locator( '[data-type="dp/field-label"]' )
		.filter( { hasText: label } )
		.locator( 'xpath=following-sibling::*[1]' );
}

/**
 * The shipped things this file saved, so it can take them away again.
 *
 * **They are drafts and stay drafts.** A published `dp_ship` is a bar on the
 * chart every other spec reads, and one attached to a role turns that role from
 * a bare lane into a lane with something under it — which is precisely what
 * `timeline.spec.ts` filters on. ADR-0013's rule is that content a global query
 * reads belongs to nobody; a draft is read by this file and by nothing else.
 */
const drafts: number[] = [];

/**
 * The blocks a `dp_ship` opens with, in order.
 *
 * Eighteen fields: ten bound paragraphs with a label each, and eight controls.
 * Written out rather than derived, because the point of the assertion is that
 * the canvas agrees with a list somebody can read.
 */
const SHIP_FORM = [
	'dp/meta-reference',
	'dp/meta-year',
	'dp/meta-year',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/meta-text',
	'dp/field-label',
	'core/paragraph',
	'dp/meta-lines',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/meta-text',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/field-label',
	'core/paragraph',
	'dp/meta-flag',
	'dp/meta-reference',
] as const;

test.afterAll( async ( { requestUtils } ) => {
	for ( const id of drafts ) {
		await requestUtils.rest( {
			path: `/wp/v2/dp_ship/${ id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}

	drafts.length = 0;
} );

test.describe( 'a shipped thing', () => {
	test( 'opens as a locked form with a control for every field', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost( {
			postType: 'dp_ship',
			title: 'E2E fields — a shipped thing',
		} );

		const blocks = await editor.getBlocks();

		expect( blocks.map( ( block ) => block.name ) ).toEqual( [
			...SHIP_FORM,
		] );

		// Every label the form places names a field, and is visible above it.
		await expect(
			editor.canvas.getByText( 'Headline', { exact: true } )
		).toBeVisible();

		// The controls draw their own, through a real <label>.
		await expect(
			editor.canvas.getByLabel( 'Role it came from', { exact: true } )
		).toBeVisible();
		await expect(
			editor.canvas.getByLabel( 'Featured on a work card', {
				exact: true,
			} )
		).toBeVisible();
		await expect(
			editor.canvas.getByLabel( 'Artifact', { exact: true } )
		).toBeVisible();
	} );

	test( 'saves what is typed into it, and attaches a role by name', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const roles = await requestUtils.rest< Created[] >( {
			path: '/wp/v2/dp_role',
			params: { per_page: 1, orderby: 'title', order: 'asc' },
		} );

		expect( roles.length ).toBeGreaterThan( 0 );

		await admin.createNewPost( {
			postType: 'dp_ship',
			title: 'E2E fields — filled in by hand',
		} );

		/*
		 * A bound paragraph, found through the label in front of it. Core names a
		 * bound rich text after the meta key while it is empty and "Paragraph"
		 * once it is not, so the label block is the only stable handle on it —
		 * which is the same reason the label block exists at all.
		 */
		await theFieldUnder( editor, 'Headline' ).click();
		await page.keyboard.type( 'Typed into the canvas' );

		// The picker. No post ID is typed anywhere.
		const picker = editor.canvas.getByRole( 'combobox', {
			name: 'Role it came from',
		} );

		await picker.click();
		await editor.canvas
			.getByRole( 'option' )
			.filter( { hasNotText: '— none —' } )
			.first()
			.click();

		await editor.saveDraft();

		const postId = Number(
			new URL( page.url() ).searchParams.get( 'post' )
		);

		expect( postId ).toBeGreaterThan( 0 );

		drafts.push( postId );

		const saved = await requestUtils.rest< {
			content: { raw: string };
			meta: { dp_headline: string; dp_role_id: number };
		} >( {
			path: `/wp/v2/dp_ship/${ postId }`,
			params: { context: 'edit' },
		} );

		expect( saved.meta.dp_headline ).toBe( 'Typed into the canvas' );
		expect( saved.meta.dp_role_id ).toBeGreaterThan( 0 );

		// The binding is the only copy: the paragraph in the markup stays empty.
		expect( saved.content.raw ).not.toContain( 'Typed into the canvas' );

		// And it comes back. The value is in meta, so the form has to fetch it.
		await admin.editPost( postId );

		await expect( theFieldUnder( editor, 'Headline' ) ).toHaveText(
			'Typed into the canvas'
		);
		await expect(
			editor.canvas.getByRole( 'combobox', {
				name: 'Role it came from',
			} )
		).not.toHaveValue( '— none —' );
	} );
} );

test( 'a page keeps its canvas and gets its fields in the sidebar', async ( {
	admin,
	editor,
	page,
	requestUtils,
} ) => {
	const created = await requestUtils.rest< Created >( {
		path: '/wp/v2/pages',
		method: 'POST',
		data: { title: 'E2E fields — a page with a deck', status: 'draft' },
	} );

	try {
		await admin.editPost( created.id );

		// The canvas is the page's, not a form: it opens empty and unlocked.
		expect( await editor.getBlocks() ).toEqual( [] );

		const panel = page.getByRole( 'button', { name: 'Page fields' } );

		await expect( panel ).toBeVisible();

		if ( 'false' === ( await panel.getAttribute( 'aria-expanded' ) ) ) {
			await panel.click();
		}

		const deck = page.getByLabel( 'Deck', { exact: true } );

		await expect( deck ).toBeVisible();
		await deck.fill( 'A deck typed into the sidebar.' );

		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Save draft' } )
			.click();
		await page
			.getByRole( 'button', { name: 'Dismiss this notice' } )
			.waitFor();

		const saved = await requestUtils.rest< { meta: { dp_lead: string } } >(
			{
				path: `/wp/v2/pages/${ created.id }`,
				params: { context: 'edit' },
			}
		);

		expect( saved.meta.dp_lead ).toBe( 'A deck typed into the sidebar.' );
	} finally {
		await requestUtils.rest( {
			path: `/wp/v2/pages/${ created.id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	}
} );

test( 'none of the form blocks can be put into a post', async ( {
	admin,
	page,
} ) => {
	await admin.createNewPost( { title: 'E2E fields — an ordinary post' } );

	await page.getByLabel( 'Block Inserter', { exact: true } ).click();

	const search = page.getByPlaceholder( 'Search' );

	for ( const term of [ 'Field label', 'Field:' ] ) {
		await search.fill( term );

		await expect(
			page.getByRole( 'option', { name: /^Field/ } )
		).toHaveCount( 0 );
	}
} );
