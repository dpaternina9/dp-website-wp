<?php
/**
 * Unit tests for the maintenance gate's decision.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Maintenance;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DP\Core\Maintenance\Gate;
use DP\Core\Maintenance\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The whole feature is one sentence, and this is it.
 *
 * `closes()` decides who sees a site that is being built and who sees a holding
 * page, so every case below is written as a claim about a person or a context
 * rather than as a branch: the switch is off and nothing changes; the switch is
 * on and a visitor is stopped; the switch is on and somebody who can edit posts
 * is not; the switch is on and a scheduled job is not either.
 *
 * The capability is the part most likely to be got wrong later, so it is pinned
 * twice — that it is `edit_posts` and not `read`, because an account on a site
 * is not a reason to be shown an unfinished one, and that the filter can move it
 * but cannot empty it, because a blank capability is granted to everybody and
 * would silently turn the feature off.
 */
final class GateTest extends TestCase {

	/**
	 * Option name to stored value.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Capabilities the pretend current user holds.
	 *
	 * @var list<string>
	 */
	private array $capabilities = array();

	/**
	 * Start Brain Monkey and stand in for the two functions the gate uses.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		$this->options      = array();
		$this->capabilities = array();

		Functions\when( 'get_option' )->alias( $this->option( ... ) );
		Functions\when( 'current_user_can' )->alias( $this->can( ... ) );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
	}

	/**
	 * Stop Brain Monkey.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Stand in for `get_option()`.
	 *
	 * @param string $name The option name.
	 * @return mixed The stored value, or false for an option nobody has set.
	 */
	public function option( string $name ): mixed {
		return $this->options[ $name ] ?? false;
	}

	/**
	 * Stand in for `current_user_can()`.
	 *
	 * @param string $capability The capability being asked about.
	 * @return bool
	 */
	public function can( string $capability ): bool {
		return in_array( $capability, $this->capabilities, true );
	}

	/**
	 * A site with nothing configured is a site nothing happens to.
	 *
	 * This is the acceptance criterion for shipping it: installing the plugin
	 * must not change a running site.
	 *
	 * @return void
	 */
	public function test_it_is_off_until_the_switch_is_set(): void {
		$gate = new Gate();

		$this->assertFalse( $gate->is_on() );
		$this->assertFalse( $gate->closes() );
	}

	/**
	 * Only the stored `'1'` counts as on.
	 *
	 * An option can arrive by routes the sanitizer never saw, and a truthy-looking
	 * value that is not the one the checkbox writes must not switch a public site
	 * off.
	 *
	 * @return void
	 */
	public function test_only_the_switch_s_own_value_turns_it_on(): void {
		$gate = new Gate();

		foreach ( array( '', '0', 'yes', 'true', 0, null, array( '1' ) ) as $stored ) {
			$this->options[ Settings::ENABLED ] = $stored;

			$this->assertFalse( $gate->is_on(), sprintf( 'Stored %s read as on.', var_export( $stored, true ) ) );
		}

		$this->options[ Settings::ENABLED ] = '1';

		$this->assertTrue( $gate->is_on() );
	}

	/**
	 * On, and nobody signed in: the curtain is down.
	 *
	 * @return void
	 */
	public function test_a_visitor_is_shown_the_screen(): void {
		$this->options[ Settings::ENABLED ] = '1';

		$this->assertTrue( ( new Gate() )->closes() );
	}

	/**
	 * On, and somebody who can edit posts: the real site, unchanged.
	 *
	 * @return void
	 */
	public function test_somebody_who_can_edit_posts_sees_the_site(): void {
		$this->options[ Settings::ENABLED ] = '1';
		$this->capabilities                 = array( 'edit_posts' );

		$gate = new Gate();

		$this->assertTrue( $gate->may_see_the_site() );
		$this->assertFalse( $gate->closes() );
	}

	/**
	 * A subscriber is a member of the public.
	 *
	 * `read` is the capability every signed-in account has, so gating on it would
	 * have meant anybody who ever registered could read the unfinished site.
	 *
	 * @return void
	 */
	public function test_a_signed_in_reader_is_still_the_public(): void {
		$this->options[ Settings::ENABLED ] = '1';
		$this->capabilities                 = array( 'read' );

		$this->assertTrue( ( new Gate() )->closes() );
	}

	/**
	 * Cron is never curtained.
	 *
	 * A scheduled job answered with a 503 is not a page anybody sees; it is a job
	 * that stopped working, and nothing would say so.
	 *
	 * @return void
	 */
	public function test_cron_is_never_curtained(): void {
		$this->options[ Settings::ENABLED ] = '1';

		Functions\when( 'wp_doing_cron' )->justReturn( true );

		$this->assertTrue( ( new Gate() )->is_on(), 'The switch should still read as on.' );
		$this->assertFalse( ( new Gate() )->closes() );
	}

	/**
	 * The default capability is the editor's one, not the administrator's.
	 *
	 * @return void
	 */
	public function test_the_default_capability_is_edit_posts(): void {
		$this->assertSame( 'edit_posts', Gate::capability() );
	}

	/**
	 * `dp_maintenance_capability` widens the gate without a deploy.
	 *
	 * @return void
	 */
	public function test_the_capability_is_filterable(): void {
		Filters\expectApplied( 'dp_maintenance_capability' )->andReturn( 'read' );

		$this->options[ Settings::ENABLED ] = '1';
		$this->capabilities                 = array( 'read' );

		$this->assertSame( 'read', Gate::capability() );
		$this->assertFalse( ( new Gate() )->closes() );
	}

	/**
	 * A filter answering with the wrong shape narrows nothing and opens nothing.
	 *
	 * An empty capability is held by everyone, so honouring one would turn a
	 * mistyped snippet into a silently disabled feature.
	 *
	 * @return void
	 */
	public function test_a_malformed_capability_falls_back_to_the_default(): void {
		Filters\expectApplied( 'dp_maintenance_capability' )->andReturn( '' );

		$this->assertSame( Gate::CAPABILITY, Gate::capability() );
	}
}
