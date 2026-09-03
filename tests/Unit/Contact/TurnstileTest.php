<?php
/**
 * Unit tests for the contact form's Turnstile gate.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Contact;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DP\Core\Contact\Turnstile;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every way a challenge can fail, and the one way it can pass.
 *
 * This class is the only place in the project that decides whether a stranger's
 * message is allowed to reach David, so the property under test is not "it
 * accepts a good token" — that is one test — but that **nothing else does**. A
 * gate with nine refusal paths and eight of them tested is a gate with an
 * untested hole, and the hole is not visible from the outside: a verification
 * that wrongly returns true looks exactly like a message getting through.
 *
 * So every failure has its own test and every one of them asserts `false`.
 *
 * Two things about the harness are deliberate.
 *
 * **The configuration is constructor-injected, not defined.** The keys are
 * `wp-config.php` constants in production, and a constant cannot be undefined
 * once it is set — one test defining `DP_TURNSTILE_SITEKEY` would silently
 * configure every test that ran after it in the same process, including the one
 * whose whole claim is that an unconfigured site does nothing. `Turnstile`
 * takes its configuration for the same reason `Stamp` takes the time.
 *
 * **`WP_Error` is not faked.** The unit harness has no such class and a fake
 * would be worth nothing (`DP\Tests\Unit\Maintenance\CurtainTest` says the same
 * of it). What is under test is the branch, so `is_wp_error()` is stood up to
 * recognise whatever the transport was told to return, and the object it
 * returns answers `get_error_message()` because that is the only thing this
 * class asks of it.
 */
final class TurnstileTest extends TestCase {

	/**
	 * What the pretend transport answers with.
	 *
	 * @var mixed
	 */
	private mixed $response = null;

	/**
	 * The arguments `wp_remote_post()` was called with, in order.
	 *
	 * @var list<array{string, array<string, mixed>}>
	 */
	private array $requests = array();

	/**
	 * The sitekey the tests configure a working site with.
	 *
	 * @var string
	 */
	private const SITEKEY = '0x4AAAAAAAtestsitekey';

	/**
	 * The secret the tests configure a working site with.
	 *
	 * @var string
	 */
	private const SECRET = '0x4AAAAAAAtestsecretkey';

	/**
	 * The host the tests treat as this site's.
	 *
	 * @var string
	 */
	private const HOST = 'dpaternina.example';

	/**
	 * Start Brain Monkey and stand the HTTP API up.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->response = null;
		$this->requests = array();

		Functions\when( 'wp_remote_post' )->alias( $this->post( ... ) );
		Functions\when( 'wp_json_encode' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- this IS the stand-in for wp_json_encode(); calling it here would recurse.
			static fn ( mixed $data ): string => (string) json_encode( $data )
		);
		Functions\when( 'is_wp_error' )->alias(
			static fn ( mixed $thing ): bool => is_object( $thing ) && method_exists( $thing, 'get_error_message' )
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn ( mixed $response ): mixed => is_array( $response ) ? ( $response['code'] ?? 0 ) : 0
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn ( mixed $response ): string => is_array( $response ) && is_string( $response['body'] ?? null )
				? $response['body']
				: ''
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
	 * Stand in for `wp_remote_post()`, recording what it was asked.
	 *
	 * @param string               $url       Where the request went.
	 * @param array<string, mixed> $arguments What it carried.
	 * @return mixed
	 */
	public function post( string $url, array $arguments = array() ): mixed {
		$this->requests[] = array( $url, $arguments );

		return $this->response;
	}

	/**
	 * With no keys, nothing happens and nothing is allowed through.
	 *
	 * Both halves matter. The gate must not open — a site that half-configured
	 * Turnstile and let everything past would be worse than one that never
	 * tried — and it must not reach the network, because "no third-party
	 * request unless David turned this on" is the promise the whole feature is
	 * conditional on.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_site_verifies_nothing(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$turnstile = new Turnstile( '', '', array( self::HOST ) );

		$this->assertFalse( $turnstile->is_configured() );
		$this->assertFalse( $turnstile->verify( 'a-token', '203.0.113.7' ) );
		$this->assertSame( '', $turnstile->script_url() );
	}

	/**
	 * One key without the other is not configuration.
	 *
	 * @return void
	 */
	public function test_half_a_configuration_is_no_configuration(): void {
		$this->assertFalse( ( new Turnstile( self::SITEKEY, '' ) )->is_configured() );
		$this->assertFalse( ( new Turnstile( '', self::SECRET ) )->is_configured() );
	}

