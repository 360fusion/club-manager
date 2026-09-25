<script setup>
// Earlier saves of a page. "Restore" loads that version into the editor as unsaved changes: nothing is changed on
// the site until the admin saves, and the version they replaced is remembered too.
import { ref, watch } from 'vue';
import { formatLocal } from '@/Utils/localDateTime';

const props = defineProps({
    show: { type: Boolean, default: false },
    clubSlug: { type: String, required: true },
    pageId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'restore']);

const revisions = ref([]);
const loading = ref(false);
const restoringId = ref(null);
const error = ref('');

const SOURCES = { save: 'Saved', publish: 'Draft published', restore: 'Restored', copy: 'Element copied in' };

const getJson = async (url) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

    if (!response.ok) throw new Error('failed');

    return response.json();
};

const load = async () => {
    error.value = '';
    revisions.value = [];

    if (!props.pageId) return;

    loading.value = true;

    try {
        revisions.value = (await getJson(route('admin.pages.revisions', { clubSlug: props.clubSlug, id: props.pageId }))).revisions;
    } catch {
        error.value = 'The history could not be loaded. Check your connection and try again.';
    } finally {
        loading.value = false;
    }
};

const restore = async (revision) => {
    restoringId.value = revision.id;
    error.value = '';

    try {
        const { content } = await getJson(route('admin.pages.revision', { clubSlug: props.clubSlug, id: props.pageId, revisionId: revision.id }));
        emit('restore', content, revision);
    } catch {
        error.value = 'That version could not be loaded.';
    } finally {
        restoringId.value = null;
    }
};

watch(() => props.show, (open) => { if (open) load(); });
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm" @click.self="emit('close')" @keydown.esc="emit('close')">
            <div role="dialog" aria-modal="true" aria-labelledby="page-history-title" class="w-full max-w-xl max-h-[85vh] flex flex-col bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl">
                <div class="flex items-start justify-between gap-4 p-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 id="page-history-title" class="text-lg font-bold text-slate-900 dark:text-white">Page history</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Restoring puts that version in the editor as unsaved changes. Nothing changes on your site until you save.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1 cursor-pointer" aria-label="Close" @click="emit('close')">✕</button>
                </div>

                <div class="p-4 overflow-y-auto space-y-2">
                    <p v-if="loading" class="text-xs text-slate-400 p-2">Loading…</p>
                    <p v-else-if="error" class="text-xs font-bold text-rose-600 dark:text-rose-400 p-2">{{ error }}</p>
                    <p v-else-if="!revisions.length" class="text-xs text-slate-400 italic p-2">No earlier versions yet. A version is kept each time you save.</p>

                    <div v-for="(revision, index) in revisions" :key="revision.id" class="flex items-center justify-between gap-3 p-3 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <div class="min-w-0 text-xs">
                            <p class="font-bold text-slate-900 dark:text-white truncate">
                                {{ formatLocal(revision.created_at) }}
                                <span v-if="index === 0" class="ml-1 px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] uppercase">Latest</span>
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 truncate">{{ SOURCES[revision.source] || 'Saved' }}<template v-if="revision.user"> by {{ revision.user }}</template> · {{ revision.block_count }} element{{ revision.block_count === 1 ? '' : 's' }}</p>
                            <p class="text-slate-400 truncate">{{ revision.title }}</p>
                        </div>
                        <button type="button" :disabled="restoringId !== null" class="shrink-0 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs disabled:opacity-50 cursor-pointer" @click="restore(revision)">
                            {{ restoringId === revision.id ? 'Loading…' : 'Restore' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
