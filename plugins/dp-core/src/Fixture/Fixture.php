<?php
/**
 * The design's fixture, transcribed.
 *
 * @package DP\Core
 */

declare( strict_types=1 );

namespace DP\Core\Fixture;

/**
 * `LANES`, `POSTS`, `PAGES`, `VIDEOS`, `SERIES` and `TERMS` from
 * `design-source/dpaternina.dc.html`, as data this plugin can seed.
 *
 * **Nothing here is improved.** Four of the six roles say "Placeholder role
 * description". Statistics read `—` and `EXAMPLE`. Kiveo's description ends
 * "copy to come". The Colophon claims the site runs no analytics, which is the
 * opposite of what it will do. All of that is transcribed exactly as written,
 * because CLAUDE.md is explicit that the copy is placeholder and that inventing
 * plausible-sounding facts about David to fill a gap is worse than leaving the
 * gap visible. A seed that reads well is a seed that has started lying.
 *
 * The one thing that is not verbatim is the shape: the design writes decimal
 * years, caps category tokens and heterogeneous body objects because it is
 * JavaScript in a design tool. Those become floats, slugs and `FixtureBlock`s
 * here. Values are identical; only the container changed.
 */
final class Fixture {

	/**
	 * The five categories, from `TERM_NAMES` and `TERMS`.
	 *
	 * @return list<array{token: string, slug: string, name: string, description: string}>
	 */
	public function categories(): array {
		return array(
			array(
				'token'       => 'DEV',
				'slug'        => 'dev',
				'name'        => 'Dev',
				'description' => 'Building things and the decisions behind them — WordPress, PHP, Laravel, and whatever is currently on fire.',
			),
			array(
				'token'       => 'MY LIFE STORY',
				'slug'        => 'my-life-story',
				'name'        => 'My life story',
				'description' => 'The long-form series about how I got here, written one part at a time.',
			),
			array(
				'token'       => 'FOOD',
				'slug'        => 'food',
				'name'        => 'Food',
				'description' => 'Mostly coffee, and the equipment I keep telling myself I do not need.',
			),
			array(
				'token'       => 'MUSIC',
				'slug'        => 'music',
				'name'        => 'Music',
				'description' => 'Guitar, recording at home, and buying tools instead of practising.',
			),
			array(
				'token'       => 'PHOTOGRAPHY',
				'slug'        => 'photography',
				'name'        => 'Photography',
				'description' => 'One prime lens, the same few streets, and what shows up after a month of looking.',
			),

			/*
			 * The sixth is not the design's. `TERM_NAMES` has five and the design
			 * fills every one of them, so the state its category archive draws for
			 * an empty term — "Nothing filed here yet." — is unreachable on a
			 * seeded site, and a state nobody can look at is a state nobody
			 * reviews. This term stays empty on purpose and says so in its own
			 * description. `core/categories` hides empty terms, so it is absent
			 * from the pill row and from the footer; only its own archive shows it.
			 */
			array(
				'token'       => 'PLACEHOLDER',
				'slug'        => 'nothing-filed',
				'name'        => 'Placeholder — nothing filed',
				'description' => 'Placeholder. Seeded empty so the archive\'s empty state can be looked at. Delete it on the real site.',
			),
		);
	}

	/**
	 * A second series, so the archive is not a one-term special case.
	 *
	 * The design ships one series and the theme used to be able to assume it.
	 * `DP\Theme\Chrome\Destinations::featured_series()` picks the series with the
	 * most published parts, and a fixture with one term would never exercise the
	 * comparison — so this exists, it is visibly placeholder, and it is seeded
	 * with three published parts against the design series' two, which is what
	 * makes the choice a real one to assert on.
	 *
	 * @return array{slug: string, title: string, deck: string}
	 */
	public function extra_series(): array {
		return array(
			'slug'  => 'placeholder-series',
			'title' => 'Placeholder series',
			'deck'  => 'Placeholder. A second series so the series archive, the part navigation and the featured-series nomination all have more than one term to run against. Delete it on the real site.',
		);
	}

	/**
	 * The one series, from `SERIES`.
	 *
	 * @return array{slug: string, title: string, deck: string}
	 */
	public function series(): array {
		return array(
			'slug'  => 'life-story',
			'title' => 'My life story',
			'deck'  => 'The long version of how I got here, one part at a time. Each part stands on its own, and the numbers follow the order they went up.',
		);
	}

	/**
	 * The six timeline lanes, from `LANES`.
	 *
	 * `org` becomes the post title; there is no `dp_org` meta field. `accent` is
	 * empty for every lane but Fanxie Lab, which the design gives pink and which
	 * therefore also earns a legend swatch.
	 *
	 * @return list<array{key: string, org: string, title: string, start: float, end: float, range: string, detail: string, stack: string, accent: string}>
	 */
	public function roles(): array {
		$placeholder = 'Placeholder role description — a couple of sentences on what the job was and what you owned.';

		return array(
			array(
				'key'    => 'backbone',
				'org'    => 'Backbone Technology',
				'title'  => 'Developer',
				'start'  => 2014.0,
				'end'    => 2016.0,
				'range'  => '2014 — 2016',
				'detail' => $placeholder,
				'stack'  => 'STACK · PLACEHOLDER',
				'accent' => '',
			),
			array(
				'key'    => 'imaginamos',
				'org'    => 'Imaginamos',
				'title'  => 'Developer',
				'start'  => 2016.0,
				'end'    => 2018.0,
				'range'  => '2016 — 2018',
				'detail' => $placeholder,
				'stack'  => 'STACK · PLACEHOLDER',
				'accent' => '',
			),
			array(
				'key'    => 'aplyca',
				'org'    => 'Aplyca',
				'title'  => 'Developer',
				'start'  => 2018.0,
				'end'    => 2020.0,
				'range'  => '2018 — 2020',
				'detail' => $placeholder,
				'stack'  => 'STACK · PLACEHOLDER',
				'accent' => '',
			),
			array(
				'key'    => 'globant',
				'org'    => 'Globant',
				'title'  => 'Developer',
				'start'  => 2020.0,
				'end'    => 2022.0,
				'range'  => '2020 — 2022',
				'detail' => $placeholder,
				'stack'  => 'STACK · PLACEHOLDER',
				'accent' => '',
			),
			array(
				'key'    => 'monsterinsights',
				'org'    => 'MonsterInsights',
				'title'  => 'Developer team lead',
				'start'  => 2022.0,
				'end'    => 2026.4,
				'range'  => '2022 — 2026',
				'detail' => 'I led development on the most popular Google Analytics plugin for WordPress, running on over 3 million websites. The work was making complex analytics feel simple: shipping to millions of users, performance optimization, and analytics integration.',
				'stack'  => 'PHP · VUE.JS · REST APIS · WP-CLI',
				'accent' => '',
			),
			array(
				'key'    => 'fanxie-lab',
				'org'    => 'Fanxie Lab',
				'title'  => 'CTO & founder',
				'start'  => 2016.0,
				'end'    => 2026.6,
				'range'  => '2016 — now',
				'detail' => 'The thread running under everything else. An innovation lab focused on design, research, and development methodologies — simple but sophisticated solutions, working closely with partners rather than treating them as clients. Partner work across Latin America, and the place my own products get built.',
				'stack'  => 'LARAVEL · NESTJS · SWIFTUI · DIGITALOCEAN · CLOUDFLARE',
				'accent' => 'pink',
			),
		);
	}

