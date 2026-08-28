<?php
/**
 * The editing form the three custom post types open with.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Editor;

use DP\Core\Content\ContentModel;
use DP\Core\Content\Meta;
use DP\Core\Content\MetaAuth;
use DP\Core\Content\MetaField;
use DP\Core\Content\PostTypes;
use DP\Core\Editor\Control;
use DP\Core\Editor\FieldForm;
use DP\Core\Editor\FormBlock;
use WP_Block;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Thirty-three fields, thirty-three controls, and no way for that to stop
 * being true.
 *
 * ADR-0016 diagnosed a class of defect no test in the suite could see: a field
 * registered with `show_in_rest`, written by the seeder, read at render, and
 * with no control anywhere in the admin. It fixed the eight on `post` by
 * deleting them. The thirty-three on `dp_role`, `dp_ship` and `dp_video` cannot
 * be deleted — nothing else knows what they hold — so they are given controls,
 * and this is the assertion that keeps them.
 *
 * `test_the_form_covers_every_registered_field()` is the one that matters. It
 * takes the registered keys for a post type and the keys the form mentions, and
 * insists they are the same set with no repeats. A field added to `Meta` and
 * forgotten here fails; a control left behind by a field that was removed fails
 * too.
 */
final class FieldFormTest extends WP_UnitTestCase {

	/**
	 * The form under test.
	 *
	 * @var FieldForm
	 */
	private FieldForm $form;

	/**
	 * The declarations it is built from.
	 *
	 * @var Meta
	 */
	private Meta $meta;

	/**
	 * Re-register the content model and build the collaborators.
	 *
	 * `WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`, so
	 * everything the plugin registered on `init` is gone from the second test
	 * onwards.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();

		$this->meta = new Meta( new MetaAuth() );
		$this->form = new FieldForm( $this->meta );
	}

	/**
	 * The three post types whose canvas is a form.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function form_post_types(): array {
		return array(
			'role'  => array( PostTypes::ROLE ),
			'ship'  => array( PostTypes::SHIP ),
			'video' => array( PostTypes::VIDEO ),
		);
	}

	/**
	 * The meta key one block in the form edits.
	 *
	 * @param FormBlock $block The block.
	 * @return string The key, or the empty string for a block that edits nothing.
	 */
	private function key_of( FormBlock $block ): string {
		if ( FieldForm::LABEL_BLOCK === $block->name ) {
			return '';
		}

		if ( FieldForm::BOUND_BLOCK === $block->name ) {
			$metadata = $block->attrs['metadata'] ?? null;
			$bindings = is_array( $metadata ) ? $metadata['bindings'] ?? null : null;
			$content  = is_array( $bindings ) ? $bindings['content'] ?? null : null;
			$args     = is_array( $content ) ? $content['args'] ?? null : null;

			return is_array( $args ) && is_string( $args['key'] ?? null ) ? $args['key'] : '';
		}

		return $this->text( $block, 'metaKey' );
	}

