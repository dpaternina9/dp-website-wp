<?php
/**
 * Integration tests for `dp/photo-wall`.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Photos;

use DP\Core\Photos\Library;
use DP\Core\Photos\PhotoWall;
use DP\Core\Photos\PostType;
use DP\Core\Photos\Taxonomies;
use WP_HTML_Tag_Processor;

/**
 * The wall as a reader with no script gets it.
 *
 * Everything the page can do is a URL, so everything is testable as a render:
 * the index and its counts, a filter, a page of the wall, and an open photo
 * drawn in full. The script is an upgrade over exactly this markup.
 */
final class PhotoWallTest extends PhotosTestCase {

	/**
	 * The block under test, built as the plugin builds it.
	 *
	 * @var PhotoWall
	 */
	private PhotoWall $wall;

	/**
	 * Build the renderer.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->wall = new PhotoWall( dirname( __DIR__, 3 ) . '/plugins/dp-core', new Library() );
	}

	/**
	 * Render for a query.
	 *
	 * @param array<string, string> $query The query args.
	 * @return string
	 */
	private function render( array $query = array() ): string {
		return $this->wall->render_for( array(), $query );
	}

	/**
	 * The data-slug of every tile, in order.
	 *
	 * @param string $html The render.
	 * @return list<string>
	 */
	private function tiles( string $html ): array {
		$slugs = array();
		$tags  = new WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag(
			array(
				'tag_name'   => 'A',
				'class_name' => 'dp-pw-tile',
			)
		) ) {
			$slugs[] = (string) $tags->get_attribute( 'data-slug' );
		}

