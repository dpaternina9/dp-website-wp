<?php
/**
 * The editing surface for the content model.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Editor;

use DP\Core\Content\PostTypes;
use WP_Block_Editor_Context;
use WP_Post;

/**
 * Everything that makes the content model's fields reachable by hand.
 *
 * `Content` says what the site is. This says how David says it — the same split
 * `Admin` makes, one layer up: `Admin\SeriesOrderScreen` is a classic admin page
 * because a taxonomy has no block editor, and this is the block editor's half of
 * the same job.
 *
 * Four things are attached from here.
 *
 * **The blocks the form is drawn with** (`FieldBlocks`). Registered on `init`
 * with everything else, because that is when block types have to exist.
 *
 * **The form itself, for every post that is not created in the editor.** See
 * `form_content()`: `register_post_type()`'s `template` is applied by the
 * editor and only to an `auto-draft`, so everything else — REST, WP-CLI, the
 * seeder, an import — would otherwise arrive with an empty canvas that
 * `template_lock => 'all'` makes impossible to fill.
 *
 * **The Custom Fields panel is switched off on the three post types.** This is
 * not tidying. Core's `core/post-meta` bindings source refuses to let a bound
 * paragraph be edited while `enableCustomFields` is true — `canUserEditValue()`
 * returns false on exactly that condition, so a user who has ever ticked
 * Preferences → Panels → Custom fields would open a Role and find every bound
 * field read-only, with nothing on screen saying why. The raw key/value table is
 * also the thing this phase exists to replace: it is where attaching a shipped
 * thing to a role meant typing a post ID. `custom-fields` stays in `supports`,
 * which is what keeps `meta` in the REST schema (see `PostTypes`); only the
 * panel goes, and only on the three screens whose every field now has a control.
 *
 * **The form's stylesheet**, on those screens and nowhere else.
 */
final class Editor {

	/**
	 * The stylesheet's handle.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'dp-core-editor-fields';

	/**
	 * The stylesheet, relative to the plugin root.
	 *
	 * @var string
	 */
	private const STYLE_PATH = 'assets/css/editor-fields.css';

	/**
	 * Constructor.
	 *
	 * @param FieldBlocks $blocks      Registers the blocks the form is made of.
	 * @param FieldForm   $form        The form itself.
	 * @param string      $plugin_file Absolute path to the plugin's entry file.
	 * @param string      $version     Plugin version, for asset cache busting.
	 */
	private function __construct(
		private readonly FieldBlocks $blocks,
		private readonly FieldForm $form,
		private readonly string $plugin_file,
		private readonly string $version
	) {}

	/**
	 * Build with the default collaborators.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version.
	 * @return self
	 */
	public static function create( string $plugin_file, string $version ): self {
		return new self( new FieldBlocks( plugin_dir_path( $plugin_file ) ), new FieldForm(), $plugin_file, $version );
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->blocks->register();

		add_filter( 'wp_insert_post_data', $this->form_content( ... ) );
		add_filter( 'block_editor_settings_all', $this->settings( ... ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', $this->enqueue( ... ) );
	}

	/**
	 * Give a post of one of the three types the form, when it has no content.
	 *
	 * `register_post_type()`'s `template` is applied by the editor, in
	 * JavaScript, and only while the post is an `auto-draft` —
	 * `setupEditor()` checks exactly that and nothing re-applies it afterwards.
	 * So the template covers the one path where David presses "Add Shipped
	 * thing", and covers nothing else. A Role created over REST, by WP-CLI, by
	 * the seeder or by an import arrives with empty content, and `template_lock
	 * => 'all'` then means it opens on a blank canvas with no way to add
	 * anything to it — every field unreachable, which is the defect this whole
	 * phase exists to remove.
	 *
	 * This is the same form by the other door. It **fills a blank and never
	 * replaces a value** (ADR-0018, rule 3): a post that already has content
	 * keeps it, whatever it is, so nothing here can take a form apart or
	 * overwrite an edit.
	 *
	 * The markup is slashed because `wp_insert_post()` calls `wp_unslash()` on
	 * this array *after* running this filter.
	 *
	 * @param array<string, mixed> $data The post row about to be written.
	 * @return array<string, mixed>
	 */
	public function form_content( array $data ): array {
		$post_type = $data['post_type'] ?? '';
		$content   = $data['post_content'] ?? '';

		if ( ! is_string( $post_type ) || ! in_array( $post_type, PostTypes::all(), true ) ) {
			return $data;
		}

		if ( ! is_string( $content ) || '' !== trim( $content ) ) {
			return $data;
		}

		$data['post_content'] = wp_slash( $this->form->markup( $post_type ) );

		return $data;
	}

	/**
	 * Take the raw Custom Fields panel off the three post types.
	 *
	 * @param array<string, mixed>    $settings The editor settings.
	 * @param WP_Block_Editor_Context $context  The editor asking.
	 * @return array<string, mixed>
	 */
	public function settings( array $settings, WP_Block_Editor_Context $context ): array {
		$post = $context->post;

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, PostTypes::all(), true ) ) {
			return $settings;
		}

		$settings['enableCustomFields'] = false;

		return $settings;
	}

	/**
	 * Load the form's stylesheet, on the screens that draw a form.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! in_array( $this->editing(), PostTypes::all(), true ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( self::STYLE_PATH, $this->plugin_file ),
			array(),
			$this->asset_version()
		);
	}

	/**
	 * The post type the current editor screen is editing.
	 *
	 * @return string The post type, or the empty string outside a post editor.
	 */
	private function editing(): string {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return '';
		}

		$screen = get_current_screen();

		return null === $screen ? '' : (string) $screen->post_type;
	}

	/**
	 * The version the stylesheet is served under.
	 *
	 * The plugin version everywhere but a local install, where the file's own
	 * modified time is appended so editing it is visible on the next reload
	 * rather than on the next release. The same rule as `SeriesOrderScreen`.
	 *
	 * @return string
	 */
	private function asset_version(): string {
		if ( 'local' !== wp_get_environment_type() ) {
			return $this->version;
		}

		$path     = plugin_dir_path( $this->plugin_file ) . self::STYLE_PATH;
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return false === $modified ? $this->version : $this->version . '.' . (string) $modified;
	}
}
