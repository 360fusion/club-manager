// The website builder's theme is two independent choices, combined into one string stored in
// `settings.website_theme`:
//   - a LAYOUT (structure, typography, shape — see SITE_LAYOUTS / LAYOUT_TABLE)
//   - a COLOR SCHEME (just the brand hue — see SITE_COLOR_SCHEMES / COLOR_SCHEME_TABLE)
// The stored value is either the legacy id `masonic` (a fixed, self-contained design that ignores
// colour schemes), or `${layoutId}:${colorSchemeId}`, e.g. `banded:navy_gold`.
//
// A layout's Tailwind classes reference the colour scheme only through `var(--cm-*)` custom
// properties (accent, accent-bright, accent-deep, tint, hero-to) — never a literal hue — so any
// layout can be painted with any colour scheme. `themeClasses()` resolves the stored
// string into the merged class table plus a `cssVars` object that must be bound with `:style` on
// the same element `theme.wrapper` is applied to (Public/Site.vue, Admin/PageList.vue), so the
// custom properties cascade down to every themed child.

export const SITE_LAYOUTS = [
    {
        id: 'banded',
        name: 'Heritage Banded',
        badge: 'Full-Width Banded Layout',
        description: 'A distinguished institutional layout with full-width alternating bands, serif display headings, and a dedicated dark hero band — closer to a heritage society\'s site than a boxed template.',
        features: ['Full-bleed alternating bands', 'Serif display headings', 'Dedicated dark hero band', 'Neutral slate chrome'],
    },
    {
        id: 'classic',
        name: 'Classic Banner',
        badge: 'Solid Header & Footer',
        description: 'A clean, traditional layout: a solid brand-coloured header and footer around white content, a soft tinted title band, serif headings and neat rounded cards.',
        features: ['Solid brand-coloured header and footer', 'Soft tinted title band', 'Clean white content bands', 'Serif headings'],
    },
    {
        id: 'editorial',
        name: 'Editorial Broadsheet',
        badge: 'Sharp, Asymmetric Layout',
        description: 'A modern, magazine-style layout: left-aligned oversized headlines, hairline borders, sharp corners and generous whitespace instead of boxed, rounded cards.',
        features: ['Left-aligned asymmetric hero', 'Sharp, hairline-bordered cards', 'No rounded corners', 'Warm stone chrome'],
    },
    {
        id: 'bold',
        name: 'Vivid Bold',
        badge: 'High-Energy, Rounded',
        description: 'A loud, energetic layout for a youthful club: a saturated gradient hero, thick borders, and oversized pill-shaped buttons and cards.',
        features: ['Saturated gradient hero', 'Thick, colourful card borders', 'Oversized rounded shapes', 'Bold pill navigation'],
    },
    {
        id: 'traditional',
        name: 'Traditional Lodge',
        badge: 'Navy, Gold & Serif',
        description: 'A formal lodge or society look: white and cream bands with navy sections, Playfair serif headings over clean Inter text, small letter-spaced gold labels, gold dividers and square-cornered gold buttons. Pairs best with the Navy, Gold & Cream colours.',
        features: ['Navy, cream and white bands', 'Serif headings with gold dividers', 'Letter-spaced uppercase menu and buttons', 'Navy multi-column footer'],
    },
];

// The legacy, self-contained design The Lodge of Fraternity uses — fixed navy & gold, not part of
// the layout/colour-scheme matrix (picking it hides the colour scheme picker in the builder).
export const LEGACY_THEME = {
    id: 'masonic',
    name: 'Royal Masonic Dark & Gold',
    badge: 'Regal Dark (fixed colours)',
    description: 'Traditional Masonic & fraternal lodge aesthetic featuring deep royal navy, rich gold foil borders, crest embellishments, and classic serif typography. Fixed colours — not paired with a colour scheme.',
    palette: ['#0c1938', '#f59e0b', '#d97706', '#1e1b4b'],
};

