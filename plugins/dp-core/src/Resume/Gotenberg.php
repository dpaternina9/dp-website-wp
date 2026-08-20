<?php
/**
 * The Gotenberg adapter.
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
 * Prints the résumé through a self-hosted Gotenberg container.
 *
 * The swap-in named in `docs/plan.md` section 7.1 for the day the Cloudflare
 * route disappoints. It exists now rather than later for one reason: a port
 * with a single implementation is not a port, it is an interface somebody hopes
 * is general. Writing the second adapter is what proves the first one did not
 * leak its own shape into the contract — and both came out at one method
 * taking a URL, which is the evidence.
 *
 * Gotenberg's Chromium module takes a multipart form with a `url` field. There
 * is no client library here on purpose: the request is eleven lines of
 * `wp_remote_post()`, and `CLAUDE.md` section 6 asks what a dependency would
 * replace.
 *
 * It is expected to be reachable on a private network, so there is no
 * authentication here. Do not expose a Gotenberg container to the internet.
 */
final class Gotenberg implements PdfRenderer {

	/**
	 * The base URL, from `DP_GOTENBERG_URL`.
	 *
	 * @var string
	 */
	public const BASE_URL = 'DP_GOTENBERG_URL';

	/**
	 * The first five bytes of any PDF.
	 *
	 * @var string
	 */
	private const MAGIC = '%PDF-';

	/**
	 * Constructor.
	 *
	 * @param string $base    The container's base URL, e.g. `http://gotenberg:3000`.
	 * @param int    $timeout How long to wait, in seconds.
	 */
	public function __construct(
		private readonly string $base,
		private readonly int $timeout = 30
	) {}

	/**
	 * Build the adapter from `wp-config.php`, or say it is not configured.
	 *
	 * @return self|null
	 */
	public static function from_config(): ?self {
		$base = defined( self::BASE_URL ) ? (string) constant( self::BASE_URL ) : '';

		return '' === $base ? null : new self( untrailingslashit( $base ) );
	}

	/**
	 * Render one URL to PDF bytes.
	 *
	 * @param string $url The absolute URL to print.
	 * @return string
	 *
	 * @throws RendererFailed When Gotenberg did not answer with a PDF.
	 */
	public function render_pdf( string $url ): string {
		$boundary = 'dp' . wp_generate_password( 24, false );

		$response = wp_remote_post(
			$this->base . '/forms/chromium/convert/url',
			array(
				'timeout' => $this->timeout,
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body'    => $this->multipart(
					$boundary,
					array(
						'url'             => $url,
						'printBackground' => 'true',
						'paperWidth'      => '8.27',
						'paperHeight'     => '11.7',
						'marginTop'       => '0.55',
						'marginBottom'    => '0.55',
						'marginLeft'      => '0.47',
						'marginRight'     => '0.47',
					)
				),
			)
		);

		if ( $response instanceof WP_Error ) {
			throw new RendererFailed( 'gotenberg: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			throw new RendererFailed( 'gotenberg answered HTTP ' . (string) $status );
		}

		$pdf = wp_remote_retrieve_body( $response );

		if ( ! str_starts_with( $pdf, self::MAGIC ) ) {
			throw new RendererFailed( 'gotenberg answered with something that is not a PDF' );
		}

		return $pdf;
	}

	/**
	 * Encode a flat field list as a multipart body.
	 *
	 * @param string                $boundary The boundary token.
	 * @param array<string, string> $fields   The fields.
	 * @return string
	 */
	private function multipart( string $boundary, array $fields ): string {
		$body = '';

		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\n"
				. 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
				. $value . "\r\n";
		}

		return $body . '--' . $boundary . "--\r\n";
	}
}
