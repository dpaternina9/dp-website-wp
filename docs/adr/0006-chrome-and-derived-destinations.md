# ADR-0006 — The chrome: two lines of `theme.json`, and where a link's URL comes from

## Status

Superseded by
[ADR-0018](0018-computation-is-visible-in-the-editor-or-it-does-not-happen.md) —
pruned to this tombstone 2026-08-29.

## What survives

Only §1: `theme.json` declares the two template parts, and nothing else —

```jsonc
"templateParts": [
  { "name": "header", "title": "Header", "area": "header" },
  { "name": "footer", "title": "Footer", "area": "footer" }
]
```

## What was removed

§2 invented the `dp-to-*` derived-destination system — no template or pattern
may contain an href; a class names a destination and PHP resolves the URL at
render time. That mechanism silently overwrote author-set links, hid its trigger
from the editor, cost a day to a stale transient, and was copied four more times
before ADR-0018 deleted the whole layer. ADR-0018's Context section carries the
post-mortem; the original argument is in this file's git history.
