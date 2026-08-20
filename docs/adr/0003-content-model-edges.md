# ADR-0003 — The content model's edges

## Status

Accepted — 2026-08-20.

## Context

Phase 3 of `docs/plan.md` asks for the content model: three custom post types,
one taxonomy, `register_post_meta()` for every field in digest §3, a decimal-year
value object, the timeline geometry as pure functions, the series-parts query, and
a seed command.

Most of that the plan and the digest already decide. Phase 3.1 settles series
parts. Digest §3 lists the fields. `design-source/components/TimelineChart.dc.html`
gives the geometry as a specification block.

What follows are the places where the sources were silent, ambiguous, or in
tension with the toolchain — decisions that will still be shaping the code when
Phase 6 draws the chart and Phase 9 imports real content, and which would
otherwise have to be reconstructed from the diff.

## Decision

### 1. A decimal year's fraction is twelfths, and `2026.6` is August

Digest §3.3 says only "fractional years encode months: `2026.4` ≈ May 2026". Two
readings satisfy that:

| reading | `2026.4` | `2026.6` |
|---|---|---|
| tenths — `.4` is the fifth month | May | July |
| **twelfths — `floor( fraction × 12 ) + 1`** | **May** | **August** |

They agree on the stated example and disagree on the fixture's other value.
`2026.6` is Fanxie Lab's end, whose display range is `2016 — now`, in a design
authored in August 2026. Twelfths is the reading that makes "now" mean now, so
that is the encoding `DP\Core\Content\Year` implements, and the table above is a
test.

`Year` is closed — private constructor, named constructors that validate,
accessors only. `from_float()` throws for a non-finite value or one outside
1900–2200; `try_from_float()` returns null instead, for read paths over stored
data where one bad row should drop one bar rather than fatal a page. The bounds
are not taste: they are what makes a transposed value — a timestamp, a month
number, a percentage — fail loudly instead of drawing a bar off the chart.

`month()` rounds to nine places before flooring. That is load-bearing rather than
defensive: `2026 + 4/12` is stored as `2026.33333333333303`, whose fraction times
twelve is `3.999999999996362`, and flooring that directly reports April for a
value constructed as May.

### 2. Zero is "no date yet", and the REST schema says so with `anyOf`

A year field holds either a real decimal year or nothing. Nothing has to have a
representation, because `get_post_meta()` and the REST response must agree on what
an unset field looks like, and because `register_meta()` validates a field's
declared default against that field's own schema — so a bare `minimum: 1900` makes
the field refuse to register at all.

So `dp_start` and `dp_end` are registered with a default of `0.0` and a schema of:

```php
'anyOf' => array(
    array( 'type' => 'number', 'enum' => array( 0 ) ),
    array( 'type' => 'number', 'minimum' => 1900, 'maximum' => 2201 ),
)
```

which says "one of two things" without widening the range to include everything
between them. Over REST a field is cleared by sending `null`, which deletes the
row; zero never has to be written. The `sanitize_callback` enforces the same rule
a second time, because an importer or a WP-CLI command reaches
`update_post_meta()` without passing a schema at all.

### 3. The geometry clamps in two places the specification does not

`pos()` and the bar width are transcribed exactly. Two clamps are added:

- **`position()` is held to 0..100.** A role that started before the first
  labelled year is ordinary data David could enter tomorrow, and unclamped it
  renders at minus thirty-eight percent. Clamping changes no value inside the
  range, which is what the unit tests pin.
- **A width may not be negative.** An end before a start is not in the fixture and
  is one typo away in the admin. A negative width renders a bar inverted, which
  looks like a rendering bug rather than like a data error.

`Bar` also carries a `style()` that formats the four numbers as CSS. Geometry is
the one thing in this project that legitimately reaches the page as an inline
style — the values are per-row data and no stylesheet can hold thirteen years of
arbitrary start dates — so the formatting is decided here, once, rather than
re-derived in Phase 6.

### 4. `org` is the post title. There is no `dp_org`

