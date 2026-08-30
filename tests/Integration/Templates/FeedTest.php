<?php
/**
 * The feed the footer points at.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Integration\Templates;

use DP\Core\Content\ContentModel;
use DP\Tests\Integration\Blocks\HouseStyleFixture;
use DP\Theme\Blocks\FeedLink;
use SimpleXMLElement;

/**
 * Phase 8's one deliverable: RSS, and the link that finds it.
 *
 * `docs/plan.md` Phase 8 is deliberately almost nothing — SEO is AIOSEO's,
 * analytics is Rybbit's, and this repo writes neither. What is left is "RSS at
 * `/rss.xml` (the footer links it), with the full house-style markup surviving",
 * and until this file there was no test that the feed **renders** at all.
 * `ChromeTest` and `ComputedLinksTest` assert that the footer's block prints
 * `get_feed_link()`; nothing asserted that the URL it prints answers with a
 * feed, or what is in it.
 *
 * **`/rss.xml` is not a URL WordPress serves.** The design writes
 * `<a href="/rss.xml">` and `SiteFooter.dc.html` calls it "the one real href in
 * the whole design". Serving that exact path would need a rewrite rule, and
 * CLAUDE.md §5.1 allows the project exactly two registered rewrites, both
 * documented. So the link follows core instead — `/feed/` under pretty
 * permalinks, `?feed=rss2` without — which is what `DP\Theme\Blocks\FeedLink`
 * already does and what the first test below pins. The design and the
 * implementation disagree on the *path* and agree on the *destination*.
 *
 * The document is produced the way core's own feed tests produce it: visit the
 * URL, then include the template that a `do_feed_rss2` request would include.
 * Anything less — inspecting `$wp_query` and calling it a feed — is the test
 * `HomeTest` already has, and it cannot see a single element of the XML.
 */
final class FeedTest extends TemplateTestCase {

	/**
	 * Register the content model, and give the feed something to describe.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		ContentModel::create()->register();
	}

	/**
	 * Render the RSS 2.0 document for the current request.
	 *
	 * `feed-rss2.php` is the file `do_feed_rss2()` loads, and it echoes rather
	 * than returning. Including it under an output buffer is how core tests its
	 * own feeds, and it keeps this test on the same path a reader's feed reader
	 * takes rather than on a reimplementation of it.
	 *
	 * That file opens with `header()`, and PHPUnit has already printed its own
	 * banner to stdout, so PHP raises "Cannot modify header information" — a
	 * fact about the test runner's output rather than about the feed. The
	 * handler below swallows exactly that message and hands everything else back
	 * to PHPUnit, so a real warning raised while the feed renders still fails.
	 *
	 * @param string $url The feed URL to visit.
	 * @return string The XML document.
	 */
	private function render_feed( string $url ): string {
		$this->go_to( $url );

		$this->assertTrue( is_feed(), 'The URL the footer prints has to be answered by a feed.' );

		global $post;

		$previous = set_error_handler(
			static function ( int $number, string $message, string $file = '', int $line = 0 ) use ( &$previous ): bool {
				if ( str_contains( $message, 'Cannot modify header information' ) ) {
					return true;
				}

				return is_callable( $previous )
					&& false !== call_user_func( $previous, $number, $message, $file, $line );
			},
			E_WARNING
		);

		ob_start();

		try {
			require ABSPATH . 'wp-includes/feed-rss2.php';
		} finally {
			$feed = (string) ob_get_clean();

			restore_error_handler();
		}

		return $feed;
	}

	/**
	 * Parse the document, failing loudly rather than warning quietly.
	 *
	 * @param string $xml The document.
	 * @return SimpleXMLElement
	 */
	private function parse( string $xml ): SimpleXMLElement {
		$previous = libxml_use_internal_errors( true );
		$parsed   = simplexml_load_string( $xml );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->assertInstanceOf(
			SimpleXMLElement::class,
			$parsed,
			'The feed is not well-formed XML, which is the one thing a feed has to be.'
		);

		return $parsed;
	}

