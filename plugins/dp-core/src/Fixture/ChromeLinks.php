<?php
/**
 * The seam the active theme fills its own chrome links through.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * Asks the theme for saved template markup, and checks the shape of the answer.
 *
 * ADR-0018 removed the layer that resolved a chrome button's `href` at render
 * time, which is right and is not being reopened here. What it left behind is a
 * seeded site whose header, footer, front page and 404 ship the design's words
 * with no links behind them — a site nobody can navigate, produced by the one
 * command whose whole job is to produce a site somebody can look at.
 *
 * The links are put in the same way David would put them: as a saved
 * `wp_template` or `wp_template_part` post, which is what the site editor writes
 * when a button is linked by hand. The front end computes nothing.
 *
 * **This plugin does not know how to write that markup, and must not learn.**
 * The templates, the block names, the labels and the file names are the theme's
 * (CLAUDE.md section 2.1). So the seeder hands over a map of destination keys to
 * URLs — keys it owns, because it created the pages — and the theme hands back
 * finished markup. This class checks that what came back has the right *shape*
 * and nothing else: it never parses the content, never looks for a class, and
 * never assumes a file exists. With no theme answering, the list is empty and
 * the seeder writes nothing, which is the state the site was in before.
 */
final class ChromeLinks {

	/**
	 * The filter the active theme answers.
	 *
	 * @var string
	 */
	public const FILTER = 'dp_seed_chrome_links';

	/**
	 * The two post types a block theme stores an override in.
	 *
	 * @var list<string>
	 */
	private const TYPES = array( 'wp_template', 'wp_template_part' );

	/**
	 * Ask the theme, and return only the answers that are usable.
	 *
	 * @param array<string, string> $destinations Destination key to URL.
	 * @return list<array{type: string, slug: string, title: string, area: string, content: string}>
	 */
	public function collect( array $destinations ): array {
		/**
		 * Filters the saved chrome overrides a seed run should write.
		 *
		 * The active theme answers with one entry per template or template part
		 * it wants saved, each carrying finished block markup. `dp-core` writes
		 * what it is given and inspects none of it.
		 *
		 * @since 0.1.0
		 *
		 * @param array<int, mixed>     $overrides    What earlier filters decided.
		 * @param array<string, string> $destinations Destination key to URL, for
		 *                                            the pages this run created.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- `self::FILTER` is the literal `dp_seed_chrome_links`; `dp_` is this project's public filter prefix, and WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$answer = apply_filters( self::FILTER, array(), $destinations );

		if ( ! is_array( $answer ) ) {
			return array();
		}

		$overrides = array();

		foreach ( $answer as $entry ) {
			$override = $this->override( $entry );

			if ( null !== $override ) {
				$overrides[] = $override;
			}
		}

		return $overrides;
	}

	/**
	 * One entry of the theme's answer, or null if it is not one this can write.
	 *
	 * @param mixed $entry Whatever the theme put in the list.
	 * @return array{type: string, slug: string, title: string, area: string, content: string}|null
	 */
	private function override( mixed $entry ): ?array {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$type    = $entry['type'] ?? null;
		$slug    = $entry['slug'] ?? null;
		$content = $entry['content'] ?? null;
		$title   = $entry['title'] ?? '';
		$area    = $entry['area'] ?? '';

		if ( ! is_string( $type ) || ! in_array( $type, self::TYPES, true ) ) {
			return null;
		}

		if ( ! is_string( $slug ) || '' === $slug || sanitize_title( $slug ) !== $slug ) {
			return null;
		}

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return null;
		}

		return array(
			'type'    => $type,
			'slug'    => $slug,
			'title'   => is_string( $title ) && '' !== $title ? $title : $slug,
			'area'    => is_string( $area ) ? $area : '',
			'content' => $content,
		);
	}
}
