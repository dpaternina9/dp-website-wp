<?php
/**
 * The seventh gate: Cloudflare's challenge, verified server-side.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * A Turnstile token, checked with Cloudflare before a message is allowed through.
 *
 * This reverses a decision the project made in `docs/plan.md` Phase 7 and wrote
 * into `Rejection`'s docblock — no third-party captcha, because it is a script
 * served by somebody else that sees every visitor who reaches the page.
 * ADR-0023 records the reversal and the price of it. What matters here is the
 * shape the reversal was given, which is four properties:
 *
 * **Unconfigured is inert, not broken.** Both constants have to be present and
 * non-empty for any of this to happen: no script, no widget, no siteverify call,
 * no refusal that did not exist before. A site with nothing in `wp-config.php`
 * behaves byte for byte the way it did before this class existed, which is what
 * keeps `wp-env` working, keeps the e2e sweep's "no off-origin request on first
 * paint" true where it runs, and keeps the promise the default the right way
 * round: a deployment opts *in* to talking to Cloudflare.
 *
 * **The keys are constants, never options.** The same reasoning as
 * `DP\Core\Resume\CloudflareBrowserRendering`: a secret in `wp_options` is a
 * secret in every database backup. The sitekey is public by design and the
 * secret never leaves this class — it is not logged, not printed, and not
 * shown on the settings screen that reports whether it is set.
 *
 * **The expected hostname comes from the site, not from a setting.** A token is
 * minted for the host the widget was drawn on, and Cloudflare says which host
 * that was. Deriving the allowlist from `home_url()` means each deployment
 * validates its own host with nothing to configure — and, more usefully, means
 * production cannot accept a token minted against `localhost`, which is exactly
 * the mistake a hand-typed allowlist makes once and never notices.
 * `DP_TURNSTILE_HOSTNAMES` replaces the derived list for the deployment whose
 * public host is not the one `home_url()` returns; it is an escape hatch, not
 * part of the ordinary setup.
 *
 * **Every failure is a refusal.** A token that is missing, absurdly long, or
 * answered for with a network error, a non-2xx status, a body that is not JSON,
 * `success: false`, the wrong action or the wrong hostname all come back the
 * same way: `false`. There is no path through this class that treats "I could
 * not tell" as "it was fine". The cost of that is stated plainly in ADR-0023:
 * if Cloudflare is unreachable the contact form stops accepting messages, and
 * that is the direction a security gate has to fail in.
 *
 * Nothing is cached. A Turnstile token is redeemable exactly once, so a cached
 * "this token verified" would be a replay window with a lifetime, and the
 * second life of a token Cloudflare has already spent is a refusal anyway.
 */
final class Turnstile {

	/**
	 * The constant naming the public sitekey, from `wp-config.php`.
	 *
	 * @var string
	 */
	public const SITEKEY = 'DP_TURNSTILE_SITEKEY';

	/**
	 * The constant naming the private secret, from `wp-config.php`.
	 *
	 * @var string
	 */
	public const SECRET = 'DP_TURNSTILE_SECRET';

	/**
	 * The constant naming an explicit hostname allowlist, comma separated.
	 *
	 * @var string
	 */
	public const HOSTNAMES = 'DP_TURNSTILE_HOSTNAMES';

	/**
	 * The `data-action` the widget is drawn with and siteverify must echo back.
	 *
	 * Cloudflare returns whatever action the widget was rendered with, so
	 * checking it is what stops a token minted by some other widget on some
	 * other form of David's from opening this one — the same property a nonce
	 * action gives a nonce.
	 *
	 * @var string
	 */
	public const ACTION = 'contact';

	/**
	 * Where a token is redeemed.
	 *
	 * @var string
	 */
	public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * The script that draws the widget, for whoever enqueues it.
	 *
	 * @var string
	 */
	public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	/**
	 * The longest token this will carry to Cloudflare, in bytes.
	 *
	 * Cloudflare documents tokens as being at most 2048 characters. The check
	 * is not about correctness — a longer string would simply be refused at the
	 * other end — it is about not spending a 10-second HTTP request on a body
	 * somebody pasted a megabyte into.
	 *
	 * @var int
	 */
	public const MAX_TOKEN_LENGTH = 2048;

	/**
	 * How long to wait for Cloudflare, in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 10;

	/**
	 * Why the last verification failed, in a word. '' when none has failed.
	 *
	 * @var string
	 */
	private string $failure = '';

	/**
	 * The `error-codes` Cloudflare returned with the last refusal.
	 *
	 * @var list<string>
	 */
	private array $error_codes = array();

	/**
	 * Constructor.
	 *
	 * All three arguments default to null, which means "read `wp-config.php`".
	 * That is deliberately the default rather than the special case: `new
	 * Turnstile()` is the production object, so no wiring anywhere can end up
	 * with a silently disabled gate by forgetting to pass something. Passing
	 * values explicitly is how a test states a configuration, the same way
	 * `Stamp` takes the current time so a test can move it. Nothing here
	 * touches WordPress, so this is safe to build before `init`.
	 *
	 * @param string|null       $sitekey   The public sitekey, or null to read the constant.
	 * @param string|null       $secret    The private secret, or null to read the constant.
	 * @param list<string>|null $hostnames The expected hostnames, or null to derive them.
	 */
	public function __construct(
		private readonly ?string $sitekey = null,
		private readonly ?string $secret = null,
		private readonly ?array $hostnames = null
	) {}

