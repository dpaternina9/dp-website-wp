<?php
/**
 * Everything the contact form is, assembled.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Contact;

/**
 * One object that knows the whole of the contact form.
 *
 * Order matters once: the handler attaches before the block registers, because
 * the block asks the handler what this request decided and a handler that had
 * not run yet would answer "draw the form" on a page that had just been posted
 * to. Both are attached on `init`, and the handler's own work happens later, on
 * `template_redirect`.
 */
final class Contact {

	/**
	 * Constructor.
	 *
	 * @param Capability  $capability Grants `dp_send_message`.
	 * @param Handler     $handler    Decides what a POST means.
	 * @param ContactForm $block      Draws whichever state that is.
	 * @param Settings    $settings   The two addresses, on Settings → General.
	 */
	private function __construct(
		private readonly Capability $capability,
		private readonly Handler $handler,
		private readonly ContactForm $block,
		private readonly Settings $settings
	) {}

	/**
	 * Build the form with its default collaborators.
	 *
	 * Nothing in this call path touches WordPress, so it is safe before `init`.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return self
	 */
	public static function create( string $plugin_dir ): self {
		$handler = new Handler();

		return new self( new Capability(), $handler, new ContactForm( $plugin_dir, $handler ), new Settings() );
	}

	/**
	 * Attach everything.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->capability->register();
		$this->handler->register();
		$this->block->register();
		$this->settings->register();
	}
}
