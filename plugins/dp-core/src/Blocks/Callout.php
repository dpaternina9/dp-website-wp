<?php
/**
 * The `dp/callout` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Blocks;

/**
 * Registers the one custom block the house style needs.
 *
 * The design calls it `note` in components/PostBlocks.dc.html: a label above a
 * single paragraph, on a teal-tinted surface. Static — the markup is written
 * into the post, there is no render callback, and nothing about it needs PHP at
 * display time.
 *
 * The block is registered from the compiled metadata in `build/`, which is
 * where `npm run build` puts a copy of `src/Blocks/js/callout/block.json`
 * alongside the editor bundle it names. Registering from `build/` rather than
 * from `src/` is what makes `file:./index.js` resolve to the compiled script.
 *
 * **That directory carries far more than this block, so its absence is said out
 * loud.** `index.js` is the whole plugin's editor bundle: this block, the two
 * house style extensions, the editor previews for the five server-rendered
 * blocks, the seven blocks the content model's editing form is drawn with, and
 * the Page fields panel that is the only way to reach `dp_lead` and
 * `dp_updated`. Every one of them is enqueued by being — or by riding along
 * with — this block's `editorScript`, so a package built without running
 * `npm run build` registers no block here and loads no bundle anywhere, and the
 * editor answers with "your site doesn't include support for this block" a
 * dozen times over while the front end looks perfectly fine.
 *
 * 1.0.0 shipped exactly that way: the release workflow's one build hook was
 * spent stamping a version constant and never ran the compiler. The hook is
 * fixed, and `notice()` is here so that the next time a package is assembled
 * wrongly the site says so on its own rather than waiting to be diagnosed.
 */
final class Callout {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/callout';

	/**
	 * The editor category the block appears under.
	 *
	 * @var string
	 */
	public const CATEGORY = 'dp';

	/**
	 * Path to the compiled block, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const BUILD_PATH = '/build/callout';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory, without a trailing slash.
	 */
	public function __construct( private readonly string $plugin_dir ) {}

	/**
	 * Attach the hooks and register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_categories_all', $this->add_category( ... ) );

		if ( ! $this->is_compiled() ) {
			add_action( 'admin_notices', $this->notice( ... ) );

			return;
		}

		register_block_type( $this->plugin_dir . self::BUILD_PATH );
	}

	/**
	 * Whether the compiled bundle this plugin's editor half lives in is present.
	 *
	 * `register_block_type()` answers a missing directory with `false` and a
	 * `_doing_it_wrong()` nobody reads on a production site, so the question is
	 * asked here instead — once, cheaply, and where something can be done about
	 * the answer.
	 *
	 * @return bool
	 */
	public function is_compiled(): bool {
		return file_exists( $this->plugin_dir . self::BUILD_PATH . '/block.json' );
	}

	/**
	 * Say that the package was assembled without its compiled assets.
	 *
	 * Shown to whoever could act on it and on every admin screen rather than
	 * only in the editor: by the time the editor is open the damage reads as a
	 * dozen unrelated broken blocks, which is precisely the shape of failure
	 * that costs a day to trace back to one missing directory.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'dP Core is missing its compiled assets.', 'dp-core' ),
			esc_html(
				sprintf(
					/* translators: %s: a path inside the plugin directory. */
					__( 'Nothing is at %s, so the editor cannot draw this plugin\'s blocks and the Page fields panel is absent — the front end is unaffected. This means the package was built without running its asset build; install a release newer than 1.0.0, or run "npm run build" if this is a working copy.', 'dp-core' ),
					'dp-core' . self::BUILD_PATH
				)
			)
		);
	}

	/**
	 * Give the plugin's blocks a category of their own.
	 *
	 * Placed first so the house style is the first thing the inserter offers,
	 * rather than the last thing under "Widgets".
	 *
	 * @param array<int, array<string, mixed>> $categories The registered categories.
	 * @return array<int, array<string, mixed>> The categories, with this plugin's added once.
	 */
	public function add_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( ( $category['slug'] ?? null ) === self::CATEGORY ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'dPaternina', 'dp-core' ),
				'icon'  => null,
			)
		);

		return $categories;
	}
}
