<?php
/**
 * Why a contact submission was refused.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- PHPCompatibility does not model enums and reads `match ( $this )` in an enum
// method as `$this` outside an object. Valid PHP 8.1; this project targets 8.4.
/**
 * One case per gate the form has, so a refusal is never a bare `false`.
 *
 * The gates are `CLAUDE.md` section 1.4 read literally — capability **and**
 * nonce **and** sanitisation — plus the three the plan adds because a public
 * form has no logged-in visitor to lean on: a rate limit, a honeypot, and a
 * timing check. There is deliberately **no third-party captcha**: every one of
 * them is a script served by somebody else that sees every visitor who reaches
 * the page, which is a tracker with a job title (`docs/plan.md` Phase 7).
 *
 * Every case renders the design's *failed* panel. The reason never reaches the
 * page — it is returned to the caller so a test can assert which gate closed,
 * and it goes in the log, and that is all. Telling a sender which of six checks
 * refused them is telling a spammer which one to fix.
 */
enum Rejection: string {

	/**
	 * The nonce was missing, forged, or older than WordPress allows.
	 */
	case Nonce = 'nonce';

	/**
	 * The sender does not hold `Capability::SEND`.
	 */
	case Capability = 'capability';

	/**
	 * The hidden field a person never sees came back filled in.
	 */
	case Honeypot = 'honeypot';

	/**
	 * The form was submitted faster than a person could have typed it, or the
	 * signed timestamp it carries was not one we issued.
	 */
	case TooFast = 'too-fast';

	/**
	 * This sender has already sent as many as the window allows.
	 */
	case RateLimited = 'rate-limited';

	/**
	 * A required field was empty, or the address was not an address.
	 */
	case Incomplete = 'incomplete';

	/**
	 * Everything passed and `wp_mail()` still could not send it.
	 */
	case MailFailed = 'mail-failed';

	/**
	 * A line for the log. English, for a human, never shown to a visitor.
	 *
	 * @return string
	 */
	public function reason(): string {
		return match ( $this ) {
			self::Nonce       => 'the nonce was missing or expired',
			self::Capability  => 'the sender may not use the contact form',
			self::Honeypot    => 'the honeypot field was filled in',
			self::TooFast     => 'the form was submitted too quickly, or its timestamp was not ours',
			self::RateLimited => 'the sender has reached the rate limit',
			self::Incomplete  => 'a required field was empty or the address was invalid',
			self::MailFailed  => 'wp_mail() refused the message',
		};
	}

	/**
	 * Whether this refusal is worth telling the visitor about specifically.
	 *
	 * Only the rate limit is: it is the one refusal a person can act on, and
	 * saying "you have sent a few already" is kinder than "that did not send".
	 * Everything else gets the design's own failure copy.
	 *
	 * @return bool
	 */
	public function is_explained(): bool {
		return self::RateLimited === $this;
	}
}