		return $slugs;
	}

	/**
	 * With nothing published there is nothing to draw.
	 *
	 * @return void
	 */
	public function test_an_empty_library_renders_nothing(): void {
		$this->photo( array( 'status' => 'draft' ) );
		$this->photo( array( 'image' => 0 ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Newest first; drafts and photos with no image are not on the wall.
	 *
	 * @return void
	 */
	public function test_the_wall_is_published_photos_newest_first(): void {
		$this->photo(
			array(
				'slug' => 'older',
				'date' => '2017-01-01 10:00:00',
			)
		);
		$this->photo(
			array(
				'slug' => 'newer',
				'date' => '2018-06-01 10:00:00',
			)
		);
		$this->photo(
			array(
				'slug'   => 'draft',
				'status' => 'draft',
			)
		);
		$this->photo(
			array(
				'slug'  => 'imageless',
				'image' => 0,
			)
		);

		$html = $this->render();

		$this->assertSame( array( 'newer', 'older' ), $this->tiles( $html ) );
		$this->assertStringContainsString( '<p class="dp-pw-stats">2 photos · 2017–2018</p>', $html );
	}

	/**
	 * The index counts published photos and dates each trip from them.
	 *
	 * @return void
	 */
	public function test_the_index_counts_and_dates_what_is_published(): void {
		$trip  = $this->term( Taxonomies::TRIP, 'Duitama' );
		$topic = $this->term( Taxonomies::TOPIC, 'Night' );

		$this->photo(
			array(
				'trip'   => $trip->term_id,
				'topics' => array( $topic->term_id ),
				'date'   => '2017-11-28 10:00:00',
			)
		);
		$this->photo(
			array(
				'trip' => $trip->term_id,
				'date' => '2017-12-02 10:00:00',
			)
		);
		$this->photo(
			array(
				'trip'   => $trip->term_id,
				'status' => 'draft',
			)
		);

		$html = $this->render();

		$this->assertMatchesRegularExpression( '~data-slug="duitama" data-count="2".*?<span class="dp-pw-entry-n">2</span><span class="dp-pw-entry-when">Nov – Dec 2017</span>~s', $html );
		$this->assertMatchesRegularExpression( '~data-slug="night" data-count="1"~', $html );
		$this->assertStringContainsString( '2 photos · 1 trip · 2017', $html );
		$this->assertMatchesRegularExpression( '~dp-pw-entry-all" href="[^"]*" data-kind="" data-slug="" data-count="2" aria-current="page"~', $html );
	}

	/**
	 * `?trip=` and `?topic=` filter, mark the entry current, and fill the line.
	 *
	 * @return void
	 */
	public function test_a_filter_narrows_the_wall(): void {
		$trip  = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$topic = $this->term( Taxonomies::TOPIC, 'Cats' );

		$this->photo(
			array(
				'slug' => 'river',
				'trip' => $trip->term_id,
			)
		);
		$this->photo(
			array(
				'slug'   => 'cat',
				'topics' => array( $topic->term_id ),
			)
		);
		$this->photo( array( 'slug' => 'other' ) );

		$html = $this->render( array( 'trip' => 'putumayo' ) );

		$this->assertSame( array( 'river' ), $this->tiles( $html ) );
		$this->assertMatchesRegularExpression( '~data-slug="putumayo" data-count="1" aria-current="page"~', $html );
		$this->assertStringContainsString( '<strong class="dp-pw-filter-name">Putumayo</strong>', $html );
		$this->assertStringContainsString( '<span class="dp-pw-filter-count">1 photo</span>', $html );
		$this->assertStringContainsString( '>Show all</a>', $html );
		$this->assertStringContainsString( 'photo=river', $html, 'The tile keeps its filter.' );
		$this->assertStringContainsString( 'trip=putumayo&#038;photo=river', $html );

		$this->assertSame( array( 'cat' ), $this->tiles( $this->render( array( 'topic' => 'cats' ) ) ) );
	}

	/**
	 * Anything unrecognised is the whole wall, never an error.
	 *
	 * @return void
	 */
	public function test_unknown_values_degrade_to_the_unfiltered_page(): void {
		$this->photo( array( 'slug' => 'one' ) );
		$this->photo(
			array(
				'slug' => 'two',
				'date' => '2019-01-01 00:00:00',
			)
		);

		foreach ( array(
			array( 'trip' => 'nowhere' ),
			array( 'topic' => '<script>' ),
			array( 'photo' => 'missing' ),
			array( 'photos-page' => '99' ),
			array( 'photos-page' => '-1' ),
			array( 'photos-page' => 'abc' ),
		) as $query ) {
			$html = $this->render( $query );

			$this->assertSame( array( 'two', 'one' ), $this->tiles( $html ), (string) wp_json_encode( $query ) );
			$this->assertStringContainsString( '<p class="dp-pw-filter" hidden></p>', $html );
			$this->assertStringNotContainsString( 'dp-pw-panel', $html );
		}
	}

	/**
	 * Forty-eight a page; `?photos-page=N` is a depth; the script's append is one page.
	 *
	 * @return void
	 */
	public function test_the_wall_pages_by_forty_eight(): void {
		for ( $i = 1; $i <= 100; $i++ ) {
			$this->photo(
				array(
					'slug' => 'p' . $i,
					'date' => sprintf( '2018-01-01 01:%02d:00', $i % 60 ) . '',
				)
			);
		}

		$first = $this->render();

		$this->assertCount( PhotoWall::PER_PAGE, $this->tiles( $first ) );
		$this->assertMatchesRegularExpression( '~<a class="dp-pw-more-link" href="[^"]*photos-page=2#dp-pw-p2"[^>]*>Show more</a>~', $first );
		$this->assertStringContainsString( 'data-total="100"', $first );
		$this->assertStringNotContainsString( 'id="dp-pw-p', $first );

		// A depth: pages one and two, so a reload or a reader with no script
		// has everything up to there, and lands on what is new.
		$second = $this->render( array( 'photos-page' => '2' ) );

		$this->assertCount( 96, $this->tiles( $second ) );
		$this->assertSame( $this->tiles( $first ), array_slice( $this->tiles( $second ), 0, 48 ) );
		$this->assertMatchesRegularExpression( '~<a class="dp-pw-tile" id="dp-pw-p2"[^>]*data-position="49"~', $second );
		$this->assertStringContainsString( 'photos-page=3#dp-pw-p3', $second );

		// The script's append: page three alone.
		$part = $this->render(
			array(
				'photos-page' => '3',
				'photos-part' => '1',
			)
		);

		$this->assertCount( 4, $this->tiles( $part ) );
		$this->assertStringContainsString( 'data-position="97"', $part );
		$this->assertStringNotContainsString( 'dp-pw-more-link', $part );

		// An open photo deepens the wall to reach it, and never makes it shallower.
		$last = $this->tiles( $this->render( array( 'photos-page' => '3' ) ) );
		$this->assertCount( 100, $this->render_count( array( 'photo' => (string) end( $last ) ) ) );
		$this->assertCount(
			100,
			$this->render_count(
				array(
					'photo'       => $last[0],
					'photos-page' => '3',
				)
			)
		);
	}

	/**
	 * The tiles a query renders.
	 *
	 * @param array<string, string> $query The query args.
	 * @return array<int, string>
	 */
	private function render_count( array $query ): array {
		return $this->tiles( $this->render( $query ) );
	}

	/**
	 * `?photo=` draws the whole pop-up in the page.
	 *
	 * @return void
	 */
	public function test_an_open_photo_is_drawn_in_full_without_a_script(): void {
		$trip    = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$topic   = $this->term( Taxonomies::TOPIC, 'Water' );
		$related = $this->ok( self::factory()->post->create( array( 'post_title' => 'The long version' ) ) );
		$image   = $this->image(
			3000,
			2000,
			array(
				'camera'        => 'X30',
				'focal_length'  => '10.8',
				'aperture'      => '5',
				'shutter_speed' => '0.1',
				'iso'           => '800',
			)
		);

		$this->photo(
			array(
				'slug' => 'after',
				'date' => '2019-01-01 00:00:00',
			)
		);
		$photo = $this->photo(
			array(
				'slug'    => 'river',
				'title'   => 'The river',
				'excerpt' => 'Mocoa, Putumayo — Jan 2018',
				'content' => '<!-- wp:paragraph --><p>It rained for three days.</p><!-- /wp:paragraph -->',
				'trip'    => $trip->term_id,
				'topics'  => array( $topic->term_id ),
				'image'   => $image,
			)
		);
		$this->photo(
			array(
				'slug' => 'before',
				'date' => '2017-01-01 00:00:00',
			)
		);
		update_post_meta( $photo, PostType::RELATED_POST, $related );

		$html = $this->render( array( 'photo' => 'river' ) );

		$this->assertStringContainsString( '<section class="dp-pw-panel" id="dp-photo" data-id="' . $photo . '"', $html );
		$this->assertStringContainsString( '<span class="dp-pw-count">02 / 03</span>', $html );
		$this->assertStringContainsString( 'class="dp-pw-panel-img"', $html );
		$this->assertStringContainsString( '<h2 class="dp-pw-d-title">The river</h2>', $html );
		$this->assertStringContainsString( '<p class="dp-pw-d-caption">Mocoa, Putumayo — Jan 2018</p>', $html );
		$this->assertStringContainsString( 'It rained for three days.', $html );
		$this->assertMatchesRegularExpression( '~<a class="dp-pw-chip" href="[^"]*trip=putumayo" data-kind="trip" data-slug="putumayo">Putumayo</a>~', $html );
		$this->assertStringContainsString( '>Water</a>', $html );
		$this->assertStringContainsString( 'href="' . get_permalink( $related ) . '">Read the story', $html );
		$this->assertStringContainsString( '<p class="dp-pw-d-camera">X30 · 10.8mm · f/5 · 1/10s · ISO 800</p>', $html );
		$this->assertMatchesRegularExpression( '~dp-pw-prev" href="[^"]*photo=after#dp-photo"~', $html );
		$this->assertMatchesRegularExpression( '~dp-pw-next" href="[^"]*photo=before#dp-photo"~', $html );
	}

	/**
	 * An empty field prints nothing — not a wrapper, not a label.
	 *
	 * @return void
	 */
	public function test_empty_fields_render_nothing(): void {
		$draft = $this->ok( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$photo = $this->photo(
			array(
				'slug'    => 'bare',
				'title'   => '',
				'content' => '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $photo, PostType::RELATED_POST, $draft );

		$html  = $this->render( array( 'photo' => 'bare' ) );
		$panel = substr( $html, (int) strpos( $html, '<div class="dp-pw-panel-info">' ) );
		$panel = substr( $panel, 0, (int) strpos( $panel, '</section>' ) );

		$this->assertSame( '<div class="dp-pw-panel-info"></div>', $panel );

		foreach ( array( 'dp-pw-d-title', 'dp-pw-d-caption', 'dp-pw-d-story', 'dp-pw-d-chips', 'dp-pw-d-story-link', 'dp-pw-d-camera', 'dp-pw-tag' ) as $class ) {
			$this->assertStringNotContainsString( $class, $html, $class );
		}

		// Still a named link: the fallback name is never printed, only announced.
		$this->assertMatchesRegularExpression( '~<a class="dp-pw-tile"[^>]* aria-label="Photo from [^"]+"~', $html );
	}

	/**
	 * Every word on a control is the block's attribute.
	 *
	 * @return void
	 */
	public function test_the_copy_is_the_blocks_attributes(): void {
		$trip = $this->term( Taxonomies::TRIP, 'Putumayo' );
		$this->photo( array( 'trip' => $trip->term_id ) );

		$html = $this->wall->render_for(
			array(
				'allLabel'     => 'Everything',
				'tripsHeading' => 'Journeys',
				'showAllLabel' => 'Clear',
				'indexLabel'   => '',
			),
			array( 'trip' => 'putumayo' )
		);

		$this->assertStringContainsString( '<span class="dp-pw-entry-name">Everything</span>', $html );
		$this->assertStringContainsString( '<h2 class="dp-pw-index-heading">Journeys</h2>', $html );
		$this->assertStringContainsString( '>Clear</a>', $html );
		$this->assertStringContainsString( 'aria-label="Index"', $html, 'An emptied label falls back rather than drawing a nameless control.' );
	}

	/**
	 * The pop-up says how to move, in the block's words, and tells a screen reader about the keys.
	 *
	 * @return void
	 */
	public function test_the_lightbox_hints_are_copy_and_describe_the_dialog(): void {
		$this->photo();

		$html = $this->wall->render_for(
			array(
				'browseLabel' => 'Step',
				'swipeLabel'  => 'Swipe along',
			),
			array()
		);

		$this->assertStringContainsString( 'aria-describedby="dp-pw-keys"', $html );
		$this->assertMatchesRegularExpression( '~id="dp-pw-keys".*?<span>Step</span>.*?<kbd class="dp-pw-kbd" aria-hidden="true">Esc</kbd>.*?<span>Close</span>~s', $html );
		$this->assertStringContainsString( '<span class="dp-pw-lb-hint dp-pw-lb-hint-touch" aria-hidden="true">Swipe along</span>', $html );
		$this->assertStringNotContainsString( '←', $html, 'The arrows are drawn, not typed.' );
	}

	/**
	 * Tiles carry what the lightbox paints first, and never an iframe or a script.
	 *
	 * @return void
	 */
	public function test_a_tile_carries_its_lightbox_data(): void {
		$this->photo(
			array(
				'slug'  => 'wide',
				'image' => $this->image( 4000, 1000 ),
			)
		);

		$tags = new WP_HTML_Tag_Processor( $this->render() );

		$this->assertTrue(
			$tags->next_tag(
				array(
					'tag_name'   => 'A',
					'class_name' => 'dp-pw-tile',
				)
			)
		);
		$this->assertSame( '4000', $tags->get_attribute( 'data-w' ) );
		$this->assertSame( '1000', $tags->get_attribute( 'data-h' ) );
		$this->assertNotEmpty( $tags->get_attribute( 'data-src' ) );
		$this->assertTrue( $tags->next_tag( array( 'tag_name' => 'IMG' ) ) );
		$this->assertSame( '(max-width: 599px) 100vw, 560px', $tags->get_attribute( 'sizes' ), 'A panorama spans two columns.' );
		$this->assertNotEmpty( $tags->get_attribute( 'width' ) );
		$this->assertNotEmpty( $tags->get_attribute( 'height' ) );
	}
}
