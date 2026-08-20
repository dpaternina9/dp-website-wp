<?php
/**
 * Where a command writes.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

/**
 * A command's console, as far as a command is allowed to know.
 *
 * Three methods, because that is all any command in this plugin needs. The point
 * of the interface is not extensibility — it is that a command can be run and
 * asserted on from an integration test with no console attached at all.
 */
interface Output {

	/**
	 * Write a line.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function line( string $message ): void;

	/**
	 * Write the closing line of a command that worked.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function success( string $message ): void;

	/**
	 * Write a line about something that went wrong but is not fatal.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function warning( string $message ): void;
}
