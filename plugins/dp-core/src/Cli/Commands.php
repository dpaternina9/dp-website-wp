<?php
/**
 * What this plugin adds to WP-CLI.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

use DP\Core\Fixture\Seeder;

/**
 * Registers the `dp` command namespace, when there is a WP-CLI to register with.
 *
 * `dp` rather than `dp_core` because that is what CLAUDE.md and `docs/plan.md`
 * both write (`wp dp seed`, `wp dp migrate`). The PHPCS global prefix rule does
 * not reach a WP-CLI command name — it governs PHP symbols, and there are none
 * here that are not namespaced.
 */
final class Commands {

	/**
	 * Constructor.
	 *
	 * @param WpCli $wp_cli The adapter over WP-CLI.
	 */
	public function __construct( private readonly WpCli $wp_cli ) {}

	/**
	 * Build with the default adapter.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self( new WpCli() );
	}

	/**
	 * Register every command, if a command is what is running.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->wp_cli->is_available() ) {
			return;
		}

		$this->wp_cli->add_command( 'dp seed', new SeedCommand( Seeder::create(), $this->wp_cli ) );
	}
}
