// VERBATIM from the live Claude Design project, TimelineChart.dc.html
// `<script type="text/x-dc" data-dc-script>`. Project 2fa41a1e-87d8-4b9b-a3ce-41d8c96afe2b.
// Re-fetched 2026-08-23 after the 2026-08-19 import was found to have dropped every
// component's script block. THIS FILE IS THE SOURCE OF TRUTH for the chart's computed
// styles — the ones the parity harness called "computed inside the design tool and never
// exported". They were exported. Nobody fetched them.
//
// DO NOT EDIT. Change the design, re-fetch.

const DEMO_LANES = [
  {
    org: 'MonsterInsights', title: 'Developer team lead', start: 2022, end: 2026.4, range: '2022 — 2026',
    detail: 'Led development on an analytics plugin running on over 3 million websites.',
    stack: 'PHP · VUE.JS · REST APIS · WP-CLI',
    ships: [{
      org: 'Natural-language queries', start: 2025, end: 2025.8, range: '2025',
      headline: 'Ask your analytics a question.',
      detail: 'Plain-English queries instead of a reporting UI.',
      bullets: [{ text: 'Ships inside a plugin that updates unattended on 3M+ sites.' }],
      role: 'Developer team lead', stack: 'PHP · VUE.JS',
      artifactLabel: 'QUERY → ANSWER', artifact: '> which posts grew last month?',
      stat1: '3M+', stat1Label: 'SITES', stat2: '—', stat2Label: 'ADOPTION',
    }],
  },
  {
    org: 'Fanxie Lab', title: 'CTO & founder', start: 2024, end: 2026.6, range: '2024 — now',
    detail: 'An innovation lab serving partners across Latin America.',
    stack: 'LARAVEL · NESTJS · CLOUDFLARE', ships: [],
  },
];

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
      this.setState({ w: 1000 });
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
    const w = this.state.w;
    const narrow = w > 0 && w < 700;
    const mode = !narrow ? 'bars' : (this.props.mobileMode ?? 'stack');
    const isStack = mode === 'stack';
    const isScroll = mode === 'scroll';

    const y0 = this.props.yearStart ?? 2014;
    const y1 = this.props.yearEnd ?? 2026;
    const pos = y => ((y - y0) / (y1 - y0 + 1)) * 100;

    const laneData = this.props.lanes ?? DEMO_LANES;
    const filter = this.props.filter ?? 'Everything';
    const want = { Everything: null, Roles: 'work', Shipped: 'ship' }[filter] ?? null;
    const open = this.props.open ?? {};
    const onToggle = this.props.onToggle ?? (() => {});

    const labelCol = isStack ? 'minmax(0, 1fr)' : (isScroll ? '128px minmax(0, 1fr)' : '200px minmax(0, 1fr)');
    // In scroll mode the chart is wider than the viewport, so anything meant to be READ
    // (legend, expanded detail) is pinned to the left edge at the visible width.
    const visW = Math.max(240, w - 60);
    const pinned = isScroll ? { width: visW, maxWidth: visW, boxSizing: 'border-box' } : {};
    const gridStyle = {
      display: 'grid',
      gridTemplateColumns: labelCol,
      gap: isStack ? '2px' : '24px',
      alignItems: 'center',
    };
    const labelW = isScroll ? 128 : 200;
    const railPad = isStack ? 16 : 20;
    const shipGridStyle = {
      ...gridStyle,
      gridTemplateColumns: isStack ? 'minmax(0, 1fr)' : (labelW - railPad) + 'px minmax(0, 1fr)',
    };
    const labelCellStyle = {
      cursor: 'pointer',
      textAlign: 'left',
      position: isScroll ? 'sticky' : (isStack ? 'relative' : 'static'),
      left: 0,
      zIndex: isScroll ? 1 : 'auto',
      background: isScroll ? 'var(--bg-surface)' : 'transparent',
      paddingRight: isScroll ? 12 : (isStack ? 32 : 0),
      minHeight: isStack ? 'var(--target-min)' : 'auto',
      display: isStack ? 'flex' : 'block',
      flexDirection: 'column',
      justifyContent: 'center',
      gap: isStack ? 4 : 0,
    };

    // Stack mode has no bar track, so the card's own 16px side padding is the only
    // horizontal frame. Rows bleed 12px into it when open so the tint reads as a block,
    // and expanded panels bleed all the way back out to the card edge.
    // The lane wrapper carries spacing and the divider; the tint lives on the inner row so an
    // open role never washes its shipped rows with a second layer of the same background.
    const wrapStyle = {
      display: 'flex',
      flexDirection: 'column',
      gap: isStack ? 0 : 6,
      borderTop: isStack ? '1px solid var(--border-subtle)' : 'none',
    };

    // Shipped items hang off a hairline rail in every mode — an indent alone never read as
    // nesting. The rail's padding is subtracted back out of the ship label column below, so the
    // track still starts on the same x as the roles' and stays true to the year axis.
    const shipsWrapStyle = {
      display: 'flex', flexDirection: 'column',
      gap: isStack ? 2 : 6,
      paddingLeft: railPad,
      marginTop: isStack ? 4 : 6,
      marginBottom: isStack ? 4 : 0,
    };

    // Ship rows inset their content the same as roles do, and pull the box back out with a
    // matching negative margin — the label keeps its x (so the track stays true to the year
    // axis) while the open row's background gains breathing room left of the text. The pull is
    // smaller than railPad, so the background never reaches the hairline rail.
    const rowStyle = (isOpen, isShip) => ({
      padding: isStack
        ? (isShip ? '12px' : (isOpen ? '16px 12px 18px' : '16px 12px'))
        : (isShip
            ? (isOpen ? '6px 16px 14px' : '6px 16px')
            : (isOpen ? '8px 16px 14px' : '6px 16px')),
      margin: isStack ? '0 -12px' : '0 -16px',
      borderRadius: 'var(--radius-sm)',
      background: isOpen
        ? 'color-mix(in srgb, var(--dp-white) ' + (isShip && !isStack ? '2.5%' : '4%') + ', transparent)'
        : 'transparent',
      transition: 'background var(--dur-base) var(--ease-standard), padding var(--dur-base) var(--ease-standard)',
    });

    const barStyle = (item, isOpen, color, small) => ({
      position: 'absolute', top: small ? 6 : 4, bottom: small ? 6 : 4,
      left: pos(item.start) + '%',
      width: 'min(' + (pos(item.end) - pos(item.start)) + '%, ' + (100 - pos(item.start)) + '%)',
      minWidth: small ? 40 : 64, maxWidth: (100 - pos(item.start)) + '%',
      borderRadius: 'var(--radius-xs)',
      cursor: 'pointer', overflow: 'hidden', boxSizing: 'border-box',
      display: 'flex', alignItems: 'center',
      background: isOpen ? color : 'color-mix(in srgb, ' + color + ' 38%, var(--bg-surface))',
      boxShadow: isOpen ? '0 0 0 4px color-mix(in srgb, ' + color + ' 16%, transparent)' : 'none',
      transition: 'background var(--dur-base) var(--ease-standard), box-shadow var(--dur-base) var(--ease-standard)',
    });

    const chevronStyle = isOpen => ({
      position: 'absolute', right: 0, top: '50%',
      right: 4,
      transform: 'translateY(-50%) rotate(' + (isOpen ? '180deg' : '0deg') + ')',
      color: isOpen ? 'var(--dp-teal)' : 'var(--text-muted)',
      display: 'flex',
      transition: 'transform var(--dur-base) var(--ease-standard), color var(--dur-base) var(--ease-standard)',
    });

    const years = [];
    for (let y = y0; y <= y1; y++) years.push({ key: y, label: String(y) });

    const lanes = laneData.filter(l => want !== 'ship' || l.ships.length).map(l => {
      const key = l.org + l.title;
      const isOpen = !!open[key];
      // A lane can carry its own accent when it is not just another job on the list.
      const teal = l.accent || 'var(--dp-teal)';
      return {
        ...l,
        key,
        kindLabel: 'ROLE',
        kindLabelStyle: {
          textAlign: 'left',
          fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-xs)',
          letterSpacing: 'var(--ls-caps)', color: teal,
        },
        isOpen,
        onClick: () => onToggle(key),
        wrapStyle,
        shipsWrapStyle,
        rowStyle: rowStyle(isOpen, false),
        chevronStyle: chevronStyle(isOpen),
        orgStyle: {
          fontFamily: 'var(--font-display)',
          fontSize: isStack ? 'var(--fs-base)' : 'var(--fs-sm)',
          letterSpacing: 'var(--ls-tight)',
          color: isOpen ? teal : 'var(--text-primary)',
          paddingRight: isStack ? 28 : 0,
          transition: 'color var(--dur-base) var(--ease-standard)',
        },
        barStyle: barStyle(l, isOpen, teal, false),
        ships: (want === 'work' ? [] : l.ships).map(sh => {
          const sk = l.org + sh.org;
          const sOpen = !!open[sk];
          const gold = 'var(--dp-gold)';
          return {
            ...sh,
            key: sk,
            isOpen: sOpen,
            onClick: () => onToggle(sk),
            rowStyle: rowStyle(sOpen, true),
            chevronStyle: chevronStyle(sOpen),
            headingRowStyle: {
              display: 'flex', alignItems: 'baseline', gap: 8,
              justifyContent: 'flex-start',
              paddingRight: isStack ? 28 : 0,
            },
            orgStyle: {
              fontFamily: 'var(--font-body)',
              fontSize: isStack ? 'var(--fs-sm)' : 'var(--fs-xs)',
              fontWeight: 600,
              color: sOpen ? gold : 'var(--text-secondary)',
              transition: 'color var(--dur-base) var(--ease-standard)',
            },
            barStyle: barStyle(sh, sOpen, gold, true),
            detailStyle: {
              marginTop: isStack ? 12 : 12,
              // Full width in every mode — the panel is reading material, so it gets the whole
              // row rather than being boxed into the track column under its bar.
              marginLeft: 0,
              marginRight: 0,
              padding: isStack ? 14 : (isScroll ? 14 : 24),
              borderRadius: 'var(--radius-sm)',
              background: 'var(--band)', border: '1px solid var(--border-subtle)',
              animation: 'dpUp var(--dur-slow) var(--ease-out) both',
              ...pinned,
            },
          };
        }),
      };
    });

    return {
      hostId: this.hostId,
      isStack,
      isScroll,
      showTrack: !isStack,
      // Any lane carrying its own accent earns a legend entry, so a differently-coloured bar
      // never reads as a mistake.
      accentLegend: (want === 'ship' ? [] : laneData.filter(l => l.accent)).map(l => ({
        key: l.org,
        label: l.org.toUpperCase(),
        swatchStyle: {
          width: 8, height: 8, borderRadius: 'var(--radius-xs)',
          background: l.accent, flex: 'none',
        },
      })),
      headlineStyle: {
        margin: '0 0 12px',
        fontFamily: 'var(--font-display)',
        fontWeight: 700,
        // 30px in a rail-inset column on a phone is too heavy for the measure it gets
        fontSize: isStack ? 'var(--fs-lg)' : 'var(--fs-xl)',
        lineHeight: 'var(--lh-snug)',
        letterSpacing: 'var(--ls-display)',
        textWrap: 'pretty',
      },
      years,
      lanes,
      gridStyle,
      shipGridStyle,
      labelCellStyle,
      filterOptions: ['Everything', 'Roles', 'Shipped'],
      filter,
      onFilterChange: this.props.onFilterChange ?? (() => {}),
      toggleAllLabel: this.props.allOpen ? 'COLLAPSE ALL' : 'EXPAND ALL',
      onToggleAll: this.props.onToggleAll ?? (() => {}),
      onReadPost: this.props.onReadPost ?? (() => {}),
      cardStyle: {
        padding: isStack ? '4px 16px 24px' : 'clamp(20px, 3vw, 32px)',
        borderRadius: 'var(--radius-lg)',
        background: 'var(--bg-surface)',
        border: '1px solid var(--border-subtle)',
      },
      scrollerStyle: {
        minWidth: 0,
        maxWidth: '100%',
        overflowX: isScroll ? 'auto' : 'visible',
        overflowY: 'visible',
        margin: isScroll ? '0 -4px' : '0',
        padding: isScroll ? '0 4px' : '0',
      },
      innerStyle: { minWidth: isScroll ? '720px' : 'auto' },
      headStyle: {
        display: 'grid',
        gridTemplateColumns: labelCol,
        gap: isStack ? '8px' : '24px',
        alignItems: 'center',
        paddingTop: isStack ? 12 : 0,
        paddingBottom: isStack ? 12 : 16,
        borderBottom: '1px solid var(--border-subtle)',
      },
      legendStyle: {
        display: 'flex',
        flexDirection: isScroll ? 'column' : 'row',
        gap: isScroll ? 6 : 16,
        alignItems: isScroll ? 'flex-start' : 'center',
        flexWrap: 'wrap',
        fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-xs)',
        letterSpacing: 'var(--ls-caps)', color: 'var(--text-muted)',
      },
      detailGridStyle: {
        display: 'grid',
        gridTemplateColumns: isStack || isScroll ? 'minmax(0, 1fr)' : labelCol,
        gap: isStack || isScroll ? '8px' : '24px',
        padding: isStack ? '16px 0 0' : '16px 0 4px',
        ...pinned,
      },
      detailColsStyle: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: isStack ? '24px' : '32px',
      },
      detailLabelStyle: {
        textAlign: 'left',
        fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-xs)',
        letterSpacing: 'var(--ls-caps)', color: 'var(--accent-text)',
      },
    };
  }
}
