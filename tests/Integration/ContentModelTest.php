<?php
/**
 * Integration tests for the registered content model.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration;

use DP\Core\Content\ContentModel;
use DP\Core\Content\Meta;
use DP\Core\Content\MetaAuth;
use DP\Core\Content\PostTypes;
use DP\Core\Content\Taxonomies;
use WP_Rewrite;
use WP_Taxonomy;
use WP_UnitTestCase;

/**
 * What `dp-core` actually registers, asserted against a real WordPress.
 *
 * The class under test is `Plugin::register()`, which has already run by the
 * time a test does — so these read the registry rather than calling anything.
 * That is deliberate: a test that re-registered would prove the arguments were
 * well formed, not that the plugin passed them.
 */
final class ContentModelTest extends WP_UnitTestCase {

	/**
	 * Put the content model back.
	 *
	 * `WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`, which
	 * empties `$wp_meta_keys` for the whole process. Everything the plugin
	 * registered on `init` is therefore gone from the second test onwards, and a
	 * suite that did not re-register would quietly assert against an empty model
	 * — passing or failing according to which test ran first.
	 *
	 * Registration is idempotent, so doing it here costs nothing and makes every
	 * test in the class independent of its neighbours.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();
	}

	/**
	 * All three custom post types exist.
	 *
	 * @return void
	 */
	public function test_the_three_post_types_are_registered(): void {
		foreach ( PostTypes::all() as $post_type ) {
			$this->assertTrue( post_type_exists( $post_type ), $post_type . ' is registered.' );
		}

		$this->assertSame( array( 'dp_role', 'dp_ship', 'dp_video' ), PostTypes::all() );
	}

	/**
	 * None of the three is a URL.
	 *
	 * Digest section 3 and plan Phase 3: roles and ships expand inline on the
	 * timeline, videos render in the Watch grid, and none of them has a single
	 * view. Five arguments say that, and all five have to hold — `public` alone
	 * does not stop a query var or a feed.
	 *
	 * @dataProvider provide_post_types
	 *
	 * @param string $post_type The post type name.
	 * @return void
	 */
	public function test_a_post_type_is_data_and_not_a_url( string $post_type ): void {
		$object = get_post_type_object( $post_type );

		$this->assertNotNull( $object );
		$this->assertFalse( $object->public, 'public' );
		$this->assertFalse( $object->publicly_queryable, 'publicly_queryable' );
		$this->assertFalse( $object->has_archive, 'has_archive' );
		$this->assertFalse( $object->rewrite, 'rewrite' );
		$this->assertFalse( $object->query_var, 'query_var' );
		$this->assertTrue( $object->exclude_from_search, 'exclude_from_search' );
	}

