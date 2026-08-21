<?php
/**
 * A PDF renderer that answers without a browser.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Resume;

use DP\Core\Resume\PdfRenderer;
use DP\Core\Resume\RendererFailed;

/**
 * The port, implemented in four lines, exactly as `PdfRenderer` says it can be.
 *
 * Every path around the renderer — the cache key, the lock, the stale fallback,
 * the fall-through to the print view — has to be provable before David has any
 * Cloudflare credentials, and none of them is about how a browser prints a page.
 * So this stands in for the browser and records what it was asked for, which is
 * the other half of the assertion: the URL handed to a renderer must be the
 * plain permalink and never the one carrying `?format=pdf`.
 */
final class StubRenderer implements PdfRenderer {

	/**
	 * Every URL this renderer was asked to print, in order.
	 *
	 * @var list<string>
	 */
	public array $rendered = array();

	/**
	 * Constructor.
	 *
	 * @param string $pdf   The bytes to answer with.
	 * @param string $fails A failure message, or '' to succeed.
	 */
	public function __construct(
		private readonly string $pdf = "%PDF-1.7\nstub\n%%EOF",
		private readonly string $fails = ''
	) {}

	/**
	 * Answer, or refuse the way a real renderer refuses.
	 *
	 * @param string $url The absolute URL to print.
	 * @return string
	 *
	 * @throws RendererFailed When this stub was built to fail.
	 */
	public function render_pdf( string $url ): string {
		$this->rendered[] = $url;

		if ( '' !== $this->fails ) {
			throw new RendererFailed( $this->fails );
		}

		return $this->pdf;
	}

	/**
	 * How many times a PDF was actually produced.
	 *
	 * @return int
	 */
	public function calls(): int {
		return count( $this->rendered );
	}
}
