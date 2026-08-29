<?php
/**
 * Which control a field is edited with.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Editor;

use DP\Core\Content\MetaField;

/**
 * The one decision this phase makes per field: what David types into.
 *
 * `Bound` is the default and the preferred answer — a `core/paragraph` whose
 * `content` is bound to the field through core's own `core/post-meta` bindings
 * source. It is editable in the canvas, it is core's mechanism rather than ours,
 * and it costs no JavaScript at all.
 *
 * The other six exist because a binding cannot express them. Core's bindings
 * carry a **string** into a rich-text attribute and carry a string back out, and
 * five of the shapes in this content model are not strings:
 *
 * - `Flag` — a boolean. A paragraph reading "true" is not a checkbox.
 * - `Choice` — a closed set. The whole value of an enum is that the editor
 *   offers the five tones and refuses a sixth; free text offers anything and the
 *   sanitiser silently empties what it does not recognise.
 * - `Lines` — a list of strings. There is no rich-text attribute of that shape.
 * - `Year` — a decimal year whose fraction is a month (`Year`). Bound as text it
 *   is a box you type `2026.4` into and hope.
 * - `Reference` — a post ID. This is the phase's largest single win: `dp_ship`
 *   currently attaches to a role by typing a post ID into a field called
 *   `dp_role_id`.
 * - `Text` — a multi-line string. This one is the subtle case, and it is the
 *   reason `multiline` is consulted here at all. A bound paragraph collects rich
 *   text, so a soft line break arrives as `<br>` — and every multi-line field in
 *   this model is sanitised with `sanitize_textarea_field()`, which strips tags
 *   without putting a newline back. The line break would vanish on save. A
 *   textarea sends `\n`, which that sanitiser keeps.
 */
enum Control: string {

	case Bound     = 'bound';
	case Text      = 'text';
	case Year      = 'year';
	case Choice    = 'choice';
	case Flag      = 'flag';
	case Lines     = 'lines';
	case Reference = 'reference';

	/**
	 * The control one field earns.
	 *
	 * Order matters: a reference is an integer and an enum is a string, so the
	 * narrower facts are asked about first.
	 *
	 * @param MetaField $field The declaration.
	 * @return self
	 */
	public static function of( MetaField $field ): self {
		return match ( true ) {
			$field->is_reference() => self::Reference,
			'boolean' === $field->type => self::Flag,
			'array' === $field->type => self::Lines,
			$field->is_enum() => self::Choice,
			$field->is_year => self::Year,
			$field->multiline => self::Text,
			default => self::Bound,
		};
	}

	/**
	 * The block that draws this control, or the empty string for a binding.
	 *
	 * @return string
	 */
	public function block(): string {
		return match ( $this ) {
			self::Bound     => '',
			self::Text      => 'dp/meta-text',
			self::Year      => 'dp/meta-year',
			self::Choice    => 'dp/meta-choice',
			self::Flag      => 'dp/meta-flag',
			self::Lines     => 'dp/meta-lines',
			self::Reference => 'dp/meta-reference',
		};
	}

	/**
	 * Every block name a control can name, in declaration order.
	 *
	 * @return list<string>
	 */
	public static function blocks(): array {
		$names = array();

		foreach ( self::cases() as $control ) {
			$name = $control->block();

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return $names;
	}
}
