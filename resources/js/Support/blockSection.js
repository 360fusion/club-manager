// How a block's optional "Section" settings (background, tone, spacing) turn into classes and styles. The settings
// are saved by App\Support\BlockNormaliser::section(); a block without them, or with mode "auto", is drawn exactly as
// the theme always drew it.

const PADDING = {
    none: 'py-0',
    sm: 'py-8',
    md: 'py-12 sm:py-14',
    lg: 'py-16 sm:py-20',
    xl: 'py-24 sm:py-32',
};

const OVERLAY = { light: 'bg-black/30', medium: 'bg-black/55', strong: 'bg-black/75' };

export const SECTION_DEFAULTS = {
    mode: 'auto',
    bg: 'none',
    bg_color: '',
    bg_image: '',
    overlay: 'medium',
    tone: 'auto',
    padding: 'auto',
    anchor: '',
};

/** The block's section settings when it has its own look (mode is not "auto"); null otherwise. */
export function activeSection(block) {
    const section = block?.section;

    return section && (section.mode === 'band' || section.mode === 'contained') ? section : null;
}

const channel = (hex, at) => parseInt(hex.slice(at, at + 2), 16) / 255;
const linear = (c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

/** Relative luminance (0 dark, 1 light) of a #rrggbb colour. */
export function luminance(hex) {
    if (!/^#[0-9a-f]{6}$/i.test(hex || '')) return 1;

    return 0.2126 * linear(channel(hex, 1)) + 0.7152 * linear(channel(hex, 3)) + 0.0722 * linear(channel(hex, 5));
}

/** Whether the section's background is dark enough for light text: an explicit tone wins, otherwise it is worked out. */
export function sectionTone(section, theme) {
    if (!section) return 'light';
    if (section.tone === 'dark' || section.tone === 'light') return section.tone;

    if (section.bg === 'primary' || section.bg === 'image') return 'dark';
    if (section.bg === 'accent') return luminance(theme.cssVars?.['--cm-accent']) < 0.4 ? 'dark' : 'light';
    if (section.bg === 'custom') return luminance(section.bg_color) < 0.4 ? 'dark' : 'light';

    return 'light';
}

/** Tailwind classes for the section's background (never the image, which needs an inline style). */
export function sectionBackgroundClass(section, theme) {
    switch (section?.bg) {
        case 'tint': return theme.bandB || 'bg-[var(--cm-tint)] dark:bg-slate-900';
        case 'primary': return 'bg-[var(--cm-primary)] text-white';
        case 'accent': return 'bg-[var(--cm-accent)]';
        case 'custom': return '';
        case 'image': return 'relative overflow-hidden bg-[var(--cm-primary)] text-white';
        default: return '';
    }
}

/** Inline style for a custom colour or a photo. The colour and URL were validated when the page was saved. */
export function sectionBackgroundStyle(section) {
    if (section?.bg === 'custom' && section.bg_color) return { backgroundColor: section.bg_color };
    if (section?.bg === 'image' && section.bg_image) {
        return { backgroundImage: `url("${String(section.bg_image).replace(/["\\()\s]/g, encodeURIComponent)}")`, backgroundSize: 'cover', backgroundPosition: 'center' };
    }

    return {};
}

export const sectionOverlayClass = (section) => (section?.bg === 'image' && section.bg_image ? OVERLAY[section.overlay] || OVERLAY.medium : '');

/** Vertical padding class for a section, or the fallback when it leaves the padding on "auto". */
export const sectionPadding = (section, fallback = '') => (section && PADDING[section.padding]) || fallback;
