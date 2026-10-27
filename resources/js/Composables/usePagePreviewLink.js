import { ref } from 'vue';
import { useClipboard } from '@/Composables/useClipboard';
import { postJson } from '@/Utils/postJson';

/**
 * The private preview link of a page in the website builder: whether its panel is open, the link itself, and making,
 * renewing and copying it. `form` is the page form, whose `id` says which page the link belongs to.
 */
export function usePagePreviewLink(club, form) {
    const { copied: previewCopied, copy } = useClipboard({ resetAfter: 2500 });
    const previewUrl = ref('');
    const previewOpen = ref(false);
    const previewError = ref('');

    // Another page was loaded into the form, so the link on show belongs to the one before.
    const resetPreviewLink = () => {
        previewUrl.value = '';
        previewOpen.value = false;
    };

    const fetchPreviewLink = async (renew) => {
        previewError.value = '';
        const { ok, data } = await postJson(route('admin.pages.preview_link', { clubSlug: club.slug, id: form.id }), { renew }, 'Could not make the link. Check your connection.');
        if (ok) {
            previewUrl.value = data.url;
            previewCopied.value = '';
        } else {
            previewError.value = data.message || 'Could not make the link.';
        }
    };

    const openPreviewPanel = async () => {
        previewOpen.value = !previewOpen.value;
        if (!previewOpen.value || previewUrl.value || !form.id) return;
        await fetchPreviewLink(false);
    };

    const renewPreviewLink = async () => {
        if (!confirm('Make a new link? The old one will stop working for anyone who has it.')) return;
        await fetchPreviewLink(true);
    };

    const copyPreviewLink = async () => {
        if (!(await copy(previewUrl.value))) {
            previewError.value = 'Could not copy automatically. Select the link and copy it.';
        }
    };

    return { previewUrl, previewOpen, previewCopied, previewError, resetPreviewLink, openPreviewPanel, renewPreviewLink, copyPreviewLink };
}
