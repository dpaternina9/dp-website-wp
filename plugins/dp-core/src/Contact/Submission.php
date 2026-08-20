<?php
/**
 * What a visitor sent, after sanitisation and before any decision.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * The posted form as typed values, sanitised at the edge and never re-read.
 *
 * Everything that touches `$_POST` in this project happens in `from_post()`,
 * once, with `wp_unslash()` and a sanitiser on every field (`CLAUDE.md`
 * section 1.4). After that the submission is a readonly object, so no code path
 * downstream can reach a raw superglobal even by accident, and a test can build
 * any combination of good and bad fields without a request.
 *
 * `sanitize_textarea_field()` rather than `wp_kses_post()` for the message: the
 * message becomes a plain-text email, so markup in it is not "content to be
 * escaped later" — it is noise, and stripping it at the edge means the mailer
 * never has to think about it.
 */
final class Submission {

	/**
	 * The field names, which are also the `name` attributes on the form.
	 *
	 * @var array<string, string>
	 */
	public const FIELDS = array(
		'name'     => 'dp_contact_name',
		'email'    => 'dp_contact_email',
		'message'  => 'dp_contact_message',
		'honeypot' => 'dp_contact_reference',
		'stamp'    => 'dp_contact_stamp',
		'nonce'    => 'dp_contact_nonce',
	);

	/**
	 * The hidden field marking a POST as this form's, so nothing else is read.
	 *
	 * @var string
	 */
	public const MARKER = 'dp_contact_send';

	/**
	 * Constructor.
	 *
	 * @param string $name     The sender's name, sanitised.
	 * @param string $email    The sender's address, sanitised.
	 * @param string $message  The message, sanitised as plain text.
	 * @param string $honeypot The field no person ever fills in.
	 * @param string $stamp    The signed timestamp the form was drawn with.
	 * @param string $nonce    The nonce the form was drawn with.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $email,
		public readonly string $message,
		public readonly string $honeypot = '',
		public readonly string $stamp = '',
		public readonly string $nonce = ''
	) {}

	/**
	 * Whether this POST is the contact form's at all.
	 *
	 * @return bool
	 */
	public static function is_present(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this only answers "is this our form"; Handler verifies the nonce before anything is read or done.
		return isset( $_POST[ self::MARKER ] );
	}

	/**
	 * Read the current request's POST body.
	 *
	 * @return self
	 */
	public static function from_post(): self {
		return new self(
			self::text( self::FIELDS['name'] ),
			sanitize_email( self::text( self::FIELDS['email'] ) ),
			self::textarea( self::FIELDS['message'] ),
			self::text( self::FIELDS['honeypot'] ),
			self::text( self::FIELDS['stamp'] ),
			self::text( self::FIELDS['nonce'] )
		);
	}

	/**
	 * Whether every field a message needs is filled in and well formed.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return '' !== trim( $this->name )
			&& '' !== trim( $this->message )
			&& '' !== $this->email
			&& is_email( $this->email ) === $this->email;
	}

	/**
	 * The same submission with the fields a re-try needs and nothing else.
	 *
	 * The design's failed panel says "your message is still in the form", so the
	 * failure state carries the three typed fields back as hidden inputs behind
	 * a fresh nonce and a fresh stamp. The old nonce and the old stamp are
	 * deliberately dropped: re-posting a refused stamp would fail the timing
	 * check on its second life just as it did on its first.
	 *
	 * @return self
	 */
	public function without_credentials(): self {
		return new self( $this->name, $this->email, $this->message );
	}

	/**
	 * One single-line field, unslashed and sanitised.
	 *
	 * @param string $field The POST key.
	 * @return string
	 */
	private static function text( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is one of the fields this reads; Handler::handle() verifies it before the submission is acted on.
		if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
		return sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
	}

	/**
	 * One multi-line field, unslashed and sanitised.
	 *
	 * @param string $field The POST key.
	 * @return string
	 */
	private static function textarea( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see text(); the nonce is verified in Handler::handle() before any of this is used.
		if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
		return sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
	}
}