	/**
	 * The item whose title is given.
	 *
	 * @param SimpleXMLElement $feed  The parsed document.
	 * @param string           $title The post title.
	 * @return SimpleXMLElement
	 */
	private function item( SimpleXMLElement $feed, string $title ): SimpleXMLElement {
		foreach ( $feed->channel->item as $item ) {
			if ( (string) $item->title === $title ) {
				return $item;
			}
		}

		$this->fail( sprintf( 'The feed carries no item titled "%s".', $title ) );
	}

	/*
	 * ------------------------------------------------------ Where it lives
	 */

	/**
	 * The footer's link and the document that answers are the same URL.
	 *
	 * The point of the block is that no template can hold this address. The
	 * point of this test is that the address it computes is one that resolves —
	 * both under the permalink structure a fresh install has and under the one
	 * David runs.
	 *
	 * @return void
	 */
	public function test_the_footer_link_is_a_url_that_answers_with_a_feed(): void {
		$this->seed_categories();
		$this->seed_posts( 1 );

		$block = new FeedLink();

		foreach ( array( '', '/%postname%/' ) as $structure ) {
			$this->set_permalink_structure( $structure );

			$url = $block->url();

			$this->assertIsString( $url );
			$this->assertSame( get_feed_link(), $url );

			$feed = $this->parse( $this->render_feed( $url ) );

			$this->assertSame( 'rss', $feed->getName() );
			$this->assertSame( '2.0', (string) $feed['version'] );
			$this->assertSame(
				$url,
				(string) $feed->channel->children( 'atom', true )->link->attributes()['href'],
				'The feed names itself, and it names the URL the footer sent the reader to.'
			);
		}
	}

	/**
	 * `/rss.xml` is the design's path and not a route this repo registers.
	 *
	 * Pinned rather than fixed. CLAUDE.md §5.1: the only registered rewrites are
	 * the `dp_series` slug and the résumé `format` query var, and a third needs
	 * an ADR. If the design's exact path is ever wanted it is a redirect in
	 * David's edge configuration, not a rule here.
	 *
	 * @return void
	 */
	public function test_the_designs_rss_xml_path_is_not_a_registered_route(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$rules = get_option( 'rewrite_rules' );

		$this->assertIsArray( $rules );

		foreach ( array_keys( $rules ) as $pattern ) {
			$this->assertStringNotContainsString(
				'rss.xml',
				(string) $pattern,
				'Nothing in this repo may register a route for the design\'s /rss.xml.'
			);
		}

		$this->assertStringNotContainsString( 'rss.xml', get_feed_link() );
	}

	/*
	 * ------------------------------------------------------- What is in it
	 */

	/**
	 * The channel describes this site, and the items describe its posts.
	 *
	 * @return void
	 */
	public function test_the_feed_names_the_site_and_carries_its_posts(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$this->seed_categories();
		$posts = $this->seed_posts( 3 );

		$feed = $this->parse( $this->render_feed( get_feed_link() ) );

		$this->assertSame( get_bloginfo( 'name' ), (string) $feed->channel->title );
		$this->assertSame( home_url(), (string) $feed->channel->link );

		$this->assertCount( 3, $feed->channel->item );

		foreach ( $posts as $post_id ) {
			$item = $this->item( $feed, (string) get_the_title( $post_id ) );

			$this->assertSame(
				get_permalink( $post_id ),
				(string) $item->link,
				'An item has to link to the post, not to a query string a reader cannot share.'
			);
			$this->assertSame(
				gmdate( DATE_RFC2822, (int) get_post_time( 'U', true, $post_id ) ),
				(string) $item->pubDate,
				'The date in the feed is the post\'s own, in GMT, in the format RSS declares.'
			);
			$this->assertSame(
				'Dev',
				(string) $item->category,
				'The category the post is filed under travels with it.'
			);
			$this->assertStringContainsString(
				'The standfirst of post',
				(string) $item->description,
				'The excerpt David wrote is the summary, rather than a machine-cut one.'
			);
		}
	}

