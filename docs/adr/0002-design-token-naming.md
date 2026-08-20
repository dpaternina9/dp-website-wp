# ADR-0002 — One source of truth for design tokens, and the bridge that keeps their names

## Status

Accepted — 2026-08-20.

## Context

`CLAUDE.md` §5 makes two requirements that pull in opposite directions.

1. *"The token CSS in `design-source/_ds/tokens/` is transcribed into `theme.json`
   (`settings.color.palette`, `settings.typography.fontSizes`, `settings.spacing`,
   `settings.custom`) so the editor offers exactly these values and nothing else."*
2. *"Custom properties keep their names: a token called `--dp-teal` stays `--dp-teal`."*

WordPress does not emit `--dp-teal`. Every preset in `theme.json` becomes
`--wp--preset--{kind}--{slug}` and every `settings.custom` key becomes
`--wp--custom--{key}`, both after being kebab-cased. So satisfying (1) by itself
renames every token in the system, and satisfying (2) by itself means the editor
offers nothing.

The obvious way out — write the values twice, once in `theme.json` under
WordPress's names and once in a hand-written stylesheet under the design's — is
two hand-maintained lists of the same 129 values. That is the drift this ADR
exists to prevent, not a solution to it.

Three further constraints shaped the answer:

- **Compound tokens.** `--dp-gradient-warm` is a `linear-gradient()` over three
  other tokens. `--bg-page` is `var(--dp-ink)`. `--accent-text` under `.dp-light`
  is a `color-mix()`. `--fs-display` is a `clamp()`. Any check that compares raw
  declaration text will report false differences on all of them.
- **Scoped tokens.** `.dp-light` and `.dp-dark` re-declare tokens for a subtree.
  `theme.json` has no notion of a scoped preset and never will.
- **WordPress rewrites slugs.** `_wp_to_kebab_case()` separates a run of digits
  from the letters that follow it, so a slug of `fs-2xl` is emitted as
  `--wp--preset--font-size--fs-2-xl`.

Verified environment: WordPress 7.1, `WP_Theme_JSON::LATEST_SCHEMA === 3`,
PHP 8.4.24 in-container, PHPUnit 9.6.36.

## Decision

### 1. `design-source/` is the only place a token value is authored

`theme.json` and the theme's CSS are both **derived artefacts**. Neither is
allowed to introduce a value the design does not contain, and a disagreement is
resolved by changing the theme — or by changing the design in Claude Design and
re-importing, per `CLAUDE.md` §5.

### 2. `theme.json` holds the literal values, under WordPress's names

Every design token is written into `theme.json` as a fully resolved literal:
`--dp-gradient-warm` becomes
`linear-gradient(120deg, #ff2e63 0%, #f4795e 50%, #ffb84c 100%)`, not a
`var()` chain. That is what makes the editor correct — a colour picker cannot
render a swatch for `var(--dp-pink)`, a font-size menu cannot sort `var()`
values, and core's contrast checker needs a real colour.

Where each token lands is a decision `theme.json` records rather than one the
tooling infers:

| Design token | `theme.json` |
|---|---|
| `--dp-*` (except gradients), every semantic alias | `settings.color.palette` |
| `--dp-gradient-*` | `settings.color.gradients` |
| `--font-*` | `settings.typography.fontFamilies` |
| `--fs-*` | `settings.typography.fontSizes` |
| `--space-*` | `settings.spacing.spacingSizes` |
| everything else | `settings.custom` |

`--band`, `--accent-text` and the `--hue-*` family are colours but go to
`settings.custom` deliberately. They are text-contrast corrections, not choices:
`--dp-*` is for fills and `--hue-*` for text (digest §4), and offering the
corrected value as an editor swatch would invite someone to fill a shape with it.

**Preset slugs are the design's token names, unabbreviated** — `dp-teal`,
`bg-page`, `fs-xs`, `space-4`, `font-body`. That produces slightly verbose
generated names (`--wp--preset--font-family--font-body`) and slightly verbose
block classes (`has-fs-lg-font-size`), which is the price of a mapping with no
rules in it. The four tokens WordPress rewrites — `--fs-2xl` through `--fs-5xl` —
are written in `theme.json` in the separated form WordPress will actually emit
(`fs-2-xl`), so the file says what the browser will see.

### 3. A generated bridge stylesheet gives every token its name back

`themes/dpaternina/assets/css/tokens.css` is **generated, committed, and never
hand-edited**. It contains one alias per design token —

```css
--dp-teal: var(--wp--preset--color--dp-teal);
--space-5: var(--wp--preset--spacing--space-5);
--radius-lg: var(--wp--custom--radius-lg);
```

— and, below them, the `.dp-light` and `.dp-dark` scopes copied out of
`design-source/` byte for byte. Every stylesheet this theme ever ships is written
against the design's names. Nothing downstream of this file knows that WordPress
renamed anything.

The generator is `bin/dp-tokens.php` (`composer tokens:build` /
`composer tokens:check`). It reads the design source for the token list, the
order, and the scopes, and reads `theme.json` to find where each token was
placed. **A token missing from `theme.json` is a generation error, not a silent
gap** — the generator names it and refuses to write.

### 4. The generator and the parity test are the same code

`tests/Support/` holds the CSS reader, the design-source loader, the `theme.json`
reader and the bridge generator; `bin/dp-tokens.php` is a thin CLI over them and
`DP\Tests\Integration\TokenParityTest` is the assertion over them. Putting the
generator anywhere else would mean the thing that writes the artefact and the
thing that verifies it could disagree.