	/**
	 * The four shipped things, from the `ships` arrays inside `LANES`.
	 *
	 * `featured` marks the three the design puts above the timeline as WorkCards
	 * (`featuredWork`); "Performance work" is the one that is not.
	 *
	 * `line` is the card's own sentence, taken verbatim from `featuredWork`, and
	 * is deliberately not `detail`: the design writes a short line for the card
	 * face and a longer paragraph for the expanded panel, and Kiveo shows why
	 * they cannot be the same string — its `detail` opens "One line on what Kiveo
	 * does … copy to come." "Performance work" carries no `line` because the
	 * design never gives it one, and inventing one would be inventing copy.
	 *
	 * @return list<array{key: string, role: string, name: string, start: float, end: float, range: string, headline: string, detail: string, line: string, bullets: list<string>, ship_role: string, stack: string, artifact_label: string, artifact: string, stat1: string, stat1_label: string, stat2: string, stat2_label: string, featured: bool}>
	 */
	public function ships(): array {
		return array(
			array(
				'key'            => 'nlq',
				'role'           => 'monsterinsights',
				'name'           => 'Natural-language queries',
				'start'          => 2025.0,
				'end'            => 2025.8,
				'range'          => '2025',
				'headline'       => 'Ask your analytics a question.',
				'detail'         => 'AI-powered natural language queries that let site owners ask about their traffic in plain English instead of learning a reporting UI.',
				'line'           => 'Ask your analytics a question instead of learning a reporting UI.',
				'bullets'        => array(
					'Ships inside a plugin that updates unattended on 3M+ sites — no breaking changes allowed.',
					'Answers have to be simple and true at once; a confident wrong number is worse than no answer.',
					'Privacy constraints shaped the architecture more than the model choice did.',
				),
				'ship_role'      => 'Developer team lead',
				'stack'          => 'PHP · VUE.JS · REST APIS',
				'artifact_label' => 'QUERY → ANSWER',
				'artifact'       => "> which posts grew last month?\n\n/blog/getting-started   EXAMPLE  + EX%\n/blog/wp-cli-tricks     EXAMPLE  + EX%\n/pricing                EXAMPLE  + EX%\n\nreal figures to come",
				'stat1'          => '3M+',
				'stat1_label'    => 'SITES ON THE PLUGIN',
				'stat2'          => '—',
				'stat2_label'    => 'ADOPTION',
				'featured'       => true,
			),
			array(
				'key'            => 'performance',
				'role'           => 'monsterinsights',
				'name'           => 'Performance work',
				'start'          => 2023.0,
				'end'            => 2024.8,
				'range'          => '2023 — 2024',
				'headline'       => 'Analytics that doesn’t slow the site down.',
				'detail'         => 'Performance optimization and analytics integration across the plugin surface — page weight treated as a feature, not a report.',
				'line'           => '',
				'bullets'        => array(
					'Specific wins and before/after numbers to be filled in.',
					'Touches caching, script loading, and the REST surface.',
				),
				'ship_role'      => 'Developer team lead',
				'stack'          => 'PHP · VUE.JS · WP-CLI',
				'artifact_label' => 'WP-CLI SESSION',
				'artifact'       => "$ wp monsterinsights report --range=30d\nfetching from cache …   ok (48ms)\nreconciling ids …       ok\nSuccess: report generated.",
				'stat1'          => '—',
				'stat1_label'    => 'LOAD TIME DELTA',
				'stat2'          => '—',
				'stat2_label'    => 'RELEASES',
				'featured'       => false,
			),
			array(
				'key'            => 'kiveo',
				'role'           => 'fanxie-lab',
				'name'           => 'Kiveo',
				'start'          => 2023.0,
				'end'            => 2026.6,
				'range'          => '2023 — now',
				'headline'       => 'Native, and nothing phoning home.',
				'detail'         => 'One line on what Kiveo does and who it’s for — copy to come. Built solo, SwiftUI front to back, no accounts, nothing leaves the device.',
				'line'           => 'Native iOS, built solo, with nothing leaving the device.',
				'bullets'        => array(
					'No third-party analytics SDKs.',
					'No account required to use the app.',
					'Sync goes through the user’s own iCloud, or not at all.',
				),
				'ship_role'      => 'Everything',
				'stack'          => 'SWIFT · SWIFTUI · CLOUDKIT',
				'artifact_label' => 'SWIFTUI',
				'artifact'       => "struct EntryList: View {\n  @Query var entries: [Entry]\n  var body: some View {\n    List(entries) { EntryRow(\$0) }\n      .animation(.snappy)\n  }\n}",
				'stat1'          => '0',
				'stat1_label'    => 'TRACKERS',
				'stat2'          => '—',
				'stat2_label'    => 'APPS SHIPPED',
				'featured'       => true,
			),
			array(
				'key'            => 'agency-ops',
				'role'           => 'fanxie-lab',
				'name'           => 'Agency platform & ops',
				'start'          => 2024.0,
				'end'            => 2026.6,
				'range'          => '2024 — now',
				'headline'       => 'The plumbing a small agency runs on.',
				'detail'         => 'API design, transactional email, R2 storage, server provisioning, and the operational tooling that keeps partner infrastructure boring.',
				'line'           => 'The plumbing a small agency runs on, kept deliberately boring.',
				'bullets'        => array(
					'One command stands up a client site: droplet, DNS, TLS, storage, mail.',
					'Built so a two-person team can run real infrastructure without an ops hire.',
					'Partner list and scope to be filled in.',
				),
				'ship_role'      => 'CTO & founder',
				'stack'          => 'LARAVEL · NESTJS · CLOUDFLARE · R2',
				'artifact_label' => 'PROVISIONING',
				'artifact'       => "$ fx site:create acme --stack=laravel\n→ droplet      ok\n→ dns + tls    ok\n→ r2 bucket    ok\n→ mail domain  ok\ndone in EXAMPLE s",
				'stat1'          => 'LATAM',
				'stat1_label'    => 'PARTNER BASE',
				'stat2'          => '—',
				'stat2_label'    => 'SITES RUNNING',
				'featured'       => true,
			),
		);
	}