	/**
	 * With both keys, the widget can be drawn and the script can be asked for.
	 *
	 * @return void
	 */
	public function test_a_configured_site_publishes_its_sitekey_and_script(): void {
		$turnstile = $this->configured();

		$this->assertTrue( $turnstile->is_configured() );
		$this->assertSame( self::SITEKEY, $turnstile->sitekey() );
		$this->assertSame( Turnstile::SCRIPT_URL, $turnstile->script_url() );
	}

	/**
	 * The one path that passes: success, the right action, the right host.
	 *
	 * @return void
	 */
	public function test_a_good_token_verifies(): void {
		$this->answer( 200, array( 'success' => true ) );

		$this->assertTrue( $this->configured()->verify( 'a-good-token', '203.0.113.7' ) );
	}

	/**
	 * The request goes where it should, carrying what Cloudflare asks for.
	 *
	 * The secret is in the body of a POST rather than in a query string for the
	 * ordinary reason — query strings are logged by proxies — and `remoteip` is
	 * there because it is what Cloudflare scores against.
	 *
	 * @return void
	 */
	public function test_the_request_carries_the_secret_the_token_and_the_address(): void {
		$this->answer( 200, array( 'success' => true ) );

		$this->configured()->verify( 'a-good-token', '203.0.113.7' );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( Turnstile::VERIFY_URL, $this->requests[0][0] );

		$body = $this->requests[0][1]['body'] ?? null;

		$this->assertIsArray( $body );
		$this->assertSame( self::SECRET, $body['secret'] ?? null );
		$this->assertSame( 'a-good-token', $body['response'] ?? null );
		$this->assertSame( '203.0.113.7', $body['remoteip'] ?? null );
	}

	/**
	 * An empty token never leaves the site.
	 *
	 * @return void
	 */
	public function test_an_empty_token_is_refused_without_asking(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertFalse( $this->configured()->verify( '', '203.0.113.7' ) );
	}

	/**
	 * Neither does one longer than a token can be.
	 *
	 * @return void
	 */
	public function test_an_oversized_token_is_refused_without_asking(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$token = str_repeat( 'a', Turnstile::MAX_TOKEN_LENGTH + 1 );

		$this->assertFalse( $this->configured()->verify( $token, '203.0.113.7' ) );
	}

	/**
	 * A token exactly at the limit is still asked about.
	 *
	 * The boundary is worth one test: an off-by-one here would refuse valid
	 * tokens on a gate whose refusals are deliberately unexplained, which is
	 * the hardest possible bug to report.
	 *
	 * @return void
	 */
	public function test_a_token_at_the_limit_is_still_verified(): void {
		$this->answer( 200, array( 'success' => true ) );

		$token = str_repeat( 'a', Turnstile::MAX_TOKEN_LENGTH );

		$this->assertTrue( $this->configured()->verify( $token, '203.0.113.7' ) );
		$this->assertCount( 1, $this->requests );
	}

	/**
	 * Cloudflare saying no is a no.
	 *
	 * @return void
	 */
	public function test_an_unsuccessful_answer_is_refused(): void {
		$this->answer(
			200,
			array(
				'success'     => false,
				'error-codes' => array( 'timeout-or-duplicate' ),
			)
		);

		$turnstile = $this->configured();

		$this->assertFalse( $turnstile->verify( 'a-spent-token', '203.0.113.7' ) );
		$this->assertSame( array( 'timeout-or-duplicate' ), $turnstile->error_codes() );
	}

