<?php
/**
 * The rule that stops a sync overwriting a person.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * ADR-0018 rule 3, as one pure function: a derivation fills a blank, and where a
 * value is present the author's value wins.
 *
 * **The mechanism.** Every time the sync writes a field it also records what it
 * wrote, in a shadow copy kept on the post (`VideoSync::SHADOW`). On the next
 * run it compares the shadow with what is actually stored:
 *
 * - **They agree.** Nobody has touched the field since the sync last wrote it,
 *   so the sync may write again. If the remote value has not moved either, it
 *   writes nothing at all — which is what makes syncing twice a no-op.
 * - **They disagree.** Somebody edited the field between the two runs. That
 *   somebody is David, because nothing else writes these fields, so the field is
 *   his: it is added to the locked list (`VideoSync::LOCKED`) and never written
 *   again, whatever the shadow later says.
 * - **There is no shadow entry** and the field already holds something. The sync
 *   has never written here and the value came from somewhere else — a post David
 *   wrote by hand, a field added to the sync's set after the fact, the seeder.
 *   Locked, for the same reason.
 *
 * **Why a shadow rather than a flag set on save.** A flag written from
 * `save_post` would have to know whether the save came from the sync or from a
 * person, which means a suppression flag the sync raises around its own writes —
 * and a suppression flag that is ever wrong silently converts David's edit into
 * the sync's, which is the exact failure this rule exists to prevent. The shadow
 * asks a question that has an answer on disk instead: *is what is stored still
 * what I put there?* It needs no hook, it is right whatever route the edit
 * arrived by — the editor, REST, WP-CLI, a database restore — and its one
 * blind spot is benign: an edit that lands on precisely the synced value is
 * indistinguishable from no edit, and leaves the field reading exactly what the
 * author asked for.
 *
 * The lock is permanent by design. Once a field is in the locked list it stays
 * there even if David later edits it back to the value the sync had, because
 * "leave this field alone" is the instruction, not "leave this value alone".
 */
final class AuthorEdits {

	/**
	 * Not to be instantiated: one pure decision, namespaced.
	 */
	private function __construct() {}

	/**
	 * What the sync may do to one field.
	 *
	 * @param string      $current  What is stored on the post right now.
	 * @param string      $incoming What the platform says it should be.
	 * @param string|null $shadow   What the sync last wrote here, or null if it never has.
	 * @param bool        $locked   Whether this field is already recorded as the author's.
	 * @return FieldDecision
	 */
	public static function decide( string $current, string $incoming, ?string $shadow, bool $locked ): FieldDecision {
		if ( $locked ) {
			return FieldDecision::Locked;
		}

		if ( null === $shadow ) {
			/*
			 * Never synced. A blank is the sync's to fill; anything else was put
			 * there by somebody, and the sync does not get to find out who.
			 */
			if ( '' !== $current ) {
				return FieldDecision::Locked;
			}

			return $current === $incoming ? FieldDecision::Unchanged : FieldDecision::Write;
		}

		if ( $current !== $shadow ) {
			return FieldDecision::Locked;
		}

		return $current === $incoming ? FieldDecision::Unchanged : FieldDecision::Write;
	}
}