	/**
	 * The Watch grid, from `VIDEOS` and `LIVE_NOW`.
	 *
	 * Every `vod` and `yt` in the fixture is an empty string, and they stay empty:
	 * the design has no real ids, and inventing one would make the Watch grid
	 * point at somebody else's video.
	 *
	 * @return list<array{key: string, source: string, tone: string, ref: string, title: string, duration: string, when: string, note: string, live: bool, live_meta: string}>
	 */
	public function videos(): array {
		return array(
			array(
				'key'       => 'live',
				'source'    => 'twitch',
				'tone'      => 'pink',
				'ref'       => '',
				'title'     => 'Building the Kiveo reading-stats screen, live',
				'duration'  => '',
				'when'      => '',
				'note'      => 'SwiftUI charts, and finding out that my own reading data is embarrassing.',
				'live'      => true,
				'live_meta' => 'STREAMING NOW · 1H 12M IN',
			),
			array(
				'key'       => 'v-1',
				'source'    => 'twitch',
				'tone'      => 'purple',
				'ref'       => '',
				'title'     => 'Provisioning a client site from one command',
				'duration'  => '2H 41M',
				'when'      => 'AUG 2026',
				'note'      => 'The whole fx site:create flow, including the part where DNS lies to me.',
				'live'      => false,
				'live_meta' => '',
			),
			array(
				'key'       => 'v-2',
				'source'    => 'youtube',
				'tone'      => 'teal',
				'ref'       => '',
				'title'     => 'Why your analytics plugin is slowing the site down',
				'duration'  => '18 MIN',
				'when'      => 'JUL 2026',
				'note'      => 'A short, edited version of a rant I have given far too many times.',
				'live'      => false,
				'live_meta' => '',
			),
			array(
				'key'       => 'v-3',
				'source'    => 'twitch',
				'tone'      => 'purple',
				'ref'       => '',
				'title'     => 'Rewriting the query parser, badly, twice',
				'duration'  => '3H 05M',
				'when'      => 'JUL 2026',
				'note'      => 'Unedited. Includes the hour I spent debugging a typo.',
				'live'      => false,
				'live_meta' => '',
			),
			array(
				'key'       => 'v-4',
				'source'    => 'youtube',
				'tone'      => 'teal',
				'ref'       => '',
				'title'     => 'SwiftUI without a backend: how Kiveo stores everything',
				'duration'  => '24 MIN',
				'when'      => 'JUN 2026',
				'note'      => 'The architecture talk I wish existed when I started.',
				'live'      => false,
				'live_meta' => '',
			),
			array(
				'key'       => 'v-5',
				'source'    => 'twitch',
				'tone'      => 'purple',
				'ref'       => '',
				'title'     => 'Espresso, then eight hours of Laravel',
				'duration'  => '4H 18M',
				'when'      => 'JUN 2026',
				'note'      => 'A normal working day, streamed. Surprisingly popular.',
				'live'      => false,
				'live_meta' => '',
			),
		);
	}

	/**
	 * The four planned parts, from the entries in `SERIES.parts` that have no slug.
	 *
	 * They seed as **draft posts** carrying the series term — plan section 3.1 —
	 * and they carry nothing a draft does not already have. The `note` is the
	 * post's excerpt; the title is the title; the date is what puts them in the
	 * order the years run, because that is the field "Still to come" sorts on.
	 * They have no number: the design labels every planned row `DRAFT`, and its
	 * own deck says they get a number when they go up (ADR-0016).
	 *
	 * @return list<array{key: string, title: string, date: string, note: string}>
	 */
	public function planned_parts(): array {
		return array(
			array(
				'key'   => 'before-a-job',
				'title' => 'Before any of it was a job',
				'date'  => '2026-01-05 09:00:00',
				'note'  => 'A borrowed computer, a dial-up connection, and no idea this was work people paid for.',
			),
			array(
				'key'   => 'learning-hard-way',
				'title' => 'Learning the hard way',
				'date'  => '2026-01-06 09:00:00',
				'note'  => 'School, detours, and the first time I shipped something a stranger used.',
			),
			array(
				'key'   => 'first-office',
				'title' => 'The first office',
				'date'  => '2026-01-07 09:00:00',
				'note'  => 'What I accepted as normal, because I had nothing to compare it to.',
			),
			array(
				'key'   => 'exhausting-year',
				'title' => 'The exhausting year',
				'date'  => '2026-01-08 09:00:00',
				'note'  => 'The part I put off writing the longest.',
			),
		);
	}

