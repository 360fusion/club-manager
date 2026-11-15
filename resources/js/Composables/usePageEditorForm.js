import { computed, nextTick, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { settingsStateOf } from '@/Utils/pageBuilderSettings';
import { toLocalInput, fromLocalInput } from '@/Utils/localDateTime';
import { readCollapsed } from '@/Utils/collapsedBlocks';
import { usePagePreviewLink } from '@/Composables/usePagePreviewLink';

// The builder's screens that are not a page (they never load a page into the form and never count as unsaved).
export const MANAGEMENT_SELECTIONS = ['overview', 'settings', 'themes', 'header_footer', 'redirects', 'publish'];

/**
 * The website builder's page form: loading the selected page (or a new one) into it, the "unsaved changes" snapshot,
 * saving, drafts, restoring an earlier version and the private preview link. `activeNavSelection` is the builder's
 * selection ref (a page id, 'new' or one of MANAGEMENT_SELECTIONS); `settingsForm` is the site settings form, used for
 * the search preview.
 */
export function usePageEditorForm(props, { activeNavSelection, settingsForm, isSavedSuccess, flashSaved }) {
    const activePage = computed(() => {
        if (activeNavSelection.value === 'new' || MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
            return null;
        }
        return props.pages.find(p => String(p.id) === String(activeNavSelection.value)) || null;
    });

    const form = useForm({
        id: null,
        title: '',
        slug: '',
        is_published: true,
        is_homepage: false,
        show_in_navigation: true,
        show_in_footer: false,
        is_members_only: false,
        header_style: 'full',
        publish_at: null,
        unpublish_at: null,
        meta_title: '',
        meta_description: '',
        share_image: '',
        noindex: false,
        blocks: [],
    });

    const initialFormSnapshot = ref('');
    const isBypassingDirtyGuard = ref(false);

    // The page's settings (the things that save with the normal Save, never with a draft), in one place so the
    // "unsaved changes" check can compare them against what the server holds.
    const currentSettings = () => ({
        slug: form.slug,
        is_published: form.is_published,
        is_homepage: form.is_homepage,
        show_in_navigation: form.show_in_navigation,
        show_in_footer: form.show_in_footer,
        is_members_only: form.is_members_only,
        header_style: form.header_style,
        publish_at: form.publish_at,
        unpublish_at: form.unpublish_at,
    });

    const getFormStateString = (settings = null) => {
        return JSON.stringify({
            id: form.id,
            title: form.title,
            ...(settings ?? currentSettings()),
            meta_title: form.meta_title,
            meta_description: form.meta_description,
            share_image: form.share_image,
            noindex: form.noindex,
            blocks: form.blocks,
        });
    };

    const takeFormSnapshot = () => {
        initialFormSnapshot.value = getFormStateString();
    };

    const isDirty = computed(() => {
        if (MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
            return false;
        }
        return initialFormSnapshot.value !== '' && getFormStateString() !== initialFormSnapshot.value;
    });

    // How the page is likely to look in a search engine, from what has been typed so far.
    const searchPreview = computed(() => {
        const suffix = settingsForm.seo_title_suffix || `| ${props.club.name}`;
        const title = `${form.meta_title || form.title || 'Page title'} ${suffix}`.trim();
        const description = form.meta_description || settingsForm.seo_meta_description || 'No description yet. Search engines will pick some text from the page.';
        const path = form.is_homepage || form.slug === 'home' ? '' : ` › ${form.slug}`;

        return { title, description, url: `${window.location.host} › site › ${props.club.slug}${path}` };
    });

    // The SEO and URL card starts folded away; it shows the page's address while closed.
    const seoOpen = ref(false);

    // Compact view state (see toggleCollapsed): ids of the elements folded down to a summary. Remembered per page in
    // this browser (see Utils/collapsedBlocks), so it survives reloads of the page and copying an element elsewhere.
    const collapsedBlocks = ref(new Set());

    // The private preview link panel; declared here because loadPageIntoForm resets it.
    const previewLink = usePagePreviewLink(props.club, form);

    const loadPageIntoForm = (page) => {
        collapsedBlocks.value = page?.id ? readCollapsed(props.club.slug, page.id) : new Set();
        previewLink.resetPreviewLink();
        if (page) {
            // A page with an unpublished draft opens on the draft, since that is the latest version of its content.
            const draft = page.draft_saved_at ? (page.draft_content || {}) : null;
            form.id = page.id;
            Object.assign(form, settingsStateOf(page));
            form.title = (draft ? draft.title : page.title) || '';
            form.meta_title = (draft ? draft.meta_title : page.meta_title) || '';
            form.meta_description = (draft ? draft.meta_description : page.meta_description) || '';
            form.share_image = (draft ? draft.share_image : page.share_image) || '';
            form.noindex = page.noindex ?? false;
            const blocks = draft ? (page.draft_blocks ?? page.blocks) : page.blocks;
            form.blocks = blocks ? JSON.parse(JSON.stringify(blocks)) : [];
        } else {
            form.id = null;
            form.title = 'New Custom Page';
            form.slug = 'new-custom-page';
            form.is_published = true;
            form.is_homepage = false;
            form.show_in_navigation = true;
            form.show_in_footer = false;
            form.is_members_only = false;
            form.header_style = 'full';
            form.publish_at = null;
            form.unpublish_at = null;
            form.meta_title = '';
            form.meta_description = '';
            form.share_image = '';
            form.noindex = false;
            form.blocks = [
                {
                    id: 'block-' + Date.now(),
                    type: 'hero',
                    title: 'Welcome to Our New Page',
                    subtitle: 'Discover more about our club',
                    cta_text: 'Get Started',
                    cta_link: `/site/${props.club.slug}`,
                },
                {
                    id: 'block-' + (Date.now() + 1),
                    type: 'text',
                    heading: 'Page Overview',
                    content: '<p>Add detailed custom content here.</p>',
                }
            ];
        }
        // Folding is tracked by block id, so every block needs one.
        form.blocks.forEach((block, i) => { if (!block.id) block.id = `block-${Date.now()}-${i}`; });
        nextTick(() => {
            takeFormSnapshot();
        });
    };

    // Initialize form
    if (activePage.value) {
        loadPageIntoForm(activePage.value);
    } else if (activeNavSelection.value === 'new') {
        loadPageIntoForm(null);
    }

    watch(activeNavSelection, (newVal) => {
        if (MANAGEMENT_SELECTIONS.includes(newVal)) {
            return;
        }
        if (newVal === 'new') {
            loadPageIntoForm(null);
        } else {
            const found = props.pages.find(p => String(p.id) === String(newVal));
            if (found) {
                loadPageIntoForm(found);
            }
        }
    });

    watch(() => props.pages, (newPages) => {
        if (activeNavSelection.value !== 'new' && !MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
            const current = newPages.find(p => String(p.id) === String(activeNavSelection.value));
            if (current) {
                loadPageIntoForm(current);
            }
        }
    }, { deep: true });

    const submitForm = (onSuccessCallback = null) => {
        isBypassingDirtyGuard.value = true;
        form.post(`/${props.club.slug}/admin/pages`, {
            preserveScroll: true,
            onSuccess: () => {
                isSavedSuccess.value = true;
                nextTick(() => {
                    takeFormSnapshot();
                    isBypassingDirtyGuard.value = false;
                });
                setTimeout(() => {
                    isSavedSuccess.value = false;
                }, 3000);
                if (typeof onSuccessCallback === 'function') {
                    onSuccessCallback();
                }
            },
            onError: () => {
                isBypassingDirtyGuard.value = false;
            }
        });
    };

    // ---- Drafts, history -------------------------------------------------------------------------------------------
    const hasDraft = computed(() => !!activePage.value?.draft_saved_at);
    // The server keeps a draft only for a page that is already published, so this follows the saved page, not the checkbox.
    const canDraft = computed(() => !!form.id && !!activePage.value?.is_published);

    // Saving a draft keeps only the page's content aside, so the settings (address, menus, dates...) stay "unsaved"
    // until the normal Save; the check below compares them against what the server holds.
    const takeDraftSnapshot = () => {
        initialFormSnapshot.value = getFormStateString({ ...settingsStateOf(activePage.value || {}), slug: activePage.value?.slug ?? form.slug });
    };

    const saveDraft = () => {
        if (!form.id) return;
        isBypassingDirtyGuard.value = true;
        form.post(route('admin.pages.draft.save', { clubSlug: props.club.slug, id: form.id }), {
            preserveScroll: true,
            onSuccess: () => {
                nextTick(() => {
                    takeDraftSnapshot();
                    isBypassingDirtyGuard.value = false;
                });
                flashSaved();
            },
            onError: () => { isBypassingDirtyGuard.value = false; },
        });
    };

    const reloadFormFromServer = () => nextTick(() => loadPageIntoForm(activePage.value));

    const publishDraft = () => {
        // Anything typed since the draft was saved is published with it, so the page goes live exactly as it looks here.
        if (isDirty.value) {
            submitForm();
            return;
        }
        router.post(route('admin.pages.draft.publish', { clubSlug: props.club.slug, id: form.id }), {}, { preserveScroll: true, onSuccess: () => { reloadFormFromServer(); flashSaved(); } });
    };

    const discardDraft = () => {
        if (!confirm('Discard this draft? The live page is not affected, but the draft edits are lost.')) return;
        router.delete(route('admin.pages.draft.discard', { clubSlug: props.club.slug, id: form.id }), { preserveScroll: true, onSuccess: reloadFormFromServer });
    };

    const showHistory = ref(false);
    const restoreNotice = ref('');

    const restoreRevision = (content) => {
        form.title = content.title || form.title;
        form.meta_title = content.meta_title || '';
        form.meta_description = content.meta_description || '';
        form.share_image = content.share_image || '';
        form.blocks = JSON.parse(JSON.stringify(content.blocks || []));
        form.blocks.forEach((block, i) => { if (!block.id) block.id = `block-${Date.now()}-${i}`; });
        collapsedBlocks.value = new Set();
        showHistory.value = false;
        restoreNotice.value = 'An earlier version is loaded below. Save to make it live, or leave the page to keep the current one.';
        setTimeout(() => { restoreNotice.value = ''; }, 8000);
    };

    const publishAtLocal = computed({
        get: () => toLocalInput(form.publish_at),
        set: (value) => { form.publish_at = fromLocalInput(value); },
    });
    const unpublishAtLocal = computed({
        get: () => toLocalInput(form.unpublish_at),
        set: (value) => { form.unpublish_at = fromLocalInput(value); },
    });

    return {
        activePage, form, isDirty, isBypassingDirtyGuard, searchPreview, seoOpen, collapsedBlocks, ...previewLink,
        submitForm, hasDraft, canDraft, saveDraft, publishDraft, discardDraft, showHistory, restoreNotice, restoreRevision,
        publishAtLocal, unpublishAtLocal,
    };
}
