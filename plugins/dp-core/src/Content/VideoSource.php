<?php
/**
 * Where a video is hosted.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- PHPCompatibility does not model enums, and reads `match ( $this )` in an enum method as
// `$this` outside an object. It is valid PHP 8.1, and this project targets 8.4.
/**
 * The two platforms the Watch grid knows about.
 *
 * Stored lower case because that is a data value; the design renders the label
 * in mono caps, which is presentation and lives in `label()`.
 *
 * Thumbnails are never uploaded — digest section 3.5 resolves them to public URLs
 * per platform. That resolution is Phase 12's; the enum is what makes it a
 * `match` there instead of a string comparison.
 */
enum VideoSource: string {

	case Twitch  = 'twitch';
	case YouTube = 'youtube';

	/**
	 * The caps label the design prints on the card.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Twitch  => 'TWITCH',
			self::YouTube => 'YOUTUBE',
		};
	}

	/**
	 * Every source, as stored.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map( static fn ( self $source ): string => $source->value, self::cases() );
	}

	/**
	 * Every accepted meta value: the sources plus the empty string.
	 *
	 * @return list<string>
	 */
	public static function meta_values(): array {
		return array_merge( array( '' ), self::values() );
	}

	/**
	 * Resolve a stored meta value, accepting the design's caps spelling.
	 *
	 * @param string $value Stored meta value, or the design's `TWITCH` / `YOUTUBE`.
	 * @return self|null
	 */
	public static function try_from_meta( string $value ): ?self {
		return self::tryFrom( strtolower( $value ) );
	}
}
