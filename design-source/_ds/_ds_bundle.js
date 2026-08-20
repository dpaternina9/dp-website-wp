/* @ds-bundle: {"format":4,"namespace":"DPDanielPaterninaDesignSystem_0f0b50","components":[{"name":"Logo","sourcePath":"components/brand/Logo.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"GradientText","sourcePath":"components/core/GradientText.jsx"},{"name":"IconButton","sourcePath":"components/core/IconButton.jsx"},{"name":"Input","sourcePath":"components/core/Input.jsx"},{"name":"Switch","sourcePath":"components/core/Switch.jsx"}],"sourceHashes":{"components/brand/Logo.jsx":"fd398ab2ab15","components/core/Badge.jsx":"a84c920cf30c","components/core/Button.jsx":"00411efc1599","components/core/Card.jsx":"640368f0da81","components/core/GradientText.jsx":"b781a23081ae","components/core/IconButton.jsx":"44a6e091ccc9","components/core/Input.jsx":"52b751fb4e9b","components/core/Switch.jsx":"a9b99f2b9cec","ui_kits/website/About.jsx":"5d8a18147ede","ui_kits/website/Contact.jsx":"e15ccf389ee9","ui_kits/website/Hero.jsx":"a4d41bf3e60b","ui_kits/website/Nav.jsx":"5850dfa393bb","ui_kits/website/Stream.jsx":"f00dd1755ec7","ui_kits/website/Work.jsx":"a5f8a2d6d6b0","ui_kits/website/app.jsx":"3ed3886605cb","ui_kits/website/icons.jsx":"8e880ae95a53"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.DPDanielPaterninaDesignSystem_0f0b50 = window.DPDanielPaterninaDesignSystem_0f0b50 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/brand/Logo.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const FILES = {
  black: 'dp-mark-black.png',
  white: 'dp-mark-white.png',
  teal: 'dp-mark-teal.png',
  purple: 'dp-mark-purple.png',
  red: 'dp-mark-red.png',
  yellow: 'dp-mark-yellow.png',
  gradient: 'dp-mark-gradient-4.png',
  'gradient-warm': 'dp-mark-gradient-1.png',
  'gradient-spectrum': 'dp-mark-gradient-4.png',
  'gradient-1': 'dp-mark-gradient-1.png',
  'gradient-2': 'dp-mark-gradient-2.png',
  'gradient-3': 'dp-mark-gradient-3.png',
  'gradient-4': 'dp-mark-gradient-4.png',
  'gradient-5': 'dp-mark-gradient-5.png',
  'gradient-6': 'dp-mark-gradient-6.png',
  'badge-light': 'dp-badge-light.png',
  'badge-dark': 'dp-badge-dark.png'
};

/**
 * dP Logo — renders the dP monogram mark, optionally with a wordmark.
 * Set `basePath` to wherever the logo PNGs live relative to the page.
 */
function Logo({
  variant = 'white',
  size = 40,
  showWordmark = false,
  wordmark = 'David Paternina',
  basePath = 'assets/logos',
  src,
  style = {},
  ...rest
}) {
  const file = FILES[variant] || FILES.white;
  const url = src || `${basePath}/${file}`;
  const wordColor = variant === 'white' ? 'var(--dp-white)' : variant === 'black' ? 'var(--dp-ink)' : 'var(--text-primary)';
  return /*#__PURE__*/React.createElement("span", _extends({}, rest, {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: size * 0.32,
      ...style
    }
  }), /*#__PURE__*/React.createElement("img", {
    src: url,
    alt: "dP monogram",
    width: size,
    height: size,
    style: {
      display: 'block',
      objectFit: 'contain'
    }
  }), showWordmark && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      flexDirection: 'column',
      lineHeight: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 'var(--fw-bold)',
      fontSize: size * 0.42,
      letterSpacing: 'var(--ls-tight)',
      color: wordColor
    }
  }, wordmark)));
}
Object.assign(__ds_scope, { Logo });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/Logo.jsx", error: String((e && e.message) || e) }); }

// components/core/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONES = {
  teal: ['var(--dp-teal)', 'var(--dp-teal-100)', 'var(--dp-teal-600)'],
  pink: ['var(--dp-pink)', 'var(--dp-pink-100)', 'var(--dp-pink-600)'],
  gold: ['var(--dp-gold)', '#fff0d6', 'var(--dp-gold-600)'],
  coral: ['var(--dp-coral)', '#ffe3db', 'var(--dp-coral-600)'],
  purple: ['var(--dp-purple)', 'var(--dp-purple-100)', 'var(--dp-purple-600)'],
  neutral: ['var(--dp-gray)', 'var(--dp-mist)', 'var(--dp-ink)']
};

