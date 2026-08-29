<?php
/**
 * The `dp/contact-form` block: three panels, one of which is showing.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

use WP_Block_Template;
use WP_Post;

/**
 * Draws whichever of the design's three contact states this request is in.
 *
 * `dpaternina.dc.html`'s contact composition is a gradient-edged card holding
 * one of `showForm`, `sent` or `failed`. All three are here, the handler
 * decides which, and the same method draws it for a normal page render and for
 * the `fetch` upgrade — so the enhanced path cannot drift from the plain one,
 * because there is only one of them.
 *
 * Four things are worth knowing before changing anything here.
 *
 * **The form posts to the page it is on.** No action attribute, which means the
 * current URL — so the block works on whatever page David assigned the contact
 * template to, under any slug, and CLAUDE.md rule 2 never comes into it.
 *
 * **The copy is the block's.** Every visible string is an attribute of
 * `dp/contact-form`, edited from the block's inspector and resolved through
 * `PanelCopy`, so the panel's voice is David's to change without a release.
 * The enhanced path renders before the block does — the handler answers the
 * `fetch` from `template_redirect` — so `placed_attributes()` reads the same
 * attributes out of wherever the block is saved: the queried page's content,
 * or the template that page renders through.
 *
 * **The failure panel is a form.** The design's copy promises "your message is
 * still in the form", and a panel that replaced the form would make that a lie.
 * So the three typed fields come back as hidden inputs behind a **fresh** nonce
 * and a **fresh** stamp, and "Try again" re-posts them. The old credentials are
 * deliberately not reused: a stamp that failed the timing check would fail it
 * again, and a nonce that expired is still expired.
 *
 * **The public email address is not assumed.** The design prints
 * `hello@dpaternina.com` in two places. That is placeholder copy (CLAUDE.md),
 * and the address the form delivers to is not necessarily one David wants
 * published. So the "email instead" fallback renders only when the public
 * address setting (`DP\Core\Contact\Settings`) — or the
 * `dp_contact_public_address` filter layered over it — gives it one.
 */
final class ContactForm {

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'dp/contact-form';

	/**
	 * The id of the panel, and what a re-try link points at.
	 *
	 * @var string
	 */
	public const ROOT_ID = 'dp-contact-form';

	/**
	 * Path to the block definition, relative to the plugin directory.
	 *
	 * @var string
	 */
	private const DEFINITION = '/blocks/contact-form';

	/**
	 * Constructor.
	 *
	 * @param string  $plugin_dir Absolute path to the plugin directory.
	 * @param Handler $handler    The request's decision, already made.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly Handler $handler
	) {}

	/**
	 * Register the block, and offer the handler a renderer for its JSON reply.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type(
			$this->plugin_dir . self::DEFINITION,
			array( 'render_callback' => $this->render( ... ) )
		);

		add_filter( 'dp_contact_panel_html', $this->panel_for( ... ), 10, 2 );
	}

	/**
	 * Render the block for the page.
	 *
	 * @param array<string, mixed> $attributes The block's attributes, defaults already merged.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		if ( ! Capability::form_is_open() ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'dp-contact-panel' ) );

		return '<div ' . $wrapper . '>' . $this->panel( $this->handler->outcome(), $attributes ) . '</div>';
	}

	/**
	 * Render one panel for the enhanced path.
	 *
	 * The `fetch` upgrade is answered from `template_redirect`, before any
	 * block renders, so the attributes have to be read from where the block is
	 * saved rather than handed in by the block loader.
	 *
	 * @param string $html    Whatever an earlier filter produced. Ignored.
	 * @param mixed  $outcome What the handler decided.
	 * @return string
	 */
	public function panel_for( string $html, mixed $outcome ): string {
		unset( $html );

		return $outcome instanceof Outcome ? $this->panel( $outcome, $this->placed_attributes() ) : '';
	}

