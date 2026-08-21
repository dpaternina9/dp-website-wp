<?php
/**
 * Unit tests for the contact form's per-sender counter.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Contact;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DP\Core\Contact\RateLimiter;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The rate limit, with an array standing in for the transient store.
 *
 * The class is worth testing on its own rather than only through the handler,
 * because three of its properties are invisible from outside it:
 *
 * - **A refused attempt does not count.** Otherwise a sender who keeps pressing
 *   the button keeps extending their own lockout, and the counter stops meaning
 *   "messages sent".
 * - **The window does not slide.** Three messages in the first minute must not
 *   lock the sender out for ten minutes from the third, so the class reads the
 *   remaining life off the existing row rather than letting `set_transient()`
 *   reset the expiry.
 * - **The key is not an address.** Nothing reaching the store may be reversible
 *   into what it was made from, because the Privacy page's claim about what this
 *   site keeps has to survive the one feature with a reason to break it.
 *
 * `remaining()` calls `time()` directly, which is a PHP internal and therefore
 * not something Brain Monkey can stand in for. So the clock here is real and the
 * store models what core writes: an absolute expiry timestamp in the option row
 * beside the value. Making time appear to pass is then a matter of moving that
 * timestamp, which is also the only way to tell "reused the remaining life" from
 * "wrote a fresh window" — with no elapsed time the two are the same number.
 */
final class RateLimiterTest extends TestCase {

	/**
	 * Transient name to value.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = array();

	/**
	 * Transient name to the absolute Unix time it expires at.
	 *
	 * @var array<string, int>
	 */
	private array $expiries = array();

	/**
	 * How far an expiry assertion may be from the expected second.
	 *
	 * One, because the clock is real and a test can straddle a tick.
	 *
	 * @var int
	 */
	private const TOLERANCE = 1;

	/**
	 * Start Brain Monkey and stand the transient API up.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->transients = array();
		$this->expiries   = array();

		Functions\when( 'get_transient' )->alias( $this->read( ... ) );
		Functions\when( 'set_transient' )->alias( $this->write( ... ) );
		Functions\when( 'delete_transient' )->alias( $this->forget( ... ) );
		Functions\when( 'get_option' )->alias( $this->option( ... ) );

		Functions\when( 'wp_hash' )->alias(
			static fn ( string $data ): string => hash_hmac( 'md5', $data, 'unit-test-salt' )
		);

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'] );

		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Stand in for `get_transient()`.
	 *
	 * @param string $key The transient name.
	 * @return mixed The stored value, or false.
	 */
	public function read( string $key ): mixed {
		return $this->transients[ $key ] ?? false;
	}

	/**
	 * Stand in for `set_transient()`, recording the absolute expiry core would write.
	 *
	 * @param string $key    The transient name.
	 * @param mixed  $value  The value.
	 * @param int    $expiry How long it should live, in seconds.
	 * @return bool
	 */
	public function write( string $key, mixed $value, int $expiry ): bool {
		$this->transients[ $key ] = $value;
		$this->expiries[ $key ]   = time() + $expiry;

		return true;
	}

	/**
	 * Stand in for `delete_transient()`.
	 *
	 * @param string $key The transient name.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		unset( $this->transients[ $key ], $this->expiries[ $key ] );

		return true;
	}

	/**
	 * Stand in for `get_option()`.
	 *
	 * The only option this class reads is the timeout row core writes beside a
	 * transient. Everything else answers false, which is what a real install
	 * does for an option nobody has set.
	 *
	 * @param string $name The option name.
	 * @return mixed
	 */
	public function option( string $name ): mixed {
		$prefix = '_transient_timeout_';

		if ( ! str_starts_with( $name, $prefix ) ) {
			return false;
		}

		return $this->expiries[ substr( $name, strlen( $prefix ) ) ] ?? false;
	}

