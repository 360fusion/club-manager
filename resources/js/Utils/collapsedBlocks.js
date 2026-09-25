// Which elements of a page are folded down in the page editor, remembered in this browser so a reload of the page
// (or copying an element to another page) never opens them all again. Kept per club and page, by element id.

const key = (clubSlug) => `page-editor-collapsed:${clubSlug}`;

const readAll = (clubSlug) => {
    try {
        const stored = JSON.parse(localStorage.getItem(key(clubSlug)) || '{}');

        return stored && typeof stored === 'object' && !Array.isArray(stored) ? stored : {};
    } catch (e) {
        return {};
    }
};

/** The ids folded on this page, as a Set (empty when nothing was saved or storage is unavailable). */
export function readCollapsed(clubSlug, pageId) {
    const ids = readAll(clubSlug)[String(pageId)];

    return new Set(Array.isArray(ids) ? ids.filter((id) => typeof id === 'string') : []);
}

/** Remembers the folded ids for a page; an empty list forgets the page. */
export function writeCollapsed(clubSlug, pageId, ids) {
    const all = readAll(clubSlug);

    if (ids.length) {
        all[String(pageId)] = ids;
    } else {
        delete all[String(pageId)];
    }

    try {
        localStorage.setItem(key(clubSlug), JSON.stringify(all));
    } catch (e) {
        // Storage is unavailable (private browsing); folding still works for this visit.
    }
}