	/**
	 * The panel for one outcome.
	 *
	 * @param Outcome              $outcome    What the handler decided.
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return string
	 */
	public function panel( Outcome $outcome, array $attributes = array() ): string {
		$copy = PanelCopy::from_attributes( $attributes );

		return match ( $outcome->state ) {
			State::Sent   => $this->sent( $copy ),
			State::Failed => $this->failed( $outcome, $copy ),
			State::Form   => $this->form( $copy, $outcome->submission ),
		};
	}

	/**
	 * The form itself.
	 *
	 * @param PanelCopy       $copy   The panel's copy.
	 * @param Submission|null $values Values to pre-fill, if any.
	 * @return string
	 */
	private function form( PanelCopy $copy, ?Submission $values = null ): string {
		$name    = null === $values ? '' : $values->name;
		$email   = null === $values ? '' : $values->email;
		$message = null === $values ? '' : $values->message;

		$note = '' === trim( $copy->note )
			? ''
			: sprintf( '<p class="dp-contact-note">%s</p>', esc_html( $copy->note ) );

		return sprintf(
			'<div class="dp-contact-card" id="%1$s" tabindex="-1" data-dp-contact-state="form">'
			. '<h2 class="dp-contact-heading">%2$s</h2>'
			. '<form class="dp-contact-form" method="post" novalidate>'
			. '%3$s'
			. '%4$s'
			. '%5$s'
			. '%6$s'
			. '%7$s'
			. '<button type="submit" class="dp-contact-submit">%8$s</button>'
			. '</form>'
			. '%9$s'
			. '</div>',
			esc_attr( self::ROOT_ID ),
			esc_html( $copy->heading ),
			$this->text_field( 'name', $copy->name_label, $copy->name_placeholder, $name, 'text', 'name' ),
			$this->text_field( 'email', $copy->email_label, $copy->email_placeholder, $email, 'email', 'email' ),
			$this->textarea_field( $copy->message_label, $copy->message_placeholder, $message ),
			$this->honeypot(),
			$this->credentials(),
			esc_html( $copy->submit_label ),
			$note
		);
	}

	/**
	 * The "It landed" panel.
	 *
	 * @param PanelCopy $copy The panel's copy.
	 * @return string
	 */
	private function sent( PanelCopy $copy ): string {
		$actions = $this->link_to_posts( $copy ) . $this->again_link( 'dp-contact-action-ghost', $copy->send_another_label );

		$line = '' === trim( $copy->sent_line )
			? ''
			: sprintf( '<p class="dp-contact-result-line">%s</p>', esc_html( $copy->sent_line ) );

		return sprintf(
			'<div class="dp-contact-card dp-contact-result dp-contact-sent" id="%1$s" tabindex="-1" data-dp-contact-state="sent" role="status">'
			. '<span class="dp-contact-mark dp-contact-mark-ok" aria-hidden="true">%2$s</span>'
			. '<h2 class="dp-contact-result-title">%3$s</h2>'
			. '%4$s'
			. '<div class="dp-contact-actions">%5$s</div>'
			. '</div>',
			esc_attr( self::ROOT_ID ),
			$this->tick(),
			esc_html( $copy->sent_heading ),
			$line,
			$actions
		);
	}

