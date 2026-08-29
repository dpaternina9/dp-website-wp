<?php
/**
 * Handing a message to WordPress.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

use Closure;
use WP_Error;

/**
 * One `wp_mail()` call, with the SMTP plugin doing the delivering.
 *
 * `docs/plan.md` Phase 7 says `wp_mail` through the SMTP plugin, and that is
 * the whole design: this class composes a plain-text message and hands it over.
 * Nothing here knows about a transport, so swapping the mailer plugin changes
 * how the message leaves and nothing about what is in it.
 *
 * Three details are deliberate.
 *
 * **The From address is never the sender's.** A message claiming to come from
 * a stranger's domain fails SPF and DMARC at the receiving end, which is how a
 * contact form ends up silently undelivered. It comes from the site, and the
 * sender goes in `Reply-To`, so hitting reply still works.
 *
 * **The body is plain text.** The message was sanitised as plain text on the
 * way in, so there is nothing to render; a text body also means no HTML email
 * client decides how a stranger's words look in David's inbox.
 *
 * **A failure is a `WP_Error`, captured, not guessed at.** `wp_mail()` returns
 * false for several unrelated reasons and says which only through
 * `wp_mail_failed`. The listener is attached for the duration of the call and
 * removed after it, so this never picks up somebody else's failure.
 */
final class Mailer {

	/**
	 * The last failure `wp_mail()` reported, if any.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $failure = null;

	/**
	 * The failure listener, built once.
	 *
	 * `add_action()` identifies a closure by its object hash, so attaching
	 * `$this->remember( ... )` and then detaching a second `$this->remember( ... )`
	 * would attach a listener and remove nothing — two first-class callables
	 * over the same method are two objects. Holding one is the fix.
	 *
	 * @var Closure
	 */
	private readonly Closure $listener;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->listener = $this->remember( ... );
	}

	/**
	 * Send one submission.
	 *
	 * @param Submission $submission The message.
	 * @param string     $source     The URL the form was on, for the footer.
	 * @return bool
	 */
	public function send( Submission $submission, string $source = '' ): bool {
		$this->failure = null;

		add_action( 'wp_mail_failed', $this->listener );

		$sent = wp_mail(
			$this->recipient(),
			$this->subject( $submission ),
			$this->body( $submission, $source ),
			$this->headers( $submission )
		);

		remove_action( 'wp_mail_failed', $this->listener );

		return $sent;
	}

	/**
	 * What went wrong, for the log.
	 *
	 * @return string Empty when the last send succeeded.
	 */
	public function failure(): string {
		return $this->failure instanceof WP_Error ? $this->failure->get_error_message() : '';
	}

	/**
	 * Remember a delivery failure.
	 *
	 * @param mixed $error The error `wp_mail()` raised.
	 * @return void
	 */
	public function remember( mixed $error ): void {
		if ( $error instanceof WP_Error ) {
			$this->failure = $error;
		}
	}

	/**
	 * Where the message goes.
	 *
	 * The Delivery address on Settings → General (`DP\Core\Contact\Settings`)
	 * when David has set one; the administration address otherwise, so the form
	 * works on a fresh install with nothing configured.
	 *
	 * @return string
	 */
	private function recipient(): string {
		$admin = get_option( 'admin_email' );
		$admin = is_string( $admin ) ? $admin : '';

		$stored  = Settings::recipient();
		$default = '' === $stored ? $admin : $stored;

		/**
		 * Filters where contact messages are delivered.
		 *
		 * Receives the Delivery address setting when one is set, and Settings to
		 * General's administration address otherwise — so the filter is an
		 * override layered on top of what David set in wp-admin, not the only
		 * way to route delivery.
		 *
		 * @since 0.1.0
		 *
		 * @param string $recipient The address messages are sent to.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$recipient = (string) apply_filters( 'dp_contact_recipient', $default );

		return is_email( $recipient ) === $recipient ? $recipient : $default;
	}

	/**
	 * The subject line.
	 *
	 * @param Submission $submission The message.
	 * @return string
	 */
	private function subject( Submission $submission ): string {
		$name = get_option( 'blogname' );
		$site = wp_specialchars_decode( is_string( $name ) ? $name : '', ENT_QUOTES );

		return sprintf(
			/* translators: 1: the site's name, 2: the sender's name. */
			__( '[%1$s] A note from %2$s', 'dp-core' ),
			$site,
			$submission->name
		);
	}

	/**
	 * The message body, as plain text.
	 *
	 * @param Submission $submission The message.
	 * @param string     $source     The URL the form was on.
	 * @return string
	 */
	private function body( Submission $submission, string $source ): string {
		$lines = array(
			sprintf(
				/* translators: %s: the sender's name. */
				__( 'From: %s', 'dp-core' ),
				$submission->name
			),
			sprintf(
				/* translators: %s: the sender's email address. */
				__( 'Email: %s', 'dp-core' ),
				$submission->email
			),
			'',
			$submission->message,
		);

		if ( '' !== $source ) {
			$lines[] = '';
			$lines[] = '--';
			$lines[] = sprintf(
				/* translators: %s: the URL of the page the form was on. */
				__( 'Sent from %s', 'dp-core' ),
				$source
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * The headers, including the reply address.
	 *
	 * @param Submission $submission The message.
	 * @return list<string>
	 */
	private function headers( Submission $submission ): array {
		return array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $submission->name, $submission->email ),
		);
	}
}
