<?php
/**
 * Integration tests for `?format=pdf` and the three answers behind it.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Resume;

use DP\Core\Resume\PdfRenderer;
use DP\Core\Resume\ResumePdf;
use WP_Post;

/**
 * The query variable, and the order of preference `docs/plan.md` section 7.1 fixes.
 *
 * Two claims are being tested and only one of them is about PDFs.
 *
 * **The query variable is not a route.** CLAUDE.md section 5.1 allows this
 * project two registered things and this is the second. It has to be inert
 * everywhere except a page carrying the `dp-resume` template — which means
 * inert on a page with no template, on a page with a *different* dp- template,
 * and on a post. If it is not, `?format=pdf` becomes a URL that means something
 * on pages David never assigned it to.
 *
 * **Nothing is ever an error.** The order is: the cached file, then a stale one,
 * then the print view. That last case is not a corner — David has no Cloudflare
 * credentials, so a site with no renderer configured is the site as shipped, and
 * the handler declining the request is what makes `print.css` the fallback.
 *
 * `serve()` ends in `exit`, so the tests stop one call short of it and assert on
 * `file()`, which is the decision. What is served is a `readfile()` of what
 * `file()` chose.
 */
final class ResumePdfTest extends ResumeTestCase {

	/**
	 * `format` survives into the public query variables.
	 *
	 * @return void
	 */
	public function test_the_query_variable_is_registered(): void {
		$pdf = new ResumePdf();

		$this->assertContains( ResumePdf::QUERY_VAR, $pdf->add_query_var( array( 'p', 'page_id' ) ) );
		$this->assertContains( 'page_id', $pdf->add_query_var( array( 'p', 'page_id' ) ) );
	}

	/**
	 * Registering it twice does not list it twice.
	 *
	 * @return void
	 */
	public function test_the_query_variable_is_registered_once(): void {
		$pdf  = new ResumePdf();
		$vars = $pdf->add_query_var( $pdf->add_query_var( array( 'p' ) ) );

		$this->assertCount( 1, array_keys( $vars, ResumePdf::QUERY_VAR, true ) );
	}

	/**
	 * WordPress itself carries the variable through, so the filter is attached.
	 *
	 * @return void
	 */
	public function test_wordpress_reads_the_query_variable(): void {
		$page = $this->seed_resume_page();

		$this->go_to( $this->download_url( $page ) );

		$this->assertSame( ResumePdf::FORMAT, get_query_var( ResumePdf::QUERY_VAR ) );
	}

	/**
	 * On the résumé page, the request is one this handler owns.
	 *
	 * @return void
	 */
	public function test_the_pdf_is_offered_on_a_page_carrying_the_template(): void {
		$page = $this->seed_resume_page();

		$this->go_to( $this->download_url( $page ) );

		$requested = ( new ResumePdf() )->requested_page();

		$this->assertInstanceOf( WP_Post::class, $requested );
		$this->assertSame( $page, $requested->ID );
	}

	/**
	 * Either spelling of the template is the same template.
	 *
	 * WordPress stores a block theme's custom template under its slug, but a page
	 * imported from elsewhere — or seeded by an older version of this code —
	 * carries the file name. Both mean `dp-resume`.
	 *
	 * @return void
	 */
	public function test_the_template_is_recognised_with_or_without_the_extension(): void {
		$page = $this->seed_resume_page( ResumePdf::TEMPLATE . '.html' );

		$this->go_to( $this->download_url( $page ) );

		$this->assertInstanceOf( WP_Post::class, ( new ResumePdf() )->requested_page() );
	}

	/**
	 * On anything else the variable is registered and ignored.
	 *
	 * @param string $template What `_wp_page_template` holds on the page visited.
	 * @return void
	 *
	 * @dataProvider provide_pages_that_are_not_the_resume
	 */
	public function test_the_query_variable_is_ignored_off_the_resume_template( string $template ): void {
		$page = $this->seed_resume_page( $template );

		$this->go_to( $this->download_url( $page ) );

		$this->assertSame( ResumePdf::FORMAT, get_query_var( ResumePdf::QUERY_VAR ) );
		$this->assertNull( ( new ResumePdf() )->requested_page() );
	}

	/**
	 * The pages `?format=pdf` must mean nothing on.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_pages_that_are_not_the_resume(): array {
		return array(
			'no template at all'         => array( '' ),
			'another of ours'            => array( 'dp-about' ),
			'another of ours, as a file' => array( 'dp-contact.html' ),
			'something else entirely'    => array( 'page-whatever.php' ),
		);
	}

	/**
	 * A post is not a page, whatever its meta says.
	 *
	 * @return void
	 */
	public function test_the_query_variable_is_ignored_on_a_post(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Not the résumé' ) );

		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, '_wp_page_template', ResumePdf::TEMPLATE );

