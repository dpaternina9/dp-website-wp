<?php
/**
 * What happened to a submission, and what the page should therefore draw.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * The three states `dpaternina.dc.html` draws, as one value.
 *
 * The design's contact composition is an `sc-if` over `showForm`, `sent` and
 * `failed`. This is that switch, made explicit, so the block renders from a
 * value rather than from a pile of conditions — and so the handler can be
 * called by a test and asked what it decided without a page being rendered.
 *
 * A refused submission keeps the submission on it. The design's failure copy
 * says "your message is still in the form", and the only way that can be true
 * is if the refused text travels with the refusal.
 */
final class Outcome {

	/**
	 * Constructor.
	 *
	 * @param State           $state      Which panel to draw.
	 * @param Rejection|null  $rejection  Why, when the state is failed.
	 * @param Submission|null $submission What was sent, for a re-try.
	 */
	private function __construct(
		public readonly State $state,
		public readonly ?Rejection $rejection = null,
		public readonly ?Submission $submission = null
	) {}

	/**
	 * Nothing has been submitted: draw the form.
	 *
	 * @param Submission|null $submission Values to pre-fill, if any.
	 * @return self
	 */
	public static function form( ?Submission $submission = null ): self {
		return new self( State::Form, null, $submission );
	}

	/**
	 * It went.
	 *
	 * @return self
	 */
	public static function sent(): self {
		return new self( State::Sent );
	}

	/**
	 * It did not go, and here is the gate that closed.
	 *
	 * @param Rejection  $rejection  Which gate refused it.
	 * @param Submission $submission What was sent, so the re-try can carry it.
	 * @return self
	 */
	public static function failed( Rejection $rejection, Submission $submission ): self {
		return new self( State::Failed, $rejection, $submission );
	}
}