	/**
	 * The "That did not send" panel, carrying the message back.
	 *
	 * @param Outcome   $outcome What the handler decided.
	 * @param PanelCopy $copy    The panel's copy.
	 * @return string
	 */
	private function failed( Outcome $outcome, PanelCopy $copy ): string {
		$values  = $outcome->submission ?? new Submission( '', '', '' );
		$limited = Rejection::RateLimited === $outcome->rejection;

		$line = $limited ? $copy->rate_limited_line : $copy->failed_line;
		$line = '' === trim( $line )
			? ''
			: sprintf( '<p class="dp-contact-result-line">%s</p>', esc_html( $line ) );

		$retry = sprintf(
			'<form class="dp-contact-retry" method="post">%1$s%2$s%3$s%4$s%5$s'
			. '<button type="submit" class="dp-contact-action dp-contact-action-primary">%6$s</button>'
			. '</form>',
			$this->hidden( Submission::FIELDS['name'], $values->name ),
			$this->hidden( Submission::FIELDS['email'], $values->email ),
			$this->hidden( Submission::FIELDS['message'], $values->message ),
			$this->honeypot(),
			$this->credentials(),
			esc_html( $copy->try_again_label )
		);

		return sprintf(
			'<div class="dp-contact-card dp-contact-result dp-contact-failed" id="%1$s" tabindex="-1" data-dp-contact-state="failed" role="alert">'
			. '<span class="dp-contact-mark dp-contact-mark-bad" aria-hidden="true">%2$s</span>'
			. '<h2 class="dp-contact-result-title">%3$s</h2>'
			. '%4$s'
			. '<div class="dp-contact-actions">%5$s%6$s</div>'
			. '</div>',
			esc_attr( self::ROOT_ID ),
			$this->bang(),
			esc_html( $copy->failed_heading ),
			$line,
			$retry,
			$this->email_instead( $copy )
		);
	}

