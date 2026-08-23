// VERBATIM from the live Claude Design project, FilterPills.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// TWO THINGS THIS FILE SETTLES, both of which cost the WP build time:
//
// 1. BOX MODEL. There is no `box-sizing` reset anywhere in the design — not in
//    _ds/tokens/base.css, not in _ds/styles.css, not in theme.css. The pill measures
//    36px total ONLY because it is a <button>, and browsers apply
//    `box-sizing: border-box` to form controls in the UA stylesheet. Render the same
//    pill as an <a> and it becomes 36 + 16 padding + 2 border = 54px. The theme must
//    declare border-box explicitly; it is not adding a rule the design lacks, it is
//    restoring one the design gets for free.
//
// 2. THE ON-STATE COLOUR IS `--dp-teal`, a FILL token used as text. CLAUDE.md §5 says
//    `--dp-*` is for fills and `--hue-*` for text, and the theme uses `--hue-teal` here.
//    That is a deliberate, documented divergence for contrast — not a transcription
//    error. Do not "correct" it in either direction without re-measuring the ratio.

class Component extends DCLogic {
  renderVals() {
    const options = this.props.options ?? ['Everything', 'Roles', 'Shipped'];
    const value = this.props.value ?? options[0];
    const onChange = this.props.onChange ?? (() => {});
    const extraLabel = this.props.extraLabel ?? '';
    return {
      pills: options.map((label, i) => {
        const on = label === value;
        return {
          key: i,
          label,
          on,
          onClick: () => onChange(label),
          style: {
            cursor: 'pointer',
            minHeight: 'var(--target-min)',
            padding: '8px 20px',
            borderRadius: 'var(--radius-pill)',
            fontFamily: 'var(--font-mono)',
            fontSize: 'var(--fs-xs)',
            letterSpacing: 'var(--ls-caps)',
            border: '1px solid ' + (on ? 'var(--dp-teal)' : 'var(--border-subtle)'),
            background: on ? 'color-mix(in srgb, var(--dp-teal) 14%, transparent)' : 'transparent',
            color: on ? 'var(--dp-teal)' : 'var(--text-secondary)',
            transition: 'all var(--dur-base) var(--ease-standard)',
          },
        };
      }),
      extraLabel,
      hasExtra: !!extraLabel,
      onExtra: this.props.onExtra ?? (() => {}),
    };
  }
}