	/**
	 * The seven sample posts, from `POSTS`.
	 *
	 * Dates: the fixture prints a month and a year ("AUG 2026"); posts are seeded
	 * on the fifteenth of it, which reproduces the order the design lists them in
	 * without inventing a precision the design does not have. The date is also
	 * what numbers a series part now, so the two life-story posts come out as
	 * part 1 and part 2 without either of them saying so.
	 *
	 * **The standfirst is the first paragraph of the body.** It used to be a meta
	 * field the editor had no control for; the words are the design's either way,
	 * and this way they are in the post, where David can edit them (ADR-0016).
	 *
	 * @return list<array{slug: string, title: string, date: string, category: string, series: string, excerpt: string, body: list<FixtureBlock>}>
	 */
	public function posts(): array {
		return array(
			array(
				'slug'     => 'house-style',
				'title'    => 'The house style, and every piece of it',
				'date'     => '2026-08-15 09:00:00',
				'category' => 'dev',
				'series'   => '',
				'excerpt'  => 'Every block this blog can render, in one post, so I stop reinventing them.',
				'body'     => array(
					self::p( 'This is the reference post. Every element I let myself use in writing here appears once, in the order I usually reach for it.' ),
					self::p( "The rule is that a post is text first. Everything below exists because a paragraph could not do the job — a quote carries someone else's voice, a list carries a set, code carries an exact string. If a block is decoration, it is not in the system." ),
					self::h2( 'Headings run three deep' ),
					self::p( 'Level two splits a post into parts. It is the only heading most posts need, and it sits far enough from body copy that you can scan for it.' ),
					self::h3( 'Level three groups within a part' ),
					self::p( 'Same face, smaller, tighter to the paragraph it introduces. Use it when a section has two or three distinct moves.' ),
					self::h4( 'Level four is for reference material' ),
					self::p( 'Specs, options, parameters. If a post needs a fourth level in prose, the post wants splitting instead.' ),
					self::h2( 'Lists' ),
					self::p( 'Unordered for sets where order carries no meaning:' ),
					self::ul(
						array(
							'One idea per item, no trailing punctuation.',
							'Sentence case, same as everything else.',
							'Three to six items — longer than that is a table.',
						)
					),
					self::p( 'Numbered only for genuine sequences:' ),
					self::ol(
						array(
							'Write the thing badly and completely.',
							'Cut every sentence that repeats the one before it.',
							'Read it aloud once; fix whatever makes you stumble.',
						)
					),
					self::h2( 'Quotes' ),
					self::p( "A pull quote is for a line worth stopping on — someone else's words, or my own if they earn the space. Attribution is optional and stays quiet." ),
					self::quote( "\"It's better done than perfect.\" I'll be damned if he wasn't right.", 'A lead I had in 2013' ),
					self::h2( 'Code' ),
					self::p( 'Monospaced, labelled, and short enough to read in place. Anything longer than about fifteen lines goes in a repo and gets a link.' ),
					self::code( "$ npm run build --silent\n\n  bundle   142 kB → 61 kB gzip\n  css       18 kB →  4 kB gzip\n  done in 3.1s", 'SHELL' ),
					self::h2( 'Callouts' ),
					self::p( 'One callout per post, maximum. It is for a caveat the reader will hit in practice, not for emphasis I failed to write into the sentence.' ),
					self::note( 'Numbers in these posts are from my own projects unless a source is linked. When I do not have the figure, I say so instead of estimating.', 'NOTE' ),
					self::h2( 'Images' ),
					self::p( 'Inline images sit full column width, with a mono caption underneath. Photographs get room to breathe; screenshots get cropped to the part that matters.' ),
					self::image( 'AN INLINE FIGURE — SAME WIDTH AS THE COLUMN' ),
					self::h2( 'Tables' ),
					self::p( 'For comparisons where the reader wants to look something up rather than read a paragraph about it.' ),
					self::table(
						array( 'Block', 'Use it for', 'Limit' ),
						array(
							array( 'Quote', "A line in someone else's voice", 'Two per post' ),
							array( 'List', 'A set or a sequence', 'Six items' ),
							array( 'Code', 'An exact command or snippet', 'Fifteen lines' ),
							array( 'Callout', 'A caveat you will actually hit', 'One per post' ),
						)
					),
					self::rule(),
					self::p( 'That is the whole kit. If a post wants something that is not here, the something gets designed properly and added to this page — not improvised once and forgotten.' ),
				),
			),
			array(
				'slug'     => 'workaholic-years',
				'title'    => 'The workaholic years, and why I stopped',
				'date'     => '2026-03-15 09:00:00',
				'category' => 'my-life-story',
				'series'   => 'design',
				'excerpt'  => 'Hardly anyone would use that word about me today. That took work.',
				'body'     => array(
					self::p( 'In the previous part I covered one of the most exhausting moments of my life. This one is about the years after — the ones I mostly spent at a desk.' ),
					self::p( "We're in 2012 now. For the first time I was working somewhere that felt like it actually cared about the people doing the work. We had the tools we needed. I had a title I was proud of. And I responded to all of that the only way I knew how: by working every hour I had." ),
					self::p( "It's funny, because if you asked people about me today, hardly anyone would use the word \"workaholic\". That's because I changed. Not gracefully, and not because I read the right book — because I had to." ),
					self::h2( 'The line I still repeat' ),
					self::p( 'My lead at the time watched me rewrite the same module for the third time in a week and said the only thing that ever got through to me about scope.' ),
					self::quote( "\"It's better done than perfect.\" I'll be damned if he wasn't right.", '' ),
					self::p( "The next few years would kick my butt in ways I didn't see coming, and I'll get to those. But this is the chapter where the habit formed, and where the cost of it started quietly adding up." ),
				),
			),
			array(
				'slug'     => 'ai-features-users',
				'title'    => 'Shipping AI features without betraying your users',
				'date'     => '2026-02-15 09:00:00',
				'category' => 'dev',
				'series'   => '',
				'excerpt'  => 'What plain-English analytics taught me about privacy as a constraint.',
				'body'     => array(
					self::p( 'I spent a year building a feature that answers questions about a site in plain English. The hard part was never the model.' ),
					self::p( 'The brief sounded simple: let someone type "which posts grew last month" and get a real answer. Every interesting version of that feature wants to send your data somewhere, and every version worth shipping refuses to.' ),
					self::p( 'So privacy became the constraint I designed against instead of the disclaimer I wrote afterwards. Aggregate before you leave the server. Send shapes, not rows. Make the prompt auditable by the person whose data it describes.' ),
					self::h2( 'What it cost' ),
					self::p( "Two features died. The answers got slower by about 400ms. In exchange, I can explain exactly what leaves a customer's database, in one sentence, without a lawyer in the room." ),
					self::quote( "If you can't say what leaves the server, you haven't finished the feature.", '' ),
				),
			),
			array(
				'slug'     => 'espresso-shot',
				'title'    => 'Three years of chasing the same espresso shot',
				'date'     => '2026-01-15 09:00:00',
				'category' => 'food',
				'series'   => '',
				'excerpt'  => 'A dial-in log, a grinder I regret, and one very good morning.',
				'body'     => array(
					self::p( 'I have a spreadsheet of grind settings going back to 2023. I am aware of what that says about me.' ),
					self::p( 'The shot I keep chasing happened in a small place in Medellín — Colombian beans, roasted maybe five days earlier, and a barista who clearly was not measuring anything. Mine has been measured to the tenth of a gram for three years and has not landed there yet.' ),
					self::p( 'What did improve: I stopped buying equipment to solve technique problems. The grinder I regret was a shortcut. The habit that worked was pulling one shot a day and writing down one variable.' ),
					self::h2( 'The current recipe' ),
					self::p( '18g in, 36g out, 28 seconds, water at 93°C. Boring, repeatable, and about 80% of the way to that morning.' ),
				),
			),
			array(
				'slug'     => 'amp-sims',
				'title'    => 'Too many amp sims, one guitar',
				'date'     => '2025-12-15 09:00:00',
				'category' => 'music',
				'series'   => '',
				'excerpt'  => 'On collecting tools instead of practicing, and what finally broke the loop.',
				'body'     => array(
					self::p( 'At one point I owned nine amp simulators and could play four songs. The ratio was the problem.' ),
					self::p( 'Buying a plugin feels like progress. It has a version number, it changes the sound, it takes an afternoon to explore. Practicing feels like nothing for weeks and then feels like everything at once.' ),
					self::p( 'What broke the loop was deleting all of them and keeping one preset for a month. The playing got better because there was nothing else to adjust.' ),
					self::quote( 'Every tool I bought was a way of not sitting down with the instrument.', '' ),
					self::p( 'Same thing happens with editors, frameworks, and note apps. I recognise the shape of it now, which is most of the fix.' ),
				),
			),
			array(
				'slug'     => 'city-you-stopped-noticing',
				'title'    => 'Shooting a city you have stopped noticing',
				'date'     => '2025-11-15 09:00:00',
				'category' => 'photography',
				'series'   => '',
				'excerpt'  => 'A month of walking the same six blocks with one prime lens.',
				'body'     => array(
					self::p( 'I gave myself one lens, six blocks, and thirty days. The rule was that I could not go anywhere new.' ),
					self::p( 'The first week was terrible. I photographed the obvious things — the mural, the fruit cart, the dog outside the bakery — and got a folder of postcards nobody needed.' ),
					self::p( 'Week three is when it turned. Once the subjects run out you start seeing light instead of objects: the ten minutes when the wall opposite my building goes orange, the reflection that only works after rain.' ),
					self::h2( 'What I kept' ),
					self::p( 'Eleven frames out of about nine hundred. All of them are from the last ten days, and none of them are of anything I would have pointed a camera at on day one.' ),
				),
			),
			array(
				'slug'     => 'care-looks-like',
				'title'    => 'The job that taught me what care looks like',
				'date'     => '2025-10-15 09:00:00',
				'category' => 'my-life-story',
				'series'   => 'design',
				'excerpt'  => 'For the first time I worked somewhere that gave me what I needed.',
				'body'     => array(
					self::p( 'Part five: the year I found out that most of what I had accepted as normal at work was not normal.' ),
					self::p( 'They bought the machine I asked for. Nobody made a speech about it. That was the whole thing — the request went in on Monday and the answer was yes, and I sat there recalibrating years of assumptions.' ),
					self::p( 'It also raised the bar in a way I did not expect. When the environment stops being the obstacle, the only thing left in the way of the work is you.' ),
					self::quote( 'Care at work is mostly logistics. It looks like the thing you asked for showing up.', '' ),
					self::p( 'This is the chapter right before the one where I overcorrected and worked myself into the ground. Read them in order if you want the full picture.' ),
				),
			),
		);
	}

