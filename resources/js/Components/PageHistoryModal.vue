<script setup>
// Earlier saves of a page. "Restore" loads that version into the editor as unsaved changes: nothing is changed on
// the site until the admin saves, and the version they replaced is remembered too.
import { ref, watch } from 'vue';
import { formatLocal } from '@/Utils/localDateTime';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';

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
        <!-- Above the builder's own z-[100] overlays, as before. -->
        <div v-if="show" class="relative z-[100]">
            <Modal
                :open="show"
                title="Page history"
                subtitle="Restoring puts that version in the editor as unsaved changes. Nothing changes on your site until you save."
                size="lg"
                @close="emit('close')"
            >
                <div class="space-y-2">
                    <p v-if="loading" class="text-xs text-slate-400 p-2">Loading…</p>
                    <Alert v-else-if="error" variant="danger">{{ error }}</Alert>
                    <p v-else-if="!revisions.length" class="text-xs text-slate-400 italic p-2">No earlier versions yet. A version is kept each time you save.</p>

                    <div v-for="(revision, index) in revisions" :key="revision.id" class="flex items-center justify-between gap-3 p-3 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <div class="min-w-0 text-xs">
                            <p class="flex items-center gap-1.5 font-semibold text-slate-900 dark:text-white truncate">
                                {{ formatLocal(revision.created_at) }}
                                <Badge v-if="index === 0" variant="info">Latest</Badge>
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 truncate">{{ SOURCES[revision.source] || 'Saved' }}<template v-if="revision.user"> by {{ revision.user }}</template> · {{ revision.block_count }} element{{ revision.block_count === 1 ? '' : 's' }}</p>
                            <p class="text-slate-400 truncate">{{ revision.title }}</p>
                        </div>
                        <Button variant="secondary" size="sm" class="shrink-0" :disabled="restoringId !== null" :loading="restoringId === revision.id" @click="restore(revision)">
                            {{ restoringId === revision.id ? 'Loading…' : 'Restore' }}
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    </Teleport>
</template>