	/**
	 * Attach the seam the theme loads `api.js` through.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'dp_contact_turnstile_script', $this->script_url( ... ) );
	}

	/**
	 * Whether this site has both keys, and therefore whether any of this happens.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->sitekey() && '' !== $this->secret();
	}

	/**
	 * The public sitekey the widget is drawn with.
	 *
	 * @return string
	 */
	public function sitekey(): string {
		return $this->sitekey ?? $this->constant( self::SITEKEY );
	}

	/**
	 * The hostnames a token may claim to have been minted on, lowercased.
	 *
	 * @return list<string>
	 */
	public function hostnames(): array {
		if ( null !== $this->hostnames ) {
			return $this->hostnames;
		}

		$named = $this->split( $this->constant( self::HOSTNAMES ) );

		if ( array() !== $named ) {
			return $named;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? array( strtolower( $host ) ) : array();
	}

	/**
	 * The URL of the script that draws the widget, or '' when unconfigured.
	 *
	 * Answers `dp_contact_turnstile_script`, which is how the theme asks
	 * whether there is a widget to load a script for without naming a class in
	 * this plugin — the same seam shape as `dp_destination_url` in the other
	 * direction. A theme that gets '' enqueues nothing, which is also what
	 * happens when this plugin is not installed at all and nobody answers.
	 *
	 * @param mixed $url Whatever an earlier filter produced. Ignored.
	 * @return string
	 */
	public function script_url( mixed $url = '' ): string {
		unset( $url );

		return $this->is_configured() ? self::SCRIPT_URL : '';
	}

	/**
	 * Redeem one token with Cloudflare, and say whether it was good.
	 *
	 * Fails closed at every step. The four things that have to be true are
	 * `success`, the action the widget was drawn with, a hostname this site
	 * expects, and an answer that arrived and parsed at all.
	 *
	 * @param string $token The `cf-turnstile-response` the visitor sent.
	 * @param string $ip    The sender's address, for Cloudflare's own scoring.
	 * @return bool
	 */
	public function verify( string $token, string $ip ): bool {
		$this->failure     = '';
		$this->error_codes = array();

		if ( ! $this->is_configured() ) {
			return $this->refuse( 'not-configured' );
		}

		if ( '' === $token || strlen( $token ) > self::MAX_TOKEN_LENGTH ) {
			return $this->refuse( 'no-usable-token' );
		}

		$expected = $this->hostnames();

		if ( array() === $expected ) {
			return $this->refuse( 'no-expected-hostname' );
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'secret'   => $this->secret(),
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->refuse( 'transport: ' . $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status > 299 ) {
			return $this->refuse( 'http-' . (string) $status );
		}

		$answer = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $answer ) ) {
			return $this->refuse( 'unreadable-answer' );
		}

		$this->error_codes = $this->codes( $answer['error-codes'] ?? null );

		if ( true !== ( $answer['success'] ?? null ) ) {
			return $this->refuse( 'rejected' );
		}

		if ( self::ACTION !== ( $answer['action'] ?? null ) ) {
			return $this->refuse( 'wrong-action' );
		}

		$hostname = $answer['hostname'] ?? null;

		if ( ! is_string( $hostname ) || ! in_array( strtolower( $hostname ), $expected, true ) ) {
			return $this->refuse( 'wrong-hostname' );
		}

		return true;
	}

	/**
	 * Why the last verification failed, for the log. Never the token or the secret.
	 *
	 * @return string
	 */
	public function failure(): string {
		return $this->failure;
	}

	/**
	 * What Cloudflare called the last refusal, when it said anything at all.
	 *
	 * @return list<string>
	 */
	public function error_codes(): array {
		return $this->error_codes;
	}

	/**
	 * The private secret. Deliberately not public: nothing outside needs it.
	 *
	 * @return string
	 */
	private function secret(): string {
		return $this->secret ?? $this->constant( self::SECRET );
	}

	/**
	 * Record why this refusal happened, and refuse.
	 *
	 * @param string $reason A short, non-secret description.
	 * @return false
	 */
	private function refuse( string $reason ): bool {
		$this->failure = $reason;

		return false;
	}

	/**
	 * One `wp-config.php` constant as a trimmed string, or '' when unset.
	 *
	 * @param string $name The constant's name.
	 * @return string
	 */
	private function constant( string $name ): string {
		if ( ! defined( $name ) ) {
			return '';
		}

		$value = constant( $name );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * A comma-separated list of hostnames as a lowercased list.
	 *
	 * @param string $names The raw constant value.
	 * @return list<string>
	 */
	private function split( string $names ): array {
		$hostnames = array();

		foreach ( explode( ',', $names ) as $hostname ) {
			$hostname = strtolower( trim( $hostname ) );

			if ( '' !== $hostname ) {
				$hostnames[] = $hostname;
			}
		}

		return $hostnames;
	}

	/**
	 * Cloudflare's `error-codes`, narrowed to the strings it promises.
	 *
	 * @param mixed $codes Whatever was in the decoded body.
	 * @return list<string>
	 */
	private function codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$strings = array();

		foreach ( $codes as $code ) {
			if ( is_string( $code ) ) {
				$strings[] = $code;
			}
		}

		return $strings;
	}
}
