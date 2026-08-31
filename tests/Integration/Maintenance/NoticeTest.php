<?php
/**
 * Integration tests for the reminder that the site is dark.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Maintenance;

use DP\Core\Maintenance\Gate;
use DP\Core\Maintenance\Notice;
use WP_Admin_Bar;

/**
 * The state has to be visible to the one person it is invisible to.
 *
 * David sees the real site on every URL while the curtain is down — that is what
 * the capability check is for, and it is also exactly how a maintenance screen
 * gets left up for a week. So both halves of the reminder are asserted: that
 * they appear when the switch is on, that they say nothing when it is off, and
 * that they are not shown to somebody who could not act on them.
 */
final class NoticeTest extends MaintenanceTestCase {

	/**
	 * Load the admin-bar class.
	 *
	 * Core loads it from `_wp_admin_bar_init()`, which only runs on a request
	 * that is drawing a bar; a test that calls the callback directly has to ask
	 * for the class itself.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
	}

	/**
	 * With the switch off there is no notice and no admin-bar item.
	 *
	 * @return void
	 */
	public function test_it_says_nothing_while_the_switch_is_off(): void {
		$this->sign_in_as( 'administrator' );

		$this->assertSame( '', $this->notice() );
		$this->assertNull( $this->bar_node() );
	}

	/**
	 * On, and an administrator: a notice that cannot be dismissed, with the link.
	 *
	 * Not dismissible on purpose — a notice that can be waved away is a notice
	 * that will be, and the whole job of this one is to still be there tomorrow.
	 *
	 * @return void
	 */
	public function test_an_administrator_is_told(): void {
		$this->switch_on();
		$this->sign_in_as( 'administrator' );

		$notice = $this->notice();

		$this->assertStringContainsString( 'notice-warning', $notice );
		$this->assertStringNotContainsString( 'is-dismissible', $notice );
		$this->assertStringContainsString( 'options-general.php#dp_maintenance_enabled', $notice );
	}

	/**
	 * The admin-bar item is the half that appears on the front end.
	 *
	 * It is where the reminder can reach David while he is looking at the real
	 * site, which is the place the admin notice cannot follow him to.
	 *
	 * @return void
	 */
	public function test_the_admin_bar_carries_it_too(): void {
		$this->switch_on();
		$this->sign_in_as( 'administrator' );

		$node = $this->bar_node();

		$this->assertNotNull( $node );
		$this->assertStringContainsString( 'options-general.php', (string) ( $node->href ?? '' ) );
	}

	/**
	 * Somebody who cannot reach the switch is not told about it.
	 *
	 * The reminder is entirely a link to Settings → General, and that screen is
	 * `manage_options`; telling an editor about a control they cannot open is
	 * telling them about a problem that is not theirs.
	 *
	 * @return void
	 */
	public function test_an_editor_is_not_told(): void {
		$this->switch_on();
		$this->sign_in_as( 'editor' );

		$this->assertSame( '', $this->notice() );
		$this->assertNull( $this->bar_node() );
	}

	/**
	 * Signed out, there is nothing at all.
	 *
	 * @return void
	 */
	public function test_a_visitor_is_not_told(): void {
		$this->switch_on();
		wp_set_current_user( 0 );

		$this->assertSame( '', $this->notice() );
		$this->assertNull( $this->bar_node() );
	}

	/**
	 * The notice, as printed.
	 *
	 * @return string
	 */
	private function notice(): string {
		ob_start();
		( new Notice( new Gate() ) )->notice();
		$printed = ob_get_clean();

		return is_string( $printed ) ? $printed : '';
	}

	/**
	 * The admin-bar node, or null when none was added.
	 *
	 * @return object|null
	 */
	private function bar_node(): ?object {
		$bar = new WP_Admin_Bar();

		( new Notice( new Gate() ) )->bar_item( $bar );

		return $bar->get_node( Notice::NODE );
	}
}
