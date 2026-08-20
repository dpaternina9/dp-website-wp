<?php
/**
 * Who may write our meta.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * The `auth_callback` behind every registered field.
 *
 * `register_meta()` defaults to `__return_true` when no callback is given, which
 * means a registered, REST-exposed field is writable by anyone who can reach the
 * route. CLAUDE.md section 1.4 does not allow that, so every field in this plugin
 * names one of these two explicitly — there is no default path.
 *
 * Both delegate to the capability that governs the object itself rather than
 * inventing a capability of their own: if you may edit the post, you may edit the
 * post's fields. That keeps roles, revisions and any future capability filter
 * working without this class needing to know about them.
 *
 * They answer for `$user_id` rather than for the current user. WordPress passes
 * the user the check is about, and in a REST context that is not always the
 * global one.
 */
final class MetaAuth {

	/**
	 * Whether a user may write a post meta field.
	 *
	 * @param bool               $allowed   Whether the key is considered public. Ignored: we decide.
	 * @param string             $meta_key  The meta key being written.
	 * @param int                $object_id The post the field hangs off.
	 * @param int                $user_id   The user the check is about.
	 * @param string             $cap       The capability being mapped.
	 * @param array<int, string> $caps      The primitive capabilities the map produced.
	 * @return bool
	 */
	public function post_meta( bool $allowed, string $meta_key, int $object_id, int $user_id, string $cap, array $caps ): bool {
		unset( $allowed, $meta_key, $cap, $caps );

		return user_can( $user_id, 'edit_post', $object_id );
	}

	/**
	 * Whether a user may write a term meta field.
	 *
	 * @param bool               $allowed   Whether the key is considered public. Ignored: we decide.
	 * @param string             $meta_key  The meta key being written.
	 * @param int                $object_id The term the field hangs off.
	 * @param int                $user_id   The user the check is about.
	 * @param string             $cap       The capability being mapped.
	 * @param array<int, string> $caps      The primitive capabilities the map produced.
	 * @return bool
	 */
	public function term_meta( bool $allowed, string $meta_key, int $object_id, int $user_id, string $cap, array $caps ): bool {
		unset( $allowed, $meta_key, $cap, $caps );

		return user_can( $user_id, 'edit_term', $object_id );
	}
}
