<?php
/**
 * One registered field.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * The declaration of a single meta field.
 *
 * Every field in the content model is one of these, so registration is a loop
 * over data rather than forty near-identical `register_post_meta()` calls that
 * drift from one another. What a field cannot express here, it may not do.
 *
 * Three of the properties exist for the editor rather than for the database,
 * and they are here rather than in a parallel table in `DP\Core\Editor` for the
 * reason the paragraph above gives: a field is declared once. `label` is the
 * short noun phrase a control is labelled with — the `description` is a
 * sentence, which is help text and not a label. `reference` names the post type
 * an ID field points at, which is the whole difference between a number box and
 * a picker. `preformatted` says the line breaks are content.
 */
final class MetaField {

	/**
	 * Constructor.
	 *
	 * @param string             $key          Meta key. Prefixed `dp_` without exception.
	 * @param string             $type         One of `string`, `integer`, `number`, `boolean`, `array`.
	 * @param string             $label        The short name of the field, as a control is labelled.
	 * @param string             $description  What the field holds. Shown in the REST schema and as help text.
	 * @param bool               $multiline    Whether the value keeps its line breaks.
	 * @param bool               $preformatted Whether the line breaks are content rather than wrapping.
	 * @param bool               $is_year      Whether the number is a decimal year, validated through `Year`.
	 * @param array<int, string> $allowed      Closed set of accepted values, if the field is an enum.
	 * @param string             $reference    The post type an ID field points at, if it points at one.
	 * @param float|null         $minimum      Lowest accepted number, if the field is bounded.
	 * @param float|null         $maximum      Highest accepted number, if the field is bounded.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $type,
		public readonly string $label,
		public readonly string $description,
		public readonly bool $multiline = false,
		public readonly bool $preformatted = false,
		public readonly bool $is_year = false,
		public readonly array $allowed = array(),
		public readonly string $reference = '',
		public readonly ?float $minimum = null,
		public readonly ?float $maximum = null
	) {}

	/**
	 * Whether the field accepts only a closed set of values.
	 *
	 * @return bool
	 */
	public function is_enum(): bool {
		return array() !== $this->allowed;
	}

	/**
	 * Whether the field holds the ID of another post.
	 *
	 * @return bool
	 */
	public function is_reference(): bool {
		return '' !== $this->reference;
	}

	/**
	 * The value a post without this field reports.
	 *
	 * Registered so `get_post_meta()` and the REST response agree on what
	 * "unset" looks like, instead of one saying `''` and the other `null`.
	 *
	 * @return string|int|float|bool|list<string>
	 */
	public function default_value(): string|int|float|bool|array {
		return match ( $this->type ) {
			'integer' => 0,
			'number'  => 0.0,
			'boolean' => false,
			'array'   => array(),
			default   => '',
		};
	}
}
