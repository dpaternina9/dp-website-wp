<?php
/**
 * What a renderer throws when it cannot answer.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Resume;

use RuntimeException;

/**
 * A renderer could not produce a PDF, and said so rather than returning junk.
 *
 * Every failure mode ends up here — no credentials, a refused request, a
 * timeout, a body that is not a PDF — because the caller's response to all of
 * them is identical: serve the stale copy if there is one, and otherwise fall
 * back to the print view. Distinguishing them at the call site would be
 * distinguishing them in order to do the same thing.
 *
 * The message is written for `debug.log`, never for a page. It may name a host
 * and a status code; it must never carry a credential.
 */
final class RendererFailed extends RuntimeException {}