### 5. The parity test compares against the CSS WordPress actually generates

This is the part that makes the guard worth having. The test does not compare two
JSON documents; it builds the theme's real variable table from
`wp_get_global_stylesheet( array( 'variables' ) )` plus the generated bridge,
resolves every `var()` chain on both sides to a literal, normalises whitespace
and hex case, and compares. `--dp-teal` is followed through the bridge, into
`--wp--preset--color--dp-teal`, to `#08d9d6` — the same journey a stylesheet in
this theme makes.

That is why core's slug rewriting needs no mirror in our code. If
`_wp_to_kebab_case()` ever changes, the bridge's `var()` stops resolving and the
test fails naming the token and both values, instead of a copied word-splitter
drifting quietly out of sync.

The test also asserts that the design source parsed to more than a hundred
tokens, so a broken parser fails loudly rather than passing vacuously.

### 6. Core's own presets are removed, not merely hidden

`defaultPalette`, `defaultGradients`, `defaultFontSizes`, `defaultSpacingSizes`
and `shadow.defaultPresets` are all `false`, which stops the editor *offering*
core's presets. It does not stop WordPress *emitting* them: those flags only
control whether a theme preset may shadow a default one. Twelve colours, twelve
gradients, four font sizes, seven spacing steps and five shadows were still being
declared on every page — about 7 KB of CSS, and about 7 KB of ways to end up with
a colour that is not in the design.

`DP\Theme\CorePresets` empties them at the source through
`wp_theme_json_data_default`. `dimensions.aspectRatios` is left alone: core's own
image and cover block CSS resolves those variables, so removing them would break
a feature rather than an opinion.

### 7. `contentSize` is `--container-md`, `wideSize` is `--container-lg`

`--container-lg` (1120px) is the page shell the design uses for every band and
for the header and footer; `--container-md` (880px) is the narrower column it
uses for hero copy and body text. Mapping them onto `wideSize` and `contentSize`
is what makes an unstyled block land where the design would have put it.
`--container-sm` and `--container-xl` appear nowhere in the design's markup; both
are still transcribed, because the design declares them.

`layout.contentSize` is written as a literal `880px` rather than
`var(--wp--custom--container-md)` because parts of the editor parse it as a
number. `TokenParityTest` asserts both widths equal the resolved container
tokens, so the literal cannot drift.

## Consequences

- There is exactly one hand-maintained list of token *values* (`theme.json`) and
  one hand-maintained list of *placements* (also `theme.json`). The CSS that
  authors actually write against is generated from both.
- Changing a token is: change `design-source/`, update `theme.json`, run
  `composer tokens:build`, run `composer test`. Forgetting the middle step fails
  generation by name; forgetting the last step fails the parity test by name.
- `theme.json` may not be edited without regenerating. `composer tokens:check`
  and the parity test both catch it, so a stale bridge cannot reach `main`.
- The generated preset names are verbose. Block markup will carry classes like
  `has-fs-lg-font-size` and `has-bg-surface-background-color`. That is the cost
  of never having to think about a name mapping again.
- **Content that carries a core colour class loses its colour.** This matters for
  Phase 9: WXR imported from the old site may contain `has-vivid-red-color` and
  friends. The migration maps old colours onto this palette, so the outcome is
  intended — but it is a reason to run the migration before the cutover, not
  after, and to check the report rather than the pages.
- Light mode remains carried but unwired. `.dp-light` is in the bridge because
  the design declares it and `CLAUDE.md` §5 says to keep it. Nothing toggles it,
  and the parity test asserts it is carried, not that it works.
- The theme now has its own `composer.json` and its own `vendor/`, on the pattern
  ADR-0001 §1 predicted. Same reasoning: the release artefact is a zip of the
  theme directory and nothing else, so the autoloader travels with it. The root
  manifest regenerates it via `post-install-cmd`.
- The integration bootstrap now activates `dpaternina` for the whole run. Without
  it every assertion about `theme.json` was being made against the test suite's
  own `default` theme — which is how this was found.

## Alternatives considered

**Hand-write the bridge and let the parity test guard it.** Two lists of 129
values, kept in step by a test. Rejected: the test would report the drift, but
the drift would happen on every token change, and a guard that fires routinely
stops being read.

**Put `var(--dp-teal)` in `theme.json` and the literals in a hand-written token
stylesheet.** One list of literals, names preserved everywhere. Rejected: the
editor's colour picker cannot render a swatch for a `var()`, the font-size menu
cannot order them, core's contrast and duotone code cannot read them, and the
site editor's global-styles UI would show unusable values. It moves the problem
from the CSS to the admin, where it is worse.

**Generate `theme.json` as well, from the design source.** The strongest form of
one-source-of-truth. Rejected for now: `theme.json` grows a large hand-written
`styles` section in Phase 4, and a generator that has to splice into a file it
does not own is more fragile than a generator that writes a whole file. Worth
revisiting if `styles` ever becomes derivable too.

**Mirror `_wp_to_kebab_case()` in the generator.** Rejected: it is a
lodash-derived word splitter, forty lines of unicode character classes, and a
copy of it would drift silently. Choosing slugs that survive it, and testing
against the CSS WordPress really emits, gets the same result with none of the
exposure.

**Put the token toolkit in `themes/dpaternina/src/`.** Rejected: it reads
`design-source/`, which does not ship with the theme, so it would be dead code
in every released artefact.