	/**
	 * Under the limit, every attempt is allowed.
	 *
	 * @return void
	 */
	public function test_attempts_up_to_the_limit_are_allowed(): void {
		$limiter = new RateLimiter();

		for ( $attempt = 1; $attempt <= RateLimiter::LIMIT; $attempt++ ) {
			$this->assertTrue( $limiter->allow( 'sender' ), sprintf( 'attempt %d', $attempt ) );
		}
	}

	/**
	 * One past the limit is refused.
	 *
	 * @return void
	 */
	public function test_the_attempt_after_the_limit_is_refused(): void {
		$limiter = new RateLimiter();

		$this->spend( $limiter, RateLimiter::LIMIT );

		$this->assertFalse( $limiter->allow( 'sender' ) );
	}

	/**
	 * A refused attempt does not push the count any further.
	 *
	 * @return void
	 */
	public function test_a_refused_attempt_does_not_count(): void {
		$limiter = new RateLimiter();

		$this->spend( $limiter, RateLimiter::LIMIT );

		$stored = $this->transients;

		$limiter->allow( 'sender' );
		$limiter->allow( 'sender' );

		$this->assertSame( $stored, $this->transients );
	}

	/**
	 * Counters are per sender, not per site.
	 *
	 * @return void
	 */
	public function test_one_sender_reaching_the_limit_does_not_refuse_another(): void {
		$limiter = new RateLimiter();

		$this->spend( $limiter, RateLimiter::LIMIT );

		$this->assertFalse( $limiter->allow( 'sender' ) );
		$this->assertTrue( $limiter->allow( 'somebody-else' ) );
	}

	/**
	 * The first attempt opens a full window.
	 *
	 * @return void
	 */
	public function test_the_first_attempt_opens_a_full_window(): void {
		( new RateLimiter() )->allow( 'sender' );

		$this->assertExpiresIn( RateLimiter::WINDOW );
	}

	/**
	 * A later attempt inherits the remaining life rather than resetting it.
	 *
	 * @return void
	 */
	public function test_the_window_is_not_extended_by_a_second_attempt(): void {
		$limiter = new RateLimiter();

		$limiter->allow( 'sender' );

		// Five of the ten minutes have gone by.
		$this->expiries[ $this->only_key() ] = time() + 300;

		$limiter->allow( 'sender' );

		$this->assertExpiresIn( 300 );
	}

	/**
	 * With no timeout row to read — an external object cache — it fails safe.
	 *
	 * It cannot see the remaining life, so it writes a full window. That extends
	 * the window rather than shortening it, which is the direction that cannot
	 * let an extra message through.
	 *
	 * @return void
	 */
	public function test_an_unreadable_expiry_falls_back_to_a_full_window(): void {
		$limiter = new RateLimiter();

		$limiter->allow( 'sender' );

		// What an object cache looks like from here: the value is there, the row is not.
		$this->expiries = array();

		$limiter->allow( 'sender' );

		$this->assertExpiresIn( RateLimiter::WINDOW );
	}

	/**
	 * An expiry already in the past is treated as no window at all.
	 *
	 * @return void
	 */
	public function test_an_expired_window_is_reopened_in_full(): void {
		$limiter = new RateLimiter();

		$limiter->allow( 'sender' );

		$this->expiries[ $this->only_key() ] = time() - 5;

		$limiter->allow( 'sender' );

		$this->assertExpiresIn( RateLimiter::WINDOW );
	}

	/**
	 * Forgetting a sender clears the count.
	 *
	 * @return void
	 */
	public function test_forget_clears_the_count(): void {
		$limiter = new RateLimiter();

		$this->spend( $limiter, RateLimiter::LIMIT );

		$this->assertFalse( $limiter->allow( 'sender' ) );

		$limiter->forget( 'sender' );

		$this->assertSame( array(), $this->transients );
		$this->assertTrue( $limiter->allow( 'sender' ) );
	}

