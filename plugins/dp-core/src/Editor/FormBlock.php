<?php
/**
 * One block in a post type's editing form.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Editor;

/**
 * A block, described once and emitted twice.
 *
 * The same form has to reach the editor by two routes that have nothing in
 * common. A **new** post gets it from `register_post_type()`'s `template`, which
 * WordPress hands the editor as nested arrays and `synchronizeBlocksWithTemplate()`
 * turns into blocks. An **existing** post — every post the seeder writes — gets
 * it from its own `post_content`, because `setupEditor()` applies the template
 * only when the post's status is `auto-draft`. Anything the seeder creates is
 * published the moment it exists and will never see the template again.
 *
 * So the form is declared as these, and `FieldForm` renders them into either
 * shape. Declaring it twice is how the two would drift.
 */
final class FormBlock {

	/**
	 * Constructor.
	 *
	 * @param string               $name  Block name, namespaced.
	 * @param array<string, mixed> $attrs Block attributes, as they are written into the block comment.
	 * @param string               $html  The block's saved HTML, if it saves any. Blocks whose `save`
	 *                                    returns null serialise self-closing and leave this empty.
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $attrs,
		public readonly string $html = ''
	) {}

	/**
	 * The pair `register_post_type()`'s `template` wants.
	 *
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	public function to_template(): array {
		return array( $this->name, $this->attrs );
	}

	/**
	 * The parsed-block array `serialize_block()` wants.
	 *
	 * @return array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string>}
	 */
	public function to_parsed(): array {
		return array(
			'blockName'    => $this->name,
			'attrs'        => $this->attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $this->html,
			'innerContent' => '' === $this->html ? array() : array( $this->html ),
		);
	}
}