	/**
	 * One labelled single-line field.
	 *
	 * @param string $field       The key in Submission::FIELDS.
	 * @param string $label       The visible label.
	 * @param string $placeholder The placeholder.
	 * @param string $value       The current value.
	 * @param string $type        The input type.
	 * @param string $autocomplete The autocomplete token.
	 * @return string
	 */
	private function text_field( string $field, string $label, string $placeholder, string $value, string $type, string $autocomplete ): string {
		$id = self::ROOT_ID . '-' . $field;

		return sprintf(
			'<p class="dp-field"><label class="dp-field-label" for="%1$s">%2$s</label>'
			. '<input class="dp-field-input" id="%1$s" name="%3$s" type="%4$s" value="%5$s" placeholder="%6$s" autocomplete="%7$s" required></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( Submission::FIELDS[ $field ] ),
			esc_attr( $type ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			esc_attr( $autocomplete )
		);
	}

	/**
	 * The message field.
	 *
	 * @param string $label       The visible label.
	 * @param string $placeholder The placeholder.
	 * @param string $value       The current value.
	 * @return string
	 */
	private function textarea_field( string $label, string $placeholder, string $value ): string {
		$id = self::ROOT_ID . '-message';

		return sprintf(
			'<p class="dp-field"><label class="dp-field-label" for="%1$s">%2$s</label>'
			. '<textarea class="dp-field-input dp-field-textarea" id="%1$s" name="%3$s" rows="5" placeholder="%4$s" required>%5$s</textarea></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( Submission::FIELDS['message'] ),
			esc_attr( $placeholder ),
			esc_textarea( $value )
		);
	}

	/**
	 * The field no person ever fills in.
	 *
	 * Off screen rather than `display: none`: a field a screen reader cannot
	 * reach at all is also a field some form fillers skip, and the point is to
	 * be filled by the things that fill everything. `aria-hidden` and
	 * `tabindex="-1"` keep it away from anyone using the form as intended, and
	 * the label says what it is for anybody who arrives there anyway.
	 *
	 * Its label is deliberately **not** an attribute: it is an instruction to a
	 * machine-filled form, invisible by stylesheet, and blanking it would break
	 * the trap while looking like a copy edit.
	 *
	 * @return string
	 */
	private function honeypot(): string {
		$id = self::ROOT_ID . '-reference';

		return sprintf(
			'<div class="dp-hp" aria-hidden="true"><label for="%1$s">%2$s</label>'
			. '<input id="%1$s" name="%3$s" type="text" value="" tabindex="-1" autocomplete="off"></div>',
			esc_attr( $id ),
			esc_html__( 'Leave this field empty.', 'dp-core' ),
			esc_attr( Submission::FIELDS['honeypot'] )
		);
	}

	/**
	 * The marker, the nonce and the signed stamp, freshly issued.
	 *
	 * @return string
	 */
	private function credentials(): string {
		return $this->hidden( Submission::MARKER, '1' )
			. $this->hidden( Submission::FIELDS['nonce'], wp_create_nonce( Handler::ACTION ) )
			. $this->hidden( Submission::FIELDS['stamp'], ( new Stamp( time() ) )->issue() );
	}

	/**
	 * One hidden input.
	 *
	 * @param string $name  The field name.
	 * @param string $value The value.
	 * @return string
	 */
	private function hidden( string $name, string $value ): string {
		return sprintf(
			'<input type="hidden" name="%1$s" value="%2$s">',
			esc_attr( $name ),
			esc_attr( $value )
		);
	}

	/**
	 * "Read something", pointing at wherever the writing is.
	 *
	 * The URL comes from `dp_destination_url`, which the theme answers from
	 * Settings to Reading. With no theme listening, or no posts page chosen,
	 * there is no link — which is what the chrome does with an unresolved
	 * destination too, rather than pointing at a guess.
	 *
	 * @param PanelCopy $copy The panel's copy.
	 * @return string
	 */
	private function link_to_posts( PanelCopy $copy ): string {
		/**
		 * Filters the URL of a named destination.
		 *
		 * The plugin may not know where a page is (CLAUDE.md rule 2); the
		 * theme does, because David assigned it a template or a Reading setting.
		 * This is the seam between the two, and it names no class on either side.
		 *
		 * @since 0.1.0
		 *
		 * @param string|null $url         The URL, or null when nothing answers.
		 * @param string      $destination The destination's name, e.g. `posts`.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$url = apply_filters( 'dp_destination_url', null, 'posts' );

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="dp-contact-action dp-contact-action-secondary" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $copy->read_something_label )
		);
	}

	/**
	 * A link back to the empty form.
	 *
	 * @param string $variant The class the design gives this control.
	 * @param string $label   The label.
	 * @return string
	 */
	private function again_link( string $variant, string $label ): string {
		return sprintf(
			'<a class="dp-contact-action %1$s" href="%2$s">%3$s</a>',
			esc_attr( $variant ),
			esc_url( $this->here() . '#' . self::ROOT_ID ),
			esc_html( $label )
		);
	}

	/**
	 * The "email instead" fallback, when there is a published address.
	 *
	 * The address is the Public address on Settings → General
	 * (`DP\Core\Contact\Settings`), so publishing one is a wp-admin decision.
	 * Empty means unpublished and the panel offers no address — deliberately
	 * not a fallback to `admin_email`: where messages are delivered and what
	 * David publishes are two decisions, and only one of them belongs on a
	 * public page.
	 *
	 * @param PanelCopy $copy The panel's copy.
	 * @return string
	 */
	private function email_instead( PanelCopy $copy ): string {
		/**
		 * Filters the address the contact panel offers as a fallback.
		 *
		 * Receives the Public address setting, so the filter is an override
		 * layered on top of what David set in wp-admin, not the only way to
		 * publish one. Returning anything that is not an address suppresses
		 * the link.
		 *
		 * @since 0.1.0
		 *
		 * @param string $address A public email address, or '' for none.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `dp_` is this project's public filter prefix; WPCS rejects prefixes of three characters or fewer, so it cannot be declared in phpcs.xml.dist.
		$address = (string) apply_filters( 'dp_contact_public_address', Settings::public_address() );

		if ( is_email( $address ) !== $address ) {
			return '';
		}

		return sprintf(
			'<a class="dp-contact-action dp-contact-action-mail" href="%1$s">%2$s</a>',
			esc_url( 'mailto:' . $address ),
			esc_html( $copy->email_instead_label )
		);
	}

	/**
	 * The saved attributes of the block this request posted to.
	 *
	 * Looked for where the block can be saved, most specific first: the queried
	 * page's own content, then the template that page renders through — the
	 * page's assigned custom template, or `page` from the hierarchy. A block
	 * that is not found (none in scope, or the block sits somewhere this does
	 * not reach, such as inside a template part) resolves to no attributes, and
	 * `PanelCopy` answers with the design's defaults — the same copy the page
	 * showed before anything was customised.
	 *
	 * @return array<string, mixed>
	 */
	private function placed_attributes(): array {
		$page = get_queried_object();

		if ( ! $page instanceof WP_Post ) {
			return array();
		}

		$found = $this->attributes_in( (string) $page->post_content );

		if ( null !== $found ) {
			return $found;
		}

		foreach ( $this->template_candidates( $page ) as $slug ) {
			$template = get_block_template( get_stylesheet() . '//' . $slug );

			if ( ! $template instanceof WP_Block_Template || ! is_string( $template->content ) ) {
				continue;
			}

			$found = $this->attributes_in( $template->content );

			if ( null !== $found ) {
				return $found;
			}
		}

		return array();
	}

	/**
	 * The template slugs worth searching for one page, most specific first.
	 *
	 * @param WP_Post $page The queried page.
	 * @return list<string>
	 */
	private function template_candidates( WP_Post $page ): array {
		$candidates = array();

		if ( 'page' === $page->post_type ) {
			$assigned = get_page_template_slug( $page->ID );
			$assigned = is_string( $assigned ) ? $assigned : '';

			if ( str_ends_with( $assigned, '.html' ) ) {
				$assigned = substr( $assigned, 0, -5 );
			}

			if ( '' !== $assigned ) {
				$candidates[] = $assigned;
			}

			$candidates[] = 'page';
		}

		return $candidates;
	}

	/**
	 * The first `dp/contact-form` in a piece of saved markup, or null.
	 *
	 * @param string $content Serialised block markup.
	 * @return array<string, mixed>|null The block's saved attributes.
	 */
	private function attributes_in( string $content ): ?array {
		if ( ! has_block( self::BLOCK_NAME, $content ) ) {
			return null;
		}

		return $this->find_block( parse_blocks( $content ) );
	}

	/**
	 * Walk a parse tree for the block.
	 *
	 * @param array<int|string, mixed> $blocks A `parse_blocks()` tree.
	 * @return array<string, mixed>|null The block's saved attributes.
	 */
	private function find_block( array $blocks ): ?array {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( self::BLOCK_NAME === ( $block['blockName'] ?? null ) ) {
				$attributes = $block['attrs'] ?? null;

				return is_array( $attributes ) ? $this->named( $attributes ) : array();
			}

			$inner = $block['innerBlocks'] ?? null;

			if ( is_array( $inner ) ) {
				$found = $this->find_block( $inner );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Keep only string-keyed attributes, which is all a block can save.
	 *
	 * @param array<int|string, mixed> $attributes The parsed attributes.
	 * @return array<string, mixed>
	 */
	private function named( array $attributes ): array {
		$named = array();

		foreach ( $attributes as $key => $value ) {
			if ( is_string( $key ) ) {
				$named[ $key ] = $value;
			}
		}

		return $named;
	}

	/**
	 * The URL of the page being viewed.
	 *
	 * @return string
	 */
	private function here(): string {
		$id = get_queried_object_id();

		if ( $id > 0 ) {
			$permalink = get_permalink( $id );

			if ( is_string( $permalink ) ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}

	/**
	 * The tick in the teal ring.
	 *
	 * @return string
	 */
	private function tick(): string {
		return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
			. 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">'
			. '<path d="M20 6 9 17l-5-5"></path></svg>';
	}

	/**
	 * The exclamation in the pink ring.
	 *
	 * @return string
	 */
	private function bang(): string {
		return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
			. 'stroke-width="2" stroke-linecap="round" focusable="false">'
			. '<path d="M12 8v5"></path><path d="M12 17h.01"></path></svg>';
	}
}
