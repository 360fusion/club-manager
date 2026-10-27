import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { postJson } from '@/Utils/postJson';

/**
 * Copying an element of the page being edited onto another page. `pages` is a function returning the club's pages, so the
 * list of targets follows the page list as the server refreshes it; `form` is the page form (its `id` is left out of the targets).
 */
export function usePageBlockCopy(club, form, pages) {
    const copyTargets = computed(() => pages().filter((p) => p.id !== form.id));
    const copyNotice = ref('');
    const copyNoticeIsError = ref(false);

    // Called from the target dropdown's change event.
    const copyBlockTo = async (block, event) => {
        const targetId = event.target.value;
        event.target.value = '';
        if (!targetId) return;

        const { ok, data } = await postJson(route('admin.pages.copy_block', { clubSlug: club.slug, id: targetId }), { block: JSON.parse(JSON.stringify(block)) }, 'Could not copy that element. Check your connection.');

        copyNoticeIsError.value = !ok;
        copyNotice.value = ok
            ? `Copied to "${data.title}"${data.to_draft ? ' (added to its draft)' : ''}. Open that page to see it.`
            : (data.message || data.errors?.block?.[0] || 'Could not copy that element.');
        setTimeout(() => { copyNotice.value = ''; }, 5000);

        // The page list held by this screen is now out of date for the target; refresh it so editing that page next starts from the copy.
        if (ok) router.reload({ only: ['pages'], preserveScroll: true, preserveState: true });
    };

    return { copyTargets, copyNotice, copyNoticeIsError, copyBlockTo };
}
