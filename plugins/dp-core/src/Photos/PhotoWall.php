<?php
/**
 * The `dp/photo-wall` block.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Photos;

use WP_Post;
use WP_Query;

/**
 * The Photos page's index, wall and pop-up, in one server render.
 *
 * **Everything works with the scripts off** (`CLAUDE.md`, ADR-0007). An index
 * entry is a link to `?trip=` or `?topic=`, and this render applies it. A tile
 * is a link to `?photo=<slug>`, keeping the active filter, and this render then
 * draws that photo's whole pop-up — image, title, excerpt, story, chips, related
 * link, camera — in an open panel above the wall, with previous and next links
 * through the same set. "Show more" is a link to the next forty-eight. The
 * theme's `photos.js` upgrades each of these in place — filters without a page
 * load, the panel as a lightbox dialog, the next page appended — and deleting it
 * changes how fast the page responds and nothing about what it can do.
 *
 * **Where the pop-up's text comes from once scripted.** Every tile is followed by
 * an inert `<template>` holding the same details markup the panel prints
 * (`details()`), so opening a photo paints everything at once, from one renderer,
 * with no endpoint to maintain and nothing to authorise. The cost is bytes: a
 * page of tiles carries its photos' stories, and a page is forty-eight photos.
 * Template content is not rendered, fetched or run until it is cloned.
 *
 * **Nothing here is invented.** Counts, date ranges and the stats line are
 * computed (`Library`, `DateRange`) and the block is a named block in the
 * inserter, so the computation announces itself (ADR-0018); every word on a
 * control is a block attribute David edits in the sidebar; and a field he left
 * empty prints nothing (`Photo`).
 */
final class PhotoWall {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/photo-wall';

	/**
	 * How many tiles a page of the wall draws.
	 *
	 * @var int
	 */
	public const PER_PAGE = 48;

	/**
	 * The query arg that pages the wall.
	 *
	 * Not `page` or `paged`: both are core query vars, and on a page they mean
	 * "the Nth part of a `<!--nextpage-->` post", which would 404.
	 *
	 * @var string
	 */
	public const PAGE_ARG = 'photos-page';

	/**
	 * The query arg the script fetches one page with.
	 *
	 * `?photos-page=N` is a **depth**: it draws pages 1 to N, so a reload, a
	 * bookmark, the back button and a reader with no script all get the wall as
	 * deep as it had been scrolled, in one request, with nothing to stitch
	 * together. Appending needs the opposite — only the page after the last one
	 * on the wall — so the script adds `?photos-part=1` to the same URL and gets
	 * page N alone. It is a no-op anywhere else and never printed in a link.
	 *
	 * @var string
	 */
	public const PART_ARG = 'photos-part';

	/**
	 * The query arg that opens one photo.
	 *
	 * @var string
	 */
	public const PHOTO_ARG = 'photo';

	/**
	 * The anchor of the open-photo panel.
	 *
	 * @var string
	 */
	public const PANEL_ID = 'dp-photo';

	/**
	 * How many tiles at the top of a page load eagerly.
	 *
	 * Enough to fill the first screen at the widest layout (six columns, two
	 * rows and a bit); everything below it is `loading="lazy"`.
	 *
	 * @var int
	 */
	private const EAGER = 12;

	/**
	 * The attribute each visible string lives in, and its default.
	 *
	 * The defaults repeat `block.json`'s. They are here too because the block
	 * can be rendered with attributes the editor never saw — an old saved copy, a
	 * test, `do_blocks()` on a bare comment — and a control must never render
	 * with no words on it.
	 *
	 * @var array<string, string>
	 */
	private const COPY = array(
		'indexLabel'    => 'Index',
		'allLabel'      => 'All photos',
		'tripsHeading'  => 'Trips',
		'topicsHeading' => 'Topics',
		'showAllLabel'  => 'Show all',
		'showMoreLabel' => 'Show more',
		'storyLabel'    => 'Read the story',
		'browseLabel'   => 'Browse',
		'closeLabel'    => 'Close',
		'swipeLabel'    => 'Swipe to browse',
	);

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/photo-wall';

	/**
	 * The words on this render's controls.
	 *
	 * @var array<string, string>
	 */
	private array $copy = self::COPY;

