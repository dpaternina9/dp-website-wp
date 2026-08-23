// VERBATIM from the live Claude Design project, PostRow.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// The ResizeObserver probe is the design tool's stand-in for a container query.
// WP build: `narrow` is `@container (width < 560px)`.

class Component extends DCLogic {
  state = { w: 0 };
  hostId = 'dph-' + Math.random().toString(36).slice(2, 9);

  // Width comes from a zero-height probe so it can never be pushed around by our own
  // content. Several triggers because the host preview does not always run frame callbacks.
  measure() {
    const el = document.getElementById(this.hostId);
    if (!el) return;
    const w = Math.round(el.getBoundingClientRect().width);
    if (w > 0 && Math.abs(w - this.state.w) > 4) this.setState({ w });
  }

  start() {
    if (this.started) return;
    const el = document.getElementById(this.hostId);
    if (!el) { this.retry = setTimeout(() => this.start(), 32); return; }
    this.started = true;
    this.measure();
    if (typeof ResizeObserver !== 'undefined') {
      this.ro = new ResizeObserver(() => this.measure());
      this.ro.observe(el);
    } else {
      this.setState({ w: 900 });
    }
    this.onResize = () => this.measure();
    window.addEventListener('resize', this.onResize);
    this.poll = setInterval(() => this.measure(), 250);
    setTimeout(() => { clearInterval(this.poll); this.poll = null; }, 4000);
  }

  stop() {
    if (this.ro) this.ro.disconnect();
    if (this.poll) clearInterval(this.poll);
    if (this.retry) clearTimeout(this.retry);
    if (this.onResize) window.removeEventListener('resize', this.onResize);
  }

  componentWillUnmount() { this.stop(); }

  renderVals() {
    if (!this.started) setTimeout(() => this.start(), 0);
    const variant = this.props.variant ?? 'list';
    const w = this.state.w;
    // w === 0 on the very first paint; assume wide so the desktop layout never flashes narrow.
    const narrow = w > 0 && w < 560;

    const base = {
      position: 'relative',
      cursor: 'pointer',
      display: 'grid',
      alignItems: 'baseline',
      transition: 'background var(--dur-base) var(--ease-standard), padding-left var(--dur-base) var(--ease-standard)',
    };

    let rowStyle;
    let hoverStyle;
    if (variant === 'compact') {
      rowStyle = {
        ...base,
        gridTemplateColumns: narrow ? 'minmax(0, 1fr)' : 'minmax(0, 1fr) auto',
        gap: narrow ? '10px' : '24px',
        padding: '18px 0',
        borderTop: '1px solid var(--border-subtle)',
      };
      hoverStyle = 'padding-left: 10px';
    } else {
      rowStyle = {
        ...base,
        gridTemplateColumns: narrow ? 'minmax(0, 1fr)' : '130px minmax(0, 1fr) 130px',
        gap: narrow ? '10px' : '28px',
        padding: '18px 8px',
        borderBottom: '1px solid var(--border-subtle)',
      };
      hoverStyle = 'background: color-mix(in srgb, var(--text-primary) 3%, transparent)';
    }

    return {
      hostId: this.hostId,
      rowStyle,
      hoverStyle,
      date: this.props.date ?? 'AUG 2026',
      cat: this.props.cat ?? 'DEV',
      title: this.props.title ?? 'The house style, and every piece of it',
      excerpt: this.props.excerpt ?? 'Every block this blog can render, in one post.',
      // NOTE: compact gets the LARGER excerpt. Not a typo in the export.
      excerptSize: variant === 'compact' ? 'var(--fs-base)' : 'var(--fs-sm)',
      onOpen: this.props.onOpen ?? (() => {}),
      showMetaBar: narrow,
      showLeftDate: !narrow && variant === 'list',
      showRightCat: !narrow && variant === 'list',
      showRightStack: !narrow && variant === 'compact',
    };
  }
}
