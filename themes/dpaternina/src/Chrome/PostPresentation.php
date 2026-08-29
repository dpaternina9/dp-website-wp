<?php
/**
 * The kicker and the tone a post is drawn in.
 *
 * @package DP\Theme
 */

declare( strict_types=1 );

namespace DP\Theme\Chrome;

use DP\Core\Content\SeriesParts;
use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Term;

/**
 * The coloured token above a post's title, derived the way the design derives it.
 *
 * `dpaternina.dc.html` computes it in one line, in three places:
 *
 *     kicker: p.part ? 'SERIES · PART ' + p.part : p.cat
 *     tone:   p.part ? 'pink' : 'teal'
 *
 * Neither is expressible in a template: a block binding returns a string but
 * cannot choose between two sources, and a class cannot be bound at all. So the
 * kicker arrives through a **block bindings source**, which is the sanctioned way
 * for a theme to feed a core block a computed string, and the tone arrives as a
 * class added at render time to any block asking for one.
 *
 * **Nothing here reads a stored override any more.** `dp_kicker`, `dp_tone` and
 * `dp_read_time` were registered meta fields with no editor control anywhere, so
 * the only value they could ever hold on a post David wrote was the empty string,
 * and the override branch that checked them first was dead code guarding a field
 * that could not be set. All three are deleted (ADR-0016); the derivations they
 * shadowed are the whole of what is left.
 *
 * All of it is presentation and all of it is the theme's: switching themes
 * changes what a kicker looks like and takes this derivation with it, while the
 * posts, the categories and the series membership stay in the database where
 * `dp-core` put them.
 *
 * The same source also reads the handful of `dp_role` and `dp_ship` fields the
 * homepage's record strip and the work cards print, and that is not a
 * convenience. `core/post-meta` refuses them outright: its callback returns null
 * unless `is_post_publicly_viewable()` agrees, and none of `dp-core`'s three
 * types is viewable — ADR-0003 registers them `public => false` because a role
 * has no URL of its own, not because its job title is a secret. Core's guard is
 * the right default for arbitrary meta on a hidden post; what it cannot know is
 * that these particular fields are the design's public copy. So they are named
 * here, one at a time, per post type. A key that is not on the list below is not
 * readable through this source, which is the difference between an allowlist and
 * a hole.
 */
final class PostPresentation {

	/**
	 * The bindings source name.
	 */
	public const SOURCE = 'dpaternina/post';

	/**
	 * The class that asks for the post's tone to be added.
	 */
	public const TONE_CLASS = 'dp-tone-auto';

	/**
	 * The block types that may carry `dp-tone-auto`.
	 *
	 * The filter used to be a bare `render_block`, which meant parsing a class
	 * attribute for every block on every page to find the one or two that asked
	 * — and ADR-0018's second rule is that a computation announces itself, not
	 * that it stands in the way of everything else. Two blocks carry the class
	 * today, both `core/paragraph`; `core/heading` and `core/group` are here
	 * because the badge is a run of text in a box and those are the two other
	 * shapes the design could reasonably draw it as.
	 *
	 * `DP\Tests\Integration\Templates\ChromeTest` holds the list against the
	 * shipped markup, so a fourth block type carrying the class fails a test
	 * rather than quietly losing its tone.
	 *
	 * @var list<string>
	 */
	public const TONE_BLOCKS = array( 'core/paragraph', 'core/heading', 'core/group' );

	/**
	 * The token a navigation label uses to ask for the neighbour's part number.
	 *
	 * The design labels the two cards in a post's series footer "← PART 1" and
	 * "PART 3 →" — the number belongs to the post being linked *to*, which is a
	 * thing `core/post-navigation-link`'s static `label` attribute cannot say.
	 * Core hands the finished link through `previous_post_link` / `next_post_link`
	 * with the adjacent post attached, so the number is substituted there, with
	 * no query of our own.
	 *
	 * A token rather than a class because the label is text and this replaces
	 * text. It is visible in the site editor's label field, which is the point:
	 * the template says out loud that the label is computed.
	 */
	public const PART_TOKEN = '%dp-part%';

	/**
	 * The tone a post in a series takes.
	 */
	private const SERIES_TONE = 'pink';

	/**
	 * The tone everything else takes.
	 */
	private const DEFAULT_TONE = 'teal';