/** dP Badge — small status/label pill. solid | soft | outline. */
function Badge({
  children,
  tone = 'teal',
  variant = 'soft',
  style = {},
  ...rest
}) {
  const [base, soft, deep] = TONES[tone] || TONES.teal;
  const variants = {
    solid: {
      background: base,
      color: tone === 'gold' || tone === 'teal' ? 'var(--dp-ink)' : 'var(--dp-white)',
      border: '1px solid transparent'
    },
    soft: {
      background: `color-mix(in srgb, ${base} 18%, transparent)`,
      color: base,
      border: `1px solid color-mix(in srgb, ${base} 32%, transparent)`
    },
    outline: {
      background: 'transparent',
      color: base,
      border: `1px solid ${base}`
    }
  };
  return /*#__PURE__*/React.createElement("span", _extends({}, rest, {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 5,
      padding: '3px 10px',
      borderRadius: 'var(--radius-pill)',
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--fs-xs)',
      fontWeight: 'var(--fw-medium)',
      letterSpacing: 'var(--ls-wide)',
      lineHeight: 1.4,
      whiteSpace: 'nowrap',
      ...(variants[variant] || variants.soft),
      ...style
    }
  }), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * dP Button — primary action element.
 * Variants: primary (teal), gradient (warm signature), secondary (outline), ghost.
 */
function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  fullWidth = false,
  iconLeft = null,
  iconRight = null,
  as = 'button',
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const [active, setActive] = React.useState(false);
  const sizes = {
    sm: {
      padding: '0 14px',
      height: 34,
      fontSize: 'var(--fs-sm)',
      gap: 6
    },
    md: {
      padding: '0 20px',
      height: 44,
      fontSize: 'var(--fs-base)',
      gap: 8
    },
    lg: {
      padding: '0 28px',
      height: 54,
      fontSize: 'var(--fs-md)',
      gap: 10
    }
  };
  const s = sizes[size] || sizes.md;
  const base = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: s.gap,
    height: s.height,
    padding: s.padding,
    width: fullWidth ? '100%' : 'auto',
    fontFamily: 'var(--font-display)',
    fontWeight: 'var(--fw-semibold)',
    fontSize: s.fontSize,
    letterSpacing: 'var(--ls-tight)',
    lineHeight: 1,
    borderRadius: 'var(--radius-pill)',
    border: 'var(--border-width) solid transparent',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.45 : 1,
    textDecoration: 'none',
    whiteSpace: 'nowrap',
    userSelect: 'none',
    transition: 'transform var(--dur-fast) var(--ease-standard), background var(--dur-base) var(--ease-standard), box-shadow var(--dur-base) var(--ease-standard), border-color var(--dur-base) var(--ease-standard)',
    transform: active && !disabled ? 'scale(0.97)' : 'scale(1)'
  };
  const variants = {
    primary: {
      background: hover ? 'var(--accent-hover)' : 'var(--accent)',
      color: 'var(--accent-contrast)',
      boxShadow: hover && !disabled ? 'var(--shadow-glow-teal)' : 'var(--shadow-sm)'
    },
    gradient: {
      background: 'var(--dp-gradient-warm)',
      color: 'var(--dp-white)',
      backgroundSize: '160% 160%',
      backgroundPosition: hover ? '100% 50%' : '0% 50%',
      boxShadow: hover && !disabled ? 'var(--shadow-glow-pink)' : 'var(--shadow-md)'
    },
    secondary: {
      background: hover ? 'color-mix(in srgb, var(--accent) 12%, transparent)' : 'transparent',
      color: 'var(--text-primary)',
      borderColor: hover ? 'var(--accent)' : 'var(--border-strong)'
    },
    ghost: {
      background: hover ? 'color-mix(in srgb, var(--text-primary) 8%, transparent)' : 'transparent',
      color: 'var(--text-secondary)'
    }
  };
  const Tag = as;
  return /*#__PURE__*/React.createElement(Tag, _extends({}, rest, {
    disabled: as === 'button' ? disabled : undefined,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => {
      setHover(false);
      setActive(false);
    },
    onMouseDown: () => setActive(true),
    onMouseUp: () => setActive(false),
    style: {
      ...base,
      ...(variants[variant] || variants.primary),
      ...style
    }
  }), iconLeft, children, iconRight);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** dP Card — content surface. Variants: surface (default), outline, gradient-edge. */
