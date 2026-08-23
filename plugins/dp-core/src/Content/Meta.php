<?php
/**
 * Every field in the content model.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Content;

/**
 * Registers digest section 3 as typed, REST-exposed, authorised post and term meta.
 *
 * This is what stands in for ACF (`docs/plan.md`): `register_post_meta()` with an
 * explicit JSON-schema per field, so the editor, the REST API, the block bindings
 * and the tests all read the same declaration. Nothing here is a free-form
 * string bag — an enum field carries its `enum`, a year carries its bounds, and
 * anything outside those is refused by the REST controller before it reaches the
 * database.
 *
 * Three rules hold across the whole table:
 *
 * - **Every field has an `auth_callback`.** CLAUDE.md section 1.4. There is no
 *   inherited default; see `MetaAuth`.
 * - **Every field has a `sanitize_callback`**, which also runs on direct
 *   `update_post_meta()` calls, so the REST schema is a second gate rather than
 *   the only one.
 * - **`org` is never a meta field.** For a role the post title *is* the
 *   organisation, for a shipped thing it is the thing's name. Duplicating either
 *   into meta would create two places to rename it from.
 */
final class Meta {

	/**
	 * Constructor.
	 *
	 * @param MetaAuth $auth Supplies the permission callbacks.
	 */
	public function __construct( private readonly MetaAuth $auth ) {}

	/**
	 * Register every field.
	 *
	 * @return void
	 */
	public function register(): void {
		/*
		 * The two argument arrays are written out here rather than returned from
		 * a shared builder. A helper returning `array<string, mixed>` would make
		 * both calls unverifiable: static analysis can only check these against
		 * `register_post_meta()`'s declared shape while they are literals.
		 */
		foreach ( $this->post_fields() as $post_type => $fields ) {
			foreach ( $fields as $field ) {
				register_post_meta(
					$post_type,
					$field->key,
					array(
						'type'              => $field->type,
						'description'       => $field->description,
						'single'            => true,
						'default'           => $field->default_value(),
						'sanitize_callback' => $this->sanitizer( $field ),
						'auth_callback'     => $this->auth->post_meta( ... ),
						'show_in_rest'      => array( 'schema' => $this->schema( $field ) ),
					)
				);
			}
		}

		foreach ( $this->term_fields() as $taxonomy => $fields ) {
			foreach ( $fields as $field ) {
				register_term_meta(
					$taxonomy,
					$field->key,
					array(
						'type'              => $field->type,
						'description'       => $field->description,
						'single'            => true,
						'default'           => $field->default_value(),
						'sanitize_callback' => $this->sanitizer( $field ),
						'auth_callback'     => $this->auth->term_meta( ... ),
						'show_in_rest'      => array( 'schema' => $this->schema( $field ) ),
					)
				);
			}
		}
	}

	/**
	 * The post meta table, keyed by post type.
	 *
	 * @return array<string, list<MetaField>>
	 */
	public function post_fields(): array {
		return array(
			'post'           => $this->post_type_post_fields(),
			'page'           => $this->page_fields(),
			PostTypes::ROLE  => $this->role_fields(),
			PostTypes::SHIP  => $this->ship_fields(),
			PostTypes::VIDEO => $this->video_fields(),
		);
	}

	/**
	 * The term meta table, keyed by taxonomy.
	 *
	 * @return array<string, list<MetaField>>
	 */
	public function term_fields(): array {
		return array(
			Taxonomies::SERIES => array(
				new MetaField(
					'dp_series_deck',
					'string',
					__( 'The standfirst under the series title on its archive.', 'dp-core' ),
					multiline: true
				),
			),
		);
	}