// The 5 colour schemes: just a brand hue, applied through CSS custom properties so every layout
// can use every scheme. `swatch` is what the gallery shows; the rest feed `cssVars`.
export const SITE_COLOR_SCHEMES = [
    {
        id: 'navy_gold',
        name: 'Navy & Gold',
        swatch: ['#ffffff', '#0f172a', '#d97706', '#92400e'],
        vars: { accent: '#d97706', accentBright: '#fbbf24', accentDeep: '#92400e', tint: '#fffbeb', heroTo: '#78350f', primary: '#0f172a', primarySoft: '#1e293b' },
    },
    {
        id: 'rust_stone',
        name: 'Rust & Stone',
        swatch: ['#fafaf9', '#1c1917', '#c2410c', '#7c2d12'],
        vars: { accent: '#c2410c', accentBright: '#fb923c', accentDeep: '#7c2d12', tint: '#fff7ed', heroTo: '#78350f', primary: '#1c1917', primarySoft: '#44403c' },
    },
    {
        id: 'crimson_rose',
        name: 'Crimson & Rose',
        swatch: ['#ffffff', '#960018', '#f4b9c7', '#fbeaee'],
        vars: { accent: '#960018', accentBright: '#f4b9c7', accentDeep: '#7a0013', tint: '#fbeaee', heroTo: '#7a0013', primary: '#4a0d18', primarySoft: '#7a0013' },
    },
    {
        id: 'violet_coral',
        name: 'Violet & Coral',
        swatch: ['#ffffff', '#2e1065', '#7c3aed', '#c084fc'],
        vars: { accent: '#7c3aed', accentBright: '#e879f9', accentDeep: '#5b21b6', tint: '#f5f3ff', heroTo: '#fb923c', primary: '#2e1065', primarySoft: '#5b21b6' },
    },
    {
        id: 'forest_moss',
        name: 'Forest & Moss',
        swatch: ['#ffffff', '#052e16', '#15803d', '#14532d'],
        vars: { accent: '#15803d', accentBright: '#4ade80', accentDeep: '#14532d', tint: '#f0fdf4', heroTo: '#0d9488', primary: '#052e16', primarySoft: '#14532d' },
    },
    {
        id: 'ocean_teal',
        name: 'Ocean & Teal',
        swatch: ['#ffffff', '#083344', '#0e7490', '#164e63'],
        vars: { accent: '#0e7490', accentBright: '#22d3ee', accentDeep: '#164e63', tint: '#ecfeff', heroTo: '#4338ca', primary: '#083344', primarySoft: '#164e63' },
    },
    // The lodge schemes also fix the shades that would otherwise be worked out (`cream`, `ink`, `muted`) so they match
    // the reference design exactly.
    {
        id: 'lodge_navy_gold',
        name: 'Navy, Gold & Cream',
        swatch: ['#f8f6f1', '#1b2a4a', '#c8a961', '#2a3f6a'],
        vars: { accent: '#c8a961', accentBright: '#d9bc7a', accentDeep: '#8c6f2e', tint: '#f8f6f1', heroTo: '#2a3f6a', primary: '#1b2a4a', primarySoft: '#2a3f6a', cream: '#f8f6f1', ink: '#2c2c2c', muted: '#6b6b6b' },
    },
    {
        id: 'lodge_burgundy_gold',
        name: 'Burgundy, Gold & Cream',
        swatch: ['#f8f6f1', '#6b2d3e', '#c8a961', '#8a3d51'],
        vars: { accent: '#c8a961', accentBright: '#d9bc7a', accentDeep: '#8c6f2e', tint: '#f8f6f1', heroTo: '#8a3d51', primary: '#6b2d3e', primarySoft: '#8a3d51', cream: '#f8f6f1', ink: '#2c2c2c', muted: '#6b6b6b' },
    },
];

export const DEFAULT_THEME_KEY = 'editorial:rust_stone';

/** `${layoutId}:${colorSchemeId}` for every valid combination — used to build server-side validation lists. */
export const SITE_THEME_KEYS = [
    LEGACY_THEME.id,
    ...SITE_LAYOUTS.flatMap((layout) => SITE_COLOR_SCHEMES.map((scheme) => `${layout.id}:${scheme.id}`)),
];

// ---- palette maths ----------------------------------------------------------------------------------------------------
// A theme's colours are two choices (a deep "primary" and an "accent"); every other shade is worked out from them so a
// lodge's own colours follow through the whole site: light and dark bands, text, dark mode and buttons.

