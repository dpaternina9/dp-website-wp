<?php
/**
 * The design's component files, read as markup rather than as prose.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Answers what the design declares on one element of one `.dc.html` file.
 *
 * `design-source/` expresses every value as an inline `style` attribute, because
 * the design tool has no stylesheet (CLAUDE.md §5). That is a liability when a
 * human reads it and an asset when a machine does: the file is a list of
 * elements each carrying its own complete declaration block, which is exactly
 * what a parity baseline needs.
 *
 * Nothing here writes to `design-source/`, and nothing here interprets a value —
 * `var()` references are left intact so the fixture reads the way the design
 * reads, and so the browser resolves both sides through the same tokens.
 */
final class DesignMarkup {

	/**
	 * Parsed documents, keyed by path relative to `design-source/`.
	 *
	 * @var array<string, DOMXPath>
	 */
	private array $documents = array();

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute path to `design-source/`.
	 */
	private function __construct( private readonly string $directory ) {}

	/**
	 * Read the design source at the repository's canonical location.
	 *
	 * @param string $repository_root Absolute path to the repository root.
	 * @return self
	 */
	public static function from_repository( string $repository_root ): self {
		return new self( rtrim( $repository_root, '/' ) . '/design-source' );
	}

	/**
	 * The declarations on the one element an XPath expression selects.
	 *
	 * The expression must select exactly one element. An expression that selects
	 * none means the design moved and the anchor did not follow; one that selects
	 * several means the anchor was never specific enough to be trusted. Both are
	 * failures rather than guesses, which is what makes `composer design:check`
	 * a useful thing to run in CI.
	 *
	 * @param string $file  Path relative to `design-source/`.
	 * @param string $xpath An expression selecting one element with a `style` attribute.
	 * @return array<string, string> Property name to raw value, in source order.
	 *
	 * @throws RuntimeException If the expression is invalid, or does not select exactly one element.
	 */
	public function declarations( string $file, string $xpath ): array {
		$found = $this->document( $file )->query( $xpath );

		if ( false === $found ) {
			throw new RuntimeException( sprintf( '%s: "%s" is not a valid XPath expression.', $file, $xpath ) );
		}

		if ( 1 !== $found->length ) {
			throw new RuntimeException(
				sprintf(
					'%s: "%s" selects %d elements, not one. The design has moved under the anchor; '
						. 're-read the component and point the anchor at what it says now.',
					$file,
					$xpath,
					$found->length
				)
			);
		}

		$element = $found->item( 0 );

		if ( ! $element instanceof DOMElement ) {
			throw new RuntimeException( sprintf( '%s: "%s" selects something that is not an element.', $file, $xpath ) );
		}

		return CssParser::declarations( $element->getAttribute( 'style' ) );
	}

	/**
	 * Parse one design-source file, once.
	 *
	 * @param string $file Path relative to `design-source/`.
	 * @return DOMXPath
	 *
	 * @throws RuntimeException If the file is missing or unreadable.
	 */
	private function document( string $file ): DOMXPath {
		if ( isset( $this->documents[ $file ] ) ) {
			return $this->documents[ $file ];
		}

		$path = $this->directory . '/' . ltrim( $file, '/' );

		/*
		 * design-source/ is a plain directory on disk, read from the command line
		 * before WordPress exists. WP_Filesystem is not available here, exactly as
		 * it is not available to DesignTokens.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $contents ) {
			throw new RuntimeException( sprintf( 'Cannot read the design source at %s.', $path ) );
		}

		$document = new DOMDocument();

		/*
		 * `.dc.html` is HTML with the design tool's own elements in it — `<x-dc>`,
		 * `<sc-if>`, `<dc-import>` — and `{{ }}` holes inside attribute values.
		 * The HTML parser takes all of that as ordinary unknown elements and
		 * ordinary attribute text; the only thing it objects to is the custom tag
		 * names, which is what the error suppression is for. Nothing here reads
		 * an entity or follows a reference, so there is nothing for an external
		 * entity to reach.
		 */
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="UTF-8">' . $contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->documents[ $file ] = new DOMXPath( $document );

		return $this->documents[ $file ];
	}
}