	/**
	 * Every meta key this plugin registers, flattened.
	 *
	 * @return list<string>
	 */
	public function all_keys(): array {
		$keys = array();

		foreach ( array( $this->post_fields(), $this->term_fields() ) as $table ) {
			foreach ( $table as $fields ) {
				foreach ( $fields as $field ) {
					$keys[] = $field->key;
				}
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Fields on the native `post` type. Digest section 3.1.
	 *
	 * @return list<MetaField>
	 */
	private function post_type_post_fields(): array {
		return array(
			new MetaField(
				'dp_kicker',
				'string',
				__( 'Overrides the coloured token above the title. Empty means derive it: the series part if there is one, otherwise the category.', 'dp-core' )
			),
			new MetaField(
				'dp_tone',
				'string',
				__( 'Which hue the kicker and the badge take.', 'dp-core' ),
				allowed: Tone::meta_values()
			),
			new MetaField(
				'dp_read_time',
				'string',
				__( 'Reading time as it is printed, e.g. "6 MIN READ". Computed on save, stored, and overridable by hand.', 'dp-core' )
			),
			new MetaField(
				'dp_lead',
				'string',
				__( 'The standfirst paragraph above the body.', 'dp-core' ),
				multiline: true
			),
			new MetaField(
				'dp_hero_caption',
				'string',
				__( 'Mono caps caption under the lead image.', 'dp-core' )
			),
			new MetaField(
				'dp_series_part',
				'integer',
				__( 'Which part of its series this post is. 0 when it belongs to none.', 'dp-core' ),
				minimum: 0.0
			),
			new MetaField(
				'dp_series_years',
				'string',
				__( 'The years a planned part will cover, e.g. "1995 — 2007". Planned parts only.', 'dp-core' )
			),
			new MetaField(
				'dp_series_note',
				'string',
				__( 'One line describing a planned part, shown under "Still to come". Planned parts only.', 'dp-core' ),
				multiline: true
			),
		);
	}

	/**
	 * Fields on `page`. Digest section 3.7 — pages carry a deck and an updated stamp.
	 *
	 * @return list<MetaField>
	 */
	private function page_fields(): array {
		return array(
			new MetaField(
				'dp_lead',
				'string',
				__( 'The deck under the page title.', 'dp-core' ),
				multiline: true
			),
			new MetaField(
				'dp_updated',
				'string',
				__( 'The mono caps stamp at the top of the page, e.g. "UPDATED AUG 2026". Overrides the modified date.', 'dp-core' )
			),
		);
	}

	/**
	 * Fields on `dp_role`. Digest section 3.3.
	 *
	 * @return list<MetaField>
	 */
	private function role_fields(): array {
		return array(
			new MetaField(
				'dp_role_title',
				'string',
				__( 'The job title. The post title holds the organisation.', 'dp-core' )
			),
			$this->year_field( 'dp_start', __( 'When the role began, as a decimal year. The fraction is the month: 2026.4 is May 2026.', 'dp-core' ) ),
			$this->year_field( 'dp_end', __( 'When the role ended, as a decimal year. An ongoing role ends at today.', 'dp-core' ) ),
			new MetaField(
				'dp_range',
				'string',
				__( 'The range exactly as it is printed, e.g. "2016 — now". Never derived from the dates: "now" is a choice.', 'dp-core' )
			),
			new MetaField(
				'dp_detail',
				'string',
				__( 'What the job was and what it owned. Two or three sentences.', 'dp-core' ),
				multiline: true
			),
			new MetaField(
				'dp_stack',
				'string',
				__( 'The mono caps stack line, e.g. "PHP · VUE.JS · REST APIS".', 'dp-core' )
			),
			new MetaField(
				'dp_accent',
				'string',
				__( 'An accent this lane owns, overriding the default teal. A lane with one also earns a legend swatch.', 'dp-core' ),
				allowed: Tone::meta_values()
			),
		);
	}

	/**
	 * Fields on `dp_ship`. Digest section 3.4.
	 *
	 * @return list<MetaField>
	 */
	private function ship_fields(): array {
		return array(
			new MetaField(
				'dp_role_id',
				'integer',
				__( 'The role this hangs off. A shipped thing with no role does not appear on the timeline.', 'dp-core' ),
				minimum: 0.0
			),
			$this->year_field( 'dp_start', __( 'When work on it began, as a decimal year.', 'dp-core' ) ),
			$this->year_field( 'dp_end', __( 'When it shipped, or today if it is still going, as a decimal year.', 'dp-core' ) ),
			new MetaField(
				'dp_range',
				'string',
				__( 'The range exactly as it is printed, e.g. "2023 — now".', 'dp-core' )
			),
			new MetaField(
				'dp_headline',
				'string',
				__( 'One line, in the display face, at the top of the expanded panel.', 'dp-core' )
			),
			new MetaField(
				'dp_detail',
				'string',
				__( 'What it is and who it is for.', 'dp-core' ),
				multiline: true
			),
			new MetaField(
				'dp_line',
				'string',
				__( 'One short sentence, written for the card above the timeline. Not the same copy as the detail: the card gets a line, the expanded panel gets the paragraph.', 'dp-core' )
			),
			new MetaField(
				'dp_bullets',
				'array',
				__( 'The constraints that shaped it. Three is the house maximum.', 'dp-core' )
			),
			new MetaField(
				'dp_ship_role',
				'string',
				__( 'What David did on it, e.g. "Everything". Distinct from the role it hangs off.', 'dp-core' )
			),
			new MetaField(
				'dp_stack',
				'string',
				__( 'The mono caps stack line for this piece of work.', 'dp-core' )
			),
			new MetaField(
				'dp_artifact_label',
				'string',
				__( 'The label above the artifact block, e.g. "WP-CLI SESSION".', 'dp-core' )
			),
			new MetaField(
				'dp_artifact',
				'string',
				__( 'A preformatted terminal or code sample. Line breaks are content here, so they survive.', 'dp-core' ),
				multiline: true
			),
			new MetaField( 'dp_stat1', 'string', __( 'The first statistic. An em dash means the number is not in yet.', 'dp-core' ) ),
			new MetaField( 'dp_stat1_label', 'string', __( 'What the first statistic counts.', 'dp-core' ) ),
			new MetaField( 'dp_stat2', 'string', __( 'The second statistic.', 'dp-core' ) ),
			new MetaField( 'dp_stat2_label', 'string', __( 'What the second statistic counts.', 'dp-core' ) ),
			new MetaField(
				'dp_featured',
				'boolean',
				__( 'Whether this appears as a WorkCard above the timeline.', 'dp-core' )
			),
			new MetaField(
				'dp_writeup_id',
				'integer',
				__( 'A post that writes this up, if one exists. 0 when there is none.', 'dp-core' ),
				minimum: 0.0
			),
		);
	}

	/**
	 * Fields on `dp_video`. Digest section 3.5.
	 *
	 * @return list<MetaField>
	 */
	private function video_fields(): array {
		return array(
			new MetaField(
				'dp_video_source',
				'string',
				__( 'Where the video is hosted.', 'dp-core' ),
				allowed: VideoSource::meta_values()
			),
			new MetaField(
				'dp_video_ref',
				'string',
				__( 'The platform identifier: a Twitch VOD id or a YouTube video id. Read according to the source.', 'dp-core' )
			),
			new MetaField(
				'dp_tone',
				'string',
				__( 'Which hue the card takes. The design codes the platform with it.', 'dp-core' ),
				allowed: Tone::meta_values()
			),
			new MetaField( 'dp_duration', 'string', __( 'Runtime as it is printed, e.g. "2H 41M".', 'dp-core' ) ),
			new MetaField( 'dp_when', 'string', __( 'When it went out, e.g. "AUG 2026".', 'dp-core' ) ),
			new MetaField(
				'dp_note',
				'string',
				__( 'One line under the title.', 'dp-core' ),
				multiline: true
			),
			new MetaField(
				'dp_live',
				'boolean',
				__( 'Whether this is the live-now panel rather than an archived video.', 'dp-core' )
			),
			new MetaField(
				'dp_live_meta',
				'string',
				__( 'The live strapline, e.g. "STREAMING NOW · 1H 12M IN".', 'dp-core' )
			),
		);
	}

	/**
	 * A decimal-year field, bounded to what `Year` will accept.
	 *
	 * The bounds are on the REST schema as well as in the sanitiser so a bad
	 * value is a 400 with a readable message, not a silent zero.
	 *
	 * @param string $key         Meta key.
	 * @param string $description What it holds.
	 * @return MetaField
	 */
	private function year_field( string $key, string $description ): MetaField {
		return new MetaField(
			$key,
			'number',
			$description,
			is_year: true,
			minimum: (float) Year::MIN_YEAR,
			maximum: (float) ( Year::MAX_YEAR + 1 )
		);
	}

	/**
	 * The sanitiser for one field, bound to its declaration.
	 *
	 * The parameter is optional because WordPress declares `sanitize_callback`
	 * as taking none, and a closure that insists on one is not substitutable for
	 * that. It is always passed one in practice.
	 *
	 * @param MetaField $field The declaration.
	 * @return callable(): (string|int|float|bool|array<int, string>)
	 */
	private function sanitizer( MetaField $field ): callable {
		return fn ( mixed $value = '' ): string|int|float|bool|array => $this->sanitize( $field, $value );
	}

	/**
	 * The JSON schema the REST API validates against.
	 *
	 * @param MetaField $field The declaration.
	 * @return array<string, mixed>
	 */
	private function schema( MetaField $field ): array {
		$schema = array(
			'type'        => $field->type,
			'description' => $field->description,
		);

		if ( 'array' === $field->type ) {
			$schema['items'] = array( 'type' => 'string' );
		}

		if ( $field->is_enum() ) {
			$schema['enum'] = $field->allowed;
		}

		if ( $field->is_year ) {
			/*
			 * A year field holds either a real decimal year or zero, which is the
			 * sentinel for "no date yet" and therefore also the registered
			 * default. `register_meta()` validates that default against this very
			 * schema, so a bare `minimum` of 1900 would make the field refuse to
			 * register at all. `anyOf` is what says "one of two things" without
			 * widening the range to include every year between them.
			 */
			$schema['anyOf'] = array(
				array(
					'type' => 'number',
					'enum' => array( 0 ),
				),
				array(
					'type'    => 'number',
					'minimum' => $field->minimum,
					'maximum' => $field->maximum,
				),
			);

			return $schema;
		}

		if ( null !== $field->minimum ) {
			$schema['minimum'] = $field->minimum;
		}

		if ( null !== $field->maximum ) {
			$schema['maximum'] = $field->maximum;
		}

		return $schema;
	}

	/**
	 * Clean an incoming value.
	 *
	 * `mixed` is honest here and nowhere else: this is the boundary, and what
	 * arrives is whatever a request, an import or a `update_post_meta()` call
	 * put in front of us. Everything downstream of this function is typed.
	 *
	 * @param MetaField $field The declaration.
	 * @param mixed     $value The raw value.
	 * @return string|int|float|bool|list<string>
	 */
	private function sanitize( MetaField $field, mixed $value ): string|int|float|bool|array {
		if ( 'array' === $field->type ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			return array_values(
				array_map(
					static fn ( mixed $item ): string => sanitize_text_field( is_scalar( $item ) ? (string) $item : '' ),
					$value
				)
			);
		}

		if ( 'boolean' === $field->type ) {
			return (bool) $value;
		}

		if ( 'integer' === $field->type ) {
			$number = is_numeric( $value ) ? (int) $value : 0;

			return null !== $field->minimum ? max( (int) $field->minimum, $number ) : $number;
		}

		if ( 'number' === $field->type ) {
			return $this->sanitize_number( $field, $value );
		}

		$string = is_scalar( $value ) ? (string) $value : '';

		if ( $field->multiline ) {
			$string = sanitize_textarea_field( $string );
		} else {
			$string = sanitize_text_field( $string );
		}

		if ( $field->is_enum() && ! in_array( $string, $field->allowed, true ) ) {
			return '';
		}

		return $string;
	}

	/**
	 * Clean an incoming number, holding it inside the field's bounds.
	 *
	 * @param MetaField $field The declaration.
	 * @param mixed     $value The raw value.
	 * @return float
	 */
	private function sanitize_number( MetaField $field, mixed $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		$number = (float) $value;

		if ( ! is_finite( $number ) ) {
			return 0.0;
		}

		if ( $field->is_year ) {
			/*
			 * Zero is the sentinel for "no date yet", which is why the field's
			 * default is 0.0 and why the REST schema's minimum does not have to
			 * accommodate it: over REST a field is cleared by sending null,
			 * which deletes the row. Anything else that Year will not accept is
			 * stored as unset rather than as a bar somewhere in the year 3000.
			 */
			if ( 0.0 === $number ) {
				return 0.0;
			}

			return Year::try_from_float( $number )?->value() ?? 0.0;
		}

		if ( null !== $field->minimum && $number < $field->minimum ) {
			return $field->minimum;
		}

		if ( null !== $field->maximum && $number > $field->maximum ) {
			return $field->maximum;
		}

		return $number;
	}
}