const HEX = /^#[0-9a-f]{6}$/i;
const toRgb = (hex) => [1, 3, 5].map((at) => parseInt(hex.slice(at, at + 2), 16));
const toHex = (rgb) => `#${rgb.map((c) => Math.round(Math.min(255, Math.max(0, c))).toString(16).padStart(2, '0')).join('')}`;

/** Blends `hex` toward `toward` (`amount` 0 = unchanged, 1 = fully `toward`). */
export const mixColours = (hex, toward, amount) => {
    const a = toRgb(hex);
    const b = toRgb(toward);

    return toHex(a.map((c, i) => c + (b[i] - c) * amount));
};

const channelLuminance = (c) => {
    const v = c / 255;

    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
};
const luminanceOf = (hex) => {
    const [r, g, b] = toRgb(hex);

    return 0.2126 * channelLuminance(r) + 0.7152 * channelLuminance(g) + 0.0722 * channelLuminance(b);
};

/** WCAG contrast ratio between two colours (1 to 21). */
export const contrastRatio = (a, b) => {
    const [hi, lo] = [luminanceOf(a), luminanceOf(b)].sort((x, y) => y - x);

    return (hi + 0.05) / (lo + 0.05);
};

/** Moves `hex` toward `toward` in small steps until it has at least `minimum` contrast against `against`. */
const nudge = (hex, toward, against, minimum) => {
    let colour = hex;

    for (let step = 0; step < 20 && contrastRatio(colour, against) < minimum; step++) {
        colour = mixColours(colour, toward, 0.08);
    }

    return colour;
};

export const isHexColour = (value) => typeof value === 'string' && HEX.test(value);

/** White or `dark`, whichever has the higher contrast on `background`. */
const readableOn = (background, dark) => (contrastRatio('#ffffff', background) >= contrastRatio(dark, background) ? '#ffffff' : dark);

/**
 * Fills in every shade a palette needs. `vars` holds at least an accent and a primary (the deep colour behind dark
 * sections); anything already present is kept, anything missing is derived, and the shades that carry text are
 * nudged until they are readable (4.5:1) on what they sit on.
 */
export function completePalette(vars) {
    const accent = vars.accent;
    const primary = nudge(vars.primary, '#000000', '#ffffff', 8);
    const primarySoft = vars.primarySoft ?? mixColours(primary, '#ffffff', 0.14);
    const night = vars.night ?? mixColours(primary, '#000000', 0.55);

    return {
        accent,
        accentBright: vars.accentBright ?? nudge(mixColours(accent, '#ffffff', 0.25), '#ffffff', primary, 4.5),
        accentDeep: vars.accentDeep ?? nudge(mixColours(accent, '#000000', 0.2), '#000000', '#ffffff', 4.5),
        tint: vars.tint ?? mixColours(accent, '#ffffff', 0.9),
        heroTo: vars.heroTo ?? primarySoft,
        primary,
        primarySoft,
        cream: vars.cream ?? vars.tint ?? mixColours(accent, '#ffffff', 0.92),
        night,
        nightSoft: vars.nightSoft ?? mixColours(primary, '#000000', 0.3),
        ink: vars.ink ?? nudge(mixColours(primary, '#000000', 0.3), '#000000', '#ffffff', 12),
        // Text on an accent-coloured button or bar: white or near-black, whichever reads better on that accent.
        onAccent: readableOn(accent, night),
        onAccentBright: readableOn(vars.accentBright ?? nudge(mixColours(accent, '#ffffff', 0.25), '#ffffff', primary, 4.5), night),
        muted: vars.muted ?? nudge(mixColours('#6b6b6b', primary, 0.25), '#000000', '#ffffff', 4.6),
    };
}

/** The palette for a lodge's own two colours (see settings.theme_colors), or null when they are not both valid. */
export function paletteFromBrand(colours) {
    if (!isHexColour(colours?.primary) || !isHexColour(colours?.accent)) return null;

    return completePalette({ primary: colours.primary.toLowerCase(), accent: colours.accent.toLowerCase() });
}

