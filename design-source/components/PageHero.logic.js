// VERBATIM from the live Claude Design project, PageHero.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// `tight` is the ONLY difference between the two hero paddings: 24px bottom instead of
// 40px. The design applies it to the generic page view, not to Work.
// h1 is clamp(2.25rem, 5.5vw, 3.75rem) with --lh-tight and --ls-display.
// Note `width: 'md'` and `width: 'reading'` both resolve to --container-md.

class Component extends DCLogic {
  renderVals() {
    const width = this.props.width ?? 'lg';
    const maxWidth = width === 'reading' ? 'var(--container-md)' : width === 'md' ? 'var(--container-md)' : 'var(--container-lg)';
    const meta = this.props.meta ?? '';
    return {
      title: this.props.title ?? 'Page title.',
      deck: this.props.deck ?? '',
      hasDeck: !!(this.props.deck ?? ''),
      meta,
      hasMeta: !!meta,
      // The prop editor defaults titleMax to '20ch'; the fallback here is 'none'.
      titleMax: this.props.titleMax ?? 'none',
      wrapStyle: {
        maxWidth,
        margin: '0 auto',
        padding: 'clamp(48px, 8vw, 80px) var(--gutter) ' + (this.props.tight ? '24px' : '40px'),
      },
    };
  }
}