	/**
	 * Constructor.
	 *
	 * @param string  $plugin_dir Absolute path to the plugin directory, without a trailing slash.
	 * @param Library $library    The published set.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly Library $library
	) {}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type(
			$this->plugin_dir . self::DEFINITION,
			array( 'render_callback' => $this->render( ... ) )
		);
	}

	/**
	 * Render the block for the current request.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		/*
		 * Read-only, on a public page, with nothing to forge: each value selects
		 * which already-public photos are drawn and is checked against the
		 * published set before anything is done with it. A nonce would protect
		 * nothing and would break every link anybody bookmarked — the same
		 * reasoning as the timeline's `dp-filter` (ADR-0007).
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; see above.
		$query = wp_unslash( $_GET );

		return $this->render_for( $attributes, is_array( $query ) ? $query : array() );
	}

	/**
	 * Render the block for a given set of query args.
	 *
	 * @param array<string, mixed>    $attributes The block's attributes.
	 * @param array<array-key, mixed> $query      The query args, unslashed.
	 * @return string
	 */
	public function render_for( array $attributes, array $query ): string {
		$this->copy = $this->copy_from( $attributes );

		$all = $this->library->entries();

		if ( array() === $all ) {
			return '';
		}

		$filter = Filter::from_query( $query );
		$open   = $this->library->find( $this->text_arg( $query, self::PHOTO_ARG ) );

		if ( null !== $open && ! $open->in( $filter ) ) {
			// A photo outside the filter it was linked with: the photo is the
			// request, so the filter is what gives way.
			$filter = Filter::none();
		}

		$set   = $this->library->filtered( $filter );
		$pages = max( 1, (int) ceil( count( $set ) / self::PER_PAGE ) );
		$page  = $this->page( $query, $pages, $set, $open );
		$first = '' !== $this->text_arg( $query, self::PART_ARG ) ? $page : 1;
		$slice = array_slice( $set, ( $first - 1 ) * self::PER_PAGE, ( $page - $first + 1 ) * self::PER_PAGE );
		$shown = $this->photos( $open ? array_merge( $slice, array( $open ) ) : $slice );
		$links = new Links( $this->base_url(), $filter );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'            => 'dp-pw',
				'data-dp-photos'   => '',
				'data-total'       => (string) count( $set ),
				'data-page'        => (string) $page,
				'data-pages'       => (string) $pages,
				'data-page-arg'    => self::PAGE_ARG,
				'data-part-arg'    => self::PART_ARG,
				'data-per-page'    => (string) self::PER_PAGE,
				'data-photo-arg'   => self::PHOTO_ARG,
				'data-filter'      => $filter->kind,
				'data-filter-slug' => $filter->slug(),
			)
		);

		return '<div ' . $wrapper . '>'
			. $this->stats( $all )
			. '<div class="dp-pw-body">'
			. $this->spine( $filter )
			. $this->index( $filter, $links, count( $all ) )
			. '<div class="dp-pw-main">'
			. $this->filter_line( $filter, count( $set ), $links )
			. ( null !== $open && isset( $shown[ $open->id ] ) ? $this->panel( $shown[ $open->id ], $set, $links ) : '' )
			. $this->wall( $slice, $shown, $links, ( $first - 1 ) * self::PER_PAGE )
			. $this->more( $page, $pages, $links )
			. $this->status()
			. '</div>'
			. '</div>'
			. $this->dock( $filter, count( $all ), count( $set ), $links )
			. '<dialog class="dp-pw-sheet" aria-label="' . esc_attr( $this->copy['indexLabel'] ) . '"><div class="dp-pw-grab" aria-hidden="true"></div></dialog>'
			. $this->lightbox()
			. $this->hint()
			. '</div>';
	}

	/**
	 * The mono line under the intro: "34 photos · 6 trips · 2016–2018".
	 *
	 * @param array<int, Entry> $all Every photo on the page.
	 * @return string
	 */
	private function stats( array $all ): string {
		$count = count( $all );
		$trips = count( $this->library->trips() );
		$first = '';
		$last  = '';

		foreach ( $all as $entry ) {
			$first = '' === $first ? $entry->date : min( $first, $entry->date );
			$last  = max( $last, $entry->date );
		}

		$parts = array(
			/* translators: %s: how many photos there are. */
			sprintf( _n( '%s photo', '%s photos', $count, 'dp-core' ), number_format_i18n( $count ) ),
		);

		if ( $trips > 0 ) {
			/* translators: %s: how many trips there are. */
			$parts[] = sprintf( _n( '%s trip', '%s trips', $trips, 'dp-core' ), number_format_i18n( $trips ) );
		}

		$years = DateRange::years( $first, $last );

		if ( '' !== $years ) {
			$parts[] = $years;
		}

		return '<p class="dp-pw-stats">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
	}

	/**
	 * The 44px rail the index slides out of, when there is no room for a margin.
	 *
	 * Drawn in every render and shown by the stylesheet only in spine mode once
	 * the script has claimed the block (`.is-enhanced`): a button that opens a
	 * panel is nothing without a script, so without one the index is simply
	 * drawn in the flow above the wall.
	 *
	 * @param Filter $filter The active filter.
	 * @return string
	 */
	private function spine( Filter $filter ): string {
		$current = $filter->active()
			? '<span class="dp-pw-spine-current"> · <b>' . esc_html( $filter->name() ) . '</b></span>'
			: '<span class="dp-pw-spine-current"></span>';

		return '<div class="dp-pw-spine">'
			. '<button type="button" class="dp-pw-spine-button" aria-expanded="false" aria-controls="dp-pw-index">'
			. self::icon( 'index' )
			. '<span class="dp-pw-spine-label">' . esc_html( $this->copy['indexLabel'] ) . $current . '</span>'
			. '</button>'
			. '</div>';
	}

	/**
	 * The index: all photos, then the trips, then the topics.
	 *
	 * @param Filter $filter The active filter.
	 * @param Links  $links  Builds the hrefs.
	 * @param int    $total  How many photos there are in all.
	 * @return string
	 */
	private function index( Filter $filter, Links $links, int $total ): string {
		$trips  = '';
		$topics = '';

		foreach ( $this->library->trips() as $group ) {
			$trips .= $this->entry( $group, $filter, $links );
		}

		foreach ( $this->library->topics() as $group ) {
			$topics .= $this->entry( $group, $filter, $links );
		}

		$all = sprintf(
			'<li><a class="dp-pw-entry dp-pw-entry-all" href="%1$s" data-kind="" data-slug="" data-count="%2$d"%3$s><span class="dp-pw-entry-name">%4$s</span><span class="dp-pw-entry-n">%5$s</span></a></li>',
			esc_url( $links->filtered( Filter::none() ) ),
			$total,
			$filter->active() ? '' : ' aria-current="page"',
			esc_html( $this->copy['allLabel'] ),
			esc_html( number_format_i18n( $total ) )
		);

		return '<nav class="dp-pw-index" id="dp-pw-index" aria-label="' . esc_attr( $this->copy['indexLabel'] ) . '">'
			. '<div class="dp-pw-index-inner">'
			. '<button type="button" class="dp-pw-index-close" aria-label="' . esc_attr__( 'Close the index', 'dp-core' ) . '">' . self::icon( 'close' ) . '</button>'
			. '<ul class="dp-pw-index-list dp-pw-index-all">' . $all . '</ul>'
			. ( '' === $trips ? '' : '<h2 class="dp-pw-index-heading">' . esc_html( $this->copy['tripsHeading'] ) . '</h2><ul class="dp-pw-index-list dp-pw-trips">' . $trips . '</ul>' )
			. ( '' === $topics ? '' : '<h2 class="dp-pw-index-heading">' . esc_html( $this->copy['topicsHeading'] ) . '</h2><ul class="dp-pw-index-list dp-pw-topics">' . $topics . '</ul>' )
			. '</div>'
			. '</nav>';
	}

	/**
	 * One index entry.
	 *
	 * @param Group  $group  The trip or topic.
	 * @param Filter $filter The active filter.
	 * @param Links  $links  Builds the hrefs.
	 * @return string
	 */
	private function entry( Group $group, Filter $filter, Links $links ): string {
		$target = Filter::for_term( $group->term );
		$when   = Filter::TRIP === $target->kind ? $group->when() : '';

		return sprintf(
			'<li><a class="dp-pw-entry" href="%1$s" data-kind="%2$s" data-slug="%3$s" data-count="%4$d"%5$s><span class="dp-pw-entry-name">%6$s</span><span class="dp-pw-entry-n">%7$s</span>%8$s</a></li>',
			esc_url( $links->filtered( $target ) ),
			esc_attr( $target->kind ),
			esc_attr( $target->slug() ),
			$group->count,
			$target->is( $filter ) ? ' aria-current="page"' : '',
			esc_html( $group->term->name ),
			esc_html( number_format_i18n( $group->count ) ),
			'' === $when ? '' : '<span class="dp-pw-entry-when">' . esc_html( $when ) . '</span>'
		);
	}

	/**
	 * The line above the wall: "Duitama, Boyacá · Dec 2017 · 6 photos · Show all".
	 *
	 * Always in the document, and `hidden` when nothing is filtered, so the
	 * script has one element to fill rather than one to create; the stylesheet
	 * keeps its height on wide layouts, so filtering does not push the wall down.
	 *
	 * @param Filter $filter The active filter.
	 * @param int    $count  How many photos it shows.
	 * @param Links  $links  Builds the hrefs.
	 * @return string
	 */
	private function filter_line( Filter $filter, int $count, Links $links ): string {
		if ( ! $filter->active() ) {
			return '<p class="dp-pw-filter" hidden></p>';
		}

		$group = null;

		foreach ( Filter::TRIP === $filter->kind ? $this->library->trips() : array() as $candidate ) {
			if ( $candidate->term->term_id === $filter->term_id() ) {
				$group = $candidate;
			}
		}

		$when = null === $group ? '' : $group->when();

		return '<p class="dp-pw-filter">'
			. '<strong class="dp-pw-filter-name">' . esc_html( $filter->name() ) . '</strong>'
			. ( '' === $when ? '' : '<span class="dp-pw-filter-when">' . esc_html( $when ) . '</span>' )
			/* translators: %s: how many photos the filter shows. */
			. '<span class="dp-pw-filter-count">' . esc_html( sprintf( _n( '%s photo', '%s photos', $count, 'dp-core' ), number_format_i18n( $count ) ) ) . '</span>'
			. '<a class="dp-pw-filter-clear" href="' . esc_url( $links->filtered( Filter::none() ) ) . '" data-kind="" data-slug="">' . esc_html( $this->copy['showAllLabel'] ) . '</a>'
			. '</p>';
	}

	/**
	 * The wall: one link per photo, each followed by its details.
	 *
	 * @param array<int, Entry> $slice  The entries on this page.
	 * @param array<int, Photo> $shown  Their photos, by post ID.
	 * @param Links             $links  Builds the hrefs.
	 * @param int               $offset How many photos precede this page in the set.
	 * @return string
	 */
	private function wall( array $slice, array $shown, Links $links, int $offset ): string {
		$tiles = '';

		foreach ( $slice as $index => $entry ) {
			$photo = $shown[ $entry->id ] ?? null;

			if ( null !== $photo ) {
				$tiles .= $this->tile( $photo, $entry, $links, $offset + $index + 1, $index < self::EAGER && 0 === $offset );
			}
		}

		/*
		 * One shared Edit link for the whole wall, outside it, which the script
		 * floats over whichever tile is hovered or focused. It is not inside a
		 * tile because a tile is a link, and not beside each tile because tiles
		 * are placed by transform and reused across filters. Printed only for
		 * someone who may edit photos; each tile's own `data-edit` says whether
		 * it applies to that photo.
		 */
		$float = self::may_edit_photos() ? self::edit_link( '', 'dp-pw-edit-float' ) : '';

		return '<div class="dp-pw-wall">' . $tiles . '</div>' . $float;
	}

	/**
	 * One tile, and the template carrying its details.
	 *
	 * @param Photo $photo    The photo.
	 * @param Entry $entry    Its index entry.
	 * @param Links $links    Builds the hrefs.
	 * @param int   $position Its 1-based place in the filtered set.
	 * @param bool  $eager    Whether it is near enough the top to load at once.
	 * @return string
	 */
	private function tile( Photo $photo, Entry $entry, Links $links, int $position, bool $eager ): string {
		$meta   = wp_get_attachment_metadata( $photo->image );
		$width  = is_array( $meta ) && is_numeric( $meta['width'] ?? null ) ? (int) $meta['width'] : 0;
		$height = is_array( $meta ) && is_numeric( $meta['height'] ?? null ) ? (int) $meta['height'] : 0;
		$wide   = $height > 0 && $width / $height > 2.2;

		$image = wp_get_attachment_image(
			$photo->image,
			'medium_large',
			false,
			array(
				'class'    => 'dp-pw-img',
				'alt'      => '',
				'sizes'    => $wide ? '(max-width: 599px) 100vw, 560px' : '(max-width: 599px) 50vw, 280px',
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);

		$large  = wp_get_attachment_image_src( $photo->image, 'large' );
		$srcset = wp_get_attachment_image_srcset( $photo->image, 'full' );

		$topics = implode( ' ', array_map( static fn ( \WP_Term $term ): string => $term->slug, $photo->topics ) );

		/*
		 * The first tile of every page after the first carries an id, which is
		 * where "Show more" lands a reader with no script: the wall is drawn
		 * from the top again, one page deeper, and the fragment scrolls to what
		 * is new.
		 */
		$edit   = self::edit_url( $photo->post->ID );
		$anchor = 1 === $position % self::PER_PAGE && $position > 1
			? ' id="' . esc_attr( self::page_anchor( intdiv( $position, self::PER_PAGE ) + 1 ) ) . '"'
			: '';

		return sprintf(
			'<a class="dp-pw-tile"' . $anchor . ' href="%1$s" aria-label="%2$s" data-id="%3$d" data-slug="%4$s" data-position="%5$d" data-trip="%6$s" data-topics="%7$s" data-w="%8$d" data-h="%9$d" data-src="%10$s" data-srcset="%11$s"%15$s>%12$s%13$s</a><template class="dp-pw-details" data-for="%3$d">%14$s</template>',
			esc_url( $links->photo( $entry->slug ) . '#' . self::PANEL_ID ),
			esc_attr( $photo->label() ),
			$photo->post->ID,
			esc_attr( $entry->slug ),
			$position,
			esc_attr( null === $photo->trip ? '' : $photo->trip->slug ),
			esc_attr( $topics ),
			$width,
			$height,
			esc_url( is_array( $large ) && is_string( $large[0] ) ? $large[0] : '' ),
			esc_attr( is_string( $srcset ) ? $srcset : '' ),
			$image,
			'' === $photo->title ? '' : '<span class="dp-pw-tag" aria-hidden="true">' . esc_html( $photo->title ) . '</span>',
			$this->details( $photo, $links ),
			'' === $edit ? '' : ' data-edit="' . esc_url( $edit ) . '"'
		);
	}

	/**
	 * The pop-up's text column. One renderer for the panel and the lightbox.
	 *
	 * Each part is printed only when it has something in it.
	 *
	 * @param Photo $photo The photo.
	 * @param Links $links Builds the chips' hrefs.
	 * @return string
	 */
	private function details( Photo $photo, Links $links ): string {
		$chips = '';

		foreach ( array_merge( null === $photo->trip ? array() : array( $photo->trip ), $photo->topics ) as $term ) {
			$target = Filter::for_term( $term );
			$chips .= sprintf(
				'<a class="dp-pw-chip" href="%1$s" data-kind="%2$s" data-slug="%3$s">%4$s</a>',
				esc_url( $links->filtered( $target ) ),
				esc_attr( $target->kind ),
				esc_attr( $target->slug() ),
				esc_html( $term->name )
			);
		}

		return ( '' === $photo->title ? '' : '<h2 class="dp-pw-d-title">' . esc_html( $photo->title ) . '</h2>' )
			. ( '' === $photo->excerpt ? '' : '<p class="dp-pw-d-caption">' . esc_html( $photo->excerpt ) . '</p>' )
			. ( '' === $photo->story ? '' : '<div class="dp-pw-d-story">' . $photo->story . '</div>' )
			. ( '' === $chips ? '' : '<p class="dp-pw-d-chips">' . $chips . '</p>' )
			. ( '' === $photo->related ? '' : '<a class="dp-pw-d-story-link" href="' . esc_url( $photo->related ) . '">' . esc_html( $this->copy['storyLabel'] ) . ' <span aria-hidden="true">→</span></a>' )
			. ( '' === $photo->camera ? '' : '<p class="dp-pw-d-camera">' . esc_html( $photo->camera ) . '</p>' );
	}

	/**
	 * The open photo, drawn in the page for a request carrying `?photo=`.
	 *
	 * The scriptless half of the lightbox: the same image, the same details, a
	 * counter, previous and next as links through the same filtered set, and a
	 * close link back to the wall. The script replaces it with the dialog.
	 *
	 * @param Photo             $photo The open photo.
	 * @param array<int, Entry> $set   The filtered set it is in.
	 * @param Links             $links Builds the hrefs.
	 * @return string
	 */
	private function panel( Photo $photo, array $set, Links $links ): string {
		$position = 0;

		foreach ( $set as $index => $entry ) {
			if ( $entry->id === $photo->post->ID ) {
				$position = $index;
			}
		}

		$previous = $set[ $position - 1 ] ?? null;
		$next     = $set[ $position + 1 ] ?? null;

		$image = wp_get_attachment_image(
			$photo->image,
			'large',
			false,
			array(
				'class'    => 'dp-pw-panel-img',
				'alt'      => $photo->label(),
				'sizes'    => '(max-width: 599px) 100vw, calc(100vw - 480px)',
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);

		$step = static function ( ?Entry $to, string $modifier, string $label, string $icon ) use ( $links ): string {
			return null === $to
				? '<span class="dp-pw-panel-step ' . $modifier . '" aria-hidden="true">' . self::icon( $icon ) . '</span>'
				: '<a class="dp-pw-panel-step ' . $modifier . '" href="' . esc_url( $links->photo( $to->slug ) . '#' . self::PANEL_ID ) . '" aria-label="' . esc_attr( $label ) . '">' . self::icon( $icon ) . '</a>';
		};

		return '<section class="dp-pw-panel" id="' . esc_attr( self::PANEL_ID ) . '" data-id="' . esc_attr( (string) $photo->post->ID ) . '" aria-label="' . esc_attr( $photo->label() ) . '">'
			. '<div class="dp-pw-panel-top">'
			. '<span class="dp-pw-count">' . esc_html( self::counter( $position + 1, count( $set ) ) ) . '</span>'
			. '<div class="dp-pw-top-actions">'
			. self::edit_link( self::edit_url( $photo->post->ID ), 'dp-pw-panel-edit' )
			. '<a class="dp-pw-panel-close" href="' . esc_url( $links->filtered( $links->filter ) ) . '" aria-label="' . esc_attr__( 'Close the photo', 'dp-core' ) . '">' . self::icon( 'close' ) . '</a>'
			. '</div>'
			. '</div>'
			. '<div class="dp-pw-panel-stage">'
			. $step( $previous, 'dp-pw-prev', __( 'Previous photo', 'dp-core' ), 'prev' )
			. $image
			. $step( $next, 'dp-pw-next', __( 'Next photo', 'dp-core' ), 'next' )
			. '</div>'
			. '<div class="dp-pw-panel-info">' . $this->details( $photo, $links ) . '</div>'
			. '</section>';
	}

	/**
	 * "Show more", when there is a next page.
	 *
	 * @param int   $page  The page being drawn.
	 * @param int   $pages How many there are.
	 * @param Links $links Builds the href.
	 * @return string
	 */
	private function more( int $page, int $pages, Links $links ): string {
		if ( $page >= $pages ) {
			return '';
		}

		return '<p class="dp-pw-more"><a class="dp-pw-more-link" href="' . esc_url( $links->page( $page + 1 ) . '#' . self::page_anchor( $page + 1 ) ) . '" data-page="' . esc_attr( (string) ( $page + 1 ) ) . '">' . esc_html( $this->copy['showMoreLabel'] ) . '</a></p>';
	}

	/**
	 * The phone's docked pill: "Index 34", or "Putumayo 5" and a clear button.
	 *
	 * Drawn always and shown only in phone mode once the script has claimed the
	 * block; without a script the index is in the flow above the wall instead.
	 *
	 * @param Filter $filter The active filter.
	 * @param int    $total  How many photos there are in all.
	 * @param int    $count  How many the filter shows.
	 * @param Links  $links  Builds the clear link's href.
	 * @return string
	 */
	private function dock( Filter $filter, int $total, int $count, Links $links ): string {
		return '<div class="dp-pw-dock">'
			. '<button type="button" class="dp-pw-dock-open" aria-haspopup="dialog">'
			. self::icon( 'index' )
			. '<span class="dp-pw-dock-label">' . esc_html( $filter->active() ? $filter->name() : $this->copy['indexLabel'] ) . '</span>'
			. '<span class="dp-pw-dock-n">' . esc_html( number_format_i18n( $filter->active() ? $count : $total ) ) . '</span>'
			. '</button>'
			. '<a class="dp-pw-dock-clear" href="' . esc_url( $links->filtered( Filter::none() ) ) . '" data-kind="" data-slug="" aria-label="' . esc_attr__( 'Show all photos', 'dp-core' ) . '"' . ( $filter->active() ? '' : ' hidden' ) . '>' . self::icon( 'close' ) . '</a>'
			. '</div>';
	}

	/**
	 * The lightbox: one dialog for the whole set, filled by the script.
	 *
	 * @return string
	 */
	private function lightbox(): string {
		return '<dialog class="dp-pw-lb" aria-label="' . esc_attr__( 'Photo', 'dp-core' ) . '" aria-describedby="dp-pw-keys">'
			. '<div class="dp-pw-lb-top">'
			. '<div class="dp-pw-lb-meta">'
			. '<span class="dp-pw-count" aria-live="polite"></span>'
			. $this->hints()
			. '</div>'
			. '<div class="dp-pw-top-actions">'
			. ( self::may_edit_photos() ? self::edit_link( '', 'dp-pw-lb-edit' ) : '' )
			. '<button type="button" class="dp-pw-lb-close" aria-label="' . esc_attr__( 'Close the photo', 'dp-core' ) . '">' . self::icon( 'close' ) . '</button>'
			. '</div>'
			. '</div>'
			. '<div class="dp-pw-lb-stage">'
			. '<button type="button" class="dp-pw-lb-step dp-pw-prev" aria-label="' . esc_attr__( 'Previous photo', 'dp-core' ) . '">' . self::icon( 'prev' ) . '</button>'
			. '<img class="dp-pw-lb-img" alt="" decoding="async">'
			. '<button type="button" class="dp-pw-lb-step dp-pw-next" aria-label="' . esc_attr__( 'Next photo', 'dp-core' ) . '">' . self::icon( 'next' ) . '</button>'
			. '</div>'
			. '<div class="dp-pw-lb-info"></div>'
			. '</dialog>';
	}

	/**
	 * "5 below ↓" — shown while an index entry is lit and none of its photos is
	 * on screen, so the dimmed wall says where they went.
	 *
	 * A visual cue for a pointer hovering the index, so it is `aria-hidden`: the
	 * entry's own count is what a screen reader hears. The script fills in the
	 * number; the sentence around it is here so it can be translated.
	 *
	 * @return string
	 */
	private function hint(): string {
		return '<span class="dp-pw-hint" aria-hidden="true" hidden'
			/* translators: %s: how many lit photos are further down the page. */
			. ' data-below="' . esc_attr__( '%s below ↓', 'dp-core' ) . '"'
			/* translators: %s: how many lit photos are further up the page. */
			. ' data-above="' . esc_attr__( '%s above ↑', 'dp-core' ) . '"'
			. '></span>';
	}

	/**
	 * Where to edit a photo, for someone allowed to — and '' for anyone else.
	 *
	 * Admin chrome, like the admin bar's "Edit Page": checked on the server per
	 * photo, so neither the URL nor the control ever reaches a visitor who could
	 * not use it. Hiding it with CSS would still print the URL.
	 *
	 * @param int $post_id The photo.
	 * @return string
	 */
	private static function edit_url( int $post_id ): string {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		$url = get_edit_post_link( $post_id, 'raw' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Whether the current user may edit photos at all.
	 *
	 * Decides whether the two script-driven Edit links — the one floated over
	 * the wall and the one in the lightbox — are printed. Their href is empty
	 * until the script copies it from a tile that carries one.
	 *
	 * @return bool
	 */
	private static function may_edit_photos(): bool {
		$type = get_post_type_object( PostType::NAME );

		return null !== $type && current_user_can( (string) $type->cap->edit_posts );
	}

	/**
	 * The Edit pill, or '' when there is nowhere to go.
	 *
	 * With an empty URL (the script-driven ones) it is printed `hidden` with no
	 * href, and the script fills both in. Its words are admin chrome and are
	 * translated rather than David's copy: the visible "Edit", and the name
	 * "Edit this photo", which contains it (WCAG 2.5.3).
	 *
	 * @param string $url      The edit URL, or '' for a script-driven link.
	 * @param string $modifier An extra class.
	 * @return string
	 */
	private static function edit_link( string $url, string $modifier ): string {
		if ( '' === $url && 'dp-pw-panel-edit' === $modifier ) {
			return '';
		}

		return '<a class="dp-pw-edit ' . esc_attr( $modifier ) . '"'
			. ( '' === $url ? ' hidden' : ' href="' . esc_url( $url ) . '"' )
			. ' aria-label="' . esc_attr__( 'Edit this photo', 'dp-core' ) . '">'
			. self::icon( 'edit' )
			. '<span>' . esc_html__( 'Edit', 'dp-core' ) . '</span>'
			. '</a>';
	}

	/**
	 * The id of a page's first tile.
	 *
	 * @param int $page 1-based, two or more.
	 * @return string
	 */
	public static function page_anchor( int $page ): string {
		return 'dp-pw-p' . $page;
	}

	/**
	 * The polite live region, carrying the sentences the script fills it with.
	 *
	 * The script composes nothing of its own: "48 more photos loaded" is here,
	 * translatable, and the script only puts the number in.
	 *
	 * @return string
	 */
	private function status(): string {
		return '<p class="dp-pw-status dp-pw-sr" role="status"'
			/* translators: %s: how many photos were just added to the wall; always 1. */
			. ' data-loaded-one="' . esc_attr__( '%s more photo loaded', 'dp-core' ) . '"'
			/* translators: %s: how many photos were just added to the wall. */
			. ' data-loaded-many="' . esc_attr__( '%s more photos loaded', 'dp-core' ) . '"'
			. '></p>';
	}

	/**
	 * How to move around the pop-up: keys on a desk, a swipe on glass.
	 *
	 * Both are always in the markup; the stylesheet shows the keys only where
	 * the primary pointer is fine and can hover — a mouse, so a keyboard is
	 * almost certainly there too — and the swipe only where it is coarse.
	 *
	 * **The keys are the one thing the buttons cannot tell anybody.** "Previous
	 * photo" and "Close the photo" are announced from the buttons already, but
	 * that the arrow keys step and Escape closes is a shortcut with no control
	 * of its own. So the key hint is not hidden from assistive technology: it
	 * is the dialog's description (`aria-describedby`), read once as the dialog
	 * opens, in words — the drawn arrows are `aria-hidden` and a visually hidden
	 * phrase names them. The swipe hint is `aria-hidden`: a screen reader on a
	 * phone moves with its own gestures, and "swipe to browse" would be wrong
	 * advice there. Every visible word is a block attribute.
	 *
	 * @return string
	 */
	private function hints(): string {
		$arrow = static fn ( string $path ): string => '<kbd class="dp-pw-kbd" aria-hidden="true"><svg class="dp-pw-icon" width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="' . $path . '"/></svg></kbd>';

		return '<span class="dp-pw-lb-hint dp-pw-lb-hint-keys" id="dp-pw-keys">'
			. '<span class="dp-pw-lb-hint-group">'
			. $arrow( 'M7.5 2.5L4 6l3.5 3.5' )
			. $arrow( 'M4.5 2.5L8 6l-3.5 3.5' )
			. '<span class="dp-pw-sr">' . esc_html__( 'Left and right arrow keys:', 'dp-core' ) . ' </span>'
			. '<span>' . esc_html( $this->copy['browseLabel'] ) . '</span>'
			. '</span>'
			. '<span class="dp-pw-lb-hint-group">'
			. '<kbd class="dp-pw-kbd" aria-hidden="true">Esc</kbd>'
			. '<span class="dp-pw-sr">' . esc_html__( 'Escape key:', 'dp-core' ) . ' </span>'
			. '<span>' . esc_html( $this->copy['closeLabel'] ) . '</span>'
			. '</span>'
			. '</span>'
			. '<span class="dp-pw-lb-hint dp-pw-lb-hint-touch" aria-hidden="true">' . esc_html( $this->copy['swipeLabel'] ) . '</span>';
	}

	/**
	 * "07 / 34".
	 *
	 * @param int $position 1-based.
	 * @param int $total    The set's size.
	 * @return string
	 */
	public static function counter( int $position, int $total ): string {
		return sprintf( '%02d / %02d', $position, $total );
	}

	/**
	 * How deep to draw the wall: pages 1 to this.
	 *
	 * The depth asked for, and at least deep enough to hold the open photo, so
	 * its tile is on the wall for the lightbox to step from. Page one for
	 * anything that is not a page of this set.
	 *
	 * @param array<array-key, mixed> $query The query args.
	 * @param int                     $pages How many pages the set has.
	 * @param array<int, Entry>       $set   The filtered set.
	 * @param Entry|null              $open  The open photo.
	 * @return int
	 */
	private function page( array $query, int $pages, array $set, ?Entry $open ): int {
		$raw   = $this->text_arg( $query, self::PAGE_ARG );
		$asked = ctype_digit( $raw ) && (int) $raw >= 1 && (int) $raw <= $pages ? (int) $raw : 1;

		if ( null !== $open ) {
			foreach ( $set as $index => $entry ) {
				if ( $entry->id === $open->id ) {
					return max( $asked, intdiv( $index, self::PER_PAGE ) + 1 );
				}
			}
		}

		return $asked;
	}

	/**
	 * The photos for a set of entries, loaded in one query, by post ID.
	 *
	 * @param array<int, Entry> $entries The entries to draw.
	 * @return array<int, Photo>
	 */
	private function photos( array $entries ): array {
		$ids = array_values( array_unique( array_map( static fn ( Entry $entry ): int => $entry->id, $entries ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => PostType::NAME,
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $ids ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		update_post_thumbnail_cache( $query );

		$photos = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$photo = Photo::from_post( $post );

			if ( null !== $photo ) {
				$photos[ $post->ID ] = $photo;
			}
		}

		return $photos;
	}

	/**
	 * The URL every link on the wall is built from.
	 *
	 * The page being viewed, by its own permalink, so no stray argument from the
	 * current request survives into a link. Rendered anywhere else — the
	 * editor's preview, a test — it is the current URL without the wall's own
	 * arguments.
	 *
	 * @return string
	 */
	private function base_url(): string {
		$id = get_queried_object_id();

		if ( $id > 0 && is_singular() ) {
			$url = get_permalink( $id );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return remove_query_arg( array( Filter::TRIP, Filter::TOPIC, self::PHOTO_ARG, self::PAGE_ARG, self::PART_ARG ) );
	}

	/**
	 * One scalar query arg, sanitised as a slug-like token.
	 *
	 * @param array<array-key, mixed> $query The query args.
	 * @param string                  $name  The arg.
	 * @return string
	 */
	private function text_arg( array $query, string $name ): string {
		$value = $query[ $name ] ?? '';

		return is_string( $value ) ? sanitize_title( $value ) : '';
	}

	/**
	 * The words for this render: the block's attributes over the defaults.
	 *
	 * An attribute David emptied falls back to the default rather than drawing
	 * a control with no words on it.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return array<string, string>
	 */
	private function copy_from( array $attributes ): array {
		$copy = self::COPY;

		foreach ( self::COPY as $key => $default ) {
			$value        = $attributes[ $key ] ?? $default;
			$copy[ $key ] = is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : $default;
		}

		return $copy;
	}

	/**
	 * One of the five inline icons. Decorative, always `aria-hidden`.
	 *
	 * @param string $name `index`, `edit`, `close`, `prev` or `next`.
	 * @return string
	 */
	private static function icon( string $name ): string {
		return match ( $name ) {
			'index' => '<svg class="dp-pw-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3 4.5h12M3 9h12M3 13.5h7"/></svg>',
			'edit'  => '<svg class="dp-pw-icon" width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.5 2.5l3 3L5.5 13.5H2.5v-3z"/><path d="M9 4l3 3"/></svg>',
			'close' => '<svg class="dp-pw-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/></svg>',
			'prev'  => '<svg class="dp-pw-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>',
			default => '<svg class="dp-pw-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>',
		};
	}
}