export const cssVarsFromPalette = (palette) => ({
    '--cm-accent': palette.accent,
    '--cm-accent-bright': palette.accentBright,
    '--cm-accent-deep': palette.accentDeep,
    '--cm-tint': palette.tint,
    '--cm-hero-to': palette.heroTo,
    '--cm-primary': palette.primary,
    '--cm-primary-soft': palette.primarySoft,
    '--cm-cream': palette.cream,
    '--cm-night': palette.night,
    '--cm-night-soft': palette.nightSoft,
    '--cm-ink': palette.ink,
    '--cm-muted': palette.muted,
    '--cm-on-accent': palette.onAccent,
    '--cm-on-accent-bright': palette.onAccentBright,
});

/**
 * A colour scheme a lodge made (`{ id, name, primary, accent }`) in the same shape as the built-in ones, or null when
 * its colours are not valid. `swatch` is what the gallery shows.
 */
export function asColourScheme(custom) {
    if (!custom || !isHexColour(custom.primary) || !isHexColour(custom.accent)) return null;

    const vars = { primary: custom.primary.toLowerCase(), accent: custom.accent.toLowerCase() };
    const palette = completePalette(vars);

    return { id: custom.id, name: custom.name, custom: true, swatch: [palette.cream, palette.primary, palette.accent, palette.primarySoft], vars };
}

/** The full palette of a preset colour scheme (used by the gallery and the brand-colour step too). */
export const paletteForScheme = (scheme) => completePalette(scheme.vars);

const cssVarsFor = (scheme) => cssVarsFromPalette(paletteForScheme(scheme));

/**
 * Resolves a stored `website_theme` value (`masonic`, or `${layoutId}:${colorSchemeId}`) into the
 * merged Tailwind class table BlockRenderer/PublicHeader/PublicFooter consume, plus the `cssVars`
 * that must be bound with `:style` on the wrapper. `container`/`nav` are aliases of the same
 * classes for the editor's boxed-in preview mockup.
 */
export function themeClasses(key, customSchemes = []) {
    if (key === LEGACY_THEME.id) {
        return { ...MASONIC_CLASSES, container: MASONIC_CLASSES.wrapper, nav: MASONIC_CLASSES.header, cssVars: MASONIC_VARS };
    }

    const [layoutId, colorSchemeId] = String(key || '').split(':');
    const layout = LAYOUT_TABLE[layoutId] || LAYOUT_TABLE[DEFAULT_THEME_KEY.split(':')[0]];
    const scheme = SITE_COLOR_SCHEMES.find((s) => s.id === colorSchemeId)
        || asColourScheme((customSchemes || []).find((s) => s.id === colorSchemeId))
        || SITE_COLOR_SCHEMES.find((s) => s.id === DEFAULT_THEME_KEY.split(':')[1]);

    return {
        ...layout,
        container: layout.wrapper,
        nav: layout.header,
        cssVars: {
            ...cssVarsFor(scheme),
            // A layout can bring its own typefaces (a lodge pairing overrides these again in withSiteStyle).
            ...(layout.fonts ? { '--font-sans': layout.fonts.sans, '--font-serif': layout.fonts.serif } : {}),
        },
    };
}

/**
 * The theme as seen by a block sitting on a dark section (navy band, photo, dark custom colour): headings and text
 * turn light and cards become translucent, so every block reads correctly on it without knowing about its background.
 * A light section gets the theme as it is (its own dark-mode variants still apply).
 */
export function toneTheme(theme, tone) {
    if (tone !== 'dark') return theme;

    return {
        ...theme,
        headingText: `${theme.headingFont || ''} text-white`.trim(),
        bodyText: 'text-white/80',
        accentText: 'text-[var(--cm-accent-bright)]',
        cardBg: 'bg-white/[0.06] border border-[var(--cm-accent)]/25 text-white shadow-none hover:bg-white/10 hover:border-[var(--cm-accent)]/60 transition-all duration-300',
        // A layout's own button can be dark (editorial), which would vanish on a dark section.
        heroCta: `bg-[var(--cm-accent-bright)] hover:brightness-110 text-[var(--cm-on-accent-bright)] font-semibold uppercase tracking-[0.1em]`,
        onDark: true,
    };
}

