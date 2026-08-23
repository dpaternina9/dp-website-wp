<?php
/**
 * The reader that turns the design's computed styles back into declarations.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit;

use DP\Tests\Support\DesignLogic;
use RuntimeException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Guards `DP\Tests\Support\DesignLogic` against the two ways it could lie.
 *
 * It could read the wrong branch — bars and stack are the same object evaluated
 * under different flags, and half the chart's styling is a ternary on one of
 * them. Or it could fail quietly, which is the failure mode this whole
 * apparatus exists to remove: a reader that returns a shorter list when the
 * design grows a construct it cannot parse is a harness with a new hole in it.
 *
 * A unit test rather than an integration one: nothing here touches WordPress.
 * It reads `design-source/`, which is a directory of files, and the values it
 * asserts are the design's own text quoted back.
 */
final class DesignLogicTest extends TestCase {

	/**
	 * The chart's logic, which is where the interesting branches are.
	 */
	private const CHART = 'components/TimelineChart.logic.js';

	/**
	 * The reader under test.
	 *
	 * @var DesignLogic
	 */
	private DesignLogic $logic;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		$this->logic = DesignLogic::from_repository( dirname( __DIR__, 2 ) );
	}

	/**
	 * A mode flag selects a branch, and both branches are real values.
	 *
	 * `rowStyle` is the case that matters most: it is a function of the mode and
	 * of the open state, so it has four answers per mode, and for three review
	 * rounds the theme asserted none of them.
	 *
	 * @return void
	 */
	public function test_a_mode_selects_a_branch(): void {
		$bars = $this->logic->declarations(
			self::CHART,
			'rowStyle',
			1,
			array(
				'isStack'  => false,
				'isScroll' => false,
				'isOpen'   => true,
				'isShip'   => false,
			)
		);

		$stack = $this->logic->declarations(
			self::CHART,
			'rowStyle',
			1,
			array(
				'isStack'  => true,
				'isScroll' => false,
				'isOpen'   => true,
				'isShip'   => false,
			)
		);

		$this->assertSame( '8px 16px 14px', $bars['padding'] );
		$this->assertSame( '0 -16px', $bars['margin'] );
		$this->assertSame( '16px 12px 18px', $stack['padding'] );
		$this->assertSame( '0 -12px', $stack['margin'] );
	}

	/**
	 * A string built out of a ternary and a concatenation comes back whole.
	 *
	 * The open row's tint is `'color-mix(in srgb, var(--dp-white) ' + (…) + ',
	 * transparent)'`, and a shipped thing in bars mode is the one case that
	 * takes 2.5% rather than 4.
	 *
	 * @return void
	 */
	public function test_a_concatenated_value_comes_back_whole(): void {
		$ship = $this->logic->declarations(
			self::CHART,
			'rowStyle',
			1,
			array(
				'isStack'  => false,
				'isScroll' => false,
				'isOpen'   => true,
				'isShip'   => true,
			)
		);

		$this->assertSame( 'color-mix(in srgb, var(--dp-white) 2.5%, transparent)', $ship['background'] );
	}

	/**
	 * Arithmetic on two of the design's own numbers.
	 *
	 * A shipped thing's label column is the role's minus the rail, which is why
	 * its bar stays on the same year axis. The design writes it as
	 * `(labelW - railPad) + 'px minmax(0, 1fr)'` rather than as a number.
	 *
	 * @return void
	 */
	public function test_arithmetic_is_evaluated(): void {
		$grid = $this->logic->declarations(
			self::CHART,
			'shipGridStyle',
			1,
			array(
				'isStack'  => false,
				'isScroll' => false,
				'labelCol' => '200px minmax(0, 1fr)',
				'labelW'   => 200,
				'railPad'  => 20,
			)
		);

		$this->assertSame( '180px minmax(0, 1fr)', $grid['grid-template-columns'] );
	}

	/**
	 * A spread pulls in the object it names, and later keys win.
	 *
	 * @return void
	 */
	public function test_a_spread_is_resolved_in_place(): void {
		$tile = $this->logic->declarations( 'dpaternina.dc.html', 'tileLab', 1, array( 'narrow' => false ) );

		// From `bentoTile`, which `tileLab` spreads.
		$this->assertSame( 'var(--radius-lg)', $tile['border-radius'] );

		// `tileLab` overrides both of these after the spread.
		$this->assertSame( '32px', $tile['padding'] );
		$this->assertSame( 'var(--bg-surface)', $tile['background'] );
	}

	/**
	 * Numbers become pixels the way a DOM style attribute makes them pixels.
	 *
	 * Zero stays zero, a length gains `px`, and a unitless property keeps its
	 * number — `font-weight: 600`, not `600px`.
	 *
	 * @return void
	 */
	public function test_numbers_are_rendered_as_a_style_attribute_renders_them(): void {
		$ship = $this->logic->declarations(
			self::CHART,
			'orgStyle',
			2,
			array(
				'isStack' => false,
				'sOpen'   => false,
				'gold'    => 'var(--dp-gold)',
			)
		);

		$this->assertSame( '600', $ship['font-weight'] );

		$bar = $this->logic->declarations(
			self::CHART,
			'barStyle',
			1,
			array(
				'isOpen' => false,
				'color'  => 'var(--dp-gold)',
				'small'  => true,
			),
			array( 'top', 'min-width' )
		);

		$this->assertSame( '6px', $bar['top'] );
		$this->assertSame( '40px', $bar['min-width'] );
	}

	/**
	 * A property name is spelled the way CSS spells it.
	 *
	 * @return void
	 */
	public function test_property_names_are_kebab_cased(): void {
		$legend = $this->logic->declarations(
			self::CHART,
			'legendStyle',
			1,
			array(
				'isStack'  => false,
				'isScroll' => false,
			)
		);

		$this->assertArrayHasKey( 'flex-direction', $legend );
		$this->assertArrayHasKey( 'letter-spacing', $legend );
		$this->assertSame( 'var(--text-muted)', $legend['color'] );
	}

	/**
	 * A named constant is readable, because the markup interpolates it.
	 *
	 * @return void
	 */
	public function test_a_constant_map_can_be_read(): void {
		$this->assertSame(
			'var(--hue-gold)',
			$this->logic->constant( 'components/SectionHead.logic.js', 'TONES', 'gold' )
		);
	}

	/**
	 * An unbound identifier is a failure, not a silently missing property.
	 *
	 * @return void
	 */
	public function test_an_unbound_identifier_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/isStack/' );

		$this->logic->declarations( self::CHART, 'headlineStyle', 1, array() );
	}

	/**
	 * Asking for a property the design does not declare is a failure too.
	 *
	 * This is what stops a map entry from quietly asserting less than it says:
	 * if the design renames a property, the entry that named it fails rather
	 * than returning one declaration fewer.
	 *
	 * @return void
	 */
	public function test_asking_for_a_property_the_design_dropped_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/does not declare "border-collapse"/' );

		$this->logic->declarations(
			self::CHART,
			'legendStyle',
			1,
			array(
				'isStack'  => false,
				'isScroll' => false,
			),
			array( 'border-collapse' )
		);
	}

	/**
	 * A binding that is not there names itself and the file it was looked for in.
	 *
	 * @return void
	 */
	public function test_a_missing_binding_throws(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/no object literal named "gutterStyle"/' );

		$this->logic->declarations( self::CHART, 'gutterStyle' );
	}

	/**
	 * The properties an entry chose not to assert are still knowable.
	 *
	 * `declarations()` with an `only` list is how an entry pins the part of an
	 * object that is house style and leaves the part that is data; `properties()`
	 * is what lets the generator write down which names those were. A fixture
	 * that documents its own hole is the difference between this harness and the
	 * one that passed three times over half a component.
	 *
	 * @return void
	 */
	public function test_the_names_left_out_are_still_reported(): void {
		$environment = array(
			'isOpen' => true,
			'color'  => 'var(--dp-teal)',
			'small'  => false,
		);

		$all    = $this->logic->properties( self::CHART, 'barStyle', 1, $environment );
		$pinned = $this->logic->declarations( self::CHART, 'barStyle', 1, $environment, array( 'top', 'background' ) );

		$this->assertContains( 'left', $all, 'A bar\'s geometry is still part of the object.' );
		$this->assertSame( array( 'top', 'background' ), array_keys( $pinned ) );
	}

	/**
	 * A `.dc.html` with no script block says so, and says why it matters.
	 *
	 * `WorkCard` was checked at re-import and deliberately has no `*.logic.js`,
	 * because its script block is prop defaults. Asking it for a computed style
	 * has to fail loudly: a silent empty answer here is exactly the shape of the
	 * mistake that cost three audits.
	 *
	 * @return void
	 */
	public function test_a_component_with_no_script_block_says_so(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/carries no <script type="text\/x-dc"> block/' );

		$this->logic->declarations( 'components/WorkCard.dc.html', 'cardStyle' );
	}
}