function Card({
  children,
  variant = 'surface',
  interactive = false,
  padding = 'var(--space-5)',
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const variants = {
    surface: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-subtle)',
      boxShadow: interactive && hover ? 'var(--shadow-lg)' : 'var(--shadow-md)'
    },
    outline: {
      background: 'transparent',
      border: '1px solid var(--border-strong)',
      boxShadow: 'none'
    },
    gradientEdge: {
      background: 'var(--bg-surface)',
      border: '1px solid transparent',
      backgroundImage: 'linear-gradient(var(--bg-surface), var(--bg-surface)), var(--dp-gradient-spectrum)',
      backgroundOrigin: 'border-box',
      backgroundClip: 'padding-box, border-box',
      boxShadow: interactive && hover ? 'var(--shadow-lg)' : 'var(--shadow-md)'
    }
  };
  return /*#__PURE__*/React.createElement("div", _extends({}, rest, {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      borderRadius: 'var(--radius-lg)',
      padding,
      color: 'var(--text-primary)',
      fontFamily: 'var(--font-body)',
      transform: interactive && hover ? 'translateY(-3px)' : 'translateY(0)',
      transition: 'transform var(--dur-base) var(--ease-out), box-shadow var(--dur-base) var(--ease-out)',
      cursor: interactive ? 'pointer' : 'default',
      ...(variants[variant] || variants.surface),
      ...style
    }
  }), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/GradientText.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const GRADIENTS = {
  warm: 'var(--dp-gradient-warm)',
  spectrum: 'var(--dp-gradient-spectrum)',
  cool: 'var(--dp-gradient-cool)'
};

/** dP GradientText — signature gradient-filled display text for headlines. */
function GradientText({
  children,
  gradient = 'warm',
  as = 'span',
  style = {},
  ...rest
}) {
  const Tag = as;
  return /*#__PURE__*/React.createElement(Tag, _extends({}, rest, {
    style: {
      backgroundImage: GRADIENTS[gradient] || GRADIENTS.warm,
      WebkitBackgroundClip: 'text',
      backgroundClip: 'text',
      WebkitTextFillColor: 'transparent',
      color: 'transparent',
      fontFamily: 'var(--font-display)',
      fontWeight: 'var(--fw-bold)',
      letterSpacing: 'var(--ls-tight)',
      display: 'inline-block',
      ...style
    }
  }), children);
}
Object.assign(__ds_scope, { GradientText });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/GradientText.jsx", error: String((e && e.message) || e) }); }

// components/core/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** dP IconButton — square/circular icon-only action. */
function IconButton({
  children,
  label,
  variant = 'ghost',
  size = 'md',
  shape = 'circle',
  disabled = false,
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const [active, setActive] = React.useState(false);
  const dims = {
    sm: 34,
    md: 44,
    lg: 54
  }[size] || 44;
  const variants = {
    solid: {
      background: hover ? 'var(--accent-hover)' : 'var(--accent)',
      color: 'var(--accent-contrast)'
    },
    outline: {
      background: hover ? 'color-mix(in srgb, var(--accent) 12%, transparent)' : 'transparent',
      color: 'var(--text-primary)',
      border: 'var(--border-width) solid var(--border-strong)'
    },
    ghost: {
      background: hover ? 'color-mix(in srgb, var(--text-primary) 10%, transparent)' : 'transparent',
      color: 'var(--text-secondary)'
    }
  };
  return /*#__PURE__*/React.createElement("button", _extends({}, rest, {
    "aria-label": label,
    disabled: disabled,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => {
      setHover(false);
      setActive(false);
    },
    onMouseDown: () => setActive(true),
    onMouseUp: () => setActive(false),
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      width: dims,
      height: dims,
      borderRadius: shape === 'circle' ? 'var(--radius-circle)' : 'var(--radius-md)',
      border: '1px solid transparent',
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.45 : 1,
      color: 'var(--text-secondary)',
      transform: active && !disabled ? 'scale(0.92)' : 'scale(1)',
      transition: 'transform var(--dur-fast) var(--ease-standard), background var(--dur-base) var(--ease-standard)',
      ...(variants[variant] || variants.ghost),
      ...style
    }
  }), children);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/core/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** dP Input — text field with optional label, leading icon, and hint/error. */
function Input({
  label,
  hint,
  error,
  iconLeft,
  id,
  style = {},
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const inputId = id || React.useId();
  const borderColor = error ? 'var(--dp-pink)' : focus ? 'var(--accent)' : 'var(--border-subtle)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      width: '100%',
      fontFamily: 'var(--font-body)'
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: inputId,
    style: {
      fontSize: 'var(--fs-sm)',
      fontWeight: 'var(--fw-semibold)',
      color: 'var(--text-secondary)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      height: 46,
      padding: '0 14px',
      background: 'var(--bg-raised)',
      border: `1px solid ${borderColor}`,
      borderRadius: 'var(--radius-md)',
      boxShadow: focus ? '0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent)' : 'none',
      transition: 'border-color var(--dur-base) var(--ease-standard), box-shadow var(--dur-base) var(--ease-standard)'
    }
  }, iconLeft && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      color: 'var(--text-muted)'
    }
  }, iconLeft), /*#__PURE__*/React.createElement("input", _extends({
    id: inputId
  }, rest, {
    onFocus: e => {
      setFocus(true);
      rest.onFocus?.(e);
    },
    onBlur: e => {
      setFocus(false);
      rest.onBlur?.(e);
    },
    style: {
      flex: 1,
      minWidth: 0,
      background: 'transparent',
      border: 'none',
      outline: 'none',
      color: 'var(--text-primary)',
      fontFamily: 'var(--font-body)',
      fontSize: 'var(--fs-base)',
      ...style
    }
  }))), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--fs-xs)',
      color: error ? 'var(--dp-pink)' : 'var(--text-muted)'
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Input.jsx", error: String((e && e.message) || e) }); }

