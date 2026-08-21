# ADR-0010 — The test site answers its own mail, and each e2e run is its own sender

## Status

Accepted — 2026-08-21.

## Context

The contact form draws one of three panels, and which one is decided by a POST
and rendered by the server. Two of the three are reachable in a browser without
help. The third is not.

**`wp_mail()` returns false on wp-env.** The containers have no mail transport,
so a submission that passes all six gates still ends in `Rejection::MailFailed`
and the *failed* panel. The design's **sent** state — "It landed. Thanks." — is
unreachable on the test site, which means `tests/e2e/contact.spec.ts` could only
ever prove two of the design's three states, and the one it could not prove is
the one the whole feature exists for. That is a property of Docker, not of the
code under test.

**The rate limiter counts by address, and every Playwright run shares one.**
`RateLimiter` allows three messages per sender per ten minutes and keys on
`REMOTE_ADDR`. A spec that sends twice is fine once; the third `npm run test:e2e`
inside ten minutes fails, on a gate that is working exactly as designed, with a
message about the wrong panel.

Both are environment problems with the same shape: the thing under test is
correct and the harness cannot reach it.

## Decision

**A test-support must-use plugin, mapped into the wp-env `tests` environment
only.** `tests/Support/mu-plugins/dp-test-mail.php`, mounted at
`wp-content/mu-plugins` by `.wp-env.json`'s `env.tests.mappings`. It is in
neither shipped package, `bin/dp-build.sh` never sees it, and every filter in it
returns early unless `wp_get_environment_type()` is `local`.

It does two things.

**It answers `pre_wp_mail` at priority 999, and only when nothing else has.**
The priority is the whole design. `DP\Tests\Integration\Contact\ContactTestCase`
attaches its own `pre_wp_mail` filter at priority 10 to count the calls and to
fail a delivery on purpose; that value arrives here already non-null and is
returned untouched. So the integration suite still owns what the transport did,
and a plain browser request — where nothing else filters — gets a successful
send and the *sent* panel.

**It points `dp_contact_sender_address` at a request header the spec sets.**
`tests/e2e/contact.spec.ts` generates one value per run and sets it as
`extraHTTPHeaders`, so each run owns its own counter. The limiter is not
weakened: still three per sender, still counted, still able to fail the suite if
it stops working.

## Consequences

**What this makes easy.** All three of the design's contact states are provable
in a browser, including the plain-POST path with scripting disabled. The e2e
suite is re-runnable without waiting out a ten-minute window.

**What it costs.** `.wp-env.json` gained a mapping, so a checkout that was
already running needs `npm run env:start` before the suite passes — the `sent`
test fails with the *failed* panel otherwise, which is a confusing symptom for a
missing bind mount. It is recorded in the merge queue for that reason.

The mu-plugin is also loaded during `composer test:integration`, which runs
against the same WordPress install. That is deliberate and is why the priority
and the null check exist; it also means `wp_mail()` now succeeds by default in
the integration suite, where before it silently failed.

**What it commits us to.** Every future test-support hook goes in this one file,
gated the same way, and any hook that could change a result an assertion depends
on has to defer to a filter the suite itself attached.

**The uncomfortable part, stated plainly.** `RateLimiter::fingerprint()`'s own
documentation says the sender-address filter must never be pointed at a header
the client can set, and it is right — on a real site that is a rate limit anyone
can walk around by sending a fresh header. Doing it here is safe only because
this file cannot reach a real site: it is not in either package, it is mapped
into one disposable environment, and it refuses to act outside a local one. If
that file ever appears on a server, the limiter is off.

## Alternatives considered

**Raise the rate limit on the test site.** Rejected: it takes the gate out of the
run. A limiter that never refuses anybody is a limiter no e2e test can notice
breaking.

**Install an SMTP catcher container.** Rejected: a fourth container and a service
to configure, to observe a boolean that `pre_wp_mail` already answers. The
project has a standing burden of proof for dependencies and this one could not
meet it.

**Write the mu-plugin into the container from `global-setup.ts`.** Rejected: an
imperative step outside the repository, invisible in review, and gone after
`wp-env destroy`. A mapping is declarative and lives in the diff.

**Assert the *sent* state only in the integration suite.** Rejected as the thing
the plan actually asks for: "e2e for the three form states". Two of three, with
the third excused by the harness, is the kind of gap that is still there a year
later.