// The legacy theme's fixed colours, so blocks and sections that read var(--cm-*) work on it too.
const MASONIC_VARS = {
    '--cm-accent': '#f59e0b',
    '--cm-accent-bright': '#fbbf24',
    '--cm-accent-deep': '#d97706',
    '--cm-tint': '#0c1938',
    '--cm-hero-to': '#1e1b4b',
    '--cm-primary': '#0c1938',
    '--cm-primary-soft': '#1e1b4b',
    '--cm-cream': '#0c1938',
    '--cm-night': '#070d1f',
    '--cm-night-soft': '#0c1938',
    '--cm-ink': '#fef3c7',
    '--cm-muted': '#cbd5e1',
    '--cm-on-accent': '#0c1938',
    '--cm-on-accent-bright': '#0c1938',
};

const MASONIC_CLASSES = {
    layout: 'boxed',
    bandB: 'bg-blue-950/60',
    wrapper: 'bg-slate-950 text-slate-100 font-serif selection:bg-amber-500 selection:text-slate-950',
    header: 'sticky top-0 z-50 backdrop-blur-xl bg-blue-950/90 border-b border-amber-500/30 shadow-md',
    navActive: 'bg-amber-500/20 text-amber-300 border-amber-500/50 shadow-sm',
    navInactive: 'text-slate-300 hover:text-amber-300 border-transparent',
    heroBg: 'bg-gradient-to-br from-blue-950 via-slate-950 to-blue-950 border-2 border-amber-500/30 shadow-2xl',
    heroPill: 'bg-amber-500/10 border-amber-500/30 text-amber-400 font-sans tracking-widest',
    heroCta: 'bg-gradient-to-r from-amber-600 to-amber-500 text-slate-950 dark:text-white font-black shadow-amber-500/20',
    cardBg: 'bg-blue-950/60 border border-amber-500/25 text-slate-100 shadow-xl',
    headingText: 'text-amber-100',
    bodyText: 'text-slate-300',
    accentText: 'text-amber-400',
    accentBg: 'bg-amber-500',
    footer: 'bg-blue-950 border-t border-amber-500/20 text-slate-400 font-sans',
};

const LODGE_SANS = '"Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
const LODGE_SERIF = '"Playfair Display", Georgia, Cambria, "Times New Roman", Times, serif';