	/**
	 * The filler posts, which are not the design's and say so.
	 *
	 * The design's fixture is seven posts. Three of the states this theme has to
	 * draw need more than that and cannot be judged without them: the pager needs
	 * enough posts for three pages, the range line needs a page that is neither
	 * the first nor the last, and the end-of-archive panel needs a last page to
	 * appear on. `posts_per_page` is ten, and the blog index holds its featured
	 * post back, so twenty-four published posts is the smallest number that gives
	 * all three.
	 *
	 * **Nothing here reads like something David wrote.** CLAUDE.md section 6 is
	 * explicit that inventing plausible copy to fill a gap is worse than leaving
	 * the gap visible, and a folder of invented blog posts would be exactly that.
	 * Every one of these announces what it is, in its title, its excerpt and its
	 * body, so a seeded site reads as a seeded site. The first paragraph of each
	 * body is its standfirst, which is the whole of what `dp_lead` used to be.
	 *
	 * Two of them carry the second series, which is what gives the part navigation
	 * a neighbour to find outside the design's own series.
	 *
	 * @return list<array{slug: string, title: string, date: string, category: string, series: string, excerpt: string, body: list<FixtureBlock>}>
	 */
	public function filler_posts(): array {
		$categories = array( 'dev', 'dev', 'food', 'music', 'photography', 'dev', 'food', 'music', 'photography', 'dev', 'dev', 'food', 'music', 'photography', 'dev', 'dev', 'food', 'dev', 'dev', 'dev' );
		$posts      = array();

		foreach ( $categories as $index => $category ) {
			$number = $index + 1;
			$month  = 9 - ( intdiv( $index, 3 ) );
			$year   = $month > 0 ? 2025 : 2024;
			$month  = $month > 0 ? $month : $month + 12;

			$posts[] = array(
				'slug'     => sprintf( 'placeholder-post-%02d', $number ),
				'title'    => sprintf( 'Placeholder post %02d', $number ),
				'date'     => sprintf( '%04d-%02d-%02d 09:00:00', $year, $month, 20 - ( $index % 18 ) ),
				'category' => $category,
				'excerpt'  => 'Placeholder. Seed filler, so the archive has enough entries to paginate.',
				'series'   => '',
				'body'     => array(
					self::p( 'Placeholder. `wp dp seed` writes this post so the pager, the range line and the end-of-archive panel have something to run against.' ),
					self::p( 'Placeholder. This post is seed filler and carries no content of its own. Delete it, or write over it, on the real site.' ),
				),
			);
		}

		/*
		 * Two, not three. The featured series is derived — the one with the most
		 * published parts, term ID ascending on a tie (ADR-0016) — and the design's
		 * series has two published parts, so a placeholder series with three would
		 * quietly take the blog index's "read my life story in order" link away
		 * from the one the design points it at. Two makes it a tie, the design's
		 * term is created first, and the tie is the state a real two-series site
		 * is in anyway.
		 */
		$parts = array(
			array( 'First placeholder part', 'It opens the placeholder series.' ),
			array( 'Second placeholder part', 'It closes the published half of the placeholder series, and gives the part navigation a neighbour to find.' ),
		);

		foreach ( $parts as $index => $part ) {
			$posts[] = array(
				'slug'     => sprintf( 'placeholder-part-%d', $index + 1 ),
				'title'    => $part[0],
				'date'     => sprintf( '2026-0%d-10 09:00:00', $index + 4 ),
				'category' => 'my-life-story',
				'excerpt'  => 'Placeholder. ' . $part[1],
				'series'   => 'extra',
				'body'     => array(
					self::p( 'Placeholder. Part of the seeded second series; it exists so the series archive and the part navigation have more than one term to run against.' ),
					self::p( 'Placeholder. This part is seed filler and carries no content of its own.' ),
				),
			);
		}

		return $posts;
	}

