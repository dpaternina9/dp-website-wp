<?php
/**
 * Unit tests for the author-edit guard's decision.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Watch;

use DP\Core\Watch\AuthorEdits;
use DP\Core\Watch\FieldDecision;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * ADR-0018 rule 3, asserted one field at a time.
 *
 * "Code never overwrites a value an author set" is the rule the whole sync is
 * built around, and it reduces to this one pure function. Every case below is a
 * sentence from that rule rather than a branch from the implementation: a blank
 * gets filled, a value the sync put there gets refreshed, and anything else is
 * somebody's and stays theirs.
 */
final class AuthorEditsTest extends TestCase {

	/**
	 * A field the sync has never written, holding nothing, is the sync's to fill.
	 *
	 * @return void
	 */
	public function test_a_blank_is_filled(): void {
		$this->assertSame(
			FieldDecision::Write,
			AuthorEdits::decide( '', '18 MIN', null, false )
		);
	}

	/**
	 * A blank that is already what the platform says is not a write.
	 *
	 * This is what stops an entry with no runtime — a live broadcast, say —
	 * counting as an update on every hourly run for the rest of its life.
	 *
	 * @return void
	 */
	public function test_a_blank_that_is_already_right_is_not_written(): void {
		$this->assertSame(
			FieldDecision::Unchanged,
			AuthorEdits::decide( '', '', null, false )
		);
	}

	/**
	 * A field the sync has never written, already holding something, is not the
	 * sync's at all.
	 *
	 * This is the case that protects a `dp_video` written by hand and a field
	 * added to the sync's set after the fact. The sync does not get to find out
	 * where the value came from; it is enough that it did not put it there.
	 *
	 * @return void
	 */
	public function test_a_value_the_sync_never_wrote_is_the_author_s(): void {
		$this->assertSame(
			FieldDecision::Locked,
			AuthorEdits::decide( 'A title David typed', 'The platform title', null, false )
		);
	}

	/**
	 * A field still holding what the sync last put there is refreshed.
	 *
	 * @return void
	 */
	public function test_an_untouched_field_takes_the_new_value(): void {
		$this->assertSame(
			FieldDecision::Write,
			AuthorEdits::decide( 'Old title', 'New title', 'Old title', false )
		);
	}

	/**
	 * A field that already agrees with the platform is left alone.
	 *
	 * This is idempotency: it is why a second sync writes nothing and reports
	 * everything as unchanged.
	 *
	 * @return void
	 */
	public function test_a_field_that_already_agrees_is_unchanged(): void {
		$this->assertSame(
			FieldDecision::Unchanged,
			AuthorEdits::decide( 'Same title', 'Same title', 'Same title', false )
		);
	}

	/**
	 * A field that has moved since the sync last wrote it is the author's.
	 *
	 * Nothing else writes these fields, so "different from what I left here"
	 * means "somebody edited it", whatever route the edit arrived by.
	 *
	 * @return void
	 */
	public function test_an_edited_field_becomes_the_author_s(): void {
		$this->assertSame(
			FieldDecision::Locked,
			AuthorEdits::decide( 'David rewrote this', 'The platform title', 'What the sync wrote', false )
		);
	}

	/**
	 * A locked field stays locked, whatever the values later say.
	 *
	 * The last case is the one the flag exists for: David edits a title, then
	 * later edits it back to exactly what the sync had. Without the permanent
	 * lock the shadow would agree again and the field would silently return to
	 * being the sync's, which is not what "leave this field alone" means.
	 *
	 * @return void
	 */
	public function test_a_locked_field_is_never_unlocked(): void {
		$this->assertSame(
			FieldDecision::Locked,
			AuthorEdits::decide( 'Mine', 'Theirs', 'What the sync wrote', true )
		);

		$this->assertSame(
			FieldDecision::Locked,
			AuthorEdits::decide( 'Back to the synced value', 'Theirs', 'Back to the synced value', true ),
			'A field edited back to the synced value returned to the sync.'
		);

		$this->assertSame(
			FieldDecision::Locked,
			AuthorEdits::decide( '', '', null, true ),
			'A blank locked field is still locked.'
		);
	}
}
