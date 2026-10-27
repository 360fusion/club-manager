// Every screen in the website builder has its own address, so it can be bookmarked, reloaded, opened in a new tab and
// reached with the back button. A page is /pages/7/edit (add ?view=preview for the preview); the rest are named screens.
export const SCREEN_PATHS = { new: 'create', settings: 'settings', themes: 'themes', header_footer: 'header-footer', redirects: 'redirects', overview: 'overview' };

/** The address of a screen: a named one (`settings`) or a page id, in edit or preview mode. */
export const selectionUrl = (clubSlug, selection, mode = 'edit') => {
    const base = `/${clubSlug}/admin/pages`;
    const key = String(selection);

    if (SCREEN_PATHS[key]) return `${base}/${SCREEN_PATHS[key]}`;

    return `${base}/${key}/edit${mode === 'preview' ? '?view=preview' : ''}`;
};

/** The selection and view mode an address stands for, or null if it is not one of the builder's screens. */
export const parseSelectionUrl = (url) => {
    const [path, query = ''] = String(url).split('?');
    const match = path.match(/\/admin\/pages\/([^/]+)(?:\/edit)?\/?$/);

    if (!match) return null;

    const screen = Object.entries(SCREEN_PATHS).find(([, segment]) => segment === match[1]);
    if (screen) return { selection: screen[0], mode: 'edit' };

    if (!/^\d+$/.test(match[1]) || !/\/edit\/?$/.test(path)) return null;

    return { selection: match[1], mode: new URLSearchParams(query).get('view') === 'preview' ? 'preview' : 'edit' };
};

/** The icon shown beside a page in the builder's lists. */
export const getPageIcon = (p) => {
    if (!p) return '📄';
    if (p.is_homepage || p.slug === 'home') return '🏠';
    if (p.slug === 'about') return 'ℹ️';
    if (p.slug === 'join-us') return '🤝';
    if (p.slug === 'news') return '📰';
    if (p.slug === 'contact') return '✉️';
    return '📄';
};
