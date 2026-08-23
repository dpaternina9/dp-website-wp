// VERBATIM from the live Claude Design project, CtaBanner.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// Two variants, one difference: `filled` gets --bg-surface, `plain` is transparent.
// Both keep the same border, radius and padding. The theme ships these as the
// cta-banner and cta-banner-filled patterns.
//
// Type is smaller than it looks: the title is --fs-base in the DISPLAY face, not a
// heading size; the line is --fs-sm. The optional mark is 64px.

class Component extends DCLogic {
  renderVals() {
    const filled = (this.props.variant ?? 'plain') === 'filled';
    return {
      wrapStyle: {
        padding: 'clamp(24px, 4vw, 32px)',
        borderRadius: 'var(--radius-lg)',
        border: '1px solid var(--border-subtle)',
        background: filled ? 'var(--bg-surface)' : 'transparent',
        display: 'flex',
        alignItems: 'center',
        gap: '24px',
        flexWrap: 'wrap',
      },
      showMark: !!this.props.showMark,
      title: this.props.title ?? 'Questions about any of this?',
      line: this.props.line ?? "Ask and I'll answer with specifics.",
      buttonLabel: this.props.buttonLabel ?? 'Say hi',
      onClick: this.props.onClick ?? (() => {}),
    };
  }
}