	/**
	 * A truthy `success` that is not `true` is not a success.
	 *
	 * JSON gives no reason to expect the string `"true"` here, which is exactly
	 * why the comparison is strict: the failure mode of a loose one is a gate
	 * that opens for anything non-empty.
	 *
	 * @return void
	 */
	public function test_a_success_that_is_not_the_boolean_is_refused(): void {
		$this->answer( 200, array( 'success' => 'true' ) );

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * A token minted by another widget of David's does not open this form.
	 *
	 * @return void
	 */
	public function test_a_token_for_another_action_is_refused(): void {
		$this->answer(
			200,
			array(
				'success' => true,
				'action'  => 'newsletter',
			)
		);

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * So does a missing action: absent is not "ours".
	 *
	 * @return void
	 */
	public function test_an_answer_with_no_action_is_refused(): void {
		// Built by hand rather than through `answer()`, which fills the action in.
		$this->response = array(
			'code' => 200,
			'body' => (string) wp_json_encode(
				array(
					'success'  => true,
					'hostname' => self::HOST,
				)
			),
		);

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * A token minted on a host this site is not is refused.
	 *
	 * This is the check that stops a token farmed on a staging copy, or on a
	 * `localhost` widget with the same sitekey, from opening production.
	 *
	 * @return void
	 */
	public function test_a_token_from_another_hostname_is_refused(): void {
		$this->answer(
			200,
			array(
				'success'  => true,
				'action'   => Turnstile::ACTION,
				'hostname' => 'localhost',
			)
		);

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * The hostname comparison ignores case, because DNS does.
	 *
	 * @return void
	 */
	public function test_the_hostname_is_compared_without_case(): void {
		$this->answer(
			200,
			array(
				'success'  => true,
				'action'   => Turnstile::ACTION,
				'hostname' => strtoupper( self::HOST ),
			)
		);

		$this->assertTrue( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * Cloudflare not answering at all is a refusal, not a pass.
	 *
	 * This is the fail-closed case, and it is the expensive one: if Cloudflare
	 * is unreachable the contact form stops accepting messages. That is the
	 * direction a security gate has to fail in, and ADR-0023 says so out loud
	 * rather than leaving it to be discovered.
	 *
	 * @return void
	 */
	public function test_a_transport_error_is_refused(): void {
		$this->response = new class() {

			/**
			 * What the pretend `WP_Error` says went wrong.
			 *
			 * @return string
			 */
			public function get_error_message(): string {
				return 'cURL error 28: Operation timed out';
			}
		};

		$turnstile = $this->configured();

		$this->assertFalse( $turnstile->verify( 'a-token', '203.0.113.7' ) );
		$this->assertStringContainsString( 'timed out', $turnstile->failure() );
	}

	/**
	 * So is anything that is not a 2xx.
	 *
	 * @param int $status What Cloudflare answered with.
	 * @return void
	 *
	 * @dataProvider provide_statuses_that_are_not_success
	 */
	public function test_a_non_2xx_answer_is_refused( int $status ): void {
		$this->answer( $status, array( 'success' => true ) );

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * The statuses worth naming: a redirect, a refusal, an outage.
	 *
	 * @return array<string, array{int}>
	 */
	public static function provide_statuses_that_are_not_success(): array {
		return array(
			'moved'        => array( 301 ),
			'bad request'  => array( 400 ),
			'forbidden'    => array( 403 ),
			'rate limited' => array( 429 ),
			'server error' => array( 500 ),
			'gateway'      => array( 502 ),
			'nothing'      => array( 0 ),
		);
	}

	/**
	 * A body that is not JSON is refused rather than guessed at.
	 *
	 * An HTML error page from a captive portal or a proxy is the realistic
	 * shape of this, and it arrives with HTTP 200.
	 *
	 * @param string $body What came back instead of JSON.
	 * @return void
	 *
	 * @dataProvider provide_bodies_that_are_not_an_answer
	 */
	public function test_a_body_that_is_not_json_is_refused( string $body ): void {
		$this->response = array(
			'code' => 200,
			'body' => $body,
		);

		$this->assertFalse( $this->configured()->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * The ways a body can fail to be an answer.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_bodies_that_are_not_an_answer(): array {
		return array(
			'empty'       => array( '' ),
			'html'        => array( '<!doctype html><title>502</title>' ),
			'truncated'   => array( '{"success": tr' ),
			'a bare true' => array( 'true' ),
			'a string'    => array( '"success"' ),
		);
	}

	/**
	 * With no expected hostname to check against, nothing verifies.
	 *
	 * A site that cannot say what host it is cannot tell a token minted on it
	 * from a token minted anywhere else, and the safe answer to that is no.
	 *
	 * @return void
	 */
	public function test_an_empty_hostname_allowlist_refuses_everything(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$turnstile = new Turnstile( self::SITEKEY, self::SECRET, array() );

		$this->assertFalse( $turnstile->verify( 'a-token', '203.0.113.7' ) );
	}

	/**
	 * Left to itself, the allowlist is the site's own host.
	 *
	 * This is the property that makes the feature safe to deploy without a
	 * setting: production accepts tokens for production and nothing else,
	 * derived rather than typed.
	 *
	 * @return void
	 */
	public function test_the_expected_hostname_is_the_sites_own(): void {
		Functions\when( 'home_url' )->justReturn( 'https://dpaternina.com/' );
		Functions\when( 'wp_parse_url' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- this IS the stand-in for wp_parse_url().
			static fn ( string $url, int $component = -1 ): mixed => parse_url( $url, $component )
		);

		$turnstile = new Turnstile( self::SITEKEY, self::SECRET );

		$this->assertSame( array( 'dpaternina.com' ), $turnstile->hostnames() );
	}

	/**
	 * Nothing verified is remembered: each token is asked about on its own.
	 *
	 * A Turnstile token is redeemable once, so a cached "this verified" would be
	 * a replay window with a lifetime. Two verifications, two requests.
	 *
	 * @return void
	 */
	public function test_no_verification_is_reused(): void {
		$this->answer( 200, array( 'success' => true ) );

		$turnstile = $this->configured();

		$turnstile->verify( 'a-token', '203.0.113.7' );
		$turnstile->verify( 'a-token', '203.0.113.7' );

		$this->assertCount( 2, $this->requests );
	}

	/**
	 * A failure from one call does not linger into the next.
	 *
	 * @return void
	 */
	public function test_a_later_success_clears_the_earlier_failure(): void {
		$turnstile = $this->configured();

		$this->answer(
			200,
			array(
				'success'     => false,
				'error-codes' => array( 'invalid-input-response' ),
			)
		);
		$turnstile->verify( 'a-token', '203.0.113.7' );

		$this->answer( 200, array( 'success' => true ) );
		$turnstile->verify( 'another-token', '203.0.113.7' );

		$this->assertSame( '', $turnstile->failure() );
		$this->assertSame( array(), $turnstile->error_codes() );
	}

	/**
	 * Nothing this class reports about a failure is a credential.
	 *
	 * @return void
	 */
	public function test_the_failure_never_carries_the_secret_or_the_token(): void {
		$this->answer( 500, array( 'success' => false ) );

		$turnstile = $this->configured();

		$turnstile->verify( 'a-secret-looking-token', '203.0.113.7' );

		$reported = $turnstile->failure() . ' ' . implode( ' ', $turnstile->error_codes() );

		$this->assertStringNotContainsString( self::SECRET, $reported );
		$this->assertStringNotContainsString( 'a-secret-looking-token', $reported );
	}

	/**
	 * A Turnstile configured the way a working site is.
	 *
	 * @return Turnstile
	 */
	private function configured(): Turnstile {
		return new Turnstile( self::SITEKEY, self::SECRET, array( self::HOST ) );
	}

	/**
	 * Set what Cloudflare answers with, filling in the fields a pass needs.
	 *
	 * The action and the hostname default to the ones that verify, so a test
	 * about `success` is not also a test about them, and a test about them says
	 * so by naming them.
	 *
	 * @param int                  $status The HTTP status.
	 * @param array<string, mixed> $answer The decoded body.
	 * @return void
	 */
	private function answer( int $status, array $answer ): void {
		$body = (string) wp_json_encode(
			array_merge(
				array(
					'action'   => Turnstile::ACTION,
					'hostname' => self::HOST,
				),
				$answer
			)
		);

		$this->response = array(
			'code' => $status,
			'body' => $body,
		);
	}
}