// Layouts reference colour only via var(--cm-*) custom properties, so any of the 5 colour schemes
// can paint any layout. Neutrals (bg/ink/borders), type, shape and structure stay fixed per layout.
const LAYOUT_TABLE = {
    // Full-bleed alternating bands (white / accent-tinted), serif headings, dedicated dark hero band.
    banded: {
        layout: 'banded',
        bandA: 'bg-white dark:bg-[var(--cm-night)]',
        bandB: 'bg-[var(--cm-tint)] dark:bg-[var(--cm-night-soft)]',
        wrapper: 'bg-white dark:bg-[var(--cm-night)] text-[var(--cm-ink)] dark:text-white font-serif selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-white/95 dark:bg-[var(--cm-night)]/95 backdrop-blur-md border-b border-[var(--cm-accent)]/20 shadow-sm',
        navActive: 'bg-[var(--cm-primary)] dark:bg-[var(--cm-accent)]/15 text-white dark:text-[var(--cm-accent-bright)] border-[var(--cm-primary)] dark:border-[var(--cm-accent)]/30 font-sans font-bold',
        navInactive: 'text-[var(--cm-muted)] dark:text-white/75 hover:text-[var(--cm-ink)] dark:hover:text-[var(--cm-accent-bright)] font-sans border-transparent',
        heroBg: 'bg-gradient-to-br from-[var(--cm-night)] via-[var(--cm-primary)] to-[var(--cm-night)] text-white',
        heroPill: 'bg-[var(--cm-accent)]/15 border-[var(--cm-accent)]/30 text-[var(--cm-accent-bright)] font-sans tracking-widest',
        heroCta: 'bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] font-bold',
        cardBg: 'bg-white dark:bg-[var(--cm-night)]/60 border border-[var(--cm-accent)]/25 dark:border-[var(--cm-accent)]/20 text-[var(--cm-ink)] dark:text-white shadow-sm hover:border-[var(--cm-accent)]/50 transition-colors',
        headingText: 'text-[var(--cm-ink)] dark:text-white',
        bodyText: 'text-[var(--cm-muted)] dark:text-white/75',
        accentText: 'text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-[var(--cm-primary)] border-t border-[var(--cm-accent)]/20 text-white/60 font-sans',
        headingFont: 'font-serif',
        radiusLg: 'rounded-2xl',
        radiusMd: 'rounded-xl',
    },

    // Solid brand-coloured header and footer around white bands, a tinted title band, serif headings. The header and
    // footer are dark surfaces, so they carry their own text and button classes (headerHeading, headerBody, headerCta,
    // footerHeading, footerBody) that PublicHeader/PublicFooter prefer over the page-level ones.
    classic: {
        layout: 'banded',
        bandA: 'bg-white dark:bg-[var(--cm-night)]',
        bandB: 'bg-[var(--cm-tint)] dark:bg-[var(--cm-night-soft)]',
        wrapper: 'bg-white dark:bg-[var(--cm-night)] text-[var(--cm-ink)] dark:text-white/90 font-sans selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-[var(--cm-accent)] text-[var(--cm-on-accent)] shadow-md',
        headerHeading: 'font-serif text-[var(--cm-on-accent)]',
        headerBody: 'text-[var(--cm-on-accent)]/75',
        headerCta: 'bg-white hover:bg-[var(--cm-tint)] text-[var(--cm-accent-deep)] font-semibold',
        navActive: 'text-[var(--cm-on-accent)] border-b-2 border-transparent border-b-[color:var(--cm-on-accent)] font-semibold rounded-none',
        navInactive: 'text-[var(--cm-on-accent)]/85 hover:text-[var(--cm-on-accent)] border-transparent rounded-none',
        heroBg: 'bg-[var(--cm-tint)] dark:bg-[var(--cm-night-soft)] text-[var(--cm-accent-deep)] dark:text-white font-serif',
        heroPill: 'bg-white dark:bg-[var(--cm-night-soft)] border-[var(--cm-accent)]/30 text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] font-sans tracking-widest',
        heroCta: 'bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] font-semibold',
        cardBg: 'bg-white dark:bg-[var(--cm-night-soft)] border border-black/10 dark:border-white/15 text-[var(--cm-ink)] dark:text-white shadow-none hover:border-[var(--cm-accent)]/50 transition-colors',
        headingText: 'font-serif text-[var(--cm-ink)] dark:text-white',
        bodyText: 'text-[var(--cm-muted)] dark:text-white/75',
        accentText: 'text-[var(--cm-accent)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-[var(--cm-accent)] border-white/10 text-[var(--cm-on-accent)]/85 font-sans',
        footerHeading: 'font-serif text-[var(--cm-on-accent)]',
        footerBody: 'text-[var(--cm-on-accent)]/75',
        headingFont: 'font-serif',
        radiusLg: 'rounded-2xl',
        radiusMd: 'rounded-xl',
    },

    // Sharp, hairline-bordered, left-aligned hero. No rounded corners anywhere.
    editorial: {
        layout: 'editorial',
        wrapper: 'bg-stone-50 dark:bg-stone-950 text-stone-900 dark:text-stone-100 font-sans selection:bg-stone-900 selection:text-white',
        header: 'sticky top-0 z-50 bg-stone-50/95 dark:bg-stone-950/95 backdrop-blur-md border-b-2 border-stone-900 dark:border-stone-100',
        navActive: 'text-stone-900 dark:text-white border-b-2 border-[var(--cm-accent)] dark:border-[var(--cm-accent-bright)] font-bold rounded-none',
        navInactive: 'text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white border-transparent rounded-none',
        heroBg: 'bg-stone-50 dark:bg-stone-950 text-stone-900 dark:text-white',
        heroPill: 'bg-transparent border-[var(--cm-accent)] dark:border-[var(--cm-accent-bright)] text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] font-sans tracking-widest',
        heroCta: 'bg-stone-900 hover:bg-[var(--cm-accent-deep)] dark:bg-white dark:hover:bg-stone-200 text-white dark:text-stone-900 font-bold shadow-none',
        cardBg: 'bg-transparent border border-stone-300 dark:border-stone-700 text-stone-900 dark:text-stone-100 shadow-none hover:border-[var(--cm-accent)] dark:hover:border-[var(--cm-accent-bright)] transition-colors',
        headingText: 'text-stone-900 dark:text-white',
        bodyText: 'text-stone-600 dark:text-stone-300',
        accentText: 'text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-stone-50 dark:bg-stone-950 border-t-2 border-stone-900 dark:border-stone-100 text-stone-500 dark:text-stone-400 font-sans',
        radiusLg: 'rounded-none',
        radiusMd: 'rounded-none',
    },

    // Saturated gradient hero, thick borders, oversized rounded/pill shapes.
    bold: {
        layout: 'boxed',
        wrapper: 'bg-white dark:bg-[var(--cm-night)] text-[var(--cm-ink)] dark:text-white font-sans selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-white/90 dark:bg-[var(--cm-night)]/90 backdrop-blur-md border-b-4 border-[var(--cm-accent)] shadow-sm',
        navActive: 'bg-[var(--cm-accent)] text-[var(--cm-on-accent)] font-bold shadow-md',
        navInactive: 'text-[var(--cm-muted)] dark:text-white/75 hover:text-[var(--cm-accent)] dark:hover:text-[var(--cm-accent-bright)] border-transparent',
        heroBg: 'bg-gradient-to-br from-[var(--cm-accent)] via-[var(--cm-accent-deep)] to-[var(--cm-hero-to)] text-white border-0 shadow-2xl',
        heroPill: 'bg-white/20 border-white/30 text-white font-black tracking-widest',
        heroCta: 'bg-white hover:bg-[var(--cm-tint)] text-[var(--cm-accent-deep)] font-black shadow-xl',
        cardBg: 'bg-white dark:bg-[var(--cm-night-soft)]/60 border-4 border-[var(--cm-accent)]/20 dark:border-[var(--cm-accent)]/30 text-[var(--cm-ink)] dark:text-white shadow-lg hover:border-[var(--cm-accent)] transition-colors',
        headingText: 'text-[var(--cm-ink)] dark:text-white',
        bodyText: 'text-[var(--cm-muted)] dark:text-white/75',
        accentText: 'text-[var(--cm-accent)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-[var(--cm-primary)] border-t-4 border-[var(--cm-accent)] text-white/60 font-sans',
        radiusLg: 'rounded-[2.5rem]',
        radiusMd: 'rounded-3xl',
    },

    // A formal lodge look: white / cream bands and navy sections, Playfair headings over Inter, small letter-spaced gold
    // labels, square-cornered gold buttons. Uses --cm-primary (navy) and --cm-cream from the lodge colour schemes; on any
    // other scheme they fall back to slate and the tint, so the layout still works.
    traditional: {
        layout: 'banded',
        fonts: { sans: LODGE_SANS, serif: LODGE_SERIF },
        bandA: 'bg-white dark:bg-[var(--cm-night)]',
        bandB: 'bg-[var(--cm-cream)] dark:bg-[var(--cm-night-soft)]',
        wrapper: 'bg-white dark:bg-[var(--cm-night)] text-[var(--cm-ink)] dark:text-white/90 font-sans selection:bg-[var(--cm-accent)] selection:text-[var(--cm-primary)]',
        header: 'sticky top-0 z-50 bg-white/95 dark:bg-[var(--cm-night)]/95 backdrop-blur-md shadow-[0_2px_20px_rgba(0,0,0,0.08)]',
        headerHeading: 'font-serif !font-semibold text-[var(--cm-primary)] dark:text-white',
        headerBody: '!text-[10px] uppercase tracking-[0.3em] text-[var(--cm-muted)] dark:text-white/60',
        headerCta: 'bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] uppercase tracking-[0.12em] !rounded-[3px] !font-semibold',
        navActive: '!rounded-none border-b-2 border-transparent border-b-[color:var(--cm-accent)] text-[var(--cm-primary)] dark:text-white uppercase tracking-[0.15em] !text-xs !font-medium',
        navInactive: '!rounded-none border-transparent text-[var(--cm-primary)]/80 dark:text-white/75 hover:text-[var(--cm-accent-deep)] dark:hover:text-[var(--cm-accent-bright)] uppercase tracking-[0.15em] !text-xs !font-medium',
        heroBg: 'bg-gradient-to-br from-[var(--cm-primary)] via-[var(--cm-primary)] to-[var(--cm-primary-soft)] text-white font-serif',
        heroPill: 'bg-transparent border-[var(--cm-accent)]/40 text-[var(--cm-accent-bright)] font-sans !tracking-[0.3em]',
        heroCta: 'bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] font-medium uppercase tracking-[0.12em]',
        cardBg: 'bg-[var(--cm-cream)] dark:bg-[var(--cm-night-soft)] border border-transparent text-[var(--cm-ink)] dark:text-white shadow-none hover:border-[var(--cm-accent)] hover:-translate-y-1 hover:shadow-[0_10px_30px_rgba(0,0,0,0.06)] transition-all duration-300',
        headingText: 'font-serif text-[var(--cm-primary)] dark:text-white',
        headingFont: 'font-serif',
        bodyText: 'text-[var(--cm-muted)] dark:text-white/75',
        accentText: 'text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-[var(--cm-primary)] border-t-2 border-[var(--cm-accent)]/40 text-white/70 font-sans',
        footerHeading: 'font-serif text-white',
        footerBody: 'text-white/70',
        radiusLg: 'rounded-md',
        radiusMd: 'rounded-[3px]',
    },
};