// components/core/Switch.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** dP Switch — on/off toggle. */
function Switch({
  checked = false,
  onChange,
  disabled = false,
  label,
  id,
  style = {},
  ...rest
}) {
  const switchId = id || React.useId();
  const track = {
    width: 46,
    height: 26,
    borderRadius: 'var(--radius-pill)',
    padding: 3,
    background: checked ? 'var(--accent)' : 'var(--border-strong)',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: checked ? 'flex-end' : 'flex-start',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.45 : 1,
    transition: 'background var(--dur-base) var(--ease-standard)',
    border: 'none'
  };
  const knob = {
    width: 20,
    height: 20,
    borderRadius: 'var(--radius-circle)',
    background: 'var(--dp-white)',
    boxShadow: 'var(--shadow-sm)',
    transition: 'transform var(--dur-base) var(--ease-spring)'
  };
  const toggle = () => {
    if (!disabled) onChange?.(!checked);
  };
  const control = /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    role: "switch",
    "aria-checked": checked,
    id: switchId,
    disabled: disabled,
    onClick: toggle,
    style: {
      ...track,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: knob
  }));
  if (!label) return control;
  return /*#__PURE__*/React.createElement("label", {
    htmlFor: switchId,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 10,
      cursor: disabled ? 'not-allowed' : 'pointer',
      fontFamily: 'var(--font-body)',
      fontSize: 'var(--fs-sm)',
      color: 'var(--text-secondary)'
    }
  }, control, label);
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Switch.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/About.jsx
try { (() => {
// dP website — about section
function About({
  basePath
}) {
  const {
    Card,
    Badge,
    GradientText,
    Logo
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  const stack = ['React', 'TypeScript', 'Node', 'Next.js', 'Figma', 'Motion', 'WebGL', 'Rust'];
  return /*#__PURE__*/React.createElement("section", {
    style: {
      padding: '72px 40px',
      maxWidth: 1000,
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1.4fr',
      gap: 40,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    variant: "gradientEdge",
    padding: "var(--space-7)",
    style: {
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      gap: 18,
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    variant: "gradient",
    size: 140,
    basePath: basePath
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 22,
      color: 'var(--dp-white)'
    }
  }, "David Paternina"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      color: 'var(--dp-teal)',
      letterSpacing: '0.08em',
      marginTop: 4
    }
  }, "DEVELOPER \xB7 CREATOR"))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      letterSpacing: '0.14em',
      color: 'var(--dp-teal)',
      textTransform: 'uppercase'
    }
  }, "About"), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: '8px 0 16px',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 40,
      letterSpacing: '-0.02em',
      color: 'var(--dp-white)'
    }
  }, "Design-minded, ", /*#__PURE__*/React.createElement(GradientText, {
    gradient: "warm"
  }, "ship-focused"), "."), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '0 0 14px',
      color: 'var(--text-secondary)',
      fontSize: 17,
      lineHeight: 1.65
    }
  }, "I've spent the last decade building products that feel effortless to use. I care about the details \u2014 motion, spacing, the exact right shade of teal \u2014 and I ship."), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '0 0 22px',
      color: 'var(--text-secondary)',
      fontSize: 17,
      lineHeight: 1.65
    }
  }, "When I'm not building, I'm live-coding on stream, breaking down how real products get made, and helping folks level up."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      flexWrap: 'wrap'
    }
  }, stack.map(s => /*#__PURE__*/React.createElement(Badge, {
    key: s,
    tone: "neutral",
    variant: "outline"
  }, s))))));
}
window.About = About;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/About.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Contact.jsx
try { (() => {
// dP website — contact form (fake submit) + footer
function Contact() {
  const {
    Button,
    Input,
    Card,
    GradientText,
    IconButton
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  const [sent, setSent] = React.useState(false);
  return /*#__PURE__*/React.createElement("section", {
    style: {
      padding: '72px 40px 40px',
      maxWidth: 640,
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      marginBottom: 30
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      letterSpacing: '0.14em',
      color: 'var(--dp-teal)',
      textTransform: 'uppercase'
    }
  }, "Contact"), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: '8px 0 0',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 44,
      letterSpacing: '-0.02em',
      color: 'var(--dp-white)'
    }
  }, "Let's ", /*#__PURE__*/React.createElement(GradientText, {
    gradient: "warm"
  }, "build"), " something")), /*#__PURE__*/React.createElement(Card, {
    variant: "surface",
    padding: "var(--space-6)"
  }, sent ? /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      padding: '30px 0',
      color: 'var(--dp-white)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--dp-teal)',
      display: 'flex',
      justifyContent: 'center',
      marginBottom: 12
    }
  }, I.sparkles(34)), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 22
    }
  }, "Message sent!"), /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-secondary)'
    }
  }, "I'll reply within a day. (Demo \u2014 nothing was sent.)"), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    onClick: () => setSent(false)
  }, "Send another")) : /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(Input, {
    label: "Name",
    placeholder: "Ada Lovelace"
  }), /*#__PURE__*/React.createElement(Input, {
    label: "Email",
    placeholder: "you@domain.com",
    iconLeft: I.mail(16),
    hint: "I reply within a day."
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6
    }
  }, /*#__PURE__*/React.createElement("label", {
    style: {
      fontSize: 14,
      fontWeight: 600,
      color: 'var(--text-secondary)',
      fontFamily: 'var(--font-body)'
    }
  }, "Message"), /*#__PURE__*/React.createElement("textarea", {
    rows: "4",
    placeholder: "Tell me about the project\u2026",
    style: {
      background: 'var(--bg-raised)',
      border: '1px solid var(--border-subtle)',
      borderRadius: 'var(--radius-md)',
      padding: '12px 14px',
      color: 'var(--text-primary)',
      fontFamily: 'var(--font-body)',
      fontSize: 16,
      resize: 'vertical',
      outline: 'none'
    }
  })), /*#__PURE__*/React.createElement(Button, {
    variant: "gradient",
    size: "lg",
    fullWidth: true,
    iconRight: I.send(18),
    onClick: () => setSent(true)
  }, "Send message"))));
}
function Footer({
  basePath
}) {
  const {
    Logo,
    IconButton
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      borderTop: '1px solid var(--border-subtle)',
      padding: '30px 40px',
      display: 'flex',
      alignItems: 'center',
      gap: 20,
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    variant: "white",
    size: 30,
    basePath: basePath,
    showWordmark: true
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      color: 'var(--text-muted)',
      marginLeft: 8
    }
  }, "\xA9 2026 \xB7 dpaternina.com"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      marginLeft: 'auto'
    }
  }, /*#__PURE__*/React.createElement(IconButton, {
    label: "GitHub",
    variant: "ghost"
  }, I.github(20)), /*#__PURE__*/React.createElement(IconButton, {
    label: "Twitch",
    variant: "ghost"
  }, I.twitch(20)), /*#__PURE__*/React.createElement(IconButton, {
    label: "X",
    variant: "ghost"
  }, I.x(20)), /*#__PURE__*/React.createElement(IconButton, {
    label: "Email",
    variant: "ghost"
  }, I.mail(20))));
}
window.Contact = Contact;
window.Footer = Footer;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Contact.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Hero.jsx
try { (() => {
// dP website — hero
function Hero({
  setRoute,
  live,
  basePath
}) {
  const {
    Button,
    Badge,
    GradientText
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  return /*#__PURE__*/React.createElement("section", {
    style: {
      position: 'relative',
      padding: '96px 40px 80px',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: -120,
      right: -80,
      width: 460,
      height: 460,
      borderRadius: '50%',
      background: 'radial-gradient(circle, color-mix(in srgb, var(--dp-teal) 32%, transparent), transparent 70%)',
      filter: 'blur(30px)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      bottom: -160,
      left: -60,
      width: 420,
      height: 420,
      borderRadius: '50%',
      background: 'radial-gradient(circle, color-mix(in srgb, var(--dp-pink) 26%, transparent), transparent 70%)',
      filter: 'blur(30px)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      maxWidth: 900,
      margin: '0 auto',
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'center',
      marginBottom: 22
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    tone: live ? 'pink' : 'teal',
    variant: "soft"
  }, live ? '● NOW STREAMING' : 'AVAILABLE FOR WORK')), /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 'clamp(48px, 7vw, 84px)',
      lineHeight: 1.02,
      letterSpacing: '-0.03em',
      color: 'var(--dp-white)'
    }
  }, "I build ", /*#__PURE__*/React.createElement(GradientText, {
    gradient: "warm"
  }, "delightful"), /*#__PURE__*/React.createElement("br", null), "things for the web."), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '24px auto 0',
      maxWidth: 560,
      fontSize: 19,
      lineHeight: 1.6,
      color: 'var(--text-secondary)'
    }
  }, "Developer, creator, and streamer. I design and ship products, tools, and live-coding sessions \u2014 always aiming for bold, modern, and clean."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 14,
      justifyContent: 'center',
      marginTop: 34
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "gradient",
    size: "lg",
    iconRight: I.arrowRight(18),
    onClick: () => setRoute('work')
  }, "See the work"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    size: "lg",
    iconLeft: I.play(16),
    onClick: () => setRoute('stream')
  }, "Watch the stream")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 26,
      justifyContent: 'center',
      marginTop: 56,
      color: 'var(--text-muted)'
    }
  }, [['12+', 'years shipping'], ['40+', 'projects'], ['800+', 'streams']].map(([n, l]) => /*#__PURE__*/React.createElement("div", {
    key: l,
    style: {
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 30,
      color: 'var(--dp-white)'
    }
  }, n), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 11,
      letterSpacing: '0.1em',
      textTransform: 'uppercase'
    }
  }, l))))));
}
window.Hero = Hero;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Hero.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Nav.jsx
try { (() => {
// dP website — sticky top nav
function Nav({
  route,
  setRoute,
  live,
  basePath
}) {
  const {
    Logo,
    Button,
    Badge
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  const links = ['work', 'about', 'stream', 'contact'];
  return /*#__PURE__*/React.createElement("header", {
    style: {
      position: 'sticky',
      top: 0,
      zIndex: 20,
      display: 'flex',
      alignItems: 'center',
      gap: 24,
      padding: '16px 40px',
      background: 'color-mix(in srgb, var(--dp-ink) 82%, transparent)',
      backdropFilter: 'blur(12px)',
      borderBottom: '1px solid var(--border-subtle)'
    }
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => setRoute('home'),
    style: {
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      padding: 0
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    variant: "gradient",
    size: 38,
    basePath: basePath,
    showWordmark: true,
    wordmark: "David Paternina"
  })), /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      gap: 4,
      marginLeft: 'auto'
    }
  }, links.map(l => /*#__PURE__*/React.createElement("button", {
    key: l,
    onClick: () => setRoute(l),
    style: {
      background: route === l ? 'color-mix(in srgb, var(--accent) 14%, transparent)' : 'transparent',
      color: route === l ? 'var(--dp-white)' : 'var(--text-secondary)',
      border: 'none',
      cursor: 'pointer',
      padding: '8px 16px',
      borderRadius: 'var(--radius-pill)',
      fontFamily: 'var(--font-display)',
      fontWeight: 600,
      fontSize: 15,
      textTransform: 'capitalize',
      transition: 'color .2s, background .2s'
    }
  }, l, l === 'stream' && live && /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: 6,
      display: 'inline-block',
      width: 7,
      height: 7,
      borderRadius: '50%',
      background: 'var(--dp-pink)',
      boxShadow: '0 0 8px var(--dp-pink)'
    }
  })))), /*#__PURE__*/React.createElement(Button, {
    variant: "gradient",
    size: "sm",
    iconRight: I.arrowRight(16),
    onClick: () => setRoute('contact')
  }, "Get in touch"));
}
window.Nav = Nav;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Nav.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Stream.jsx
try { (() => {
// dP website — stream (live) panel
function Stream({
  live,
  setLive
}) {
  const {
    Button,
    Badge,
    Card,
    Switch,
    GradientText
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  return /*#__PURE__*/React.createElement("section", {
    style: {
      padding: '72px 40px',
      maxWidth: 1000,
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      justifyContent: 'space-between',
      marginBottom: 24
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      letterSpacing: '0.14em',
      color: 'var(--dp-pink)',
      textTransform: 'uppercase'
    }
  }, "Live coding"), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: '8px 0 0',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 44,
      letterSpacing: '-0.02em',
      color: 'var(--dp-white)'
    }
  }, "On ", /*#__PURE__*/React.createElement(GradientText, {
    gradient: "warm"
  }, "stream"))), /*#__PURE__*/React.createElement(Switch, {
    checked: live,
    onChange: setLive,
    label: "Simulate live"
  })), /*#__PURE__*/React.createElement(Card, {
    variant: "surface",
    padding: "0",
    style: {
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      aspectRatio: '16/7',
      background: 'var(--dp-gradient-cool)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: 'radial-gradient(circle at 50% 50%, transparent, rgba(0,0,0,0.55))'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      gap: 14,
      color: 'var(--dp-white)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 74,
      height: 74,
      borderRadius: '50%',
      background: 'rgba(0,0,0,0.35)',
      border: '2px solid rgba(255,255,255,0.7)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, I.play(30)), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 22
    }
  }, live ? 'Building the dP design system' : 'Stream offline — back Thursday 7pm')), live && /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 16,
      left: 16
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    tone: "pink",
    variant: "solid"
  }, "\u25CF LIVE \xB7 1.2K"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 14,
      padding: '20px 24px',
      borderTop: '1px solid var(--border-subtle)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--dp-purple)'
    }
  }, I.twitch(24)), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 600,
      color: 'var(--dp-white)'
    }
  }, "twitch.tv/dpaternina"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      color: 'var(--text-muted)'
    }
  }, "Live-coding \xB7 design systems \xB7 Q&A")), /*#__PURE__*/React.createElement(Button, {
    variant: live ? 'gradient' : 'secondary',
    iconLeft: I.twitch(16)
  }, live ? 'Watch now' : 'Follow'))));
}
window.Stream = Stream;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Stream.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Work.jsx
try { (() => {
// dP website — selected work grid with tag filter
function Work() {
  const {
    Card,
    Badge,
    GradientText
  } = window.DPDanielPaterninaDesignSystem_0f0b50;
  const I = window.DPIcons;
  const projects = [{
    t: 'Streamdeck OS',
    d: 'A browser-based control surface for live streamers.',
    tags: ['React', 'WebRTC'],
    tone: 'teal',
    feat: true
  }, {
    t: 'Palette',
    d: 'Color-system generator with instant token export.',
    tags: ['Design', 'TS'],
    tone: 'pink'
  }, {
    t: 'Loop',
    d: 'Habit tracker with a delightfully minimal UI.',
    tags: ['Mobile'],
    tone: 'gold'
  }, {
    t: 'Coderef',
    d: 'Searchable snippet vault for my streams.',
    tags: ['Node'],
    tone: 'purple'
  }, {
    t: 'Overlay Kit',
    d: 'Animated stream overlays & transitions.',
    tags: ['Motion'],
    tone: 'coral'
  }, {
    t: 'dpaternina.com',
    d: 'This very site — rebuilt on the new system.',
    tags: ['Next'],
    tone: 'teal'
  }];
  const [filter, setFilter] = React.useState('All');
  const allTags = ['All', 'React', 'Design', 'Mobile', 'Motion'];
  const shown = filter === 'All' ? projects : projects.filter(p => p.tags.includes(filter));
  return /*#__PURE__*/React.createElement("section", {
    style: {
      padding: '72px 40px',
      maxWidth: 1120,
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      justifyContent: 'space-between',
      flexWrap: 'wrap',
      gap: 16,
      marginBottom: 30
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      letterSpacing: '0.14em',
      color: 'var(--dp-teal)',
      textTransform: 'uppercase'
    }
  }, "Selected work"), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: '8px 0 0',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 44,
      letterSpacing: '-0.02em',
      color: 'var(--dp-white)'
    }
  }, "Things I've ", /*#__PURE__*/React.createElement(GradientText, {
    gradient: "cool"
  }, "shipped"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      flexWrap: 'wrap'
    }
  }, allTags.map(t => /*#__PURE__*/React.createElement("button", {
    key: t,
    onClick: () => setFilter(t),
    style: {
      padding: '7px 14px',
      borderRadius: 'var(--radius-pill)',
      cursor: 'pointer',
      fontFamily: 'var(--font-mono)',
      fontSize: 12,
      letterSpacing: '0.04em',
      background: filter === t ? 'var(--accent)' : 'transparent',
      color: filter === t ? 'var(--dp-ink)' : 'var(--text-secondary)',
      border: `1px solid ${filter === t ? 'var(--accent)' : 'var(--border-strong)'}`,
      transition: 'all .2s'
    }
  }, t)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
      gap: 18
    }
  }, shown.map(p => /*#__PURE__*/React.createElement(Card, {
    key: p.t,
    variant: p.feat ? 'gradientEdge' : 'surface',
    interactive: true,
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12,
      minHeight: 170
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: `var(--dp-${p.tone})`
    }
  }, I.layers(22)), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-muted)'
    }
  }, I.arrowUpRight(18))), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 22,
      color: 'var(--dp-white)'
    }
  }, p.t), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      color: 'var(--text-secondary)',
      fontSize: 15,
      lineHeight: 1.5,
      flex: 1
    }
  }, p.d), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8
    }
  }, p.tags.map(t => /*#__PURE__*/React.createElement(Badge, {
    key: t,
    tone: p.tone,
    variant: "soft"
  }, t)))))));
}
window.Work = Work;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Work.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/app.jsx
try { (() => {
// dP website — app shell (routing between sections)
function App({
  basePath
}) {
  const [route, setRoute] = React.useState('home');
  const [live, setLive] = React.useState(false);
  const body = (() => {
    switch (route) {
      case 'work':
        return /*#__PURE__*/React.createElement(Work, null);
      case 'about':
        return /*#__PURE__*/React.createElement(About, {
          basePath: basePath
        });
      case 'stream':
        return /*#__PURE__*/React.createElement(Stream, {
          live: live,
          setLive: setLive
        });
      case 'contact':
        return /*#__PURE__*/React.createElement(Contact, null);
      default:
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Hero, {
          setRoute: setRoute,
          live: live,
          basePath: basePath
        }), /*#__PURE__*/React.createElement(Work, null));
    }
  })();
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100%',
      background: 'var(--dp-ink)'
    }
  }, /*#__PURE__*/React.createElement(Nav, {
    route: route,
    setRoute: setRoute,
    live: live,
    basePath: basePath
  }), body, /*#__PURE__*/React.createElement(Footer, {
    basePath: basePath
  }));
}
window.DPWebsiteApp = App;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/app.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/icons.jsx
try { (() => {
// dP UI kit — Lucide-style inline icons (2px rounded stroke, currentColor).
const _svg = (paths, size = 20) => /*#__PURE__*/React.createElement("svg", {
  width: size,
  height: size,
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  strokeLinejoin: "round",
  style: {
    display: 'block'
  }
}, paths);
const Icons = {
  arrowRight: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M5 12h14"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M13 6l6 6-6 6"
  })), p),
  arrowUpRight: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M7 17 17 7"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M8 7h9v9"
  })), p),
  github: p => _svg(/*#__PURE__*/React.createElement("path", {
    d: "M9 19c-5 1.5-5-2.5-7-3m14 6v-3.9a3.4 3.4 0 0 0-.9-2.6c3-.3 6.2-1.5 6.2-6.7A5.2 5.2 0 0 0 20 4.8a4.9 4.9 0 0 0-.1-3.6s-1.1-.3-3.6 1.4a12.3 12.3 0 0 0-6.6 0C7.2.9 6.1 1.2 6.1 1.2A4.9 4.9 0 0 0 6 4.8a5.2 5.2 0 0 0-1.4 3.6c0 5.2 3.2 6.4 6.2 6.7a3.4 3.4 0 0 0-.9 2.6V22"
  }), p),
  twitch: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M21 2H3v16h5v4l4-4h5l4-4V2Z"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M16 7v5M11 7v5"
  })), p),
  x: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M4 4l16 16M20 4 4 20"
  })), p),
  mail: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "4",
    width: "20",
    height: "16",
    rx: "2"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m2 7 10 6 10-6"
  })), p),
  play: p => _svg(/*#__PURE__*/React.createElement("path", {
    d: "M6 4l14 8-14 8V4Z"
  }), p),
  code: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "m16 18 6-6-6-6"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m8 6-6 6 6 6"
  })), p),
  sparkles: p => _svg(/*#__PURE__*/React.createElement("path", {
    d: "M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"
  }), p),
  video: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "6",
    width: "14",
    height: "12",
    rx: "2"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m22 8-6 4 6 4V8Z"
  })), p),
  layers: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "m12 2 9 5-9 5-9-5 9-5Z"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m3 12 9 5 9-5"
  })), p),
  send: p => _svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M22 2 11 13"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M22 2 15 22l-4-9-9-4 20-7Z"
  })), p)
};
window.DPIcons = Icons;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/icons.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Logo = __ds_scope.Logo;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.GradientText = __ds_scope.GradientText;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Switch = __ds_scope.Switch;

})();

