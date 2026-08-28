<?php
/**
 * The screen that puts a series in order.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Admin;

use DP\Core\Content\Taxonomies;
use WP_Post;
use WP_Term;

/**
 * One series' parts, in one list, dragged into the order they read in.
 *
 * **Why it exists.** A series' reading order used to be the publish date and
 * nothing else. A planned part is a draft, and a draft's `post_date` is whenever
 * it happened to be created, so "Still to come" came out in the order the drafts
 * were started. The only way to change it was the Publish panel's date picker on
 * a draft — obscure, and a future date silently turns the post into *Scheduled*
 * when it is published. This screen replaces that with dragging a row.
 *
 * **Where it is.** A row action on Posts → Series, so a series is picked before
 * an order is edited. It is deliberately not a menu entry: the screen is
 * meaningless without a term, and a menu item that leads to "which series did
 * you mean?" is a menu item that has to be answered before it can be used. The
 * page is registered under `edit.php` and then removed from the submenu, which
 * is the ordinary way to have a routed admin page that is not in the menu.
 *
 * Because `remove_submenu_page()` takes the entry out of `$submenu`, core's own
 * `user_can_access_admin_page()` falls through to the *parent's* capability —
 * `edit_posts` — rather than this page's. So the check below is not belt and
 * braces, it is the only capability check core will perform on a GET: `render()`
 * asks for it again, itself, and dies if it is not there.
 *
 * **The form is a form.** No fetch, no REST route, no nonce handed to
 * JavaScript, and therefore no inline `<script>` to hand it in (CLAUDE.md §1.4).
 * Each row carries a hidden input with its post ID; dragging a row moves the
 * input with it; submitting posts the IDs in the order the list is now in. The
 * JavaScript's entire job is `insertBefore`. With JavaScript off the screen
 * still renders the true order — it is simply not draggable, and there is
 * nothing to save.
 *
 * That is also why the Save button is a Save button rather than a write on drop:
 * it is what Appearance → Menus does, it lets several moves be made and
 * abandoned, and it needs no channel between the browser and PHP that the form
 * did not already provide.
 *
 * **Reordering is a mouse gesture and there is no keyboard equivalent**, which
 * is a deliberate limit rather than an oversight. WCAG 2.2 AA is an acceptance
 * criterion for the public site; the bar here is what wp-admin itself does, and
 * Appearance → Menus and the dashboard widgets are the same. Building a parallel
 * keyboard control would be a second implementation of the same reordering to
 * keep in step with the first. If this screen ever leaves a one-person site,
 * that trade should be revisited — the list is already a form, so the shape a
 * keyboard path would take is a pair of move buttons per row, not a rewrite.
 */
final class SeriesOrderScreen {

	/**
	 * The page slug, which is also its `?page=` value.
	 *
	 * @var string
	 */
	public const SLUG = 'dp-series-order';

	/**
	 * The admin-post action the form submits to, and the stem of its nonce.
	 *
	 * @var string
	 */
	public const ACTION = 'dp_core_series_order';

	/**
	 * The query variable naming the series, on the screen and in the row action.
	 *
	 * @var string
	 */
	public const TERM_VAR = 'dp_series_id';

	/**
	 * The field the ordered post IDs arrive in.
	 *
	 * @var string
	 */
	public const FIELD = 'dp_series_order';

	/**
	 * What a user needs to reorder somebody's posts.
	 *
	 * The screen lists every part of the series whoever wrote it, so the gate is
	 * `edit_others_posts`. `SeriesOrder::save()` then asks `edit_post` per row,
	 * because whether a particular post may be edited is a per-post question.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'edit_others_posts';

	/**
	 * The admin page this one hangs off.
	 *
	 * @var string
	 */
	private const PARENT = 'edit.php';

	/**
	 * The script handle and the file behind it.
	 *
	 * @var string
	 */
	private const HANDLE = 'dp-core-series-order';

	/**
	 * The stylesheet handle.
	 *
	 * @var string
	 */
	private const STYLE_HANDLE = 'dp-core-series-order';

	/**
	 * The hook suffix `add_submenu_page()` returned, or empty before `admin_menu`.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Constructor.
	 *
	 * @param SeriesOrder $order      The queries and the write.
	 * @param string      $plugin_file Absolute path to the plugin's entry file.
	 * @param string      $version     Plugin version, for asset cache busting.
	 */
	public function __construct(
		private readonly SeriesOrder $order,
		private readonly string $plugin_file,
		private readonly string $version
	) {}

	/**
	 * Attach the hooks.
	 *
	 * Every one of them is an admin-only hook, so there is no `is_admin()` guard:
	 * adding a guard would only make the wiring harder to exercise from a test.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', $this->add_page( ... ) );
		add_action( 'admin_post_' . self::ACTION, $this->handle( ... ) );
		add_action( 'admin_enqueue_scripts', $this->enqueue( ... ) );
		add_filter( Taxonomies::SERIES . '_row_actions', $this->row_action( ... ), 10, 2 );
	}

	/**
	 * Register the page, then take it back out of the menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			self::PARENT,
			__( 'Order series parts', 'dp-core' ),
			__( 'Order series parts', 'dp-core' ),
			self::CAPABILITY,
			self::SLUG,
			$this->render( ... )
		);

		$this->hook = is_string( $hook ) ? $hook : '';

		remove_submenu_page( self::PARENT, self::SLUG );
	}

	/**
	 * The hook suffix this page was registered under.
	 *
	 * Empty until `admin_menu` has run, which is also what makes `enqueue()` a
	 * no-op everywhere the page does not exist.
	 *
	 * @return string
	 */
	public function hook(): string {
		return $this->hook;
	}

