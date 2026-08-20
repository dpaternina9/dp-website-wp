<?php
/**
 * The one place this plugin touches WP-CLI.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

/**
 * An adapter over WP-CLI, and the only file allowed to know it exists.
 *
 * Every call goes through `is_callable()` on a callable array rather than a
 * static call on the class. That is not indirection for its own sake:
 *
 * - WP-CLI genuinely may not be loaded. The plugin runs under a web request far
 *   more often than under a command, and `is_callable()` is the honest guard for
 *   an optional integration — the same shape as `function_exists()` around any
 *   other environment-dependent API.
 * - It keeps the symbol out of static analysis. WP-CLI is not a Composer
 *   dependency of this project and there are no stubs for it in `vendor/`, so a
 *   direct `\WP_CLI::add_command()` is an unresolvable symbol at PHPStan level 9
 *   — an error that cannot be ignored and should not be, since it is telling the
 *   truth about what is in scope.
 *
 * Confining that to one small class is the trade: everything else in `Cli\` is
 * ordinary typed PHP that PHPStan checks fully, and this file is where the
 * boundary is paid for.
 */
final class WpCli implements Output {

	/**
	 * Whether a command is being run right now.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return defined( 'WP_CLI' ) && true === constant( 'WP_CLI' ) && class_exists( 'WP_CLI' );
	}

	/**
	 * Hand a command to WP-CLI.
	 *
	 * @param string $name    The command, e.g. `dp seed`.
	 * @param object $command An invokable object. Its `__invoke()` docblock becomes the help text.
	 * @return bool Whether WP-CLI accepted it.
	 */
	public function add_command( string $name, object $command ): bool {
		$register = array( 'WP_CLI', 'add_command' );

		if ( ! is_callable( $register ) ) {
			return false;
		}

		$register( $name, $command );

		return true;
	}

	/**
	 * Write a line.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function line( string $message ): void {
		$this->call( 'log', $message );
	}

	/**
	 * Write the closing line of a command that worked.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function success( string $message ): void {
		$this->call( 'success', $message );
	}

	/**
	 * Write a line about something that went wrong but is not fatal.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->call( 'warning', $message );
	}

	/**
	 * Call a WP-CLI output method if there is one to call.
	 *
	 * @param string $method  The method name on `WP_CLI`.
	 * @param string $message The line.
	 * @return void
	 */
	private function call( string $method, string $message ): void {
		$callable = array( 'WP_CLI', $method );

		if ( ! is_callable( $callable ) ) {
			return;
		}

		$callable( $message );
	}
}
