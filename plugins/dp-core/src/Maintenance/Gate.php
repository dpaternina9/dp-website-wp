<?php
/**
 * The one decision: is this request shown the site, or the screen?
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

/**
 * Who gets the site while maintenance is on, and who gets the curtain.
 *
 * Everything about the feature reduces to `closes()`, and it is kept away from
 * the hooks and away from the HTML so it can be read and tested as the sentence
 * it is: **the curtain is down when the switch is on, this is not automation,
 * and whoever is asking cannot edit content.**
 *
 * **The capability is `edit_posts`, not `read`.** A subscriber is a member of
 * the public — an account on a site is not a reason to show somebody a site that
 * is not finished — and it is not `manage_options` either, because the point of
 * the check is "can see the work in progress", which is an editor's business as
 * much as an administrator's. `dp_maintenance_capability` moves it without a
 * deploy: returning `'read'` opens the site to every signed-in account, and
 * returning a capability nobody holds closes it to everybody including David,
 * which is why the filter takes a capability rather than a boolean — there is no
 * value it can return that unlocks wp-admin, and no value that locks him out of
 * it.
 *
 * **Automation is never curtained.** WP-CLI and cron reach neither of the hooks
 * `Curtain` uses, so this is belt and braces rather than the mechanism — but
 * they are the two contexts where a 503 would be a silent scheduled-job failure
 * rather than a page somebody sees, so they are named here instead of being left
 * to an implementation detail of the front controller.
 *
 * Nothing in here runs a query. It is one autoloaded option and one capability
 * check, on a hook that fires on every front-end request.
 */
final class Gate {

	/**
	 * What a person needs to see the real site while maintenance is on.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'edit_posts';

	/**
	 * Whether the screen is switched on at all.
	 *
	 * @return bool
	 */
	public function is_on(): bool {
		return Settings::is_on();
	}

	/**
	 * Whether this request is shown the maintenance screen instead of the site.
	 *
	 * @return bool
	 */
	public function closes(): bool {
		if ( ! $this->is_on() ) {
			return false;
		}

		if ( self::is_automation() ) {
			return false;
		}

		return ! $this->may_see_the_site();
	}

	/**
	 * Whether whoever is asking may see the real site.
	 *
	 * @return bool
	 */
	public function may_see_the_site(): bool {
		return current_user_can( self::capability() );
	}

	/**
	 * The capability the check is made against.
	 *
	 * @return string
	 */
	public static function capability(): string {
		/**
		 * Filters the capability that sees the real site during maintenance.
		 *
		 * The way to widen the gate without a deploy: `'read'` lets every
		 * signed-in account through, a custom capability lets one role through.
		 * A filter returning something that is not a non-empty string is ignored,
		 * because a blank capability would be granted to everybody and quietly
		 * turn the feature off.
		 *
		 * @since 0.1.0
		 *
		 * @param string $capability The capability. Default 'edit_posts'.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$filtered = apply_filters( 'dp_maintenance_capability', self::CAPABILITY );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : self::CAPABILITY;
	}

	/**
	 * Whether this request is WP-CLI or cron.
	 *
	 * @return bool
	 */
	private static function is_automation(): bool {
		if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
			return true;
		}

		return wp_doing_cron();
	}
}
