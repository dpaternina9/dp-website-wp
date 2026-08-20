<?php
/**
 * The one-method port every PDF renderer implements.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

/**
 * Turn a URL into a PDF. That is the entire contract.
 *
 * `docs/plan.md` section 7.1 rules `dompdf` and `mpdf` out and says why: the
 * design leans on `clamp()`, `color-mix()` and container queries, and neither
 * engine supports any of the three. The PDF has to come out of a real browser,
 * and a real browser is a service — Cloudflare Browser Rendering today, a
 * self-hosted Gotenberg container if that disappoints.
 *
 * One method, taking a URL and returning bytes, is what makes that a
 * configuration change rather than a rewrite. Nothing above this interface
 * knows whether the browser is somebody else's or ours, and the test suite
 * implements it in four lines to exercise every path around it without a
 * network — which matters, because David has no Cloudflare credentials yet and
 * the feature has to be provably correct before he does.
 *
 * A renderer that cannot answer throws `RendererFailed`. It never returns an
 * empty string, and it never returns something that is not a PDF: the caller
 * prefers a stale PDF to a fresh nothing, and it can only do that if "nothing"
 * is unmistakable.
 */
interface PdfRenderer {

	/**
	 * Render one URL to PDF bytes.
	 *
	 * @param string $url The absolute URL to print.
	 * @return string The PDF, as bytes.
	 *
	 * @throws RendererFailed When the renderer could not produce a PDF.
	 */
	public function render_pdf( string $url ): string;
}
