<?php
/**
 * Unit tests for the contact form's signed timestamp.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Contact;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Core\Contact\Stamp;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The timing gate, without WordPress.
 *
 * `Stamp` exists because a bare hidden timestamp is editable by whatever posted
 * it, so the whole check would be theatre. That claim is only true if the
 * signature actually holds, which is what most of this file is about: a stamp
 * whose issue time has been changed, whose signature has been changed, or which
 * came from a site with different salts must all read as "not ours" — and "not
 * ours" must be indistinguishable from "too fast" at the gate, because the
 * handler treats them as one refusal.
 *
 * `wp_hash()` is stood in for with a keyed hash over a fixed salt. The stand-in
 * keeps the two properties the real function has that this class depends on: the
 * same input gives the same output, and a different input gives a different one.
 * Nothing here asserts anything about the hash's strength, which is core's
 * business and not testable from outside it.
 */
final class StampTest extends TestCase {

	/**
	 * The salt the stand-in `wp_hash()` signs with.
	 *
	 * @var string
	 */
	private const SALT = 'unit-test-salt';

	/**
	 * A fixed "now", so no assertion here depends on the wall clock.
	 *
	 * @var int
	 */
	private const NOW = 1_750_000_000;

	/**
	 * The salt the stand-in is currently keyed with.
	 *
	 * A property rather than a second `Functions\when()` call: Brain Monkey
	 * treats redefining a function inside one test as an error, and one test
	 * here deliberately mints a stamp on another install and then reads it back
	 * on this one.
	 *
	 * @var string
	 */
	private string $salt = self::SALT;

	/**
	 * Start Brain Monkey and stand `wp_hash()` up.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->salt = self::SALT;

		Functions\when( 'wp_hash' )->alias(
			fn ( string $data ): string => hash_hmac( 'md5', $data, $this->salt )
		);
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * A freshly issued stamp carries its issue time and verifies.
	 *
	 * @return void
	 */
	public function test_issue_carries_the_time_it_was_drawn(): void {
		$stamp = ( new Stamp( self::NOW ) )->issue();

		$this->assertStringStartsWith( self::NOW . '.', $stamp );
		$this->assertSame( 0, ( new Stamp( self::NOW ) )->age( $stamp ) );
	}

	/**
	 * Age is measured against the clock the stamp is read with, not the one it
	 * was written with.
	 *
	 * @return void
	 */
	public function test_age_is_the_distance_from_now(): void {
		$stamp = ( new Stamp( self::NOW ) )->issue();

		$this->assertSame( 30, ( new Stamp( self::NOW + 30 ) )->age( $stamp ) );
	}

	/**
	 * Anything that is not `{digits}.{signature}` is not one of ours.
	 *
	 * @param string $stamp The value that came back with the submission.
	 * @return void
	 *
	 * @dataProvider provide_malformed_stamps
	 */
	public function test_a_malformed_stamp_has_no_age( string $stamp ): void {
		$this->assertNull( ( new Stamp( self::NOW ) )->age( $stamp ) );
		$this->assertFalse( ( new Stamp( self::NOW ) )->is_plausible( $stamp ) );
	}

	/**
	 * The shapes a stamp can arrive in when nobody issued it.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_malformed_stamps(): array {
		return array(
			'empty'                 => array( '' ),
			'no separator'          => array( '1750000000' ),
			'no issue time'         => array( '.abcdef' ),
			'issue time not digits' => array( '17e9.abcdef' ),
			'negative issue time'   => array( '-1750000000.abcdef' ),
			'signature only'        => array( 'abcdef.' ),
			'whitespace'            => array( '  ' ),
		);
	}

	/**
	 * Editing the issue time invalidates the signature, which is the point.
	 *
	 * This is the attack the class exists to stop: a script that wants to look
	 * like a person waits nought seconds and posts a timestamp from five minutes
	 * ago instead.
	 *
	 * @return void
	 */
	public function test_a_backdated_issue_time_does_not_verify(): void {
		$honest = ( new Stamp( self::NOW ) )->issue();
		$parts  = explode( '.', $honest, 2 );
		$forged = ( self::NOW - 300 ) . '.' . $parts[1];

		$this->assertNull( ( new Stamp( self::NOW ) )->age( $forged ) );
		$this->assertFalse( ( new Stamp( self::NOW ) )->is_plausible( $forged ) );
	}

	/**
	 * Editing the signature invalidates it too.
	 *
	 * @return void
	 */
	public function test_a_rewritten_signature_does_not_verify(): void {
		$forged = self::NOW . '.' . str_repeat( 'a', 32 );

		$this->assertNull( ( new Stamp( self::NOW ) )->age( $forged ) );
	}

	/**
	 * A stamp minted against different salts is not this site's.
	 *
	 * `wp_hash()` keys off the install's own salts, so this is what stops a stamp
	 * being produced elsewhere — including on a copy of this site — and posted here.
	 *
	 * @return void
	 */
	public function test_a_stamp_from_another_site_does_not_verify(): void {
		$this->salt = 'a-different-installs-salts';
		$elsewhere  = ( new Stamp( self::NOW ) )->issue();

		$this->salt = self::SALT;

		$this->assertNull( ( new Stamp( self::NOW + 10 ) )->age( $elsewhere ) );
	}

	/**
	 * The lower bound: faster than a person could have typed it.
	 *
	 * @return void
	 */
	public function test_a_stamp_younger_than_the_minimum_is_not_plausible(): void {
		$stamp = ( new Stamp( self::NOW ) )->issue();

		$this->assertFalse( ( new Stamp( self::NOW ) )->is_plausible( $stamp ) );
		$this->assertFalse( ( new Stamp( self::NOW + Stamp::MIN_AGE - 1 ) )->is_plausible( $stamp ) );
		$this->assertTrue( ( new Stamp( self::NOW + Stamp::MIN_AGE ) )->is_plausible( $stamp ) );
	}

	/**
	 * The upper bound: a form left open for half a day.
	 *
	 * @return void
	 */
	public function test_a_stamp_older_than_the_maximum_is_not_plausible(): void {
		$stamp = ( new Stamp( self::NOW ) )->issue();

		$this->assertTrue( ( new Stamp( self::NOW + Stamp::MAX_AGE ) )->is_plausible( $stamp ) );
		$this->assertFalse( ( new Stamp( self::NOW + Stamp::MAX_AGE + 1 ) )->is_plausible( $stamp ) );
	}

	/**
	 * A stamp issued in the future is refused rather than treated as very old.
	 *
	 * Negative ages come from a clock that moved backwards, not from anything a
	 * sender did, but the gate has to answer something and "no" is the answer
	 * that cannot be gamed by sending a timestamp from next year.
	 *
	 * @return void
	 */
	public function test_a_stamp_from_the_future_is_not_plausible(): void {
		$stamp = ( new Stamp( self::NOW + 600 ) )->issue();

		$this->assertSame( -600, ( new Stamp( self::NOW ) )->age( $stamp ) );
		$this->assertFalse( ( new Stamp( self::NOW ) )->is_plausible( $stamp ) );
	}
}
