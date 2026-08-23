// VERBATIM from the live Claude Design project, SectionHead.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.

const TONES = {
  teal: 'var(--hue-teal)',
  pink: 'var(--hue-pink)',
  gold: 'var(--hue-gold)',
  purple: 'var(--hue-purple)',
  muted: 'var(--text-muted)',
};

class Component extends DCLogic {
  renderVals() {
    const as = this.props.as ?? 'kicker';
    const action = this.props.action ?? '';
    const meta = this.props.meta ?? '';
    return {
      label: this.props.label ?? 'RIGHT NOW',
      isHeading: as === 'heading',
      isKicker: as !== 'heading',
      toneColor: TONES[this.props.tone ?? 'teal'] ?? TONES.teal,
      action,
      hasAction: !!action,
      onAction: this.props.onAction ?? (() => {}),
      meta,
      // meta is suppressed entirely when an action is present — never both.
      hasMeta: !!meta && !action,
    };
  }
}
