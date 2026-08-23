// VERBATIM from the live Claude Design project, SiteHeader.dc.html script block.
// Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b. Re-fetched 2026-08-23.
// See design-source/README.md — the 2026-08-19 import dropped every component's script.
// DO NOT EDIT. Change the design, re-fetch.
//
// WP build notes:
//   `narrow` is @container (width < 720px).
//   The panel is a <dialog>; this component fakes one with a fixed overlay.
//   Escape closes and body scroll locks ONLY while narrow — see componentDidUpdate.

class Component extends DCLogic {
  state = { w: 0, menu: false };
  hostId = 'dph-' + Math.random().toString(36).slice(2, 9);

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
      this.setState({ w: 1120 });
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

  componentDidMount() {
    this.start();
    this.onKey = e => { if (e.key === 'Escape') this.setState({ menu: false }); };
    window.addEventListener('keydown', this.onKey);
  }

  componentDidUpdate() {
    const lock = this.state.menu && this.state.w > 0 && this.state.w < 720;
    document.body.style.overflow = lock ? 'hidden' : '';
  }

  componentWillUnmount() {
    this.stop();
    if (this.onKey) window.removeEventListener('keydown', this.onKey);
    document.body.style.overflow = '';
  }

  renderVals() {
    if (!this.started) setTimeout(() => this.start(), 0);
    const w = this.state.w;
    const narrow = w > 0 && w < 720;
    const at = this.props.active ?? 'home';
    const noop = () => {};
    const p = this.props;
    const close = () => this.setState({ menu: false });

    // FIVE items. Blog stays active across post, series and category.
    const defs = [
      { label: 'Home', on: p.onHome ?? noop, active: at === 'home' },
      { label: 'Work', on: p.onTimeline ?? noop, active: at === 'timeline' },
      { label: 'Blog', on: p.onBlog ?? noop, active: at === 'blog' || at === 'post' || at === 'series' || at === 'category' },
      { label: 'Watch', on: p.onWatch ?? noop, active: at === 'watch' },
      { label: 'About', on: p.onAbout ?? noop, active: at === 'about' },
    ];

    return {
      hostId: this.hostId,
      wide: !narrow,
      narrow,
      menuOpen: this.state.menu,
      menuClosed: !this.state.menu,
      showPanel: narrow && this.state.menu,
      toggleMenu: () => this.setState(s => ({ menu: !s.menu })),
      closeMenu: close,
      contactFromPanel: () => { close(); (p.onContact ?? noop)(); },
      onHome: p.onHome ?? noop,
      onContact: p.onContact ?? noop,
      live: !!p.live,
      onWatchLive: () => { close(); (p.onWatch ?? noop)(); },
      items: defs.map((d, i) => ({
        key: i,
        label: d.label,
        active: d.active,
        onClick: d.on,
        onNavigate: () => { close(); d.on(); },
        panelStyle: {
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: 16,
          minHeight: 'var(--target-comfortable)',
          padding: '16px 0',
          borderBottom: '1px solid var(--border-subtle)',
          fontFamily: 'var(--font-display)',
          fontWeight: 700,
          fontSize: 'var(--fs-xl)',
          letterSpacing: 'var(--ls-tight)',
          color: d.active ? 'var(--accent)' : 'var(--text-primary)',
        },
      })),
    };
  }
}