	/**
	 * `dp_contact_rate_limit` moves the limit.
	 *
	 * @return void
	 */
	public function test_the_limit_is_filterable(): void {
		Filters\expectApplied( 'dp_contact_rate_limit' )->andReturn( 1 );

		$limiter = new RateLimiter();

		$this->assertTrue( $limiter->allow( 'sender' ) );
		$this->assertFalse( $limiter->allow( 'sender' ) );
	}

	/**
	 * A filter cannot close the form by setting the limit to nothing.
	 *
	 * Closing it is `dp_contact_form_enabled`'s job. A zero here would refuse
	 * every message with the wrong rejection and no way to tell.
	 *
	 * @return void
	 */
	public function test_a_limit_below_one_is_floored_at_one(): void {
		Filters\expectApplied( 'dp_contact_rate_limit' )->andReturn( 0 );

		$limiter = new RateLimiter();

		$this->assertTrue( $limiter->allow( 'sender' ) );
		$this->assertFalse( $limiter->allow( 'sender' ) );
	}

	/**
	 * `dp_contact_rate_window` moves the window, with a floor of a minute.
	 *
	 * @return void
	 */
	public function test_the_window_is_filterable_with_a_floor(): void {
		Filters\expectApplied( 'dp_contact_rate_window' )->andReturn( 5 );

		( new RateLimiter() )->allow( 'sender' );

		$this->assertExpiresIn( MINUTE_IN_SECONDS );
	}

	/**
	 * The stored key is a hash, and the address is nowhere in it.
	 *
	 * @return void
	 */
	public function test_the_fingerprint_does_not_carry_the_address(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

		$fingerprint = RateLimiter::fingerprint();

		$this->assertSame( 32, strlen( $fingerprint ) );
		$this->assertMatchesRegularExpression( '~^[0-9a-f]{32}$~', $fingerprint );
		$this->assertStringNotContainsString( '203.0.113.7', $fingerprint );
	}

	/**
	 * The same address fingerprints the same way; a different one does not.
	 *
	 * @return void
	 */
	public function test_the_fingerprint_is_stable_and_distinguishing(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$first                  = RateLimiter::fingerprint();

		$this->assertSame( $first, RateLimiter::fingerprint() );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.8';

		$this->assertNotSame( $first, RateLimiter::fingerprint() );
	}

	/**
	 * With no address at all there is still a key, so the gate never opens wide.
	 *
	 * @return void
	 */
	public function test_a_missing_address_still_fingerprints(): void {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertMatchesRegularExpression( '~^[0-9a-f]{32}$~', RateLimiter::fingerprint() );
	}

	/**
	 * `dp_contact_sender_address` is what a site behind a proxy points at its edge.
	 *
	 * @return void
	 */
	public function test_the_fingerprinted_address_is_filterable(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$direct                 = RateLimiter::fingerprint();

		Filters\expectApplied( 'dp_contact_sender_address' )->andReturn( '198.51.100.9' );

		$this->assertNotSame( $direct, RateLimiter::fingerprint() );
	}

	/**
	 * Spend a number of a sender's attempts.
	 *
	 * @param RateLimiter $limiter The limiter.
	 * @param int         $count   How many attempts to make.
	 * @return void
	 */
	private function spend( RateLimiter $limiter, int $count ): void {
		for ( $attempt = 1; $attempt <= $count; $attempt++ ) {
			$limiter->allow( 'sender' );
		}
	}

	/**
	 * The one transient name the store holds.
	 *
	 * @return string
	 */
	private function only_key(): string {
		$this->assertCount( 1, $this->expiries );

		return (string) array_key_first( $this->expiries );
	}

	/**
	 * Assert the one stored window closes about this many seconds from now.
	 *
	 * @param int $seconds How far off the expiry should be.
	 * @return void
	 */
	private function assertExpiresIn( int $seconds ): void {
		$expiry = $this->expiries[ $this->only_key() ];

		$this->assertEqualsWithDelta( time() + $seconds, $expiry, self::TOLERANCE );
	}
}
