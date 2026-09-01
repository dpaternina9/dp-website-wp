# Going live — the one-time setup

Everything in this document is done once, by hand, in wp-admin. None of it is
code, and none of it is something this repo will do for you: the theme registers
no page routes, invents no URL and writes no copy, so a page exists because you
created it and a link points somewhere because you said so. That is ADR-0018,
and this document is the price of it.

Order matters in one place only — create the pages before you set the links, so
the link picker has something to find.

---

## 0. Before you start

- Install the theme and `dp-core` from their release tags (`theme-vX.Y.Z`,
  `plugin-vX.Y.Z`). The site auto-updates from `wp-updates.fanxie.cloud`
  thereafter; there is no manual upload path.
- Turn **maintenance mode on** (Settings → General) so the public sees a holding
  screen while you work. You stay logged in and see the real site throughout.
- **Settings → Permalinks**: pick anything other than Plain. Under plain
  permalinks no rewrite rule exists at all, so every path the design draws 404s
  and the `dp_series` archive is simply not there. Post name is the obvious
  choice and matches the slugs coming over from `blog.dpaternina.com`.

---

## 1. Create the pages

Eleven pages. Eight of them get a template from the **Template** dropdown in the
page sidebar; three are wired through Settings instead.

The slug is entirely yours in every case — nothing in the code branches on one.

### The eight with templates

Create the page, then in the sidebar under **Template**, pick:

| Template in the dropdown | What it draws |
|---|---|
| Work — timeline | Featured work cards and the timeline chart |
| Watch | Live panel, video archive, gear list |
| About | The about page |
| Résumé | The résumé, plus the PDF download |
| Contact | The contact form |
| Series index | The list of writing series |
| Uses | The uses page |
| Colophon | The colophon |

The `dp-` prefix on the template files is deliberate: it keeps them out of
WordPress's template hierarchy, so a page whose slug happens to be `work` never
gets the Work template by accident. Assignment is always explicit.

### The three set through Settings

| Page | Where you point at it | Template used |
|---|---|---|
| Home | Settings → Reading → *A static page* → **Homepage** | `front-page` — automatic |
| Writing | Settings → Reading → *A static page* → **Posts page** | `home` — automatic |
| Privacy | Settings → Privacy → select the page | `page` — the default |

Leave the Writing page's body empty. WordPress replaces it with the posts index;
anything you type there is never rendered.

**Homepage and Posts page move together.** `page_for_posts` does nothing at all
while *Your latest posts* is selected, so set both or neither.

---

## 2. Set the links

Fifteen link elements across three files, pointing at ten destinations. They ship
with no URL — code that filled them in was exactly the thing ADR-0018 deleted,
because a computed href is an href that can silently overwrite one you set.

Open the **Site Editor** (Appearance → Editor) and use **List View**: every one
of these carries a name, so you are looking for "Contact link", not for the
third button in a row.

| Where | Named links |
|---|---|
| **Footer** (Patterns → Site footer) | About, Colophon, Contact, Privacy, Résumé, Uses, Watch, Work, Writing |
| **Header** (Patterns → Site header) | Contact |
| **404 template** (Templates → 404) | Contact, Home, Watch, Work, Writing |

Contact, Watch, Work and Writing appear in more than one place, so set each
occurrence. With the pages already created, the URL picker finds them by title.

### The navigation menu is optional

The header's nav is a `core/navigation` block with no menu assigned, so it falls
back to listing your published pages automatically — create the pages and they
appear. Build a real menu only if you want to control the order, change a label,
or leave a page out. Same block in the mobile panel.

---

## 3. Configure the plugin

All of this is **Settings → General**, in named sections.

### Contact

| Field | Option | Notes |
|---|---|---|
| Recipient address | `dp_contact_recipient` | Where submissions are delivered. Blank falls back to the administration address, so the form works with nothing set |
| Public address | `dp_contact_public_address` | The `mailto:` the failure panel offers. Blank means no address is published — deliberately *not* a fallback to the admin address |

### Watch

Five fields, and they degrade separately — none is required for the site to
work. A site with none of them configured renders whatever videos it already has
and syncs nothing.

| Field | Option | Without it |
|---|---|---|
| Twitch login | `dp_watch_twitch_login` | No live check, no "watch the stream" link, no Twitch VOD sync |
| Twitch client ID | `dp_watch_twitch_client_id` | The live panel never shows live; Twitch thumbnails stay on fallback art |
| Twitch client secret | `dp_watch_twitch_client_secret` | Same as above — the pair works together |
| YouTube channel | `dp_watch_youtube_channel` | No YouTube uploads imported |
| YouTube API key | `dp_watch_youtube_key` | Same — already-imported videos keep rendering |

