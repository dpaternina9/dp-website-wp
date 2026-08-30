<?php
/**
 * What a sync may do to one field.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * The three answers `AuthorEdits::decide()` can give about a single field.
 *
 * An enum rather than a boolean because "leave it alone" has two causes that
 * must not be confused: the value is already right (`Unchanged`, which is what
 * makes a second sync a no-op) and the value is David's (`Locked`, which is what
 * makes it his for good). The first is recorded as a clean run; the second is
 * recorded on the post, permanently.
 */
enum FieldDecision {

	/**
	 * The sync owns this field and the remote value differs. Write it.
	 */
	case Write;

	/**
	 * The sync owns this field and the remote value is already stored.
	 */
	case Unchanged;

	/**
	 * An author has set this field. It is theirs from now on.
	 */
	case Locked;
}