	/**
	 * The one part the placeholder series has not written yet.
	 *
	 * @return list<array{key: string, title: string, date: string, note: string}>
	 */
	public function extra_planned_parts(): array {
		return array(
			array(
				'key'   => 'placeholder-part-4',
				'title' => 'Fourth placeholder part',
				'date'  => '2026-01-09 09:00:00',
				'note'  => 'Placeholder. Drafted rather than published, so "Still to come" has a row on this archive too.',
			),
		);
	}

	/**
	 * Every page the design implies, and the template each one is drawn by.
	 *
	 * Three of these are the design's own `PAGES` entries — Uses, Colophon,
	 * Privacy — and they are transcribed verbatim. The Colophon and the Privacy
	 * page both say things the finished site will not do — "no analytics script,
	 * no cookie banner", "four plugins, all load-bearing". Digest section 7 flags
	 * the Privacy page as the one place where shipping placeholder copy would be
	 * actively misleading, and it is seeded exactly as written anyway, because
	 * rewriting it here would hide the problem instead of surfacing it. It is a
	 * launch blocker, not a seed bug.
	 *
	 * The other six are the views the design draws from data rather than from a
	 * `PAGES` entry: the front page, the writing index, Work, About, the résumé
	 * and Contact. WordPress needs a page behind each of them anyway — a template
	 * with nothing assigned to it is a template nobody can reach — so each is
	 * seeded with **only the words the design actually prints**: the `<h1>` its
	 * template binds to `core/post-title`, the deck it binds to `dp_lead`, and
	 * the body copy where the template renders `core/post-content`. Where a
	 * template renders no body at all, the page carries a callout saying so
	 * rather than invented prose. CLAUDE.md section 6: seed the design's copy as
	 * written, and never write plausible-sounding facts about David to fill a
	 * gap.
	 *
	 * Three fields carry the wiring and none of them is a route.
	 *
	 * - **`key`** is this fixture's own stable name for a page, and it is what
	 *   the seeder indexes it under and what it hands the chrome-link seam. It is
	 *   deliberately not the slug: David renames a page and re-slugs it, and
	 *   nothing may break when he does.
	 * - **`template`** is a custom template's slug, e.g. `dp-work`. It is written
	 *   to `_wp_page_template`, which is the same assignment the Page Attributes
	 *   panel makes — a starting choice for a seeded site, not a rule the code
	 *   enforces (CLAUDE.md section 5.1). `wp_update_post()` validates the value
	 *   against `wp_get_theme()->get_page_templates()` and rejects the `.html`
	 *   spelling, so it is the slug.
	 * - **`role`** is what Settings to Reading and Settings to Privacy should
	 *   point at on a freshly seeded site: `front`, `posts`, `privacy`, or ''.
	 *
	 * @return list<array{key: string, slug: string, title: string, template: string, role: string, updated: string, deck: string, body: list<FixtureBlock>}>
	 */
	public function pages(): array {
		return array(
			array(
				'key'      => 'home',
				'slug'     => 'home',
				'title'    => 'Home',
				'template' => '',
				'role'     => 'front',
				'updated'  => '',
				'deck'     => '',
				'body'     => array(
					self::note(
						'Nothing on this page is rendered. The front page is drawn entirely by the theme\'s "front-page" template, which reads the site rather than this post. The page exists so that Settings to Reading has something to point at and so the chrome has somewhere to link home.',
						'PLACEHOLDER — NOT RENDERED'
					),
				),
			),
			array(
				'key'      => 'work',
				'slug'     => 'work',
				'title'    => 'Where I worked, what came out of it.',
				'template' => 'dp-work',
				'role'     => '',
				'updated'  => '',

				/*
				 * The title above and this deck are the design's `PageHero` on
				 * the timeline view, verbatim. The title is long because it is
				 * an `<h1>` in the design and `dp-work.html` binds the `<h1>` to
				 * `core/post-title`; a page called "Work" would draw a page
				 * headed "Work", which is not what the design draws.
				 */
				'deck'     => 'There\'s no separate portfolio here. Three projects I\'d show first, then every role I\'ve held and everything that came out of each one.',
				'body'     => array(
					self::note(
						'This body is not rendered. The Work template draws the featured cards and the timeline from the `dp_role` and `dp_ship` posts; the only things it takes from this page are its title and its deck.',
						'PLACEHOLDER — NOT RENDERED'
					),
				),
			),
			array(
				'key'      => 'posts',

				/*
				 * Deliberately not `blog`. Digest section 2 says the index must
				 * render correctly whatever David calls it, and a fixture that
				 * used the obvious name would never catch a slug creeping into
				 * the theme. `Writing` is what the design's own nav calls it.
				 */
				'slug'     => 'writing',
				'title'    => 'Writing',
				'template' => '',
				'role'     => 'posts',
				'updated'  => '',
				'deck'     => '',
				'body'     => array(
					self::note(
						'Nothing on this page is rendered. WordPress draws the posts index with the theme\'s "home" template, which ships its own hero — "Writing." and the line under it — because the index is a list of posts rather than a page anybody edits.',
						'PLACEHOLDER — NOT RENDERED'
					),
				),
			),
			array(
				'key'      => 'about',
				'slug'     => 'about',
				'title'    => 'Hi, I\'m David.',
				'template' => 'dp-about',
				'role'     => '',
				'updated'  => '',
				'deck'     => 'I\'m a software developer and product builder in Colombia. I run Fanxie Lab, and I build my own iOS and SaaS products on the side of it.',
				'body'     => array(
					self::p( 'Most of my work sits where performance, privacy, and the actual experience meet — the three things that get traded away first when a deadline shows up. I\'ve spent enough years watching that trade get made to have opinions about it.' ),
					self::p( 'When I\'m not writing code I\'m pulling espresso shots, playing guitar through too many amp sims, or being judged by two cats. I write about some of that here.' ),
					self::h2( 'The part I\'m best at is scale that has to stay quiet.' ),
					self::p( 'For four years I worked on an analytics plugin running on around three million WordPress sites, in PHP, Vue.js, REST APIs and WP-CLI. At that size every change is a performance decision, and the interesting problem is never the feature — it\'s shipping it without anyone noticing you were there.' ),
					self::p( 'That habit carried into everything since. The rest of what I do, in short:' ),

					/*
					 * The design's three skill rows are a mono caps label in the
					 * accent colour over a paragraph, which is exactly what `h4`
					 * is in this house style. Nothing new is invented for them.
					 */
					self::h4( 'Backend' ),
					self::p( 'Laravel and NestJS on DigitalOcean and Cloudflare — API design, transactional email, R2 storage, provisioning. The unglamorous half that decides whether the rest works.' ),
					self::h4( 'Native iOS' ),
					self::p( 'Swift, SwiftUI and CloudKit. Apps that keep their data on the device and do not phone home, because that is the version I want to use.' ),
					self::h4( 'Frontend' ),
					self::p( 'Vue.js and PHP at scale, and enough design work to know when an interface problem is really a product problem.' ),
					self::p( 'The long version of how I got here is a series I\'m still writing, one part at a time. The short version is a timeline — roles, and the things that came out of them.' ),
				),
			),
			array(
				'key'      => 'contact',
				'slug'     => 'contact',
				'title'    => 'Let\'s build something.',
				'template' => 'dp-contact',
				'role'     => '',
				'updated'  => '',
				'deck'     => '',
				'body'     => array(
					self::p( 'Agency work through Fanxie Lab, product conversations, or just a note about espresso and guitars. I read everything.' ),

					/*
					 * The design draws three `ContactMethod` rows under that
					 * paragraph — email, X, the agency. The theme ships them as
					 * the `dpaternina/contact-method` pattern, which is David's
					 * to insert: this plugin may not name a theme pattern
					 * (CLAUDE.md section 2.1), and the fixture's block
					 * vocabulary is closed on purpose. So the page says the rows
					 * are missing instead of pretending they are not.
					 */
					self::note(
						'The design puts three contact rows here — email, X, the agency — as the theme\'s "Contact method" pattern. Insert them from the pattern inserter; the seed does not, because it may not reach into the theme.',
						'PLACEHOLDER — ROWS TO INSERT'
					),
				),
			),
			array(
				'key'      => 'uses',
				'slug'     => 'uses',
				'title'    => 'Uses',
				'template' => 'dp-uses',
				'role'     => '',
				'updated'  => 'UPDATED AUG 2026',
				'deck'     => 'The hardware, software, and small objects I actually reach for. If something is on this list it survived at least a year of use.',
				'body'     => array(
					self::p( 'People ask about the setup more than anything else I write about, so it lives here instead of in my replies. I update the page when something changes, not on a schedule.' ),
					self::h2( 'Desk' ),
					self::image( 'THE DESK, AUGUST 2026' ),
					self::ul(
						array(
							'16-inch laptop, docked, lid closed, one 27-inch display above it.',
							'Mechanical keyboard with tactile switches — quiet enough for calls.',
							'A notebook, because I still think better with a pen for the first ten minutes.',
						)
					),
					self::h2( 'What I build with' ),
					self::p( 'The stack has not changed much in three years, which I take as a good sign.' ),
					self::table(
						array( 'Tool', 'For', 'Since' ),
						array(
							array( 'Neovim', 'Everything I type into a repo', '2019' ),
							array( 'Laravel + Vue', 'Client work and internal tools', '2017' ),
							array( 'SwiftUI', 'The iOS side of my own products', '2023' ),
							array( 'Figma', 'Interface work before it becomes code', '2020' ),
						)
					),
					self::h3( 'Small utilities that earn their keep' ),
					self::ul(
						array(
							'A clipboard history tool — the single biggest time saver on this list.',
							'A window manager bound to muscle memory.',
							'One password manager, one terminal, no launchers I have to configure.',
						)
					),
					self::h2( 'Off the clock' ),
					self::p( 'The camera is one body and one 35mm prime. The guitar is one guitar. The coffee setup is deliberately unremarkable and produces the same shot every morning.' ),
					self::code( '18g in · 36g out · 28s · 93°C', 'ESPRESSO' ),
					self::note( 'No affiliate links anywhere on this page. If you want a specific model number, ask me and I will tell you what I paid.', 'NOTE' ),
					self::rule(),
					self::p( 'Last thing: none of this matters as much as sitting down and doing the work. The tools are just what happened to be nearby when I did.' ),
				),
			),
			array(
				'key'      => 'resume',
				'slug'     => 'resume',
				'title'    => 'David Paternina',
				'template' => 'dp-resume',
				'role'     => '',
				'updated'  => 'RÉSUMÉ · UPDATED AUG 2026',
				'deck'     => 'Software developer and product builder · Colombia',
				'body'     => array(
					self::p( 'Twelve years building software, four of them leading development on an analytics plugin running on more than three million WordPress sites. I work where performance, privacy, and user experience meet — usually the first three things traded away when a deadline appears.' ),

					/*
					 * The design prints a mono caps contact row under that
					 * paragraph — the address, the domain, the agency. The
					 * résumé template has no block for it, so seeding one would
					 * mean inventing a style the design system does not have.
					 * The line is left out and the omission is written down
					 * here rather than approximated on the page.
					 */
				),
			),
			array(
				'key'      => 'colophon',
				'slug'     => 'colophon',
				'title'    => 'Colophon',
				'template' => 'dp-colophon',
				'role'     => '',
				'updated'  => 'UPDATED AUG 2026',
				'deck'     => 'How this site is built, what it is made of, and who to blame for the parts that are wrong.',
				'body'     => array(
					self::p( 'I kept two websites for years — one for the work, one for the writing — and maintained neither properly. This is the merge. It is built to be fast, quiet, and boring to run, because a site I dread deploying is a site I stop writing on.' ),
					self::p( 'It is WordPress, which surprises people who assume I would reach for something newer. I write in WordPress every working day and I know precisely where it is slow. Publishing a post has to take one click, or the posts stop happening.' ),
					self::h2( 'Type' ),
					self::ul(
						array(
							'Display and headings set in the display face, tight tracking, weight 700 only.',
							'Body copy at a measure of about 68 characters, because longer lines lose me too.',
							'Everything mono — dates, labels, code, captions — is the same face at one size.',
						)
					),
					self::h2( 'Colour' ),
					self::p( 'One dark ground, one teal accent, and three secondary hues that only appear to mark a category or a state. Nothing here is a gradient for its own sake; the gradient exists because the monogram does.' ),
					self::table(
						array( 'Role', 'Where it shows up', 'Rule' ),
						array(
							array( 'Teal', 'Links, active nav, primary buttons', 'The default accent' ),
							array( 'Pink', 'Fanxie Lab, the life-story series, live badge', 'Mine, personal' ),
							array( 'Gold', 'Shipped and live things', 'Earned, not decorative' ),
							array( 'Purple', 'Infrastructure and privacy', 'The quiet half' ),
						)
					),
					self::h2( 'Built with' ),
					self::p( 'WordPress, with a theme I wrote by hand — no page builder, no block library I did not choose, no plugin doing work that twenty lines of PHP would do. I have spent four years inside a plugin that runs on three million sites, so I know exactly how much of this stack I can leave out.' ),
					self::p( 'It runs on Fanxie Cloud, our own managed hosting: Redis object cache, Varnish in front, Cloudflare at the edge, DigitalOcean underneath. Pages are served from cache and the database is barely awake.' ),
					self::code( "$ git push\n→ composer     ok\n→ theme build  ok (2.4s)\n→ rsync        ok\n→ cache flush  ok\nlive in 11s", 'DEPLOY' ),
					self::ul(
						array(
							'A hand-written block theme. The editor only offers blocks this design system has.',
							'Four plugins, all load-bearing. I audit the list twice a year and it gets shorter.',
							'No analytics script, no cookie banner, because there is nothing to consent to.',
						)
					),
					self::h2( 'Credits' ),
					self::ul(
						array(
							'Photographs are mine unless a caption says otherwise.',
							'The monogram is mine, drawn far too many times.',
							'Any figure in a post comes from my own projects, or it is marked as missing.',
						)
					),
					self::note( 'Broken link, wrong date, bad take — tell me and I will fix it. Corrections get made in place, and anything substantive gets a note at the bottom of the post.', 'FOUND A MISTAKE?' ),
				),
			),
			array(
				'key'      => 'privacy',
				'slug'     => 'privacy',
				'title'    => 'Privacy',

				/*
				 * No custom template: the design draws Privacy with the same
				 * reading layout as any other page, which is `page.html`.
				 */
				'template' => '',
				'role'     => 'privacy',
				'updated'  => 'UPDATED AUG 2026',
				'deck'     => 'The short version: this site does not track you, and I do not have anything to sell about you.',
				'body'     => array(
					self::p( 'I spent a year building analytics features while arguing that privacy is a constraint you design against, not a disclaimer you add afterwards. It would be strange to then run a personal site that watched you read it.' ),
					self::h2( 'What this site collects' ),
					self::p( 'Nothing that identifies you. No cookies are set. No third-party analytics, advertising, or social scripts load on any page.' ),
					self::ul(
						array(
							'No cookies, no local storage used for tracking.',
							'No Google Analytics, no Meta pixel, no session recording.',
							'Aggregate page counts only, with no IP address stored and nothing linked to a person.',
						)
					),
					self::h2( 'If you write to me' ),
					self::p( 'The contact form sends me an email with what you typed and nothing else. I keep it in my inbox until the conversation is over. I do not add you to a list, and there is no list to add you to.' ),
					self::h2( 'Things I do not control' ),
					self::p( 'Video on the Watch page comes from Twitch and YouTube. Thumbnails load from their image servers, so those services see that request. Players do not load at all until you press play, which is the one bit of this I can actually control.' ),
					self::p( 'The site runs on WordPress. It sets a cookie only if you log in to the admin, which is me and nobody else.' ),
					self::note( 'If any of this changes, the date at the top of this page changes with it, and the change is described here rather than quietly rewritten.', 'NOTE' ),
					self::rule(),
					self::p( 'Questions about any of it: hello@dpaternina.com.' ),
				),
			),
		);
	}

