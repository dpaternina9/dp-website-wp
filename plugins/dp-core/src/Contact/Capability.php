<?php
/**
 * The one capability the contact form is gated on.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

use WP_User;

/**
 * `dp_send_message` — a real capability, held by everyone until David says otherwise.
 *
 * `CLAUDE.md` section 1.4 requires a capability check on **every** write path.
 * A public contact form has no logged-in sender to check, and the usual way out
 * of that — "it is public, so skip the capability" — turns the rule into a
 * rule with an exception, and the next write path inherits the exception.
 *
 * So the capability is made real instead. `dp_send_message` is granted through
 * `user_has_cap` to every visitor, logged in or not, **unless** something says
 * no. Three things can say no, in this order:
 *
 * 1. `dp_contact_form_enabled` returning false — the site-wide switch. This is
 *    how David closes the form: one filter in a snippet, no template edit, and
 *    the block stops rendering a form as well as refusing submissions, because
 *    both ask this same question.
 * 2. A role or a user that has the capability explicitly denied. Because it is
 *    a genuine capability, `$user->add_cap( 'dp_send_message', false )` blocks
 *    one logged-in account, and any membership plugin can do the same.
 * 3. `dp_contact_can_send`, the last word, for anything else.
 *
 * The point is not that anyone is usually refused. The point is that there is
 * exactly one `current_user_can()` call in front of the mailer, that revoking
 * it is a supported act rather than a code change, and that the integration
 * suite can close it and watch the form refuse — which is the test the rule
 * exists to make possible.
 */
final class Capability {

	/**
	 * The capability a sender must hold.
	 *
	 * @var string
	 */
	public const SEND = 'dp_send_message';

	/**
	 * Attach the grant.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'user_has_cap', $this->grant( ... ), 10, 4 );
	}

	/**
	 * Grant the capability unless something refuses it.
	 *
	 * An explicit `false` already sitting in `$capabilities` is left alone: that
	 * is a deliberate denial on the role or the user, and a default grant that
	 * overrode it would make the denial unusable.
	 *
	 * @param mixed $capabilities The capabilities this user has, keyed by name.
	 * @param mixed $required     The primitive capabilities being asked for.
	 * @param mixed $arguments    The arguments to the check. Unused.
	 * @param mixed $user         The user being asked about.
	 * @return array<string, bool>
	 */
	public function grant( mixed $capabilities, mixed $required, mixed $arguments, mixed $user ): array {
		unset( $arguments );

		$granted = array();

		if ( is_array( $capabilities ) ) {
			foreach ( $capabilities as $name => $value ) {
				$granted[ (string) $name ] = (bool) $value;
			}
		}

		/*
		 * This filter runs on every capability check WordPress makes, including
		 * every one the admin makes on every screen. Answering only when this
		 * capability is the one being asked about keeps the two filters below
		 * off that path entirely.
		 */
		if ( ! is_array( $required ) || ! in_array( self::SEND, $required, true ) ) {
			return $granted;
		}

		if ( array_key_exists( self::SEND, $granted ) ) {
			return $granted;
		}

		$granted[ self::SEND ] = self::form_is_open() && $this->allowed( $user instanceof WP_User ? $user : null );

		return $granted;
	}

	/**
	 * Whether the form is accepting messages at all.
	 *
	 * Asked by the block before it renders a form and by the handler before it
	 * accepts one, so a closed form cannot be submitted by a page that was
	 * cached while it was open.
	 *
	 * @return bool
	 */
	public static function form_is_open(): bool {
		/**
		 * Filters whether the contact form accepts messages.
		 *
		 * Return false to close it. The block renders the "that did not send"
		 * panel's sibling — the contact methods beside it stay, so there is
		 * still a way to reach David.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $open Whether the form is open. Default true.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		return (bool) apply_filters( 'dp_contact_form_enabled', true );
	}

	/**
	 * The last word on one sender.
	 *
	 * @param WP_User|null $user The user being asked about, or null for a visitor.
	 * @return bool
	 */
	private function allowed( ?WP_User $user ): bool {
		/**
		 * Filters whether one sender may use the contact form.
		 *
		 * Runs after the site-wide switch and after any explicit denial on the
		 * role or the user, so it is the place for everything else — a blocklist,
		 * a country rule, a maintenance window.
		 *
		 * @since 0.1.0
		 *
		 * @param bool         $allowed Whether this sender may send. Default true.
		 * @param WP_User|null $user    The sender, or null when logged out.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		return (bool) apply_filters( 'dp_contact_can_send', true, $user );
	}
}
