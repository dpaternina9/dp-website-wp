<?php
/**
 * Which of the contact panel's three faces is showing.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * Form, sent, failed — the three `sc-if` branches in the design's contact view.
 *
 * A string-backed enum rather than three booleans, because the three are
 * mutually exclusive and a pair of booleans can express states the design has
 * no drawing for.
 */
enum State: string {

	/**
	 * The form, ready to be filled in.
	 */
	case Form = 'form';

	/**
	 * "It landed. Thanks."
	 */
	case Sent = 'sent';

	/**
	 * "That did not send."
	 */
	case Failed = 'failed';
}
