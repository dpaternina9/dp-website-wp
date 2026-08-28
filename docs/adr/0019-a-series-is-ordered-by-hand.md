# ADR-0019 — A series is ordered by hand

## Status

Accepted — 2026-08-27. Supersedes the *"Keep `menu_order` and add
`page-attributes` to `post`"* rejection in
[ADR-0016](0016-a-post-carries-no-fields-of-ours.md).

Written after the work, not before it, per the bar in [the README](README.md).
What follows is what was built and what it cost, not a case for building it.

## Context

A series read in publish-date order, ascending, and nothing else. For published
parts that is defensible. For planned ones it is not: a planned part is a **draft
post** (plan §3.1), and a draft's `post_date` is whenever it was created, so
"Still to come" came out in the order the drafts happened to be started. The only
way to change it was the Publish panel's date field on a draft — obscure, and a
future date silently turns the post into *Scheduled* on publish.

David named this the worst part of managing a series.

ADR-0016 had considered `menu_order` and rejected it:

> Rejected. It puts an Order box on every post on the site — including the
> twenty-nine that are in no series — to solve an ordering problem the publish
> date already solves.

Half of that is right and half is a mistake about WordPress. The objection to the
Order box is real. But it assumed the box was the price of the column, and it is
not: **`wp_update_post()` writes `menu_order` whether or not the post type
declares `page-attributes`.** Confirmed in the running container against WP 7.1
before anything was built —

```
supports page-attributes: false
menu_order after wp_update_post: 7
```

The rejected alternative was rejected for a cost it did not have.

## Decision

**Series order is `menu_order ASC, then post_date ASC`**, in both places that
know the order — `SeriesParts::ids()` and
`DP\Theme\Query\QueryLoops::order_the_series_archive()`. `SeriesParts::all()`
returns published and draft parts in one sequence, so there is one definition of
the order rather than a screen restating it.

**`post` still does not declare `page-attributes`.** No Order box appears on any
post. The order is written by one screen — `Posts → Series → Order parts`, a row
action per term — which lists that series' published and draft parts together and
reorders them by dragging.

**Nothing stores a part number.** `SeriesParts::part_of()` is still the index in
the ordered list, so the numbers cannot disagree with the order the page draws.
Ordering the list is the whole interface.

Two tests hold the reasoning rather than the result:
`test_post_does_not_declare_page_attributes()` fails if anything adds the
declaration, and `test_menu_order_is_writable_without_that_declaration()` fails
if core ever makes the declaration a precondition — without which the screen
would keep accepting drags and silently write nothing.

## Consequences

**A series nobody has ordered behaves exactly as it did.** `menu_order` is 0 on
every existing post, so the sort falls through to the date. That is asserted, and
it is why this needed no migration and no seeder change.

**A new part sorts to the top of an ordered series.** Its `menu_order` is 0 and
every ordered part's is 1 or more. It is visible — the row is at position one —
and one drag settles it. The obvious fix, a `save_post` hook handing joiners the
next free position, was not built: it is the invisible computation ADR-0018 rules
out, and a wrong guess made silently is worse than a right position set by hand.

**Moving a row bumps its `post_modified`.** `wp_update_post()` is the core API and
it stamps the post. Nothing in either package reads a `post`'s modified date, so
this was preferred over a targeted `$wpdb->update()`. Rows that do not move are
not written, so opening the screen and pressing Save writes nothing.

**Order is saved, not written on drop.** The list is a form; each row carries a
hidden input, dragging moves the input, submitting posts them in order. No fetch,
no REST route, no nonce handed to JavaScript, and therefore no inline `<script>`
to hand it in (CLAUDE.md §1.4). Several moves can be made and abandoned. It is
what Appearance → Menus does.

**The screen has no keyboard reorder.** Deliberate: WCAG 2.2 AA is an acceptance
criterion for the public site, not for an admin screen used by one person. The
class docblock names the shape it would take — a pair of move buttons per row —
if this ever leaves a one-person site.

**`render()`'s capability check is load-bearing.** `remove_submenu_page()` takes
the entry out of `$submenu`, and core's `user_can_access_admin_page()` then falls
through to the *parent's* capability — `edit_posts`, from All Posts — rather than
this page's `edit_others_posts`. Without the explicit check an Author would reach
the screen. There is a test for it that first asserts the author can reach the
parent, so it cannot pass vacuously.

## Alternatives considered

**Add `page-attributes` to `post`.** The thing ADR-0016 actually rejected, and
still rejected here: an Order box on twenty-nine posts that are in no series, and
an integer to type instead of a list to look at.

**Order by a dedicated meta field.** A second column meaning what `menu_order`
already means, with none of core's query support — `orderby => 'menu_order'` is
free and `orderby => 'meta_value_num'` is a join.

**Let the draft's date carry the order, and make the date easier to set.** The
status quo with a better control. Rejected: the date would still be doing two
jobs, and the *Scheduled* trap stays.