	/**
	 * All three are editable in the admin and reachable over REST.
	 *
	 * @dataProvider provide_post_types
	 *
	 * @param string $post_type The post type name.
	 * @return void
	 */
	public function test_a_post_type_is_editable_and_in_rest( string $post_type ): void {
		$object = get_post_type_object( $post_type );

		$this->assertNotNull( $object );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_in_rest );
	}

	/**
	 * Every post type supports `custom-fields`, or none of its meta exists over REST.
	 *
	 * `WP_REST_Posts_Controller::get_item_schema()` only adds the `meta` property
	 * for a post type that supports custom fields. Without this the whole content
	 * model would be registered, and invisible.
	 *
	 * @dataProvider provide_post_types
	 *
	 * @param string $post_type The post type name.
	 * @return void
	 */
	public function test_a_post_type_supports_custom_fields( string $post_type ): void {
		$this->assertTrue( post_type_supports( $post_type, 'custom-fields' ) );
		$this->assertTrue( post_type_supports( $post_type, 'title' ) );
	}

	/**
	 * `supports` is trimmed to what is used.
	 *
	 * No editor: a role's prose is `dp_detail`, a shipped thing's is `dp_detail`
	 * plus `dp_bullets`, and a video has none. An editor nobody writes in is an
	 * editor somebody eventually writes in, and then there are two places a
	 * description can live.
	 *
	 * @return void
	 */
	public function test_a_post_type_has_no_editor(): void {
		foreach ( PostTypes::all() as $post_type ) {
			$this->assertFalse( post_type_supports( $post_type, 'editor' ), $post_type . ' has no editor.' );
			$this->assertFalse( post_type_supports( $post_type, 'comments' ), $post_type . ' takes no comments.' );
		}

		$this->assertTrue(
			post_type_supports( PostTypes::SHIP, 'thumbnail' ),
			'A shipped thing carries the WorkCard shot as a featured image.'
		);
		$this->assertFalse( post_type_supports( PostTypes::VIDEO, 'thumbnail' ) );
	}

	/**
	 * The three post types.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_post_types(): array {
		return array(
			'dp_role'  => array( 'dp_role' ),
			'dp_ship'  => array( 'dp_ship' ),
			'dp_video' => array( 'dp_video' ),
		);
	}

	/**
	 * The series taxonomy is registered on posts and is public.
	 *
	 * @return void
	 */
	public function test_the_series_taxonomy_is_registered_on_posts(): void {
		$taxonomy = get_taxonomy( Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertSame( array( 'post' ), $taxonomy->object_type );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->publicly_queryable );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->hierarchical );
	}

	/**
	 * Its archive lives at `/series/{slug}` unless somebody says otherwise.
	 *
	 * @return void
	 */
	public function test_the_series_archive_has_the_expected_slug(): void {
		$taxonomy = get_taxonomy( Taxonomies::SERIES );

		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertIsArray( $taxonomy->rewrite );
		$this->assertSame( 'series', $taxonomy->rewrite['slug'] );
		$this->assertFalse( $taxonomy->rewrite['with_front'] );
	}

	/**
	 * The slug is a filter, so renaming the archive is not a release.
	 *
	 * CLAUDE.md section 5.1 allows this project exactly one page-facing rewrite.
	 * Being the only one is precisely why it must not be the one thing hard-coded:
	 * the same rule that says David picks every page's slug says he can pick this
	 * one too.
	 *
	 * @return void
	 */
	public function test_the_series_slug_goes_through_a_filter(): void {
		$taxonomies = new Taxonomies();

		$this->assertSame( 'series', $taxonomies->rewrite_slug() );

		$rename = static fn (): string => 'writing';
		add_filter( 'dp_series_rewrite_slug', $rename );
		$this->assertSame( 'writing', $taxonomies->rewrite_slug() );
		remove_filter( 'dp_series_rewrite_slug', $rename );

		$this->assertSame( 'series', $taxonomies->rewrite_slug(), 'Removing the filter restores the default.' );
	}

	/**
	 * A filter that returns something unusable gets the default back.
	 *
	 * A slug of `''` or `'  '` would take the archive off the air, which is a
	 * worse outcome than ignoring the filter.
	 *
	 * @dataProvider provide_unusable_slugs
	 *
	 * @param string $returned What the filter returns.
	 * @param string $expected What the taxonomy should use.
	 * @return void
	 */
	public function test_an_unusable_slug_falls_back( string $returned, string $expected ): void {
		$filter = static fn (): string => $returned;
		add_filter( 'dp_series_rewrite_slug', $filter );

		$this->assertSame( $expected, ( new Taxonomies() )->rewrite_slug() );

		remove_filter( 'dp_series_rewrite_slug', $filter );
	}

	/**
	 * Slugs a filter might plausibly return.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_unusable_slugs(): array {
		return array(
			'empty'            => array( '', 'series' ),
			'only whitespace'  => array( '   ', 'series' ),
			'only punctuation' => array( '///', 'series' ),
			'needs sanitising' => array( 'My Writing!', 'my-writing' ),
			'already a slug'   => array( 'chapters', 'chapters' ),
		);
	}

	/**
	 * The taxonomy is the only permastruct this plugin adds.
	 *
	 * `NoHardcodedRoutesTest` proves nothing of ours reaches a page. This proves
	 * the converse half — that the one rewrite the project is allowed is actually
	 * there, so that test is not passing because we registered nothing at all.
	 *
	 * @return void
	 */
	public function test_the_series_taxonomy_is_the_only_permastruct_we_add(): void {
		global $wp_rewrite;

		/*
		 * `register_taxonomy()` only adds a permastruct when the site has pretty
		 * permalinks, and the test suite installs with plain ones. Without this
		 * the assertion below would pass against an empty rule set, which is the
		 * failure mode it exists to catch.
		 */
		$this->set_permalink_structure( '/%postname%/' );
		( new Taxonomies() )->register();
		( new PostTypes() )->register();

		$this->assertInstanceOf( WP_Rewrite::class, $wp_rewrite );

		$ours = array_values(
			array_filter(
				array_keys( $wp_rewrite->extra_permastructs ),
				static fn ( string $name ): bool => str_starts_with( $name, 'dp_' )
			)
		);

		$this->assertSame(
			array( Taxonomies::SERIES ),
			$ours,
			'The series archive is the one rewrite CLAUDE.md section 5.1 allows. A third needs an ADR.'
		);

		/*
		 * `wp_rewrite_rules()` would hand back the set cached in the option, which
		 * was generated by `set_permalink_structure()` a moment before the
		 * taxonomy was registered. `rewrite_rules()` builds them now.
		 */
		$rules = $wp_rewrite->rewrite_rules();

		$this->assertNotEmpty( $rules );

		$series = array_filter(
			$rules,
			static fn ( string $target ): bool => str_contains( $target, 'dp_series=' )
		);

		$this->assertNotEmpty( $series, 'The archive actually resolves.' );

		foreach ( array_keys( $series ) as $pattern ) {
			$this->assertStringStartsWith( 'series/', (string) $pattern );
		}

		foreach ( PostTypes::all() as $post_type ) {
			$this->assertSame(
				array(),
				array_filter( $rules, static fn ( string $t ): bool => str_contains( $t, $post_type . '=' ) ),
				$post_type . ' generated a rewrite rule.'
			);
		}

		$this->set_permalink_structure( '' );
	}

	/**
	 * Every field in the model is registered, typed, in REST, and authorised.
	 *
	 * The `auth_callback` assertion is the one that matters. `register_meta()`
	 * defaults to `__return_true`, so a REST-exposed field with no callback is
	 * writable by anyone who can reach the route — which CLAUDE.md section 1.4
	 * does not allow, and which is invisible in a diff.
	 *
	 * @return void
	 */
	public function test_every_field_is_typed_authorised_and_in_rest(): void {
		$meta      = new Meta( new MetaAuth() );
		$registry  = get_registered_meta_keys( 'post', 'dp_role' );
		$unchecked = 0;

		foreach ( $meta->post_fields() as $post_type => $fields ) {
			$registered = get_registered_meta_keys( 'post', $post_type );

			foreach ( $fields as $field ) {
				$this->assertArrayHasKey(
					$field->key,
					$registered,
					sprintf( '%s is not registered on %s.', $field->key, $post_type )
				);

				$args = $registered[ $field->key ];

				$this->assertSame( $field->type, $args['type'], $field->key . ' type' );
				$this->assertTrue( $args['single'], $field->key . ' is single' );
				$this->assertIsArray( $args['show_in_rest'], $field->key . ' is in REST with a schema' );
				$this->assertIsCallable( $args['auth_callback'], $field->key . ' has an auth callback' );
				$this->assertIsCallable( $args['sanitize_callback'], $field->key . ' has a sanitize callback' );
				$this->assertNotSame( '__return_true', $args['auth_callback'], $field->key . ' is not wide open' );

				++$unchecked;
			}
		}

		$this->assertGreaterThan( 30, $unchecked, 'The model is not so small that this test proves nothing.' );
		$this->assertArrayHasKey( 'dp_start', $registry );
	}

	/**
	 * A series carries no registered meta of ours either.
	 *
	 * `dp_series_deck` was the last one, and a `dp_series` term already had a
	 * core field for the sentence it held: `description`, with a textarea on both
	 * term screens, a column in the list table and a place in a WXR export. The
	 * meta is unregistered and the deck is the description, and this is the
	 * mirror of the guard ADR-0016 left on the post type — the assertion that
	 * stops a term field growing back.
	 *
	 * @return void
	 */
	public function test_a_series_carries_no_registered_meta_of_ours(): void {
		$registered = get_registered_meta_keys( 'term', Taxonomies::SERIES );

		$this->assertArrayNotHasKey( 'dp_series_deck', $registered );

		foreach ( array_keys( $registered ) as $key ) {
			$this->assertStringStartsNotWith( 'dp_', (string) $key, sprintf( '"%s" is registered on the series taxonomy again.', $key ) );
		}
	}

	/**
	 * Enum fields carry their vocabulary into the REST schema.
	 *
	 * This is what "tone is never a loose string" means in practice: the closed
	 * set is enforced by the REST controller, not by a convention.
	 *
	 * @return void
	 */
	public function test_enum_fields_carry_their_vocabulary(): void {
		$video = get_registered_meta_keys( 'post', 'dp_video' );

		$this->assertContains( 'twitch', $video['dp_video_source']['show_in_rest']['schema']['enum'] );
		$this->assertContains( 'youtube', $video['dp_video_source']['show_in_rest']['schema']['enum'] );
		$this->assertNotContains( 'vimeo', $video['dp_video_source']['show_in_rest']['schema']['enum'] );

		$this->assertContains( 'muted', $video['dp_tone']['show_in_rest']['schema']['enum'] );
	}

	/**
	 * No two fields are registered under the same key with different meanings.
	 *
	 * `dp_start`, `dp_range` and `dp_detail` are deliberately shared between roles
	 * and shipped things, and `dp_tone` between videos and nothing else now that
	 * the native `post` type carries no fields at all (ADR-0016). Each of those is
	 * the same field on the types that have it, which is fine. A key that meant
	 * two different things would not be.
	 *
	 * @return void
	 */
	public function test_shared_keys_mean_the_same_thing_everywhere(): void {
		$meta  = new Meta( new MetaAuth() );
		$types = array();

		foreach ( $meta->post_fields() as $fields ) {
			foreach ( $fields as $field ) {
				if ( isset( $types[ $field->key ] ) ) {
					$this->assertSame(
						$types[ $field->key ],
						$field->type,
						sprintf( '"%s" is registered with two different types.', $field->key )
					);
				}

				$types[ $field->key ] = $field->type;
			}
		}

		$this->assertArrayHasKey( 'dp_start', $types );
		$this->assertArrayHasKey( 'dp_lead', $types );
	}

	/**
	 * The native `post` type is registered with no meta fields at all.
	 *
	 * Eight of them used to live here — a kicker, a tone, a read time, a
	 * standfirst, a hero caption, a part number, a year range and a note — and
	 * every one was derivable from the post's own content, its terms, its date or
	 * the attachment behind its featured image. None had an editor control, so
	 * none could ever hold a value David put there. ADR-0016 deletes them, and
	 * this is the assertion that stops one growing back by habit.
	 *
	 * @return void
	 */
	public function test_a_post_carries_no_registered_meta_of_ours(): void {
		$this->assertArrayNotHasKey( 'post', ( new Meta( new MetaAuth() ) )->post_fields() );

		foreach ( array_keys( get_registered_meta_keys( 'post', 'post' ) ) as $key ) {
			$this->assertStringStartsNotWith( 'dp_', (string) $key, sprintf( '"%s" is registered on the post type again.', $key ) );
		}
	}

	/**
	 * Every registered key is prefixed.
	 *
	 * @return void
	 */
	public function test_every_key_is_prefixed(): void {
		foreach ( ( new Meta( new MetaAuth() ) )->all_keys() as $key ) {
			$this->assertStringStartsWith( 'dp_', $key );
		}
	}
}
