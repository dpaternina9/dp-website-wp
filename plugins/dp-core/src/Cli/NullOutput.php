<?php
/**
 * An output that goes nowhere.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

/**
 * Collects what a command would have printed instead of printing it.
 *
 * `beStrictAboutOutputDuringTests` is on in `phpunit.xml.dist`, so a command that
 * echoed during an integration test would fail the run. This is how the seed
 * command is exercised for real without that happening, and the collected lines
 * are themselves assertable.
 */
final class NullOutput implements Output {

	/**
	 * Everything written, in order.
	 *
	 * @var list<string>
	 */
	private array $lines = array();

	/**
	 * Write a line.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function line( string $message ): void {
		$this->lines[] = $message;
	}

	/**
	 * Write the closing line of a command that worked.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function success( string $message ): void {
		$this->lines[] = $message;
	}

	/**
	 * Write a line about something that went wrong but is not fatal.
	 *
	 * @param string $message The line.
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->lines[] = $message;
	}

	/**
	 * Everything written so far.
	 *
	 * @return list<string>
	 */
	public function lines(): array {
		return $this->lines;
	}
}
