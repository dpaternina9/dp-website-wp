<?php
/**
 * The Cloudflare Browser Rendering adapter.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

use WP_Error;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a RendererFailed never reaches a browser. It is caught by
// ResumePdf::file(), written to debug.log and turned into "serve the stale copy"; escaping the
// message would only make a log line harder to read. Same reasoning as Timeline\Geometry.
/**
 * Prints the résumé through Cloudflare's own Chromium, over the REST API.
 *
 * Chosen in `docs/plan.md` section 7.1 because David already runs Cloudflare:
 * this adds an API call rather than a service to keep alive. The REST endpoint
 * is used rather than a Worker binding for the same reason — a Worker would be
 * a second deployable with its own release story, and the point of this phase
 * is one HTTP request.
 *
 * Two things are deliberate.
 *
 * **The credentials are constants, never options.** An API token in
 * `wp_options` is an API token in every database backup and in the REST API's
 * blast radius. `wp-config.php` is where a secret belongs, and a missing
 * constant is a `RendererFailed` rather than a fatal — the whole feature
 * degrades to the print view, which is exactly what the plan asks for while
 * David has no credentials.
 *
 * **The response is checked for being a PDF.** Cloudflare answers a refused
 * request with a JSON error document and HTTP 200 in some failure modes, and a
 * cache full of JSON named `.pdf` is worse than no cache at all. The magic
 * bytes are the check.
 */
final class CloudflareBrowserRendering implements PdfRenderer {

	/**
	 * The account id, from `DP_CLOUDFLARE_ACCOUNT_ID`.
	 *
	 * @var string
	 */
	public const ACCOUNT = 'DP_CLOUDFLARE_ACCOUNT_ID';

	/**
	 * The API token, from `DP_CLOUDFLARE_API_TOKEN`.
	 *
	 * @var string
	 */
	public const TOKEN = 'DP_CLOUDFLARE_API_TOKEN';

	/**
	 * The first five bytes of any PDF.
	 *
	 * @var string
	 */
	private const MAGIC = '%PDF-';

	/**
	 * Constructor.
	 *
	 * @param string $account The Cloudflare account id.
	 * @param string $token   An API token with the Browser Rendering permission.
	 * @param int    $timeout How long to wait, in seconds.
	 */
	public function __construct(
		private readonly string $account,
		private readonly string $token,
		private readonly int $timeout = 30
	) {}

	/**
	 * Build the adapter from `wp-config.php`, or say it is not configured.
	 *
	 * @return self|null
	 */
	public static function from_config(): ?self {
		$account = defined( self::ACCOUNT ) ? (string) constant( self::ACCOUNT ) : '';
		$token   = defined( self::TOKEN ) ? (string) constant( self::TOKEN ) : '';

		return '' === $account || '' === $token ? null : new self( $account, $token );
	}

	/**
	 * Render one URL to PDF bytes.
	 *
	 * @param string $url The absolute URL to print.
	 * @return string
	 *
	 * @throws RendererFailed When Cloudflare did not answer with a PDF.
	 */
	public function render_pdf( string $url ): string {
		$body = wp_json_encode(
			array(
				'url'         => $url,
				'pdfOptions'  => array(
					'printBackground' => true,
					'format'          => 'A4',
					'margin'          => array(
						'top'    => '14mm',
						'bottom' => '14mm',
						'left'   => '12mm',
						'right'  => '12mm',
					),
				),
				'gotoOptions' => array(
					'waitUntil' => 'networkidle0',
					'timeout'   => $this->timeout * 1000,
				),
			)
		);

		if ( ! is_string( $body ) ) {
			throw new RendererFailed( 'the request body could not be encoded' );
		}

		$response = wp_remote_post(
			sprintf(
				'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/pdf',
				rawurlencode( $this->account )
			),
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( $response instanceof WP_Error ) {
			throw new RendererFailed( 'cloudflare: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			throw new RendererFailed( 'cloudflare answered HTTP ' . (string) $status );
		}

		$pdf = wp_remote_retrieve_body( $response );

		if ( ! str_starts_with( $pdf, self::MAGIC ) ) {
			throw new RendererFailed( 'cloudflare answered with something that is not a PDF' );
		}

		return $pdf;
	}
}
