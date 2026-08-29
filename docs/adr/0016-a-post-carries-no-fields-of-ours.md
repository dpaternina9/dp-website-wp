# ADR-0016 — A post carries no fields of ours

## Status

Accepted — 2026-08-26. Supersedes the `dp_series_featured` half of
[ADR-0021](0021-the-blog-templates-derive-four-things-a-template-cannot-say.md).
**Two halves reversed the same day.** The derived featured series is superseded
by
[ADR-0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md),
which makes that link one David sets in the site editor. `dp_series_deck` and the
term-edit control this ADR gave it are gone too: a `dp_series` term already had a
textarea for one or two sentences about itself, on both of the screens the new
control drew on, with a list-table column, a REST property and a place in a WXR
export — `description`. That reversal is recorded in its commit rather than in an
ADR of its own; see the ADR README on why the bar moved. The eight post fields
stand as written.

## Context

`dp-core` registered eight meta fields on the native `post` type, and two more
things hung off the same assumption — a `dp_series_featured` flag on the series
taxonomy, and `menu_order` as a series' reading order.

Reviewing the authoring surface — not the code, the *screens* — turned up one
fact that none of the tests could see: **not one of the ten had an editor
control.** No meta box, no sidebar panel, no term-edit field, nothing in the
block inspector. Every one was registered with `show_in_rest`, so the seeder
could write it and a REST client could write it, and David could not. On a post
he wrote by hand all eight were the empty string, and the design's badges, read
times and standfirsts drew blank or drew nothing.

The second fact is worse, and it is what makes this an ADR rather than a bug
report. In most cases **the derivation had been written as well as the field, not
instead of it.** `PostPresentation::kicker()`, `::short_kicker()` and `::tone()`
each checked for a stored override first and fell through to a derivation that
was already complete and already correct. The override branch was dead code
guarding a field nobody could set — and it read as a considered design, because
`dp_kicker`'s own registered description said "empty means derive it".

Two of the ten were worse still:

- **`dp_read_time`** described itself as "computed on save, stored, and
  overridable by hand". Nothing computed it. Nothing offered the hand. The only
  writer in the entire repository was the seeder, which is why the design's
  "6 MIN READ" appeared on a seeded site and never anywhere else.
- **`menu_order`** was chosen in plan §3.1 as a series' reading order, so that a
  planned part becoming a published one would keep its place. `post` does not
  declare `page-attributes`, so the Order box is not on the post editor at all;
  `menu_order` was zero on every post, and the date tiebreak beside it was doing
  the whole sort already.

## Decision

**Nothing about a post is stored that the post already knows.** All eight fields
are deleted from `DP\Core\Content\Meta`, along with `dp_series_featured`, and
each is replaced by the thing it was shadowing:

| Deleted | Derived from |
|---|---|
| `dp_kicker` | The series part if there is one, else the first category. |
| `dp_tone` | Pink in a series, teal outside one. |
| `dp_read_time` | The body, counted at render, at 200 words a minute. |
| `dp_lead` (on `post`) | The first paragraph of the post content. |
| `dp_hero_caption` | The featured image attachment's own caption. |
| `dp_series_part` | The post's position among the published posts in its series. |
| `dp_series_years` | Dropped. The design labels planned rows `DRAFT`, flat. |
| `dp_series_note` | The draft's excerpt, which is a core field with a sidebar box. |
| `dp_series_featured` | The series with the most published parts. |
| `menu_order` on `post` | The publish date, ascending. |

Three of those deserve their reasoning written down.

**The part number is a position, not a value.** `SeriesParts::part_of()` finds
the post in `published()` — the term's posts, publish status, date ascending —
and returns the index plus one. The number and the order the archive draws in can
therefore never disagree, and a part written out of sequence lands where its date
puts it and renumbers what follows. The ordered list is memoised for the request
in the object cache, keyed by the `posts` and `terms` `last_changed` stamps core
already maintains, so an archive of twenty rows each asking for its own number
runs one query and needs no invalidation hook of its own.