The fixture gives a role an `org` and a `title`, and a shipped thing an `org` that
is its name. Both become `post_title`; roles keep their job title in
`dp_role_title`. Duplicating a name into meta would create two places to rename it
from, and the admin list table would show the wrong one.

### 5. WP-CLI is reached through one adapter, by callable

`DP\Core\Cli\WpCli` is the only file in the plugin that names WP-CLI, and it does
so as `is_callable( array( 'WP_CLI', 'add_command' ) )` rather than as a static
call. Two reasons, and both are needed:

- WP-CLI genuinely may not be loaded. The plugin serves web requests far more
  often than commands, and `is_callable()` is the honest guard for an optional
  integration.
- WP-CLI is not a Composer dependency of this project and there are no stubs for
  it in `vendor/`, so `\WP_CLI::add_command()` is `class.notFound` at PHPStan
  level 9 — an error that cannot be ignored and should not be, since it is telling
  the truth about what is in scope.

Everything else under `Cli\` is ordinary typed PHP that PHPStan checks fully, and
the command itself writes through an `Output` interface so an integration test can
run the real command with no console attached.

### 6. A planned part has no post ID, so it cannot have a permalink

Plan §3.1 already decided that a planned series part is a draft post carrying the
`dp_series` term, and named the cost: draft titles in that series become public.
The implementation decision is *how* that cost is held to exactly one title, one
year range and one note.

`SeriesParts::planned()` returns `PlannedPart` objects, which are final, carry
four public readonly properties, and have no methods. There is no post ID on them,
which is the only reason there cannot be a permalink: a caller holding an ID is
one `get_permalink()` away from linking to an unfinished draft. The query itself
uses `fields => ids` so `post_content` never enters the result set.

The plan asks for a template "written to make leaking body content impossible
rather than merely unlikely". Impossible means structural rather than
conventional, which is why the guard is the shape of the object and why
`ContentSeriesPartsTest` asserts that shape — the property list, the absence of
methods, and the class being final — as well as asserting that a body does not
come back.

### 7. The seed writes markup the block editor would have written

`bin/seed.php` and `wp dp seed` reproduce the fixture including its placeholders:
four roles still say "Placeholder role description", statistics still read `—` and
`EXAMPLE`, Kiveo's description still ends "copy to come", and the Privacy page
still claims the site runs no analytics. None of that is improved, and
`ContentSeedTest` asserts each of them, so improving it later is a deliberate act
rather than a drive-by.

Post and page bodies are converted to block markup that is **byte-identical to
what `serialize_blocks()` produces**, because anything else opens as an invalid
block and the first post David would meet it on is the reference post whose whole
purpose is to show the house style working. Two consequences of that are easy to
miss and are tested: an attribute equal to its declared default is omitted from
the block comment (so `SHELL` and `NOTE` are not written out but `DEPLOY` and
`FOUND A MISTAKE?` are), and an attribute with an HTML `source` lives in the
markup rather than the comment.

The seeder removes the kses filters around its writes. `wp_filter_post_kses`
rewrites the inside of an HTML comment, which is enough to break a block's
attributes, and it is active whenever the acting user cannot `unfiltered_html` —
under WP-CLI, usually nobody at all. What is being inserted is a fixture compiled
into the plugin with no path from a request to any of it, and `kses_init()`
restores the filters by re-deciding rather than by assuming they were on.

Idempotency comes from one option, `dp_core_seed_index`, mapping a stable fixture
key to the object it created. A second run updates the same objects; `--fresh`
deletes only what is in that index, so a post David wrote himself survives.

### 8. Suppressions, and why each one is not a shrug

Three sniffs are suppressed, each at the narrowest scope that works:

- `PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext` on
  the three enums. PHPCompatibility does not model enums and reads
  `match ( $this )` in an enum method as `$this` outside an object. It is valid
  PHP 8.1 and this project targets 8.4.
- `WordPress.Security.EscapeOutput.ExceptionNotEscaped` on `Year` and `Geometry`.
  Both are pure PHP and are unit-tested with no WordPress loaded, so `esc_html()`
  does not exist when they run. The same reasoning `phpcs.xml.dist` already
  applies to `tests/` and `bin/`. Everywhere WordPress *is* loaded — the seeder —
  the message is escaped instead.
- `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` on the
  one line that fires `dp_series_rewrite_slug`. The name is specified by
  CLAUDE.md §5.1 and by `docs/plan.md` Phase 3 and is part of this project's
  public surface; the prefix list in `phpcs.xml.dist` predates it.

## Consequences

- **Phase 6 does not compute anything.** It reads `Bar::left()`, `width()`,
  `max_width()`, `min_width()` and `style()`, and the filter and the three
  container-query modes are all that is left for it to build. The geometry tests
  are already written.
- **A tone or a video source outside its vocabulary is a 400, not a render-time
  surprise.** Every enum field carries its list into the REST schema, so the
  editor's sidebar in Phase 4 can render a select rather than a text field.
- **`register_post_meta()` needs `custom-fields` support.** All three post types
  declare it, because `WP_REST_Posts_Controller` omits the `meta` property
  entirely without it and the whole model would be registered and invisible.
- **`WP_UnitTestCase::tear_down()` calls `unregister_all_meta_keys()`.** Every
  integration test that touches the model re-registers it in `set_up()`. Any
  later phase writing meta tests inherits this; without it a suite asserts
  against an empty model and passes.
- **The seed's block markup is coupled to Phase 4's block definitions.** The
  callout's saved shape and the code block's `dpLabel` attribute are mirrored from
  `src/Blocks/js/`. If either changes, re-run `wp dp seed`; do not hand-edit
  content.
- **Body images are seeded with a caption and no file.** The design ships no
  media. The block is valid and visibly unfinished, which is what CLAUDE.md asks
  placeholder content to be, but the two figure slots will look empty until David
  puts something in them.
- **The `dp_series` archive is live at `/series/{slug}`** — the project's one
  page-facing rewrite, filterable through `dp_series_rewrite_slug`. A third
  rewrite needs its own ADR; `NoHardcodedRoutesTest` and
  `ContentModelTest::test_the_series_taxonomy_is_the_only_permastruct_we_add`
  are the two halves that keep that true.
- **`phpcs.xml.dist`'s prefix list should gain `dp_series`** when somebody owns
  that file, which would remove one suppression.

## Alternatives considered

**Tenths for the month encoding** — reads `2026.4` correctly and `2026.6` as July,
in a design written in August 2026 whose display string for that value is "now".
Rejected on the evidence.

**A `minimum` of 1900 on the year schema with no `anyOf`** — simpler, and
`register_meta()` refuses to register the field, because it validates the declared
default of zero against that schema. Discovered by running it.

**Dropping the `default` from year fields instead** — makes an unset field report
`''` from `get_post_meta()` and `null` over REST, so the two disagree about what
"no date" is. Rejected: that ambiguity would surface in Phase 6 as a bar at the
year zero.

**`detail` and `headline` as `post_content` on a role or a shipped thing** — would
let the block editor write them. Rejected: it puts prose the timeline renders as
plain text through the block parser, and it makes `supports` include an editor
nobody is supposed to write in. Digest §3.3 and §3.4 list them as fields, and
fields they are; no post type in this plugin supports `editor`.

**`php-stubs/wp-cli-stubs` as a dev dependency** — would let the plugin call
`WP_CLI::` directly. Rejected for this phase: it adds a dependency to the root
manifest, which Phase 3 does not own, to solve a symbol-resolution problem that
one small adapter already solves. Worth revisiting if a later phase adds more
commands than `wp dp seed` and `wp dp migrate`.

**A `dp_seed_key` meta field instead of the index option** — discoverable in the
admin, and it would make `--fresh` a meta query. Rejected: it puts a field in the
custom-fields UI that means nothing to David, and the index is a single read.

**Seeding the callout as a paragraph until Phase 4 registers the block** — would
avoid depending on another phase's markup. Rejected: it puts drift into the
reference post, which is the one post that exists to prevent drift.