	/**
	 * One of a block's string attributes.
	 *
	 * @param FormBlock $block The block.
	 * @param string    $name  The attribute.
	 * @return string The value, or the empty string when it is not a string.
	 */
	private function text( FormBlock $block, string $name ): string {
		$value = $block->attrs[ $name ] ?? null;

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Every field of every type has exactly one control.
	 *
	 * @dataProvider form_post_types
	 *
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function test_the_form_covers_every_registered_field( string $post_type ): void {
		$declared = array_map(
			static fn ( MetaField $field ): string => $field->key,
			$this->meta->fields_for( $post_type )
		);

		$controlled = array();

		foreach ( $this->form->blocks( $post_type ) as $block ) {
			$key = $this->key_of( $block );

			if ( '' !== $key ) {
				$controlled[] = $key;
			}
		}

		$this->assertNotEmpty( $declared, $post_type . ' declares no fields.' );
		$this->assertSame(
			$declared,
			$controlled,
			$post_type . ' has a field with no control, or a control with no field.'
		);
		$this->assertCount(
			count( array_unique( $controlled ) ),
			$controlled,
			$post_type . ' edits one field from two places.'
		);
	}

	/**
	 * Every label in the form names the field that follows it.
	 *
	 * A bound paragraph carries no label of its own, so the pairing is what makes
	 * it a labelled control rather than an anonymous box.
	 *
	 * @dataProvider form_post_types
	 *
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function test_every_bound_paragraph_is_preceded_by_its_label( string $post_type ): void {
		$blocks   = $this->form->blocks( $post_type );
		$declared = array();

		foreach ( $this->meta->fields_for( $post_type ) as $field ) {
			$declared[ $field->key ] = $field->label;
		}

		$bound = 0;

		foreach ( $blocks as $index => $block ) {
			if ( FieldForm::BOUND_BLOCK !== $block->name ) {
				continue;
			}

			++$bound;

			$key   = $this->key_of( $block );
			$label = $blocks[ $index - 1 ] ?? null;

			$this->assertInstanceOf( FormBlock::class, $label );
			$this->assertSame( FieldForm::LABEL_BLOCK, $label->name, $key . ' has no label in front of it.' );
			$this->assertSame( $key, $this->text( $label, 'metaKey' ), 'The label in front of ' . $key . ' names a different field.' );
			$this->assertSame( $declared[ $key ], $this->text( $label, 'label' ), $key . ' is labelled with something other than its own name.' );
		}

		$this->assertGreaterThan( 0, $bound, $post_type . ' binds nothing, so the preferred mechanism is unused.' );
	}

	/**
	 * Every control the form places is a block that exists.
	 *
	 * @dataProvider form_post_types
	 *
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function test_every_block_in_the_form_is_registered( string $post_type ): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( $this->form->blocks( $post_type ) as $block ) {
			$this->assertNotNull(
				$registry->get_registered( $block->name ),
				$block->name . ' is placed in the form and is not registered.'
			);
		}
	}

	/**
	 * The two ID fields are pickers, and nothing in the form asks for a number.
	 *
	 * This is the phase's headline: attaching a shipped thing to a role was
	 * typing a post ID into a key/value table.
	 *
	 * @return void
	 */
	public function test_the_id_fields_are_pickers(): void {
		$references = array();

		foreach ( $this->form->blocks( PostTypes::SHIP ) as $block ) {
			if ( Control::Reference->block() === $block->name ) {
				$references[ $this->text( $block, 'metaKey' ) ] = $this->text( $block, 'reference' );
			}
		}

		$this->assertSame(
			array(
				'dp_role_id'    => PostTypes::ROLE,
				'dp_writeup_id' => 'post',
			),
			$references
		);
	}

	/**
	 * Each type opens with its own form, and the form cannot be taken apart.
	 *
	 * @dataProvider form_post_types
	 *
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function test_each_type_opens_with_its_form_locked( string $post_type ): void {
		$object = get_post_type_object( $post_type );

		$this->assertNotNull( $object );
		$this->assertSame( 'all', $object->template_lock );
		$this->assertSame( $this->form->template( $post_type ), $object->template );
		$this->assertTrue( post_type_supports( $post_type, 'custom-fields' ), 'Removing custom-fields strips meta from the REST schema, and the bindings with it.' );
	}

	/**
	 * A page keeps its canvas. Its two fields are in the sidebar instead.
	 *
	 * A page carries real body content, so a locked template would take the page
	 * away to make room for two fields.
	 *
	 * @return void
	 */
	public function test_a_page_is_not_given_a_locked_form(): void {
		$object = get_post_type_object( 'page' );

		$this->assertNotNull( $object );
		$this->assertNotSame( 'all', $object->template_lock );
		$this->assertNotEmpty( $this->meta->fields_for( 'page' ) );
	}

	/**
	 * The markup the seeder writes is the template the editor would have built.
	 *
	 * The editor applies a post type's template only while the post is an
	 * `auto-draft`, so everything the seeder publishes has to carry the form in
	 * its own content. These are two renderings of one declaration, and this is
	 * what holds them together.
	 *
	 * @dataProvider form_post_types
	 *
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function test_the_markup_and_the_template_describe_the_same_form( string $post_type ): void {
		$parsed = array_values(
			array_filter(
				parse_blocks( $this->form->markup( $post_type ) ),
				static fn ( array $block ): bool => null !== $block['blockName']
			)
		);

		$expected = $this->form->template( $post_type );

		$this->assertCount( count( $expected ), $parsed );

		foreach ( $expected as $index => $pair ) {
			$this->assertSame( $pair[0], $parsed[ $index ]['blockName'] );
			$this->assertSame( $pair[1], $parsed[ $index ]['attrs'] );
		}
	}

	/**
	 * A value set through a bound paragraph is the value the paragraph shows.
	 *
	 * The binding is the whole mechanism for sixteen of the thirty fields, and it
	 * has four ways to be silently inert — an unregistered key, a key without
	 * `show_in_rest`, a protected key, and a post the reader may not read. This
	 * renders one for real and reads the answer.
	 *
	 * @return void
	 */
	public function test_a_bound_paragraph_renders_the_stored_value(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => PostTypes::SHIP ) );

		$this->assertIsInt( $user_id );
		$this->assertIsInt( $post_id );

		wp_set_current_user( $user_id );

		update_post_meta( $post_id, 'dp_headline', 'Everything the timeline draws' );

		$block = new WP_Block(
			array(
				'blockName'    => FieldForm::BOUND_BLOCK,
				'attrs'        => array(
					'metadata' => array(
						'bindings' => array(
							'content' => array(
								'source' => FieldForm::BINDINGS_SOURCE,
								'args'   => array( 'key' => 'dp_headline' ),
							),
						),
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => "\n<p></p>\n",
				'innerContent' => array( "\n<p></p>\n" ),
			),
			array(
				'postId'   => $post_id,
				'postType' => PostTypes::SHIP,
			)
		);

		$this->assertStringContainsString( 'Everything the timeline draws', $block->render() );
	}
}
