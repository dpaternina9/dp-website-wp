<?php
/**
 * The editing form for a post type, derived from its fields.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Editor;

use DP\Core\Content\Meta;
use DP\Core\Content\MetaAuth;
use DP\Core\Content\MetaField;

/**
 * Turns `Meta`'s declarations into the form David edits them with.
 *
 * ADR-0016 found that eight fields on `post` had no editor control and deleted
 * them, because a post already knew everything they held. The thirty-three
 * fields on `dp_role`, `dp_ship` and `dp_video` have the same defect and the
 * opposite answer: nothing else knows what they hold, so they get controls.
 *
 * **The form is generated, never written out.** It is one loop over
 * `Meta::fields_for()`, so a field added to the content model arrives in the
 * editor with it, and a field that is registered but unreachable cannot exist —
 * `FieldFormTest` asserts the form covers every registered key exactly once,
 * which is the assertion the eight deleted fields would have failed.
 *
 * **A field is a paragraph unless it cannot be.** `Control` holds that decision
 * and its reasoning. Seventeen of the thirty-three are `core/paragraph` blocks
 * bound through `core/post-meta`, which is core's own mechanism and needs
 * nothing of ours at display time; the other sixteen are one of six small
 * blocks.
 *
 * **A bound paragraph carries no label of its own**, so one is placed in front
 * of it. The six control blocks draw their own `<label>` and do not need it.
 */
final class FieldForm {

	/**
	 * The block that draws a label in front of a bound paragraph.
	 *
	 * @var string
	 */
	public const LABEL_BLOCK = 'dp/field-label';

	/**
	 * The block a bound field is edited in.
	 *
	 * @var string
	 */
	public const BOUND_BLOCK = 'core/paragraph';

	/**
	 * The bindings source a bound field reads and writes through.
	 *
	 * @var string
	 */
	public const BINDINGS_SOURCE = 'core/post-meta';

	/**
	 * What a bound paragraph saves into `post_content`.
	 *
	 * An empty paragraph, laid out the way `@wordpress/blocks`' own serializer
	 * lays one out — a newline either side of the tag. The value is not in here
	 * and must not be: the binding reads it from post meta, and a copy in the
	 * markup would be the second source of truth this whole phase exists to
	 * avoid.
	 *
	 * @var string
	 */
	private const BOUND_HTML = "\n<p></p>\n";

	/**
	 * Constructor.
	 *
	 * @param Meta $meta The field declarations. Stateless, so a default is safe.
	 */
	public function __construct( private readonly Meta $meta = new Meta( new MetaAuth() ) ) {}

	/**
	 * The form for one post type, as `register_post_type()`'s `template`.
	 *
	 * @param string $post_type The post type.
	 * @return list<array{0: string, 1: array<string, mixed>}> Empty when the type has no fields.
	 */
	public function template( string $post_type ): array {
		return array_map(
			static fn ( FormBlock $block ): array => $block->to_template(),
			$this->blocks( $post_type )
		);
	}

	/**
	 * The form for one post type, as block markup for `post_content`.
	 *
	 * @param string $post_type The post type.
	 * @return string Empty when the type has no fields.
	 */
	public function markup( string $post_type ): string {
		return implode(
			"\n\n",
			array_map(
				static fn ( FormBlock $block ): string => serialize_block( $block->to_parsed() ),
				$this->blocks( $post_type )
			)
		);
	}

	/**
	 * Every block in one post type's form, in field order.
	 *
	 * @param string $post_type The post type.
	 * @return list<FormBlock>
	 */
	public function blocks( string $post_type ): array {
		$blocks = array();

		foreach ( $this->meta->fields_for( $post_type ) as $field ) {
			foreach ( $this->field_blocks( $field ) as $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * The blocks one field is edited with.
	 *
	 * @param MetaField $field The declaration.
	 * @return list<FormBlock>
	 */
	private function field_blocks( MetaField $field ): array {
		$control = Control::of( $field );

		if ( Control::Bound === $control ) {
			return array(
				new FormBlock(
					self::LABEL_BLOCK,
					array(
						'label'   => $field->label,
						'help'    => $field->description,
						'metaKey' => $field->key,
					)
				),
				new FormBlock( self::BOUND_BLOCK, $this->bound_attributes( $field ), self::BOUND_HTML ),
			);
		}

		return array( new FormBlock( $control->block(), $this->control_attributes( $control, $field ) ) );
	}

	/**
	 * The attributes that bind one paragraph to one field.
	 *
	 * `metadata.name` is what the List View and the block breadcrumb call the
	 * block, so a screen reader moving through the form hears the field's name
	 * rather than "Paragraph" thirty times.
	 *
	 * @param MetaField $field The declaration.
	 * @return array<string, mixed>
	 */
	private function bound_attributes( MetaField $field ): array {
		return array(
			'placeholder' => $field->label,
			'metadata'    => array(
				'name'     => $field->label,
				'bindings' => array(
					'content' => array(
						'source' => self::BINDINGS_SOURCE,
						'args'   => array( 'key' => $field->key ),
					),
				),
			),
		);
	}

	/**
	 * The attributes one control block needs to draw itself.
	 *
	 * @param Control   $control The control kind.
	 * @param MetaField $field   The declaration.
	 * @return array<string, mixed>
	 */
	private function control_attributes( Control $control, MetaField $field ): array {
		$attributes = array(
			'metaKey'  => $field->key,
			'label'    => $field->label,
			'help'     => $field->description,

			/*
			 * The block's name in the List View and in its own `aria-label`.
			 * Without it every control on the screen is announced as its block
			 * type — "Field: date on the timeline", twice in a row on a Role —
			 * and the form is navigable by sight only.
			 */
			'metadata' => array( 'name' => $field->label ),
		);

		if ( Control::Choice === $control ) {
			$attributes['options'] = $this->options( $field );
		}

		if ( Control::Reference === $control ) {
			$attributes['reference'] = $field->reference;
		}

		if ( Control::Text === $control ) {
			$attributes['monospace'] = $field->preformatted;
		}

		return $attributes;
	}

	/**
	 * The choices an enum field offers.
	 *
	 * The values are the field's own `allowed` list, which came from
	 * `Tone::meta_values()` or `VideoSource::meta_values()`; nothing here
	 * restates them. They are shown verbatim as well, because a mapping from
	 * `teal` to "Teal" is a second vocabulary that can go out of step with the
	 * first for no gain. The empty string is the one value that needs saying
	 * differently: stored it means "not set", and shown as nothing it looks like
	 * a broken option.
	 *
	 * @param MetaField $field The declaration.
	 * @return list<array{value: string, label: string}>
	 */
	private function options( MetaField $field ): array {
		return array_values(
			array_map(
				static fn ( string $value ): array => array(
					'value' => $value,
					'label' => '' === $value ? __( '— not set —', 'dp-core' ) : $value,
				),
				$field->allowed
			)
		);
	}
}