		$this->go_to( $this->download_url( $post_id ) );

		$this->assertNull( ( new ResumePdf() )->requested_page() );
	}

	/**
	 * Without the variable, the page is just a page.
	 *
	 * @return void
	 */
	public function test_without_the_query_variable_nothing_is_claimed(): void {
		$page = $this->seed_resume_page();

		$this->go_to( (string) get_permalink( $page ) );

		$this->assertNull( ( new ResumePdf() )->requested_page() );
	}

	/**
	 * A `format` of something else is not this one.
	 *
	 * @return void
	 */
	public function test_another_format_is_not_claimed(): void {
		$page = $this->seed_resume_page();

		$this->go_to( add_query_arg( ResumePdf::QUERY_VAR, 'docx', (string) get_permalink( $page ) ) );

		$this->assertNull( ( new ResumePdf() )->requested_page() );
	}

	/**
	 * An unpublished résumé page is not downloadable.
	 *
	 * @return void
	 */
	public function test_a_draft_resume_page_is_not_served(): void {
		$page = $this->seed_resume_page( ResumePdf::TEMPLATE, 'draft' );

		$this->go_to( $this->download_url( $page ) );

		$this->assertNull( ( new ResumePdf() )->requested_page() );
	}

	/**
	 * With no renderer configured and nothing cached, the request falls through.
	 *
	 * This is the site as shipped: the handler declines, WordPress renders the
	 * page, and `print.css` is what the reader's own "save as PDF" uses.
	 *
	 * @return void
	 */
	public function test_with_no_renderer_and_no_cache_nothing_is_served(): void {
		$page = $this->seed_resume_page();

		$this->assertNull( ( new ResumePdf() )->renderer() );
		$this->assertNull( ( new ResumePdf() )->file( $this->post( $page ) ) );
	}

	/**
	 * With a renderer, the PDF is rendered once and then cached.
	 *
	 * @return void
	 */
	public function test_a_rendered_pdf_is_written_to_the_cache_and_reused(): void {
		$page     = $this->seed_resume_page();
		$renderer = $this->use_renderer( new StubRenderer() );

		$first = ( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertIsString( $first );
		$this->assertSame( 1, $renderer->calls() );
		$this->assertStringEqualsFile( $first, "%PDF-1.7\nstub\n%%EOF" );

		$second = ( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $renderer->calls(), 'the cached file should not have been re-rendered' );
	}

	/**
	 * The renderer is asked to print the page, not to fetch the PDF of it.
	 *
	 * @return void
	 */
	public function test_the_renderer_is_given_the_plain_permalink(): void {
		$page     = $this->seed_resume_page();
		$renderer = $this->use_renderer( new StubRenderer() );

		( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( array( (string) get_permalink( $page ) ), $renderer->rendered );
		$this->assertStringNotContainsString( ResumePdf::QUERY_VAR, $renderer->rendered[0] );
	}

	/**
	 * Editing a role re-renders it; editing nothing does not.
	 *
	 * @return void
	 */
	public function test_the_pdf_is_re_rendered_when_the_record_changes(): void {
		$page     = $this->seed_resume_page();
		$role     = $this->seed_role( 'Backbone Technology' );
		$renderer = $this->use_renderer( new StubRenderer() );

		( new ResumePdf() )->file( $this->post( $page ) );
		( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( 1, $renderer->calls() );

		$this->touch_post( $role, '2026-08-01 09:00:00' );

		( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( 2, $renderer->calls() );
	}

	/**
	 * A renderer that throws serves the stale copy rather than an error.
	 *
	 * `docs/plan.md` section 7.1 is explicit: "a stale cached PDF is always
	 * preferred over no PDF". This is that sentence.
	 *
	 * @return void
	 */
	public function test_a_stale_pdf_is_served_when_the_renderer_fails(): void {
		$page = $this->seed_resume_page();
		$role = $this->seed_role( 'Backbone Technology' );

		$working = $this->use_renderer( new StubRenderer( '%PDF-from-last-week' ) );
		$stale   = ( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertIsString( $stale );
		$this->assertSame( 1, $working->calls() );

		// The record moves on, and the renderer stops answering.
		$this->touch_post( $role, '2026-08-01 09:00:00' );

		$failing = $this->use_renderer( new StubRenderer( '', 'the browser service returned 503' ) );

		$served = ( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( 1, $failing->calls() );
		$this->assertSame( $stale, $served );
		$this->assertStringEqualsFile( $served, '%PDF-from-last-week' );
	}

	/**
	 * A failure is announced, because preferring a stale copy is silent by design.
	 *
	 * @return void
	 */
	public function test_a_failed_rendering_is_logged(): void {
		$page      = $this->seed_resume_page();
		$announced = array();

		add_action(
			'dp_core_resume_render_failed',
			static function ( mixed $message ) use ( &$announced ): void {
				$announced[] = is_string( $message ) ? $message : '';
			}
		);

		$this->use_renderer( new StubRenderer( '', 'the browser service returned 503' ) );

		( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertSame( array( 'the browser service returned 503' ), $announced );
	}

	/**
	 * With the renderer down and nothing ever cached, it still falls through.
	 *
	 * @return void
	 */
	public function test_a_failing_renderer_with_an_empty_cache_serves_nothing(): void {
		$page = $this->seed_resume_page();

		$this->use_renderer( new StubRenderer( '', 'no credentials' ) );

		$this->assertNull( ( new ResumePdf() )->file( $this->post( $page ) ) );
	}

	/**
	 * Whoever loses the render race is served the stale copy, not a second render.
	 *
	 * A cold cache plus a burst of requests would otherwise be a burst of
	 * renders, each costing an API call and a browser somewhere.
	 *
	 * @return void
	 */
	public function test_a_second_request_mid_render_does_not_render_again(): void {
		$page = $this->seed_resume_page();
		$role = $this->seed_role( 'Backbone Technology' );

		$first = $this->use_renderer( new StubRenderer( '%PDF-first' ) );

		$stale = ( new ResumePdf() )->file( $this->post( $page ) );

		$this->assertIsString( $stale );

		$this->touch_post( $role, '2026-08-01 09:00:00' );

		$pdf = new ResumePdf();
		$key = $this->cache->key( $page );

		// Somebody else got there first and is still rendering.
		set_transient( 'dp_resume_render_' . $key, 1, MINUTE_IN_SECONDS );

		$second = $this->use_renderer( new StubRenderer( '%PDF-second' ) );

		$served = $pdf->file( $this->post( $page ) );

		$this->assertSame( 0, $second->calls() );
		$this->assertSame( $stale, $served );
		unset( $first );
	}

	/**
	 * A filter returning something that is not a renderer means no renderer.
	 *
	 * @return void
	 */
	public function test_a_filter_that_does_not_return_a_renderer_disables_the_feature(): void {
		add_filter( 'dp_resume_pdf_renderer', static fn (): string => 'cloudflare' );

		$this->assertNull( ( new ResumePdf() )->renderer() );
	}

	/**
	 * The download URL is the page's own, plus the variable.
	 *
	 * @return void
	 */
	public function test_the_download_url_is_the_page_plus_the_query_variable(): void {
		$page = $this->seed_resume_page();
		$url  = ResumePdf::download_url( $page );

		$this->assertStringContainsString( ResumePdf::QUERY_VAR . '=' . ResumePdf::FORMAT, $url );
		$this->assertStringStartsWith( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ), $url );
	}

	/**
	 * The URL the renderer prints is the page, not the download.
	 *
	 * @return void
	 */
	public function test_the_print_url_is_the_permalink(): void {
		$page = $this->seed_resume_page();

		$this->assertSame(
			(string) get_permalink( $page ),
			( new ResumePdf() )->print_url( $this->post( $page ) )
		);
	}

	/**
	 * Point the feature at one renderer for the rest of this test.
	 *
	 * @param StubRenderer $renderer The stand-in.
	 * @return StubRenderer
	 */
	private function use_renderer( StubRenderer $renderer ): StubRenderer {
		remove_all_filters( 'dp_resume_pdf_renderer' );

		add_filter(
			'dp_resume_pdf_renderer',
			static fn (): PdfRenderer => $renderer
		);

		return $renderer;
	}

	/**
	 * A page as a post object, asserted to exist.
	 *
	 * @param int $page_id The page.
	 * @return WP_Post
	 */
	private function post( int $page_id ): WP_Post {
		$page = get_post( $page_id );

		$this->assertInstanceOf( WP_Post::class, $page );

		return $page;
	}

	/**
	 * The URL that asks for one page's PDF.
	 *
	 * Built with `add_query_arg()` rather than by concatenating a `?`, because
	 * the tests site has plain permalinks and a page's link already carries one.
	 *
	 * @param int $page_id The page.
	 * @return string
	 */
	private function download_url( int $page_id ): string {
		return add_query_arg( ResumePdf::QUERY_VAR, ResumePdf::FORMAT, (string) get_permalink( $page_id ) );
	}
}
