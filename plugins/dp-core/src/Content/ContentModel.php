<?php
/**
 * The content model, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * One object that knows the whole shape of the data.
 *
 * Order matters exactly once: the taxonomy registers before the post types so
 * that anything reading `get_object_taxonomies()` during post type registration
 * sees it. Meta comes last, because `register_post_meta()` for a post type that
 * does not exist yet registers a field nothing will ever read.
 */
final class ContentModel {

	/**
	 * Constructor.
	 *
	 * @param Taxonomies $taxonomies Registers `dp_series`.
	 * @param PostTypes  $post_types Registers the three custom post types.
	 * @param Meta       $meta       Registers every field.
	 */
	public function __construct(
		private readonly Taxonomies $taxonomies,
		private readonly PostTypes $post_types,
		private readonly Meta $meta
	) {}

	/**
	 * Build the model with its default collaborators.
	 *
	 * Nothing in this call path touches WordPress, so it is safe to run before
	 * `init` — which is exactly when `Plugin::boot()` runs it.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self( new Taxonomies(), new PostTypes(), new Meta( new MetaAuth() ) );
	}

	/**
	 * Register everything, on `init`.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->taxonomies->register();
		$this->post_types->register();
		$this->meta->register();
	}
}
