<?php
/**
 * Everything the Photos page is, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

/**
 * One object that knows the whole of Phase 13's server side.
 *
 * `Watch\Watch`'s shape: the collaborators are built here, shared — one
 * `Library` behind the block, the Trips screen's Dates column and nothing else —
 * and registered together on `init`.
 *
 * **What a photo is** (`PostType`, `Taxonomies`): a post with a featured image,
 * filed under at most one trip and any number of topics. Everything the page
 * prints is a core field or is read from the image. No single view, no archive,
 * no rewrite rule.
 *
 * **How David manages them:** the block editor, with a single-choice trip
 * control, a related-post picker and a read-only Camera panel in the sidebar
 * (`src/Blocks/js/photos/`); the list table, with trip and topic filters and a
 * "Set trip…" bulk action (`AdminList`); and "Add photos in bulk" beside "Add
 * Photo" (`BulkScreen`, `BulkRoute`, `BulkCreate`), which is also `wp dp photos
 * import`.
 *
 * **What the page is:** `dp/photo-wall` (`PhotoWall`), placed by the theme's
 * `dp-photos` template on whichever page David assigns it to.
 */
final class Photos {

	/**
	 * Constructor.
	 *
	 * @param PostType    $post_type  Registers `dp_photo` and its field.
	 * @param Taxonomies  $taxonomies Registers trips and topics.
	 * @param OneTrip     $one_trip   Refuses a save with two trips.
	 * @param CameraField $camera     The camera line, for the editor.
	 * @param PhotoWall   $wall       The block.
	 * @param AdminList   $admin_list     The list screens.
	 * @param BulkRoute   $route      The bulk import's REST route.
	 * @param BulkScreen  $screen     The bulk import's button and dialog.
	 */
	private function __construct(
		private readonly PostType $post_type,
		private readonly Taxonomies $taxonomies,
		private readonly OneTrip $one_trip,
		private readonly CameraField $camera,
		private readonly PhotoWall $wall,
		private readonly AdminList $admin_list,
		private readonly BulkRoute $route,
		private readonly BulkScreen $screen
	) {}

	/**
	 * Build the Photos page's parts with their shared collaborators.
	 *
	 * Nothing in this call path touches WordPress, so it is safe before `init`.
	 *
	 * @param string $plugin_file Absolute path to the plugin's entry file.
	 * @param string $version     Plugin version.
	 * @return self
	 */
	public static function create( string $plugin_file, string $version ): self {
		$library = new Library();

		return new self(
			new PostType(),
			new Taxonomies(),
			new OneTrip(),
			new CameraField(),
			new PhotoWall( rtrim( dirname( $plugin_file ), '/' ), $library ),
			new AdminList( $library ),
			new BulkRoute( new BulkCreate() ),
			new BulkScreen( $plugin_file, $version )
		);
	}

	/**
	 * Attach everything.
	 *
	 * The taxonomies register before the post type for the reason
	 * `ContentModel` gives: anything that reads `get_object_taxonomies()` while
	 * the type registers should see them.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->taxonomies->register();
		$this->post_type->register();
		$this->one_trip->register();
		$this->camera->register();
		$this->wall->register();
		$this->admin_list->register();
		$this->route->register();
		$this->screen->register();
	}
}
