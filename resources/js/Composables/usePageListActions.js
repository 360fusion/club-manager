import { router } from '@inertiajs/vue3';

/**
 * What the website builder's page list does to a page: show or hide it, duplicate it, restore or permanently delete one
 * from the bin, and move it up or down the menu. `pages` is a function returning the club's pages in menu order.
 */
export function usePageListActions(club, pages) {
    const togglePublishPage = (p) => {
        if (p.is_homepage || p.slug === 'home') return;
        router.post(`/${club.slug}/admin/pages/${p.id}/toggle-publish`, {}, { preserveScroll: true });
    };

    const duplicatePage = (p) => {
        router.post(`/${club.slug}/admin/pages/${p.id}/duplicate`, {}, { preserveScroll: true });
    };

    const restorePage = (p) => {
        router.post(`/${club.slug}/admin/pages/trash/${p.id}/restore`, {}, { preserveScroll: true });
    };

    const forceDeletePage = (p) => {
        if (!confirm(`Permanently delete "${p.title}"? This cannot be undone.`)) return;
        router.delete(`/${club.slug}/admin/pages/trash/${p.id}`, { preserveScroll: true });
    };

    const movePageUp = (index) => {
        if (index <= 1) return;
        const newPages = [...pages()];
        const temp = newPages[index];
        newPages[index] = newPages[index - 1];
        newPages[index - 1] = temp;

        const orderIds = newPages.map(p => p.id);
        router.post(`/${club.slug}/admin/pages/reorder`, { order: orderIds }, { preserveScroll: true });
    };

    const movePageDown = (index) => {
        if (index === 0 || index >= pages().length - 1) return;
        const newPages = [...pages()];
        const temp = newPages[index];
        newPages[index] = newPages[index + 1];
        newPages[index + 1] = temp;

        const orderIds = newPages.map(p => p.id);
        router.post(`/${club.slug}/admin/pages/reorder`, { order: orderIds }, { preserveScroll: true });
    };

    return { togglePublishPage, duplicatePage, restorePage, forceDeletePage, movePageUp, movePageDown };
}