// Optional typography and corner overrides a lodge can layer over any theme (Website Themes, step 3). Fonts are
// system stacks only, so nothing is downloaded from a font service and no visitor's address is shared.
// Keep the keys in step with PageAdminController::FONT_PAIRINGS / CORNER_STYLES.
export const FONT_PAIRINGS = [
    { id: 'theme', name: 'Theme default', sample: 'As the layout was designed', stack: null },
    { id: 'georgia', name: 'Classic serif', sample: 'Georgia, Times New Roman', stack: 'Georgia, Cambria, "Times New Roman", Times, serif' },
    { id: 'clean', name: 'Clean sans-serif', sample: 'System UI, Helvetica, Arial', stack: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' },
    { id: 'palatino', name: 'Book style', sample: 'Palatino, Book Antiqua', stack: '"Palatino Linotype", Palatino, "Book Antiqua", "URW Palladio L", serif' },
    // The one pairing that is not a system font: Playfair Display and Inter are served from this site (public/fonts), never
    // from a font service.
    { id: 'lodge', name: 'Lodge (Playfair & Inter)', sample: 'Playfair Display headings, Inter text', stack: null, sans: LODGE_SANS, serif: LODGE_SERIF },
];

export const CORNER_STYLES = [
    { id: 'theme', name: 'Theme default', radiusLg: null, radiusMd: null },
    { id: 'square', name: 'Square', radiusLg: 'rounded-none', radiusMd: 'rounded-none' },
    { id: 'soft', name: 'Softly rounded', radiusLg: 'rounded-xl', radiusMd: 'rounded-lg' },
    { id: 'round', name: 'Very round', radiusLg: 'rounded-[2rem]', radiusMd: 'rounded-3xl' },
];

/**
 * The theme's classes with the club's font, corner and brand-colour choices laid over them. Tailwind's font-sans and
 * font-serif read the --font-sans / --font-serif properties, so setting those on the wrapper restyles every
 * themed element without touching each layout's class table.
 */
export function withSiteStyle(theme, { font_pairing: font = 'theme', corner_style: corner = 'theme', theme_colors: brand = null } = {}) {
    const brandPalette = paletteFromBrand(brand);
    const pairing = FONT_PAIRINGS.find((f) => f.id === font);
    const stack = pairing?.stack;
    const corners = CORNER_STYLES.find((c) => c.id === corner);

    return {
        ...theme,
        ...(corners?.radiusLg ? { radiusLg: corners.radiusLg, radiusMd: corners.radiusMd } : {}),
        cssVars: {
            ...theme.cssVars,
            // A lodge's own colours replace the preset's palette.
            ...(brandPalette ? cssVarsFromPalette(brandPalette) : {}),
            ...(stack ? { '--font-sans': stack, '--font-serif': stack } : {}),
            ...(pairing?.sans ? { '--font-sans': pairing.sans, '--font-serif': pairing.serif } : {}),
        },
    };
}
