<?php
/**
 * `?format=pdf`, and everything behind it.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

use Throwable;
use WP_Post;

/**
 * The résumé's PDF: a registered query var, not a route.
 *
 * `CLAUDE.md` section 5.1 allows this project exactly two registered things,
 * and this is the second: `format`, read on `template_redirect` and acted on
 * **only** when the page being viewed carries the `dp-resume` custom template.
 * On every other page the query variable is registered and ignored. There is no
 * rewrite rule, no slug, and nothing here knows what David called the page — so
 * renaming or moving it moves the PDF with it, which is the whole reason the
 * plan chose a query variable over an endpoint.
 *
 * The order of preference when someone asks for the PDF is fixed by
 * `docs/plan.md` section 7.1 and is the part worth reading twice:
 *
 * 1. **The cached file for the current content.** Nothing renders; a file is
 *    served. This is the answer almost every time.
 * 2. **A stale cached file**, if the renderer cannot be reached or refuses. A
 *    résumé that is a week out of date is worth more to whoever is downloading
 *    it than an error page, and the plan says so outright.
 * 3. **The print view.** With no renderer configured and nothing ever cached,
 *    this handler declines the request and WordPress renders the résumé page as
 *    normal — where `print.css` is what the reader's own "save as PDF" uses,
 *    and what the renderer would have printed. The feature degrades to the
 *    thing it is built on rather than to a failure.
 *
 * That third case is not a corner: David has no Cloudflare credentials yet, so
 * it is the behaviour of the site as shipped.
 */
final class ResumePdf {

	/**
	 * The query variable. The only one this project registers.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'format';

	/**
	 * The value that asks for a PDF.
	 *
	 * @var string
	 */
	public const FORMAT = 'pdf';

	/**
	 * The custom template a page must carry for the query variable to mean anything.
	 *
	 * @var string
	 */
	public const TEMPLATE = 'dp-resume';

	/**
	 * Prefix on the transient that stops two requests rendering at once.
	 *
	 * @var string
	 */
	private const LOCK = 'dp_resume_render_';