	/**
	 * Words a minute, for the read time.
	 *
	 * The number every publication that prints one uses, and the one the design's
	 * own fixture is consistent with: its longest post is captioned "9 MIN READ".
	 * It is a constant rather than a filter because a site with one author reading
	 * at a different speed is not a thing anybody has asked for.
	 */
	private const WORDS_PER_MINUTE = 200;

	/**
	 * Read times already computed this request, by post ID.
	 *
	 * A post view prints one for the post and one for each of the three cards
	 * under it, and an archive row prints one per row. Counting the words of a
	 * body is cheap; counting them four times for the same body is waste.
	 *
	 * @var array<int, string>
	 */
	private array $read_times = array();

	/**
	 * The fields on a hidden post type this source will print, by post type.
	 *
	 * Every one of them is copy the design puts on a public page. Nothing here
	 * is a general meta reader: an unlisted key returns null, and a listed key
	 * returns nothing when asked for on the wrong post type.
	 *
	 * @var array<string, list<string>>
	 */
	private const PUBLIC_FIELDS = array(
		'dp_role' => array( 'dp_role_title', 'dp_range', 'dp_detail', 'dp_stack' ),
		'dp_ship' => array( 'dp_range', 'dp_headline', 'dp_detail', 'dp_line', 'dp_stack', 'dp_ship_role' ),
	);

	/**
	 * The role post type.
	 *
	 * Named as a string rather than through `DP\Core\Content\PostTypes`, the way
	 * the table above already is: a theme that names a plugin's class unguarded
	 * fatals on a site where the plugin is deactivated.
	 */
	private const ROLE_TYPE = 'dp_role';

	/**
	 * The shipped-work post type.
	 */
	private const SHIP_TYPE = 'dp_ship';

	/**
	 * Attach the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', $this->register_source( ... ) );

		foreach ( self::TONE_BLOCKS as $block ) {
			add_filter( 'render_block_' . $block, $this->add_tone_class( ... ), 10, 2 );
		}

		add_filter( 'previous_post_link', $this->number_the_part( ... ), 10, 5 );
		add_filter( 'next_post_link', $this->number_the_part( ... ), 10, 5 );
	}

	/**
	 * Register the bindings source.
	 *
	 * @return void
	 */
	public function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Post presentation', 'dpaternina' ),
				'get_value_callback' => $this->value( ... ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Resolve one bound value.
	 *
	 * @param array<string, mixed> $arguments The binding's arguments; `key` selects the field.
	 * @param mixed                $block     The block being rendered. Unused.
	 * @return string|null Null leaves the block's own content in place.
	 */
	public function value( array $arguments, mixed $block = null ): ?string {
		unset( $block );

		$key = isset( $arguments['key'] ) && is_string( $arguments['key'] ) ? $arguments['key'] : '';
		$id  = get_the_ID();

		if ( false === $id ) {
			return null;
		}

		return match ( $key ) {
			'kicker'       => $this->kicker( $id ),
			'short-kicker' => $this->short_kicker( $id ),
			'card-meta'    => $this->card_meta( $id ),
			'part'         => $this->part_label( $id ),
			'read-time'    => $this->read_time( $id ),
			'tone'         => $this->tone( $id ),
			'org'          => $this->org( $id ),
			default        => $this->public_field( $id, $key ),
		};
	}

	/**
	 * One allowlisted field on a `dp_role` or a `dp_ship`.
	 *
	 * @param int    $post_id The post.
	 * @param string $key     The meta key asked for.
	 * @return string|null Null when the key is not public for that post type.
	 */
	public function public_field( int $post_id, string $key ): ?string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$allowed = self::PUBLIC_FIELDS[ $post->post_type ] ?? array();

		if ( ! in_array( $key, $allowed, true ) ) {
			return null;
		}

		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * The organisation a shipped thing came out of.
	 *
	 * The design's `WorkCard` prints `org` — "FANXIE LAB", "MONSTERINSIGHTS" —
	 * beside the year, and its `featuredWork` fixture writes the string out on
	 * every card. Nothing in the content model repeats it, and deliberately so:
	 * `DP\Core\Content\Meta`'s own docblock says "`org` is never a meta field",
	 * because for a role the post title **is** the organisation. Storing it a
	 * second time on the ship would create two places to rename Fanxie Lab from.
	 *
	 * So it is derived: follow `dp_role_id` to the role and print its title.
	 * A role asked for its own org has nothing to derive — `core/post-title` is
	 * already the answer — so this key is a shipped thing's only.
	 *
	 * Null when there is nothing to print: an orphan ship, or a `dp_role_id`
	 * pointing at something that is not a role. Null leaves the bound block's own
	 * content in place, which on the card is empty — better than a stray title.
	 *
	 * @param int $post_id The post in the loop.
	 * @return string|null
	 */
	public function org( int $post_id ): ?string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::SHIP_TYPE !== $post->post_type ) {
			return null;
		}

