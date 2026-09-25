// The website builder's theme is two independent choices, combined into one string stored in
// `settings.website_theme`:
//   - a LAYOUT (structure, typography, shape — see SITE_LAYOUTS / LAYOUT_TABLE)
//   - a COLOR SCHEME (just the brand hue — see SITE_COLOR_SCHEMES / COLOR_SCHEME_TABLE)
// The stored value is either the legacy id `masonic` (a fixed, self-contained design that ignores
// colour schemes), or `${layoutId}:${colorSchemeId}`, e.g. `banded:navy_gold`.
//
// A layout's Tailwind classes reference the colour scheme only through `var(--cm-*)` custom
// properties (accent, accent-bright, accent-deep, tint, hero-to) — never a literal hue — so any
// layout can be painted with any of the 5 colour schemes. `themeClasses()` resolves the stored
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
        vars: { accent: '#d97706', accentBright: '#fbbf24', accentDeep: '#92400e', tint: '#fffbeb', heroTo: '#78350f' },
    },
    {
        id: 'rust_stone',
        name: 'Rust & Stone',
        swatch: ['#fafaf9', '#1c1917', '#c2410c', '#7c2d12'],
        vars: { accent: '#c2410c', accentBright: '#fb923c', accentDeep: '#7c2d12', tint: '#fff7ed', heroTo: '#78350f' },
    },
    {
        id: 'crimson_rose',
        name: 'Crimson & Rose',
        swatch: ['#ffffff', '#960018', '#f4b9c7', '#fbeaee'],
        vars: { accent: '#960018', accentBright: '#f4b9c7', accentDeep: '#7a0013', tint: '#fbeaee', heroTo: '#7a0013' },
    },
    {
        id: 'violet_coral',
        name: 'Violet & Coral',
        swatch: ['#ffffff', '#2e1065', '#7c3aed', '#c084fc'],
        vars: { accent: '#7c3aed', accentBright: '#e879f9', accentDeep: '#5b21b6', tint: '#f5f3ff', heroTo: '#fb923c' },
    },
    {
        id: 'forest_moss',
        name: 'Forest & Moss',
        swatch: ['#ffffff', '#052e16', '#15803d', '#14532d'],
        vars: { accent: '#15803d', accentBright: '#4ade80', accentDeep: '#14532d', tint: '#f0fdf4', heroTo: '#0d9488' },
    },
    {
        id: 'ocean_teal',
        name: 'Ocean & Teal',
        swatch: ['#ffffff', '#083344', '#0e7490', '#164e63'],
        vars: { accent: '#0e7490', accentBright: '#22d3ee', accentDeep: '#164e63', tint: '#ecfeff', heroTo: '#4338ca' },
    },
];

export const DEFAULT_THEME_KEY = 'editorial:rust_stone';

/** `${layoutId}:${colorSchemeId}` for every valid combination — used to build server-side validation lists. */
export const SITE_THEME_KEYS = [
    LEGACY_THEME.id,
    ...SITE_LAYOUTS.flatMap((layout) => SITE_COLOR_SCHEMES.map((scheme) => `${layout.id}:${scheme.id}`)),
];

const cssVarsFor = (scheme) => ({
    '--cm-accent': scheme.vars.accent,
    '--cm-accent-bright': scheme.vars.accentBright,
    '--cm-accent-deep': scheme.vars.accentDeep,
    '--cm-tint': scheme.vars.tint,
    '--cm-hero-to': scheme.vars.heroTo,
});

/**
 * Resolves a stored `website_theme` value (`masonic`, or `${layoutId}:${colorSchemeId}`) into the
 * merged Tailwind class table BlockRenderer/PublicHeader/PublicFooter consume, plus the `cssVars`
 * that must be bound with `:style` on the wrapper. `container`/`nav` are aliases of the same
 * classes for the editor's boxed-in preview mockup.
 */
export function themeClasses(key) {
    if (key === LEGACY_THEME.id) {
        return { ...MASONIC_CLASSES, container: MASONIC_CLASSES.wrapper, nav: MASONIC_CLASSES.header, cssVars: {} };
    }

    const [layoutId, colorSchemeId] = String(key || '').split(':');
    const layout = LAYOUT_TABLE[layoutId] || LAYOUT_TABLE[DEFAULT_THEME_KEY.split(':')[0]];
    const scheme = SITE_COLOR_SCHEMES.find((s) => s.id === colorSchemeId) || SITE_COLOR_SCHEMES.find((s) => s.id === DEFAULT_THEME_KEY.split(':')[1]);

    return {
        ...layout,
        container: layout.wrapper,
        nav: layout.header,
        cssVars: cssVarsFor(scheme),
    };
}

