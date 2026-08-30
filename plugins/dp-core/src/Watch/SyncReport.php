<?php
/**
 * What one sync run did.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Watch;

/**
 * The outcome of a sync, in the shape three callers need it.
 *
 * `Fixture\SeedReport`'s pattern — counts plus `lines()` and `summary()` — so
 * the WP-CLI command, the admin notice and the "last run" line on Settings →
 * General all say the same thing about the same run.
 *
 * **It refuses to claim success it did not have.** Three states read as failure
 * or as nothing, and each of them says which:
 *
 * - Nothing configured. No credentials for either platform, so no call was made.
 * - Everything configured failed. The last good data is still on the page —
 *   that is the fail-soft contract — but this run added nothing to it.
 * - A platform answered with an empty list. That is a real answer and it is
 *   reported as one, because "0 added, 0 updated" under a success heading reads
 *   like a working sync and is the thing most likely to hide a broken one.
 */
final class SyncReport {

	/**
	 * Constructor.
	 *
	 * @param int                $added       Videos that became new `dp_video` posts.
	 * @param int                $updated     Existing posts a field was written to.
	 * @param int                $unchanged   Existing posts that already agreed with the platform.
	 * @param int                $unpublished Posts drafted because the platform no longer lists them.
	 * @param int                $locked      Fields newly recognised as the author's, and left alone from now on.
	 * @param array<int, string> $failures    Why a configured platform did not answer, one sentence each.
	 * @param array<int, string> $platforms   The platforms that did answer, by name.
	 * @param bool               $configured  Whether any platform was configured at all.
	 */
	public function __construct(
		public readonly int $added,
		public readonly int $updated,
		public readonly int $unchanged,
		public readonly int $unpublished,
		public readonly int $locked,
		public readonly array $failures,
		public readonly array $platforms,
		public readonly bool $configured
	) {}

	/**
	 * How many videos the platforms listed between them.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->added + $this->updated + $this->unchanged;
	}

	/**
	 * Whether this run is one to report as a success.
	 *
	 * @return bool
	 */
	public function ok(): bool {
		return $this->configured && array() === $this->failures && array() !== $this->platforms;
	}

	/**
	 * One sentence about the run, for a notice or a command's last line.
	 *
	 * @return string
	 */
	public function summary(): string {
		if ( ! $this->configured ) {
			return __( 'Nothing was synced: no Twitch or YouTube credentials are configured.', 'dp-core' );
		}

		if ( array() === $this->platforms ) {
			return trim( __( 'Nothing was synced.', 'dp-core' ) . ' ' . implode( ' ', $this->failures ) );
		}

		$platforms = implode( ', ', $this->platforms );

		if ( 0 === $this->total() ) {
			return trim(
				sprintf(
					/* translators: %s: a comma-separated list of platform names, e.g. "Twitch, YouTube". */
					__( '%s answered, but listed no videos. Nothing was written.', 'dp-core' ),
					$platforms
				) . ' ' . implode( ' ', $this->failures )
			);
		}

		return trim(
			sprintf(
				/* translators: 1: platform names, 2: how many added, 3: how many updated, 4: how many unchanged, 5: how many unpublished. */
				__( 'Synced from %1$s: %2$s added, %3$s updated, %4$s unchanged, %5$s unpublished.', 'dp-core' ),
				$platforms,
				number_format_i18n( $this->added ),
				number_format_i18n( $this->updated ),
				number_format_i18n( $this->unchanged ),
				number_format_i18n( $this->unpublished )
			) . ' ' . implode( ' ', $this->failures )
		);
	}

	/**
	 * The run, line by line, for a terminal.
	 *
	 * @return list<string>
	 */
	public function lines(): array {
		$lines = array();

		foreach ( $this->platforms as $platform ) {
			/* translators: %s: a platform name, e.g. "Twitch". */
			$lines[] = sprintf( __( '%s answered.', 'dp-core' ), $platform );
		}

		foreach ( $this->failures as $failure ) {
			$lines[] = $failure;
		}

		if ( $this->locked > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: how many fields. */
				_n(
					'%s field is now yours and will not be synced again.',
					'%s fields are now yours and will not be synced again.',
					$this->locked,
					'dp-core'
				),
				number_format_i18n( $this->locked )
			);
		}

		return $lines;
	}
}
