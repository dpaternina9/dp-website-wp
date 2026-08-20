<?php
/**
 * The `wp dp seed` command.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Cli;

use DP\Core\Fixture\Seeder;

/**
 * Puts the design's fixture into a site.
 *
 * The class knows nothing about WP-CLI: it takes the two arrays WP-CLI hands a
 * command and writes through an `Output`. That is what lets an integration test
 * invoke the real command with a `NullOutput` and assert on what it produced,
 * rather than testing a seeder that the command might or might not call the same
 * way.
 */
final class SeedCommand {

	/**
	 * Constructor.
	 *
	 * @param Seeder $seeder Writes the fixture.
	 * @param Output $output Where the run reports to.
	 */
	public function __construct(
		private readonly Seeder $seeder,
		private readonly Output $output
	) {}

	/**
	 * Seed the design's fixture: the timeline, the posts, the series, the categories and the pages.
	 *
	 * Safe to run twice. Every object is recorded against a stable key, so a
	 * second run updates what the first one made instead of duplicating it.
	 *
	 * The content is deliberately unfinished. Four of the six roles say
	 * "Placeholder role description", several statistics are an em dash, and the
	 * Privacy page describes a site that does not run analytics. That is the
	 * design's copy, carried through unchanged; do not treat any of it as fact.
	 *
	 * ## OPTIONS
	 *
	 * [--fresh]
	 * : Delete everything a previous seed created before writing. Only objects
	 * this command created are touched; anything written by hand is left alone.
	 *
	 * ## EXAMPLES
	 *
	 *     # Seed, or bring an existing seed up to date.
	 *     $ wp dp seed
	 *
	 *     # Throw the old fixture away first.
	 *     $ wp dp seed --fresh
	 *
	 * @param array<int, string>         $args       Positional arguments. None are taken.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );

		$report = $this->seeder->seed( ! empty( $assoc_args['fresh'] ) );

		foreach ( $report->lines() as $line ) {
			$this->output->line( $line );
		}

		$this->output->success( $report->summary() );
	}
}
