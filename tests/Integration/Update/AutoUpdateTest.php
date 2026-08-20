<?php
/**
 * Integration tests for the auto-update opt-in.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Update;

use DP\Core\Update\UpdateClient;
use WP_UnitTestCase;

/**
 * `auto_update_theme` / `auto_update_plugin`, scoped to our two packages.
 *
 * The offers below are shaped the way `WP_Automatic_Updater::should_update()`
 * shapes them in WordPress 7.1: an object with `id` set from the `Update URI`
 * header, plus `theme` or `plugin` identifying the item. The `$update` argument
 * is `null` when nothing has hooked the filter — core uses that to tell
 * "undecided" from "decided false" — so a pass-through has to return `null`
 * unchanged rather than helpfully casting it to a boolean.
 */
final class AutoUpdateTest extends WP_UnitTestCase {

	use SignedManifest;

	/**
	 * Register the client with a real (if throwaway) key.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->start_update_harness();
		$this->register_client();
	}

	/**
	 * Unhook everything.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->stop_update_harness();
		parent::tear_down();
	}

	/**
	 * Build an update offer object the way core would hand it to the filter.
	 *
	 * @param array<string, string> $fields Offer fields.
	 * @return object
	 */
	private function offer( array $fields ): object {
		return (object) $fields;
	}

	/**
	 * Our theme updates itself.
	 *
	 * @return void
	 */
	public function test_our_theme_opts_in(): void {
		$decision = apply_filters(
			'auto_update_theme',
			null,
			$this->offer(
				array(
					'id'    => 'https://updates.dpaternina.com/theme',
					'theme' => UpdateClient::THEME_STYLESHEET,
				)
			)
		);

		$this->assertTrue( $decision );
	}

	/**
	 * Our plugin updates itself.
	 *
	 * @return void
	 */
	public function test_our_plugin_opts_in(): void {
		$decision = apply_filters(
			'auto_update_plugin',
			null,
			$this->offer(
				array(
					'id'     => 'https://updates.dpaternina.com/core',
					'plugin' => UpdateClient::PLUGIN_FILE,
				)
			)
		);

		$this->assertTrue( $decision );
	}

	/**
	 * A wordpress.org-hosted theme is left exactly as core decided.
	 *
	 * @return void
	 */
	public function test_a_third_party_theme_is_left_alone(): void {
		$offer = $this->offer(
			array(
				'id'    => 'w.org/themes/twentytwentyfive',
				'theme' => 'twentytwentyfive',
			)
		);

		$this->assertNull( apply_filters( 'auto_update_theme', null, $offer ) );
		$this->assertFalse( apply_filters( 'auto_update_theme', false, $offer ) );
		$this->assertTrue( apply_filters( 'auto_update_theme', true, $offer ) );
	}

	/**
	 * A wordpress.org-hosted plugin is left exactly as core decided.
	 *
	 * @return void
	 */
	public function test_a_third_party_plugin_is_left_alone(): void {
		$offer = $this->offer(
			array(
				'id'     => 'w.org/plugins/stackable-ultimate-gutenberg-blocks',
				'plugin' => 'stackable-ultimate-gutenberg-blocks/plugin.php',
			)
		);

		$this->assertNull( apply_filters( 'auto_update_plugin', null, $offer ) );
		$this->assertFalse( apply_filters( 'auto_update_plugin', false, $offer ) );
	}

	/**
	 * Our host is not enough on its own; the item must be one of our two packages.
	 *
	 * Somebody else's theme carrying our `Update URI` would land on our filter.
	 * It does not get auto-updates turned on for it.
	 *
	 * @return void
	 */
	public function test_our_host_alone_does_not_enable_a_stranger(): void {
		$decision = apply_filters(
			'auto_update_theme',
			null,
			$this->offer(
				array(
					'id'    => 'https://updates.dpaternina.com/theme',
					'theme' => 'somebody-elses-theme',
				)
			)
		);

		$this->assertNull( $decision );
	}

	/**
	 * Our slug is not enough on its own either; the offer must come from our host.
	 *
	 * @return void
	 */
	public function test_our_slug_alone_does_not_enable_a_foreign_offer(): void {
		$decision = apply_filters(
			'auto_update_theme',
			null,
			$this->offer(
				array(
					'id'    => 'https://updates.example.invalid/theme',
					'theme' => UpdateClient::THEME_STYLESHEET,
				)
			)
		);

		$this->assertNull( $decision );
	}

	/**
	 * A malformed offer is passed straight through rather than crashing the run.
	 *
	 * `WP_Automatic_Updater` also applies these filters for `core` and
	 * `translation`, where the offer has neither `theme` nor `plugin`.
	 *
	 * @return void
	 */
	public function test_an_offer_without_an_id_is_passed_through(): void {
		$this->assertNull( apply_filters( 'auto_update_theme', null, (object) array() ) );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, (object) array( 'id' => 42 ) ) );
		$this->assertNull( apply_filters( 'auto_update_plugin', null, 'not an object' ) );
	}
}
