<?php
/**
 * Everything the résumé adds, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

/**
 * The ledger block and the PDF behind `?format=pdf`, attached together.
 *
 * They are one feature: the ledger is what the PDF is a rendering of, and the
 * PDF's cache key is invalidated by the same two post types the ledger reads.
 * Splitting them would put the reason the cache key looks the way it does in a
 * different file from the thing that made it true.
 */
final class Resume {

	/**
	 * Constructor.
	 *
	 * @param Ledger    $ledger The Experience section.
	 * @param ResumePdf $pdf    The query variable, the cache, and the renderer.
	 */
	private function __construct(
		private readonly Ledger $ledger,
		private readonly ResumePdf $pdf
	) {}

	/**
	 * Build with the default collaborators.
	 *
	 * Touches no WordPress function, so it is safe before `init`.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return self
	 */
	public static function create( string $plugin_dir ): self {
		return new self( new Ledger( $plugin_dir ), new ResumePdf() );
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->ledger->register();
		$this->pdf->register();
	}
}