	/**
	 * Constructor.
	 *
	 * @param PdfCache $cache Where renderings are kept.
	 * @param Log      $log   Where a failure is recorded.
	 */
	public function __construct(
		private readonly PdfCache $cache = new PdfCache(),
		private readonly Log $log = new Log()
	) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'query_vars', $this->add_query_var( ... ) );
		add_action( 'template_redirect', $this->maybe_serve( ... ), 1 );
	}

	/**
	 * Register `format`.
	 *
	 * @param mixed $vars The public query variables.
	 * @return list<string>
	 */
	public function add_query_var( mixed $vars ): array {
		$registered = array();

		if ( is_array( $vars ) ) {
			foreach ( $vars as $var ) {
				if ( is_string( $var ) ) {
					$registered[] = $var;
				}
			}
		}

		if ( ! in_array( self::QUERY_VAR, $registered, true ) ) {
			$registered[] = self::QUERY_VAR;
		}

		return $registered;
	}

	/**
	 * Serve the PDF, if this request is asking for one and may have it.
	 *
	 * @return void
	 */
	public function maybe_serve(): void {
		$page = $this->requested_page();

		if ( ! $page instanceof WP_Post ) {
			return;
		}

		$file = $this->file( $page );

		if ( null === $file ) {
			// Nothing to serve: fall through to the print view rather than error.
			return;
		}

		$this->serve( $file, $this->filename( $page ) );
	}

	/**
	 * The résumé page this request is asking for a PDF of, if any.
	 *
	 * @return WP_Post|null
	 */
	public function requested_page(): ?WP_Post {
		if ( self::FORMAT !== $this->requested_format() ) {
			return null;
		}

		$page = get_queried_object();

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return null;
		}

		return self::TEMPLATE === $this->template_of( $page->ID ) ? $page : null;
	}

	/**
	 * A file to serve for one page, or null when there is nothing.
	 *
	 * @param WP_Post $page The résumé page.
	 * @return string|null Absolute path.
	 */
	public function file( WP_Post $page ): ?string {
		$key   = $this->cache->key( $page->ID );
		$fresh = $this->cache->fresh( $key );

		if ( null !== $fresh ) {
			return $fresh;
		}

		$renderer = $this->renderer();

		if ( null === $renderer || ! $this->claim( $key ) ) {
			return $this->cache->stale();
		}

		try {
			$pdf = $renderer->render_pdf( $this->print_url( $page ) );
		} catch ( Throwable $failure ) {
			$this->log->failed( $failure->getMessage() );

			return $this->cache->stale();
		} finally {
			$this->release( $key );
		}

		return $this->cache->write( $key, $pdf ) ?? $this->cache->stale();
	}

	/**
	 * The renderer this site is configured with, if any.
	 *
	 * @return PdfRenderer|null
	 */
	public function renderer(): ?PdfRenderer {
		$configured = defined( 'DP_RESUME_PDF_RENDERER' ) ? (string) constant( 'DP_RESUME_PDF_RENDERER' ) : '';

		$renderer = match ( $configured ) {
			'cloudflare' => CloudflareBrowserRendering::from_config(),
			'gotenberg'  => Gotenberg::from_config(),
			default      => CloudflareBrowserRendering::from_config() ?? Gotenberg::from_config(),
		};

		/**
		 * Filters the renderer the résumé PDF is produced with.
		 *
		 * The port is one method (`PdfRenderer::render_pdf()`), so this is where
		 * a third browser service, or a test double, is substituted. Returning
		 * anything that is not a `PdfRenderer` means "no renderer", which makes
		 * the feature degrade to the print view.
		 *
		 * @since 0.1.0
		 *
		 * @param PdfRenderer|null $renderer The renderer built from wp-config.php.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$filtered = apply_filters( 'dp_resume_pdf_renderer', $renderer );

		return $filtered instanceof PdfRenderer ? $filtered : null;
	}

	/**
	 * The URL the renderer is asked to print.
	 *
	 * The plain permalink, deliberately: the browser prints it with `print.css`
	 * applied, which is the same document a reader gets from their own print
	 * dialogue. Passing `?format=pdf` here would ask the renderer to fetch the
	 * PDF it is being asked to make.
	 *
	 * @param WP_Post $page The résumé page.
	 * @return string
	 */
	public function print_url( WP_Post $page ): string {
		$permalink = get_permalink( $page );

		return is_string( $permalink ) ? $permalink : home_url( '/' );
	}

	/**
	 * The URL that downloads the PDF for one page.
	 *
	 * @param int $page_id The page carrying the résumé template.
	 * @return string
	 */
	public static function download_url( int $page_id ): string {
		$permalink = get_permalink( $page_id );

		return add_query_arg(
			self::QUERY_VAR,
			self::FORMAT,
			is_string( $permalink ) ? $permalink : home_url( '/' )
		);
	}

	/**
	 * The `format` this request asked for.
	 *
	 * @return string
	 */
	private function requested_format(): string {
		$requested = get_query_var( self::QUERY_VAR );

		return is_string( $requested ) ? sanitize_key( $requested ) : '';
	}

	/**
	 * A page's template slug, in whichever of its two spellings it was stored.
	 *
	 * @param int $page_id The page.
	 * @return string
	 */
	private function template_of( int $page_id ): string {
		$template = get_page_template_slug( $page_id );
		$template = is_string( $template ) ? $template : '';

		return str_ends_with( $template, '.html' ) ? substr( $template, 0, -5 ) : $template;
	}

	/**
	 * What the downloaded file is called.
	 *
	 * @param WP_Post $page The résumé page.
	 * @return string
	 */
	private function filename( WP_Post $page ): string {
		$default = sanitize_file_name( sanitize_title( $page->post_title ) . '.pdf' );

		/**
		 * Filters the filename the résumé PDF downloads as.
		 *
		 * @since 0.1.0
		 *
		 * @param string  $filename The filename, including the extension.
		 * @param WP_Post $page     The page the PDF was rendered from.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$filename = (string) apply_filters( 'dp_resume_pdf_filename', $default, $page );

		return '' === $filename ? 'resume.pdf' : sanitize_file_name( $filename );
	}

	/**
	 * Take the render lock for one key.
	 *
	 * Without it, a cold cache plus a burst of requests is a burst of renders,
	 * each of which costs an API call and a browser somewhere. Whoever loses the
	 * race is served the stale copy, which is the same answer they get when the
	 * renderer is down — one behaviour, not two.
	 *
	 * @param string $key The cache key.
	 * @return bool
	 */
	private function claim( string $key ): bool {
		if ( false !== get_transient( self::LOCK . $key ) ) {
			return false;
		}

		set_transient( self::LOCK . $key, 1, 2 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Release the render lock.
	 *
	 * @param string $key The cache key.
	 * @return void
	 */
	private function release( string $key ): void {
		delete_transient( self::LOCK . $key );
	}

	/**
	 * Send the file and stop.
	 *
	 * @param string $path     Absolute path to the PDF.
	 * @param string $filename What it downloads as.
	 * @return void
	 */
	private function serve( string $path, string $filename ): void {
		$size = filesize( $path );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=300' );

		if ( false !== $size ) {
			header( 'Content-Length: ' . (string) $size );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );

		exit;
	}
}
