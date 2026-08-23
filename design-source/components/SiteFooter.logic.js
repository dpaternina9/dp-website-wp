// VERBATIM from the live Claude Design project, SiteFooter.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// Notes for the WP build:
//   - The © line carries the YEAR: "© 2026 DAVID PATERNINA". ADR-0006 recorded dropping
//     it as a deviation; the design does not drop it.
//   - SITE includes Watch. The theme omits it until Phase 12 ships, deliberately.
//   - RSS is the one real href in the whole design: <a href="/rss.xml">.
//   - Brand blurb is capped at 26ch, --fs-sm.
//   - Footer grid: repeat(auto-fit, minmax(min(180px, 100%), 1fr)), gap 40px 32px,
//     padding 56px var(--gutter) 32px. Bottom bar: padding 20px var(--gutter) 40px,
//     gap 12px 24px, border-top.

class Component extends DCLogic {
  renderVals() {
    const noop = () => {};
    const p = this.props;
    const g = (label, links) => ({
      key: label, label,
      links: links.map(([l, on]) => ({ key: l, label: l, onClick: on ?? noop })),
    });
    return {
      onHome: p.onHome ?? noop,
      onWatch: p.onWatch ?? noop,
      onPrivacy: p.onPrivacy ?? noop,
      onColophon: p.onColophon ?? noop,
      live: !!p.live,
      year: new Date().getFullYear(),
      groups: [
        g('SITE', [['Work', p.onTimeline], ['Watch', p.onWatch], ['About', p.onAbout], ['Contact', p.onContact]]),
        g('WRITING', [['All posts', p.onBlog], ['My life story', p.onSeries], ['Categories', p.onCategories]]),
        g('MORE', [['Uses', p.onPage], ['Résumé', p.onResume], ['Colophon', p.onColophon]]),
      ],
    };
  }
}