	/**
	 * "Order parts", on every row of Posts → Series.
	 *
	 * @param array<string, string> $actions The row's links.
	 * @param WP_Term               $term    The series.
	 * @return array<string, string>
	 */
	public function row_action( array $actions, WP_Term $term ): array {
		if ( Taxonomies::SERIES !== $term->taxonomy || ! current_user_can( self::CAPABILITY ) ) {
			return $actions;
		}

		$actions['dp-order-parts'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->url( $term->term_id ) ),
			esc_html__( 'Order parts', 'dp-core' )
		);

		return $actions;
	}

	/**
	 * Load the screen's own assets, on the screen and nowhere else.
	 *
	 * @param string $hook_suffix The admin page being rendered.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			$this->asset_url( 'assets/css/series-order.css' ),
			array(),
			$this->asset_version( 'assets/css/series-order.css' )
		);

		wp_enqueue_script(
			self::HANDLE,
			$this->asset_url( 'assets/js/series-order.js' ),
			array(),
			$this->asset_version( 'assets/js/series-order.js' ),
			true
		);
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to order series parts.', 'dp-core' ),
				'',
				array( 'response' => 403 )
			);
		}

		$term = $this->requested_term();

		echo '<div class="wrap">';

		if ( ! $term instanceof WP_Term ) {
			printf( '<h1>%s</h1>', esc_html__( 'Order series parts', 'dp-core' ) );
			printf( '<p>%s</p>', esc_html__( 'Pick a series first. Every row on Posts → Series has an "Order parts" link.', 'dp-core' ) );
			$this->render_back_link();
			echo '</div>';

			return;
		}

		printf(
			'<h1>%s</h1>',
			esc_html(
				sprintf(
					/* translators: %s: the name of a series. */
					__( 'Order: %s', 'dp-core' ),
					$term->name
				)
			)
		);

		$this->render_notice();

		$posts = $this->order->posts( $term->term_id );

		if ( array() === $posts ) {
			printf( '<p>%s</p>', esc_html__( 'This series has no parts yet. File a post or a draft under it and it will appear here.', 'dp-core' ) );
			$this->render_back_link();
			echo '</div>';

			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Drag a part to move it, then save. Part numbers are the published parts counted from the top — they are never stored, so they can never disagree with this list.', 'dp-core' )
		);

		$this->render_form( $term, $posts );
		$this->render_back_link();

		echo '</div>';
	}

	/**
	 * Accept a submitted order.
	 *
	 * @return void
	 */
	public function handle(): void {
		$term_id = $this->posted_term_id();

		check_admin_referer( self::ACTION . '_' . $term_id );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to order series parts.', 'dp-core' ),
				'',
				array( 'response' => 403 )
			);
		}

		$term = get_term( $term_id, Taxonomies::SERIES );

		if ( ! $term instanceof WP_Term ) {
			wp_die(
				esc_html__( 'That series no longer exists.', 'dp-core' ),
				'',
				array( 'response' => 404 )
			);
		}

		$moved = $this->order->save( $term->term_id, $this->posted_ids() );

		wp_safe_redirect( add_query_arg( 'dp-moved', (string) $moved, $this->url( $term->term_id ) ) );

		exit;
	}

	/**
	 * The form, the list and the button.
	 *
	 * @param WP_Term             $term  The series.
	 * @param array<int, WP_Post> $posts Its parts, in order.
	 * @return void
	 */
	private function render_form( WP_Term $term, array $posts ): void {
		printf(
			'<form id="dp-series-order-form" method="post" action="%s">',
			esc_url( admin_url( 'admin-post.php' ) )
		);

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION ) );
		printf(
			'<input type="hidden" name="%1$s" value="%2$d" />',
			esc_attr( self::TERM_VAR ),
			(int) $term->term_id
		);

		wp_nonce_field( self::ACTION . '_' . $term->term_id );

		echo '<ol class="dp-series-order" data-dp-series-order>';

		$part = 0;

		foreach ( $posts as $post ) {
			$published = 'publish' === $post->post_status;

			if ( $published ) {
				++$part;
			}

			$this->render_row( $post, $published, $part );
		}

		echo '</ol>';

		submit_button( __( 'Save order', 'dp-core' ) );

		echo '</form>';
	}

	/**
	 * One row.
	 *
	 * @param WP_Post $post      The part.
	 * @param bool    $published Whether it is published, which is whether it has a number.
	 * @param int     $part      Its part number, when it has one.
	 * @return void
	 */
	private function render_row( WP_Post $post, bool $published, int $part ): void {
		$date = get_the_date( '', $post );
		$date = is_string( $date ) ? $date : '';

		printf(
			'<li class="dp-series-order-item" data-dp-post-id="%1$d" data-dp-published="%2$s">',
			(int) $post->ID,
			$published ? '1' : '0'
		);

		printf(
			'<span class="dp-series-order-handle" aria-hidden="true"></span><span class="dp-series-order-part" data-dp-part>%s</span>',
			$published ? esc_html( (string) $part ) : '&mdash;'
		);

		printf(
			'<span class="dp-series-order-title">%s</span>',
			esc_html( '' !== $post->post_title ? $post->post_title : __( '(no title)', 'dp-core' ) )
		);

		printf(
			'<span class="dp-series-order-status">%s</span>',
			esc_html(
				$published
					? sprintf(
						/* translators: %s: a publication date. */
						__( 'Published %s', 'dp-core' ),
						$date
					)
					: __( 'Draft', 'dp-core' )
			)
		);

		printf(
			'<input type="hidden" name="%1$s[]" value="%2$d" />',
			esc_attr( self::FIELD ),
			(int) $post->ID
		);

		echo '</li>';
	}

	/**
	 * "Order saved", or "nothing moved", after a redirect.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a count out of the URL to print a sentence. It writes nothing and it is never trusted for anything but its own message.
		$raw = isset( $_GET['dp-moved'] ) && is_string( $_GET['dp-moved'] ) ? sanitize_text_field( wp_unslash( $_GET['dp-moved'] ) ) : '';

		if ( ! is_numeric( $raw ) ) {
			return;
		}

		$moved = (int) $raw;

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				$moved > 0
					? sprintf(
						/* translators: %s: how many parts moved. */
						_n( 'Order saved. %s part moved.', 'Order saved. %s parts moved.', $moved, 'dp-core' ),
						number_format_i18n( $moved )
					)
					: __( 'Order saved. Nothing had moved.', 'dp-core' )
			)
		);
	}

	/**
	 * The way back to the list this screen was reached from.
	 *
	 * @return void
	 */
	private function render_back_link(): void {
		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'edit-tags.php?taxonomy=' . Taxonomies::SERIES ) ),
			esc_html__( 'Back to series', 'dp-core' )
		);
	}

	/**
	 * The series this request is about, from the URL.
	 *
	 * @return WP_Term|null Null when the URL names nothing, or names something that is not a series.
	 */
	private function requested_term(): ?WP_Term {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing which series to display changes nothing. The write path below has its own nonce.
		$raw = isset( $_GET[ self::TERM_VAR ] ) && is_string( $_GET[ self::TERM_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TERM_VAR ] ) ) : '';
		$id  = absint( $raw );

		if ( $id <= 0 ) {
			return null;
		}

		$term = get_term( $id, Taxonomies::SERIES );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * The series a submitted form named.
	 *
	 * Read before the nonce is checked, because the nonce is scoped to the term
	 * and cannot be named without it. Nothing is done with the value until
	 * `check_admin_referer()` has agreed, and a wrong value simply fails that
	 * check.
	 *
	 * @return int
	 */
	private function posted_term_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this *is* the value the nonce is scoped to; the check is the next statement in handle().
		$raw = isset( $_POST[ self::TERM_VAR ] ) && is_string( $_POST[ self::TERM_VAR ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TERM_VAR ] ) ) : '';

		return absint( $raw );
	}

	/**
	 * The post IDs a submitted form asked for, in the order it asked for them.
	 *
	 * Whole numbers and nothing else. Whether any of them is a part of the series
	 * is `SeriesOrder::save()`'s question, and it answers it against the database
	 * rather than against the request.
	 *
	 * @return list<int>
	 */
	private function posted_ids(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- handle() has already called check_admin_referer(), and every element is put through absint() below. The field has no other form.
		$raw = isset( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$id = absint( (string) $value );

			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The screen's URL for one series.
	 *
	 * @param int $term_id The `dp_series` term.
	 * @return string
	 */
	private function url( int $term_id ): string {
		return add_query_arg(
			array(
				'page'         => self::SLUG,
				self::TERM_VAR => (string) $term_id,
			),
			admin_url( self::PARENT )
		);
	}

	/**
	 * Public URL of a file in this plugin.
	 *
	 * @param string $relative Path from the plugin root, without a leading slash.
	 * @return string
	 */
	private function asset_url( string $relative ): string {
		return plugins_url( $relative, $this->plugin_file );
	}

	/**
	 * The version an asset is served under.
	 *
	 * The plugin version everywhere but a local install, where the file's own
	 * modified time is appended so that editing a stylesheet is visible on the
	 * next reload rather than on the next release. The same rule as the theme's
	 * `Theme::asset_version()`.
	 *
	 * @param string $relative Path from the plugin root, without a leading slash.
	 * @return string
	 */
	private function asset_version( string $relative ): string {
		if ( 'local' !== wp_get_environment_type() ) {
			return $this->version;
		}

		$path     = plugin_dir_path( $this->plugin_file ) . $relative;
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return false === $modified ? $this->version : $this->version . '.' . (string) $modified;
	}
}
