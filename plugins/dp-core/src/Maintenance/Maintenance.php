<?php
/**
 * Maintenance mode, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Maintenance;

/**
 * One object that knows the whole of the maintenance screen.
 *
 * `Watch\Watch`'s shape: the collaborators are built here, the one `Gate` is
 * shared between the curtain and the reminder so there is a single answer to
 * "is it on", and everything is registered together on `init`.
 *
 * It lives in the plugin and not in the theme on purpose. The screen has to work
 * while the theme is half-configured or being switched — that is the situation
 * David is in while he builds the site by hand — so it depends on no template,
 * no block and no theme asset. Off is the shipped state: activating this plugin
 * changes nothing about a running site until the switch on Settings → General is
 * ticked.
 */
final class Maintenance {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings The switch and the copy, on Settings → General.
	 * @param Curtain  $curtain  The two hooks that answer the public.
	 * @param Notice   $notice   The reminder in wp-admin and in the admin bar.
	 */
	private function __construct(
		private readonly Settings $settings,
		private readonly Curtain $curtain,
		private readonly Notice $notice
	) {}

	/**
	 * Build the parts with their shared gate.
	 *
	 * Nothing in this call path touches WordPress, so it is safe before `init`.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version, for cache busting the stylesheet.
	 * @return self
	 */
	public static function create( string $plugin_file, string $version ): self {
		$gate = new Gate();

		return new self(
			new Settings(),
			new Curtain( $gate, new Screen( $plugin_file, $version ) ),
			new Notice( $gate )
		);
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->settings->register();
		$this->curtain->register();
		$this->notice->register();
	}
}
