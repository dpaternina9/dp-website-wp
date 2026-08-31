<?php
/**
 * The reminder that the public is looking at a curtain.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

use WP_Admin_Bar;

/**
 * Two places that say the site is dark, because forgetting is the failure mode.
 *
 * A signed-in David sees the real site on every URL, which is the whole point of
 * the capability check and is also exactly why maintenance mode gets left on: to
 * the only person who visits the site while it is being built, nothing looks
 * wrong. So the state is announced where he is rather than where the visitor is:
 *
 * - **An admin notice on every admin screen**, not dismissible, because a notice
 *   that can be waved away is a notice that will be. It carries the link to the
 *   switch, so acting on it is one click from wherever he read it.
 * - **An admin-bar item**, which is the half that appears on the *front end* —
 *   the place where the site looks finished and the notice cannot follow.
 *
 * Both are gated on `manage_options` rather than on `Gate::capability()`. Their
 * entire content is a link to Settings → General, and that screen is
 * `manage_options`; showing an editor a warning about a screen they cannot reach
 * would be telling somebody about a problem that is not theirs to fix.
 */
final class Notice {

	/**
	 * What a person needs to be shown the reminder.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The admin-bar node's id.
	 *
	 * @var string
	 */
	public const NODE = 'dp-maintenance';

	/**
	 * Constructor.
	 *
	 * @param Gate $gate The decision, asked only whether the switch is on.
	 */
	public function __construct( private readonly Gate $gate ) {}

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', $this->notice( ... ) );
		add_action( 'admin_bar_menu', $this->bar_item( ... ), 100 );
	}

	/**
	 * Print the notice.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Maintenance mode is on.', 'dp-core' ),
			esc_html__( 'Everyone who is not signed in sees the maintenance screen instead of the site, and search engines are told not to index it.', 'dp-core' ),
			esc_url( self::settings_url() ),
			esc_html__( 'Turn it off in Settings → General.', 'dp-core' )
		);
	}

	/**
	 * Add the admin-bar item.
	 *
	 * @param WP_Admin_Bar $bar The admin bar being built.
	 * @return void
	 */
	public function bar_item( WP_Admin_Bar $bar ): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => __( 'Maintenance mode on', 'dp-core' ),
				'href'  => self::settings_url(),
				'meta'  => array(
					'title' => __( 'The public sees the maintenance screen. Turn it off in Settings → General.', 'dp-core' ),
				),
			)
		);
	}

	/**
	 * Whether this person, on this request, is told.
	 *
	 * @return bool
	 */
	private function should_show(): bool {
		return $this->gate->is_on() && current_user_can( self::CAPABILITY );
	}

	/**
	 * The switch, linked to by its own field id.
	 *
	 * `add_settings_section()` gives the section no id to anchor on, but
	 * `label_for` gives the checkbox one, so the fragment lands on the control
	 * itself rather than at the top of a long screen.
	 *
	 * @return string
	 */
	private static function settings_url(): string {
		return admin_url( 'options-general.php#' . Settings::ENABLED );
	}
}