	/**
	 * A paragraph.
	 *
	 * @param string $text The text.
	 * @return FixtureBlock
	 */
	private static function p( string $text ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Paragraph, text: $text );
	}

	/**
	 * A level-two heading. The design's bare `h` is an alias for this.
	 *
	 * @param string $text The text.
	 * @return FixtureBlock
	 */
	private static function h2( string $text ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Heading2, text: $text );
	}

	/**
	 * A level-three heading.
	 *
	 * @param string $text The text.
	 * @return FixtureBlock
	 */
	private static function h3( string $text ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Heading3, text: $text );
	}

	/**
	 * A level-four heading. Mono caps in the accent colour, not the display face.
	 *
	 * @param string $text The text.
	 * @return FixtureBlock
	 */
	private static function h4( string $text ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Heading4, text: $text );
	}

	/**
	 * A pull quote, with optional attribution.
	 *
	 * @param string $text The quotation.
	 * @param string $cite The attribution, or an empty string.
	 * @return FixtureBlock
	 */
	private static function quote( string $text, string $cite ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Quote, text: $text, cite: $cite );
	}

	/**
	 * An unordered list.
	 *
	 * @param array<int, string> $items The items.
	 * @return FixtureBlock
	 */
	private static function ul( array $items ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::BulletList, items: $items );
	}

	/**
	 * An ordered list.
	 *
	 * @param array<int, string> $items The items.
	 * @return FixtureBlock
	 */
	private static function ol( array $items ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::NumberList, items: $items );
	}

	/**
	 * A labelled code block.
	 *
	 * @param string $code  The code.
	 * @param string $label The mono caps label above it.
	 * @return FixtureBlock
	 */
	private static function code( string $code, string $label ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Code, text: $code, label: $label );
	}

	/**
	 * A callout.
	 *
	 * @param string $note  The caveat.
	 * @param string $label The mono caps label.
	 * @return FixtureBlock
	 */
	private static function note( string $note, string $label ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Note, text: $note, label: $label );
	}

	/**
	 * An inline figure. The design ships no media, so only the caption is real.
	 *
	 * @param string $caption The mono caps caption.
	 * @return FixtureBlock
	 */
	private static function image( string $caption ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Image, label: $caption );
	}

	/**
	 * A table.
	 *
	 * @param array<int, string>             $head Header cells.
	 * @param array<int, array<int, string>> $rows Body rows.
	 * @return FixtureBlock
	 */
	private static function table( array $head, array $rows ): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Table, head: $head, rows: $rows );
	}

	/**
	 * The spectrum rule.
	 *
	 * @return FixtureBlock
	 */
	private static function rule(): FixtureBlock {
		return new FixtureBlock( FixtureBlockKind::Rule );
	}
}