		$role_id = get_post_meta( $post_id, 'dp_role_id', true );
		$role    = is_numeric( $role_id ) ? get_post( (int) $role_id ) : null;

		return $role instanceof WP_Post && self::ROLE_TYPE === $role->post_type
			? $role->post_title
			: null;
	}

	/**
	 * The kicker for one post.
	 *
	 * @param int $post_id The post.
	 * @return string|null
	 */
	public function kicker( int $post_id ): ?string {
		$part = $this->part( $post_id );

		if ( $part > 0 ) {
			/* translators: %d: the part's number within its series. */
			return sprintf( __( 'Series · Part %d', 'dpaternina' ), $part );
		}

		$category = $this->first_category( $post_id );

		return null === $category ? null : $category->name;
	}

	/**
	 * The kicker the design uses on a card rather than on a badge.
	 *
	 * The related-post cards under a post read `p.part ? 'PART ' + p.part : p.cat`
	 * — the number without the word SERIES in front of it, because the card is
	 * already inside a section about one post's neighbours. The badge above a
	 * title says `'SERIES · PART ' + p.part`. Two strings, two places, and the
	 * design writes both out; `kicker()` is the long one and this is the short.
	 *
	 * @param int $post_id The post.
	 * @return string|null
	 */
	public function short_kicker( int $post_id ): ?string {
		$part = $this->part_label( $post_id );

		if ( null !== $part ) {
			return $part;
		}

		$category = $this->first_category( $post_id );

		return null === $category ? null : $category->name;
	}

	/**
	 * "Dev · 6 MIN READ" — the one mono line under the featured panel's lede.
	 *
	 * The design builds it in `withOpen`: `meta: p.cat + ' · ' + p.read`. One
	 * string, one element, and no link inside it — the whole panel is the click
	 * target, so a linked category in the middle of it would be a second one.
	 * The theme used to draw this as a `core/post-terms` beside a bound
	 * paragraph, which is two elements, two link targets and a flex gap where
	 * the design has a space.
	 *
	 * Either half may be missing — an uncategorised post, or one whose read time
	 * has not been computed — and the separator goes with whichever half is not
	 * there rather than leaving a leading or trailing "·".
	 *
	 * @param int $post_id The post.
	 * @return string|null
	 */
	public function card_meta( int $post_id ): ?string {
		$category  = $this->first_category( $post_id );
		$read_time = $this->read_time( $post_id );

		$parts = array();

		if ( null !== $category ) {
			$parts[] = $category->name;
		}

		if ( null !== $read_time ) {
			$parts[] = $read_time;
		}

		return array() === $parts ? null : implode( ' · ', $parts );
	}

	/**
	 * "6 min read", counted from the post's own body.
	 *
	 * `dp_read_time`'s registered description claimed it was "computed on save,
	 * stored, and overridable by hand". Nothing ever computed it and nothing ever
	 * offered the hand — only the seeder wrote it — so on every post David wrote
	 * the byline drew an empty paragraph and the CSS hid it. Counting at render
	 * costs one already-cached read of `post_content` and cannot go stale.
	 *
	 * Block delimiters are HTML comments and `wp_strip_all_tags()` takes them out
	 * along with the markup, so what is counted is the words. The split is on
	 * Unicode whitespace rather than `str_word_count()`, which is ASCII-shaped and
	 * would undercount an accented word.
	 *
	 * @param int $post_id The post.
	 * @return string|null Null for a post with no body to count.
	 */
	public function read_time( int $post_id ): ?string {
		if ( isset( $this->read_times[ $post_id ] ) ) {
			return '' === $this->read_times[ $post_id ] ? null : $this->read_times[ $post_id ];
		}

		$this->read_times[ $post_id ] = '';

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$words = preg_split( '/[\p{Z}\s]+/u', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), -1, PREG_SPLIT_NO_EMPTY );
		$count = is_array( $words ) ? count( $words ) : 0;

		if ( 0 === $count ) {
			return null;
		}

		$minutes = max( 1, (int) ceil( $count / self::WORDS_PER_MINUTE ) );

		$this->read_times[ $post_id ] = sprintf(
			/* translators: %s: a whole number of minutes. */
			_n( '%s min read', '%s min read', $minutes, 'dpaternina' ),
			number_format_i18n( $minutes )
		);

		return $this->read_times[ $post_id ];
	}

	/**
	 * "Part 3", for a post that has a number in a series.
	 *
	 * The left column of the series archive's rows, and the label on each of the
	 * two part cards in a post's series footer.
	 *
	 * @param int $post_id The post.
	 * @return string|null Null when the post is in no series, or has no number yet.
	 */
	public function part_label( int $post_id ): ?string {
		$part = $this->part( $post_id );

		/* translators: %d: the part's number within its series. */
		return $part > 0 ? sprintf( __( 'Part %d', 'dpaternina' ), $part ) : null;
	}

	/**
	 * Fill in the part number a navigation label asked for.
	 *
	 * Runs on every adjacent-post link on the site and does nothing at all to one
	 * whose format does not carry the token, which is every link outside a series
	 * footer. `$post` is the neighbour core already found, so the number costs one
	 * meta read rather than a second query.
	 *
	 * A neighbour with no number — a post filed under the series before it was
	 * given a place in it — leaves the arrow and drops the words, rather than
	 * printing "Part 0".
	 *
	 * @param mixed $output   The rendered link.
	 * @param mixed $format   The format core built it from; carries the label.
	 * @param mixed $link     The link text pattern. Unused.
	 * @param mixed $post     The adjacent post, or an empty string when there is none.
	 * @param mixed $adjacent Which side. Unused.
	 * @return string
	 */
	public function number_the_part( mixed $output, mixed $format, mixed $link, mixed $post = null, mixed $adjacent = null ): string {
		unset( $link, $adjacent );

		if ( ! is_string( $output ) || ! is_string( $format ) || ! str_contains( $format, self::PART_TOKEN ) ) {
			return is_string( $output ) ? $output : '';
		}

		$label = $post instanceof WP_Post ? $this->part_label( $post->ID ) : null;

		return trim( str_replace( self::PART_TOKEN, null === $label ? '' : $label, $output ) );
	}

	/**
	 * The tone for one post.
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	public function tone( int $post_id ): string {
		return $this->part( $post_id ) > 0 ? self::SERIES_TONE : self::DEFAULT_TONE;
	}

	/**
	 * Add `is-tone-…` to any block that asked for it.
	 *
	 * @param string               $content The rendered block.
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string
	 */
	public function add_tone_class( string $content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$class_name = is_array( $attributes ) && isset( $attributes['className'] ) ? $attributes['className'] : '';

		if ( ! is_string( $class_name ) || ! str_contains( ' ' . $class_name . ' ', ' ' . self::TONE_CLASS . ' ' ) ) {
			return $content;
		}

		$id = get_the_ID();

		if ( false === $id ) {
			return $content;
		}

		$tone = sanitize_html_class( $this->tone( $id ) );

		if ( '' === $tone ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );

		if ( $processor->next_tag() ) {
			$processor->add_class( 'is-tone-' . $tone );
		}

		return $processor->get_updated_html();
	}

	/**
	 * The post's part number within its series, or zero.
	 *
	 * The position of the post among the published posts carrying its series
	 * term, oldest first. `dp-core` owns both the taxonomy and the ordered list,
	 * and memoises the list for the request, so an archive of twenty rows each
	 * asking for its own number still runs one query.
	 *
	 * Named through `class_exists()` rather than assumed, like every other seam
	 * this theme has on the plugin: with `dp-core` deactivated nothing is a part
	 * of anything, which is true.
	 *
	 * @param int $post_id The post.
	 * @return int
	 */
	private function part( int $post_id ): int {
		if ( ! class_exists( SeriesParts::class ) ) {
			return 0;
		}

		return ( new SeriesParts() )->part_of( $post_id );
	}

	/**
	 * The post's first category, in the order WordPress returns them.
	 *
	 * @param int $post_id The post.
	 * @return WP_Term|null
	 */
	private function first_category( int $post_id ): ?WP_Term {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return null;
		}

		$terms = get_the_terms( $post_id, 'category' );

		if ( ! is_array( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		return null;
	}
}
