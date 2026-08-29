<?php
/**
 * Every word the contact panel says, resolved from the block's attributes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * The panel's copy: David's where he set it, the design's where he did not.
 *
 * The contact form is the most personal copy on the site — "It landed. Thanks."
 * is a voice, not a UI label — and it used to be written into the render
 * callback, which made it the one piece of front-end text nothing in wp-admin
 * could change (CLAUDE.md rule 2). Every string is now a block attribute on
 * `dp/contact-form`, edited from the block's inspector, and this object is the
 * single place the attributes are read and defaulted.
 *
 * Two kinds of string, two rules:
 *
 * - **A control's text falls back when blanked.** A heading, a field label or a
 *   button with no words is not a quieter panel, it is a broken one — an
 *   unlabelled input and an empty submit button both fail WCAG outright. So
 *   `word()` treats a blank as "nothing chosen" and answers with the default.
 * - **A line is David's even when it is empty.** The helper note under the form
 *   and the paragraphs on the result panels are optional prose; clearing one in
 *   the inspector is a decision to say nothing, and the renderer omits the
 *   element. Placeholders follow the same rule, because an input with no
 *   placeholder is an ordinary input.
 *
 * The defaults live here — not in `block.json` alone — so they pass through
 * `__()` and translate. `block.json` carries the same English strings so the
 * editor's inspector shows them prefilled; the integration suite holds the two
 * lists identical, which is the same one-source discipline `CodeLabel` applies
 * to its default.
 */
final class PanelCopy {

	/**
	 * Constructor.
	 *
	 * @param string $heading             The form's heading.
	 * @param string $name_label          The name field's label.
	 * @param string $name_placeholder    The name field's placeholder.
	 * @param string $email_label         The email field's label.
	 * @param string $email_placeholder   The email field's placeholder.
	 * @param string $message_label       The message field's label.
	 * @param string $message_placeholder The message field's placeholder.
	 * @param string $submit_label        The submit button's text.
	 * @param string $note                The line under the form. '' omits it.
	 * @param string $sent_heading        The sent panel's heading.
	 * @param string $sent_line           The sent panel's paragraph. '' omits it.
	 * @param string $failed_heading      The failed panel's heading.
	 * @param string $failed_line         The ordinary failure paragraph. '' omits it.
	 * @param string $rate_limited_line   The rate-limited paragraph. '' omits it.
	 * @param string $try_again_label     The retry button's text.
	 * @param string $send_another_label  The "back to the form" link's text.
	 * @param string $read_something_label The "read something" link's text.
	 * @param string $email_instead_label The mailto fallback's text.
	 */
	private function __construct(
		public readonly string $heading,
		public readonly string $name_label,
		public readonly string $name_placeholder,
		public readonly string $email_label,
		public readonly string $email_placeholder,
		public readonly string $message_label,
		public readonly string $message_placeholder,
		public readonly string $submit_label,
		public readonly string $note,
		public readonly string $sent_heading,
		public readonly string $sent_line,
		public readonly string $failed_heading,
		public readonly string $failed_line,
		public readonly string $rate_limited_line,
		public readonly string $try_again_label,
		public readonly string $send_another_label,
		public readonly string $read_something_label,
		public readonly string $email_instead_label
	) {}

	/**
	 * Resolve the copy for one render.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return self
	 */
	public static function from_attributes( array $attributes ): self {
		$defaults = self::defaults();

		return new self(
			self::word( $attributes, 'heading', $defaults['heading'] ),
			self::word( $attributes, 'nameLabel', $defaults['nameLabel'] ),
			self::line( $attributes, 'namePlaceholder', $defaults['namePlaceholder'] ),
			self::word( $attributes, 'emailLabel', $defaults['emailLabel'] ),
			self::line( $attributes, 'emailPlaceholder', $defaults['emailPlaceholder'] ),
			self::word( $attributes, 'messageLabel', $defaults['messageLabel'] ),
			self::line( $attributes, 'messagePlaceholder', $defaults['messagePlaceholder'] ),
			self::word( $attributes, 'submitLabel', $defaults['submitLabel'] ),
			self::line( $attributes, 'note', $defaults['note'] ),
			self::word( $attributes, 'sentHeading', $defaults['sentHeading'] ),
			self::line( $attributes, 'sentLine', $defaults['sentLine'] ),
			self::word( $attributes, 'failedHeading', $defaults['failedHeading'] ),
			self::line( $attributes, 'failedLine', $defaults['failedLine'] ),
			self::line( $attributes, 'rateLimitedLine', $defaults['rateLimitedLine'] ),
			self::word( $attributes, 'tryAgainLabel', $defaults['tryAgainLabel'] ),
			self::word( $attributes, 'sendAnotherLabel', $defaults['sendAnotherLabel'] ),
			self::word( $attributes, 'readSomethingLabel', $defaults['readSomethingLabel'] ),
			self::word( $attributes, 'emailInsteadLabel', $defaults['emailInsteadLabel'] )
		);
	}

	/**
	 * The design's copy, keyed by attribute name.
	 *
	 * One list, three consumers: `from_attributes()` falls back to it,
	 * `block.json` repeats it so the inspector shows the strings being edited,
	 * and the integration suite compares the two so they cannot drift.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'heading'            => __( 'Send a note', 'dp-core' ),
			'nameLabel'          => __( 'Name', 'dp-core' ),
			'namePlaceholder'    => __( 'Your name', 'dp-core' ),
			'emailLabel'         => __( 'Email', 'dp-core' ),
			'emailPlaceholder'   => __( 'you@company.com', 'dp-core' ),
			'messageLabel'       => __( "What's on your mind?", 'dp-core' ),
			'messagePlaceholder' => __( 'A project, a question, a recommendation for a good roaster.', 'dp-core' ),
			'submitLabel'        => __( 'Send it', 'dp-core' ),
			'note'               => __( 'Goes straight to my inbox. No list, no autoreply.', 'dp-core' ),
			'sentHeading'        => __( 'It landed. Thanks.', 'dp-core' ),
			'sentLine'           => __( 'I read everything and reply to almost everything, usually within a couple of days.', 'dp-core' ),
			'failedHeading'      => __( 'That did not send.', 'dp-core' ),
			'failedLine'         => __( 'Something on my end dropped it, and I would rather tell you than pretend otherwise. Your message is still in the form — try again, or email me directly and skip this thing entirely.', 'dp-core' ),
			'rateLimitedLine'    => __( 'That is a few in a short space of time, so this one was held back rather than sent. Give it ten minutes and try again — or email me directly and skip this thing entirely.', 'dp-core' ),
			'tryAgainLabel'      => __( 'Try again', 'dp-core' ),
			'sendAnotherLabel'   => __( 'Send another', 'dp-core' ),
			'readSomethingLabel' => __( 'Read something', 'dp-core' ),
			'emailInsteadLabel'  => __( 'Email instead', 'dp-core' ),
		);
	}

	/**
	 * A control's text: the attribute unless it is blank.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $key        The attribute's name.
	 * @param string               $fallback   The design's copy.
	 * @return string
	 */
	private static function word( array $attributes, string $key, string $fallback ): string {
		$value = $attributes[ $key ] ?? null;

		return is_string( $value ) && '' !== trim( $value ) ? $value : $fallback;
	}

	/**
	 * A line of prose: the attribute as set, even when set to nothing.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @param string               $key        The attribute's name.
	 * @param string               $fallback   The design's copy.
	 * @return string
	 */
	private static function line( array $attributes, string $key, string $fallback ): string {
		$value = $attributes[ $key ] ?? null;

		return is_string( $value ) ? $value : $fallback;
	}
}