**What you need to get:**

- **Twitch**: register an application at dev.twitch.tv → Console → Applications.
  The client ID and secret are app credentials — no user OAuth, no channel
  permissions. Reading public stream metadata is all they can do.
- **YouTube**: a Google Cloud API key with the YouTube Data API v3 enabled.
  Restrict it to that one API. The channel field takes either the `UC…` id or
  the `@handle`.

Once configured, everything on the Watch page is automatic: hourly sync via
WP-Cron, plus a **Sync now** button on this same screen and a
`wp dp watch sync` CLI command. You never enter a video by hand. A `dp_video` or
`dp_live` post you *do* edit wins field by field — anything you fill in is
yours and the sync will not touch it; anything you leave blank is the sync's to
fill.

### Maintenance

The toggle and its copy live in the same screen's **Maintenance** section.

| Field | Option | Notes |
|---|---|---|
| Maintenance mode | `dp_maintenance_enabled` | The switch. Off on a fresh install |
| Heading | `dp_maintenance_heading` | The screen's `<h1>`. Emptying it restores the default — a document needs exactly one |
| Message | `dp_maintenance_message` | Body copy. Emptying it leaves it empty; a heading alone is a valid screen |
| Contact address | `dp_maintenance_contact` | Blank publishes no address. The only way to reach you while the contact form is behind the curtain |

Turn it off by unticking and saving. If you ever lock yourself out some other
way, `wp option delete dp_maintenance_enabled` is the escape hatch.

**What it does while on:** every public URL — pages, posts, feeds, sitemaps,
robots.txt, 404s — answers `503` with `Retry-After` and `X-Robots-Tag: noindex`,
and renders a standalone screen that needs no theme. The REST API refuses
anonymous requests for the same reason: a dark front end with an open
`/wp-json/wp/v2/posts` is the same content published twice.

**What it never touches:** `wp-login.php`, wp-admin, admin-ajax, WP-CLI and
cron. There is no allowlist to get wrong — those paths never load the hook the
curtain uses, so no bug in it can lock you out of the switch.

**You see the real site throughout**, on every URL, as long as you are logged in
and can edit posts. A subscriber account is treated as a member of the public.
The `dp_maintenance_capability` filter widens that without a deploy.

Every admin screen carries a warning while it is on, and so does the admin bar
on the front end, so you cannot forget to turn it off.

---

## 4. Optional: the résumé PDF

Configured in `wp-config.php`, not in the admin, because the token can spend
money. Skip it entirely and the résumé degrades to a browser print view, which
is a working feature and not an error state.

```php
define( 'DP_CLOUDFLARE_ACCOUNT_ID', '…' );
define( 'DP_CLOUDFLARE_API_TOKEN',  '…' );
```

Or point at a Gotenberg instance instead:

```php
define( 'DP_GOTENBERG_URL', 'https://…' );
```

With both configured, `DP_RESUME_PDF_RENDERER` picks one (`'cloudflare'` or
`'gotenberg'`); with neither, the print view.

---

## 5. Migration and DNS

Your work, outside this repo. The shape agreed:

- WXR export from `blog.dpaternina.com`, import here, **slugs preserved**.
- A single Cloudflare redirect rule covers the old host. There is no
  `wp dp migrate` command, no redirect map in the code, and no rewrite rules —
  any vanity redirect is likewise a Cloudflare rule.
- Import posts before setting Reading's posts page, or the index is empty when
  you first look at it. Not a problem, just confusing.

Rollback, if a release goes wrong, is installing the previous tag.

---

## 6. Turn maintenance mode off

Settings → General. Then check, logged out or in a private window:

- The homepage, and one post.
- The Writing index and its pagination.
- Each of the eight templated pages.
- The contact form — send one to yourself.
- A URL that does not exist, for the 404's links.
- The Watch page, once the credentials are in.

---

## Known open item

`--hue-purple` measures 2.80:1 on the dark ground, which fails WCAG AA for text.
The design's own token file prescribes mixing 75% toward white (4.59:1 worst
case) but `design-source/theme.css` ships the raw purple. `design-source/` is a
read-only contract, so this is a decision for you rather than a bug to fix here.
It affects accent text only, not body copy.