**The standfirst is styled by position, not by a block style.** The obvious
alternative — register a "Standfirst" paragraph variation — was rejected on a
principle rather than on a test: `CoreStylesTest` asserts that the blocks in the
house style offer *no* style variations, because the house style is a set of
blocks that already look right rather than a palette to choose from. A rule on
`.dp-post .wp-block-post-content > p:first-child` needs no new block, no
variation, and nothing for David to remember to apply. Three selectors' worth of
specificity is deliberate: core's `:root :where(.is-layout-flow) > :first-child`
is two units (ADR-0008), and a single-class rule would have won on the page and
lost in the canvas.

**The featured series is derived, and ADR-0021 said not to.** That ADR rejected
exactly this rule — "the one with the most published parts" — as "a guess dressed
as a derivation", preferring a flag David sets, "a decision recorded where the
decision belongs". The reasoning was sound and the field was not: it had no
term-edit control, so on a real site with more than one series the link was
permanently inert and there was no way for David to fix it. A rule that works is
better than a nomination that cannot be made. Ties go to the lowest term ID, so
the answer is the same on every page of the site. A site whose series have no
published parts has no answer, and the link goes inert and visible (ADR-0008).

**`dp_series_deck` survives, and finally gets a field.** It is the one thing a
series knows that nothing else can tell you — the standfirst under its title —
and `DP\Core\Content\SeriesDeckField` now draws a textarea on the Add Series and
Edit Series screens, with its own nonce and its own capability check. Term
editing is classic PHP admin; there is no block editor for a taxonomy screen and
building one would be the same over-engineering this ADR is undoing.

## Consequences

**What this makes easy.** A post David writes by hand renders exactly like a post
the seeder wrote. That was not true before this change and it is the whole point:
the fixture was passing tests that a real post could not have passed.

**What it costs.** Two things, and both are real.

The read time is counted on every render rather than looked up. It is one
already-cached read of `post_content` and a `preg_split`, memoised per post per
request, and a post view does it four times at most — but it is arithmetic where
there used to be a column.

The part number is no longer something David can override. If he wants part 3 to
read before part 2 he changes a publish date, which is a real edit with real
consequences elsewhere. That is the trade: the number cannot be wrong, and it
cannot be *set* either.

**What it commits us to.** The derivations are now the only implementation, so a
bug in one is a bug on every post rather than something a stored value papered
over. `ContentModelTest::test_a_post_carries_no_registered_meta_of_ours()` fails
if anything re-registers a `dp_`-prefixed field on `post`, which is the guard
against this growing back one convenient field at a time.

**Stale rows are left where they are.** See the note below.

## Alternatives considered

**Add the missing editor UI instead.** Rejected, and it is the alternative that
was actually on the table: eight fields, a meta box or an inspector panel each,
a build step for the JavaScript the theme currently does not have — to let David
type a value that the post already implies. Every one of the eight would then
have two sources of truth and a way for them to disagree.

**Compute the read time on save and store it.** Rejected. It is what the field's
own description claimed and it has the failure mode all denormalisation has: an
edit through any path that does not fire the hook leaves a number that is
confidently wrong. Counting at render cannot go stale.

**A "Standfirst" block style variation.** Rejected — see above. It would have
broken the house style's own rule, and it would have made the standfirst
something David has to remember to apply to every post.

**Keep `menu_order` and add `page-attributes` to `post`.** Rejected. It puts an
Order box on every post on the site — including the twenty-nine that are in no
series — to solve an ordering problem the publish date already solves. The
timeline's three post types keep their `page-attributes` and their `menu_order`
untouched, because there the Order field is real and lane order genuinely is not
a date.

**Delete the stale rows in an upgrade routine.** Rejected. See below.

## A note on existing data

Sites that ran an earlier seed have `postmeta` rows for all eight keys and
`termmeta` rows for `dp_series_featured`. **Nothing deletes them, and this is a
deliberate choice, not an oversight.**

They are inert: the keys are unregistered, so they are absent from REST, absent
from the block editor, and read by no code in either package. They cost a handful
of rows. Against that, a destructive upgrade routine would run automatically on
David's site, delete data on the strength of a key prefix, and be untestable
against the one database that matters. Deleting data nobody asked us to delete is
a worse failure than leaving a few rows behind.

`wp dp seed --fresh` removes them along with everything else it created, which is
the only path where deleting them is something somebody asked for. If they ever
need clearing on a site with real content, `wp post meta delete` over a list of
keys is a one-line command David can run when he chooses to, and it belongs in
the migration runbook rather than in an activation hook.
