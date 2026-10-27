import { formatLocal } from './localDateTime.js';

// The website builder's page settings, footer and announcement options, and the small rules that decide how a page is
// described. No component state in here, so each can be tested on its own.

export const DEFAULT_SLUGS = ['home', 'about', 'join-us', 'news', 'contact'];

export const isDefaultPage = (page) => {
    if (!page) return false;
    return page.is_homepage || DEFAULT_SLUGS.includes(page.slug);
};

export const HEADER_LAYOUTS = [
    { id: 'logo_left', name: 'Logo Left', description: 'Logo & name on the left, navigation and actions on the right — the classic layout.' },
    { id: 'logo_center', name: 'Logo Centered', description: 'Logo & name centered on top, navigation centered underneath.' },
];

export const FOOTER_LAYOUTS = [
    { id: 'simple', name: 'Simple', description: 'A single centered line with the copyright notice (and social links, if enabled).' },
    { id: 'columns', name: 'Columns', description: 'Club info, navigation, custom link columns and social links laid out above the copyright line.' },
];

export const MAX_FOOTER_COLUMNS = 4;

export const MAX_FOOTER_COLUMN_LINKS = 8;

// The server strips any 'id' field it doesn't recognise, so a reloaded column/link may come back without
// one — assign a fresh id in that case, purely for :key stability in the editor.
export const normalizeFooterLinkColumns = (columns) => (columns || []).map((col, colIdx) => ({
    id: col.id || `col-${Date.now()}-${colIdx}`,
    title: col.title || '',
    links: (col.links || []).map((link, linkIdx) => ({
        id: link.id || `link-${Date.now()}-${colIdx}-${linkIdx}`,
        label: link.label || '',
        url: link.url || '',
    })),
}));

export const ANNOUNCEMENT_STYLES = [
    { id: 'info', name: 'Blue' },
    { id: 'success', name: 'Green' },
    { id: 'warning', name: 'Amber' },
    { id: 'dark', name: 'Dark' },
];

export const isoOrNull = (value) => (value ? new Date(value).toISOString() : null);

export const settingsStateOf = (page) => ({
    slug: page.slug || '',
    is_published: page.is_published ?? true,
    is_homepage: page.is_homepage ?? false,
    show_in_navigation: page.show_in_navigation ?? true,
    show_in_footer: page.show_in_footer ?? false,
    is_members_only: page.is_members_only ?? false,
    header_style: page.header_style || 'full',
    publish_at: isoOrNull(page.publish_at),
    unpublish_at: isoOrNull(page.unpublish_at),
});

export const lifecycleText = (page) => {
    if (!page) return '';
    if (page.lifecycle_status === 'scheduled') return `Goes live ${formatLocal(page.publish_at)}`;
    if (page.lifecycle_status === 'expired') return `Came down ${formatLocal(page.unpublish_at)}`;
    if (page.lifecycle_status === 'live' && page.unpublish_at && !page.is_homepage) return `Comes down ${formatLocal(page.unpublish_at)}`;
    return '';
};