	/**
	 * The newest post is first, which is the only order a reader can rely on.
	 *
	 * @return void
	 */
	public function test_the_feed_runs_newest_first(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$this->seed_categories();
		$posts = $this->seed_posts( 3 );

		$feed  = $this->parse( $this->render_feed( get_feed_link() ) );
		$order = array();

		foreach ( $feed->channel->item as $item ) {
			$order[] = (string) $item->title;
		}

		$this->assertSame(
			array_map( static fn ( int $id ): string => (string) get_the_title( $id ), $posts ),
			$order
		);
	}

	/**
	 * The whole house style arrives rendered, not as block comments.
	 *
	 * The digest's §5.1 lists the vocabulary a post may use, and a reader in a
	 * feed reader gets the post rather than a web page — so what has to survive is
	 * the *markup*, with the block delimiters gone and the dynamic block having
	 * run. `<description>` stays the standfirst; `content:encoded` is the post.
	 *
	 * @return void
	 */
	public function test_the_house_style_survives_into_the_feed(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$this->seed_categories();

		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'The house style, and every piece of it',
				'post_excerpt'  => 'Every element I let myself use, once.',
				'post_content'  => HouseStyleFixture::content(),
				'post_category' => array( $this->categories['dev'] ),
			)
		);

		$this->assertIsInt( $post_id );

		$feed = $this->parse( $this->render_feed( get_feed_link() ) );
		$item = $this->item( $feed, 'The house style, and every piece of it' );

		$this->assertSame( 'Every element I let myself use, once.', (string) $item->description );

		$content = (string) $item->children( 'content', true )->encoded;

		$this->assertStringNotContainsString(
			'<!-- wp:',
			$content,
			'A block delimiter in the feed means the content was copied rather than rendered.'
		);

		foreach ( array(
			'a level-two heading'   => '<h2 class="wp-block-heading">',
			'a level-three heading' => '<h3 class="wp-block-heading">',
			'an unordered list'     => '<ul',
			'an ordered list'       => '<ol',
			'a quote'               => '<blockquote',
			'a code block'          => '<pre',
			'a table'               => '<table',
			'a separator'           => '<hr',
		) as $what => $needle ) {
			$this->assertStringContainsString(
				$needle,
				$content,
				sprintf( 'The house style has %s and the feed dropped it.', $what )
			);
		}

		$this->assertStringContainsString(
			'dp-callout',
			$content,
			'The callout is a dynamic block: if its render callback did not run in the '
				. 'feed, a reader gets an empty div where the caveat was.'
		);
	}

	/**
	 * Nothing that has no URL of its own is syndicated.
	 *
	 * `dp_role`, `dp_ship` and `dp_video` are `public => false` with no single
	 * view — `docs/plan.md` Phase 8 says so about sitemaps and it is the same
	 * fact here. A feed item is a link, and these have nowhere to link to.
	 *
	 * @return void
	 */
	public function test_the_feed_syndicates_posts_and_nothing_else(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$this->seed_categories();
		$this->seed_posts( 1 );

		foreach ( array( 'dp_role', 'dp_ship', 'dp_video' ) as $post_type ) {
			$created = self::factory()->post->create(
				array(
					'post_type'  => $post_type,
					'post_title' => sprintf( 'A %s nobody subscribed to', $post_type ),
				)
			);

			$this->assertIsInt( $created );
		}

		$feed = $this->parse( $this->render_feed( get_feed_link() ) );

		$this->assertCount( 1, $feed->channel->item );
		$this->assertStringNotContainsString( 'nobody subscribed to', $this->render_feed( get_feed_link() ) );
	}
}
