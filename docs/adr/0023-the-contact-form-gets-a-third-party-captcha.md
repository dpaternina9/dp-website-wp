# 0023 — The contact form gets a third-party captcha

**Status:** Accepted — 2026-09-03. Reverses the "no third-party captcha" decision
recorded in `docs/plan.md` Phase 7 and in the class docblock of
`DP\Core\Contact\Rejection`.

## Context

Phase 7 built the contact form with six gates — nonce, capability, honeypot,
timing check, field validation, rate limit — and wrote down, in two places, why
there would be no seventh:

> There is deliberately **no third-party captcha**: every one of them is a script
> served by somebody else that sees every visitor who reaches the page, which is
> a tracker with a job title.

That reasoning was not wrong and is not withdrawn. What changed is that David
decided the trade is worth making anyway. This ADR exists because the README's
bar is "a documented decision is being reversed", and this is one: somebody
reading `Rejection`'s docblock, or Phase 7, would otherwise find a rule that the
code no longer follows and no record of who stopped following it.

The six gates stop volume. They do not stop a targeted, hand-written or
model-written submission, because every one of them is a property of the
*request* — a valid nonce, a plausible delay, a well-formed address — and all of
those are cheap to produce deliberately. A challenge is the only gate on the list
that asks something of the *client* rather than of the request.

## Decision

We add Cloudflare Turnstile as a seventh gate, on these terms.

**It is off unless both keys are present.** `DP_TURNSTILE_SITEKEY` and
`DP_TURNSTILE_SECRET` are constants in `wp-config.php`. With either missing or
empty, no script is enqueued, no widget is rendered, no request is made to
Cloudflare, and no refusal exists that did not exist before. A fresh install,
`wp-env`, and CI all behave exactly as they did before this ADR. The default is
the old decision; turning it on is a deployment choice.

**The keys are constants, not options.** A secret in `wp_options` is a secret in
every database backup and in the REST API's blast radius, and the résumé PDF's
Cloudflare credentials already established this shape
(`DP\Core\Resume\CloudflareBrowserRendering`).

**The expected hostname is derived, not configured.** `wp_parse_url( home_url(),
PHP_URL_HOST )`, so each deployment validates its own host and production cannot
accept a token minted against `localhost` or a staging copy.
`DP_TURNSTILE_HOSTNAMES` replaces the derived list where a deployment's public
host is not the one `home_url()` returns, and is expected to stay unset.

**It fails closed.** A missing token, an oversized one, a network error, a
non-2xx status, a body that is not JSON, `success: false`, the wrong action or
the wrong hostname are all one answer: the message does not go.

**The gate sits sixth, before the rate limiter.** An expired token is the same
category of event as a mistyped address — something real people do — and the
rate limiter is last precisely so those do not spend a real person's three
attempts. It also sits after the field check, so a bot posting an empty body
cannot make this site issue an outbound HTTP request per attempt.

## Consequences

These are costs, not caveats.

**The contact page loads a third-party script, and that page is now outside the
"zero third-party requests" posture.** `challenges.cloudflare.com/turnstile/v0/api.js`
runs on the contact page for every visitor who reaches it, whether or not they
ever submit anything. Cloudflare sees that request, and the widget it draws is an
iframe on Cloudflare's origin doing its own client-side work. This is exactly the
thing the original decision refused, and calling it "privacy-preserving because
Cloudflare says so" would be marketing. It is a third party on a page that had
none.

**David's security plugin needs a CSP exception.** `script-src` and `frame-src`
both have to allow `https://challenges.cloudflare.com`. The headers are David's
(CLAUDE.md §1.4) and this repo has always held to "emit nothing that would force
him to loosen the policy". That promise is now broken on one page, deliberately,
and the loosening has to be made by hand before the feature works — a widget
under a policy that refuses it fails silently, which will look like a broken
form rather than a missing header.

**The Privacy page is wrong until David edits it.** It currently says "No cookies
are set" and "No third-party analytics, advertising, or social scripts load on
any page". The second sentence stops being true on the contact page the moment
the keys are set. Turnstile's own client-side storage is Cloudflare's business,
not something this repo can characterise on David's behalf. `bin/seed.php`'s copy
(in `DP\Core\Fixture\Fixture`) has been corrected so a fresh install does not
ship a false claim; **the live site's Privacy page is a database post and the
seed does not reach it.** It is David's to edit in wp-admin, and until he does,
the published page says something untrue about the published site.

**If Cloudflare is unreachable, the contact form stops accepting messages.** That
is what failing closed means, and it is the right direction for a security gate,
but it converts a dependency on Cloudflare's availability into a dependency of
being able to write to David at all. The "email instead" fallback on the failure
panel is the mitigation, and it only appears if David has set a public address on
Settings → General.

**A visitor who blocks third-party scripts cannot use the form.** No script, no
widget, no token, and the seventh gate refuses. They get the design's generic
failure copy, which does not — and deliberately must not — explain which gate
closed. The same is true of anyone whose extension blocks the iframe.

**The e2e sweep still passes, and that is a fact about CI rather than about
production.** `tests/e2e/a11y.spec.ts` asserts zero off-origin requests on first
paint for every template, and it stays green because the constants are unset
where it runs. The check was not weakened and not deleted; what it now measures
on the contact page is "the feature is off here", which is a real thing to
measure and is why the inert-by-default design is load-bearing rather than
merely tidy. End-to-end verification of a live siteverify exchange is not
possible in this repo and has not been done.

## Alternatives considered

**Keeping the six gates and doing nothing.** The status quo, and it is defensible:
the six gates have not visibly failed. Rejected because the decision was David's
to make and he made it, and because the gate this adds is categorically different
from the six — it asks something of the client rather than checking a property of
the request.

**A self-hosted proof-of-work challenge (Altcha, mCaptcha, or similar).** Keeps
the "one origin" posture whole, which is the property being given up here.
Rejected because it moves the cost onto the visitor's device rather than removing
it, because it is a dependency this repo would then own and update, and — the
deciding reason — because it is not what David asked for.

**Turnstile keys as options on Settings → General.** Would have been editable in
wp-admin, which CLAUDE.md rule 2 usually prefers. Rejected because a secret does
not belong in `wp_options`, and because rule 2 is satisfied differently here: the
state is *visible* on Settings → General as a read-only row saying whether the
challenge is configured and which hostname is enforced (ADR-0018), which is the
part of the rule that matters — nothing is computed invisibly.

**An `is_configured()` that defaults to on.** Rejected outright. The default has
to be the decision this ADR reverses, because every other install of this plugin,
every test run, and every local environment is a site that did not opt in.