const MASONIC_CLASSES = {
    layout: 'boxed',
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

// Layouts reference colour only via var(--cm-*) custom properties, so any of the 5 colour schemes
// can paint any layout. Neutrals (bg/ink/borders), type, shape and structure stay fixed per layout.
const LAYOUT_TABLE = {
    // Full-bleed alternating bands (white / accent-tinted), serif headings, dedicated dark hero band.
    banded: {
        layout: 'banded',
        bandA: 'bg-white dark:bg-slate-950',
        bandB: 'bg-[var(--cm-tint)] dark:bg-slate-900',
        wrapper: 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white font-serif selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-white/95 dark:bg-slate-950/95 backdrop-blur-md border-b border-[var(--cm-accent)]/20 shadow-sm',
        navActive: 'bg-slate-900 dark:bg-[var(--cm-accent)]/15 text-white dark:text-[var(--cm-accent-bright)] border-slate-900 dark:border-[var(--cm-accent)]/30 font-sans font-bold',
        navInactive: 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-[var(--cm-accent-bright)] font-sans border-transparent',
        heroBg: 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-white',
        heroPill: 'bg-[var(--cm-accent)]/15 border-[var(--cm-accent)]/30 text-[var(--cm-accent-bright)] font-sans tracking-widest',
        heroCta: 'bg-[var(--cm-accent)] hover:bg-[var(--cm-accent-bright)] text-slate-950 font-bold',
        cardBg: 'bg-white dark:bg-slate-950/60 border border-[var(--cm-accent)]/25 dark:border-[var(--cm-accent)]/20 text-slate-900 dark:text-white shadow-sm hover:border-[var(--cm-accent)]/50 transition-colors',
        headingText: 'text-slate-900 dark:text-white',
        bodyText: 'text-slate-700 dark:text-slate-300',
        accentText: 'text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-slate-950 border-t border-[var(--cm-accent)]/20 text-slate-400 font-sans',
        radiusLg: 'rounded-2xl',
        radiusMd: 'rounded-xl',
    },

    // Solid brand-coloured header and footer around white bands, a tinted title band, serif headings. The header and
    // footer are dark surfaces, so they carry their own text and button classes (headerHeading, headerBody, headerCta,
    // footerHeading, footerBody) that PublicHeader/PublicFooter prefer over the page-level ones.
    classic: {
        layout: 'banded',
        bandA: 'bg-white dark:bg-slate-950',
        bandB: 'bg-[var(--cm-tint)] dark:bg-slate-900',
        wrapper: 'bg-white dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-[var(--cm-accent)] text-white shadow-md',
        headerHeading: 'font-serif text-white',
        headerBody: 'text-white/75',
        headerCta: 'bg-white hover:bg-[var(--cm-tint)] text-[var(--cm-accent-deep)] font-semibold',
        navActive: 'text-white border-b-2 border-transparent border-b-[color:var(--cm-accent-bright)] font-semibold rounded-none',
        navInactive: 'text-white/85 hover:text-white border-transparent rounded-none',
        heroBg: 'bg-[var(--cm-tint)] dark:bg-slate-900 text-[var(--cm-accent-deep)] dark:text-white font-serif',
        heroPill: 'bg-white dark:bg-slate-800 border-[var(--cm-accent)]/30 text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] font-sans tracking-widest',
        heroCta: 'bg-[var(--cm-accent)] hover:bg-[var(--cm-accent-deep)] text-white font-semibold',
        cardBg: 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white shadow-none hover:border-[var(--cm-accent)]/50 transition-colors',
        headingText: 'font-serif text-slate-900 dark:text-white',
        bodyText: 'text-slate-600 dark:text-slate-300',
        accentText: 'text-[var(--cm-accent)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-[var(--cm-accent)] border-white/10 text-white/85 font-sans',
        footerHeading: 'font-serif text-white',
        footerBody: 'text-white/75',
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
        wrapper: 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white font-sans selection:bg-[var(--cm-accent)] selection:text-white',
        header: 'sticky top-0 z-50 bg-white/90 dark:bg-slate-950/90 backdrop-blur-md border-b-4 border-[var(--cm-accent)] shadow-sm',
        navActive: 'bg-[var(--cm-accent)] text-white font-bold shadow-md',
        navInactive: 'text-slate-600 dark:text-slate-300 hover:text-[var(--cm-accent)] dark:hover:text-[var(--cm-accent-bright)] border-transparent',
        heroBg: 'bg-gradient-to-br from-[var(--cm-accent)] via-[var(--cm-accent-deep)] to-[var(--cm-hero-to)] text-white border-0 shadow-2xl',
        heroPill: 'bg-white/20 border-white/30 text-white font-black tracking-widest',
        heroCta: 'bg-white hover:bg-slate-100 text-[var(--cm-accent-deep)] font-black shadow-xl',
        cardBg: 'bg-white dark:bg-slate-900/60 border-4 border-[var(--cm-accent)]/20 dark:border-[var(--cm-accent)]/30 text-slate-900 dark:text-white shadow-lg hover:border-[var(--cm-accent)] transition-colors',
        headingText: 'text-slate-900 dark:text-white',
        bodyText: 'text-slate-600 dark:text-slate-300',
        accentText: 'text-[var(--cm-accent)] dark:text-[var(--cm-accent-bright)]',
        accentBg: 'bg-[var(--cm-accent)]',
        footer: 'bg-slate-950 border-t-4 border-[var(--cm-accent)] text-slate-400 font-sans',
        radiusLg: 'rounded-[2.5rem]',
        radiusMd: 'rounded-3xl',
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
];

export const CORNER_STYLES = [
    { id: 'theme', name: 'Theme default', radiusLg: null, radiusMd: null },
    { id: 'square', name: 'Square', radiusLg: 'rounded-none', radiusMd: 'rounded-none' },
    { id: 'soft', name: 'Softly rounded', radiusLg: 'rounded-xl', radiusMd: 'rounded-lg' },
    { id: 'round', name: 'Very round', radiusLg: 'rounded-[2rem]', radiusMd: 'rounded-3xl' },
];

/**
 * The theme's classes with the club's font and corner choices laid over them. Tailwind's font-sans and
 * font-serif read the --font-sans / --font-serif properties, so setting those on the wrapper restyles every
 * themed element without touching each layout's class table.
 */
export function withSiteStyle(theme, { font_pairing: font = 'theme', corner_style: corner = 'theme' } = {}) {
    const stack = FONT_PAIRINGS.find((f) => f.id === font)?.stack;
    const corners = CORNER_STYLES.find((c) => c.id === corner);

    return {
        ...theme,
        ...(corners?.radiusLg ? { radiusLg: corners.radiusLg, radiusMd: corners.radiusMd } : {}),
        cssVars: { ...theme.cssVars, ...(stack ? { '--font-sans': stack, '--font-serif': stack } : {}) },
    };
}
