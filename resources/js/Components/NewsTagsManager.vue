<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import OrderColourPicker from '@/Components/OrderColourPicker.vue';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
    clubSlug: { type: String, required: true },
    tags: { type: Array, default: () => [] },
});

const errors = ref({});
const busy = ref(false);
const newName = ref('');
const newColour = ref('slate');
const editingId = ref(null);
const editName = ref('');
const editColour = ref('slate');

const url = (id = null) => id
    ? route('admin.settings.news_tags.update', { clubSlug: props.clubSlug, id })
    : route('admin.settings.news_tags.store', { clubSlug: props.clubSlug });

const options = {
    preserveScroll: true,
    onStart: () => { busy.value = true; },
    onFinish: () => { busy.value = false; },
    onError: (e) => { errors.value = e; },
    onSuccess: () => { errors.value = {}; },
};

const addTag = () => {
    const name = newName.value.trim();
    if (!name) return;
    router.post(url(), { name, color: newColour.value }, {
        ...options,
        onSuccess: () => { errors.value = {}; newName.value = ''; newColour.value = 'slate'; },
    });
};

const startEdit = (tag) => {
    editingId.value = tag.id;
    editName.value = tag.name;
    editColour.value = tag.color || 'slate';
    errors.value = {};
};

const saveEdit = (tag) => {
    const name = editName.value.trim();
    if (!name) return;
    router.put(url(tag.id), { name, color: editColour.value }, {
        ...options,
        onSuccess: () => { errors.value = {}; editingId.value = null; },
    });
};

const removeTag = (tag) => {
    const used = tag.posts_count > 0 ? ` It will be removed from ${tag.posts_count} news item${tag.posts_count === 1 ? '' : 's'}.` : '';
    if (!confirm(`Delete the tag "${tag.name}"?${used}`)) return;
    router.delete(route('admin.settings.news_tags.destroy', { clubSlug: props.clubSlug, id: tag.id }), options);
};
</script>

<template>
    <div class="space-y-6">
        <ul v-if="tags.length" class="divide-y divide-slate-100 rounded-2xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            <li v-for="tag in tags" :key="tag.id" class="p-4">
                <div v-if="editingId !== tag.id" class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span :class="['rounded-full px-3 py-1 text-xs font-bold', orderColour(tag.color).soft]">{{ tag.name }}</span>
                        <span class="text-[11px] text-slate-400">Used on {{ tag.posts_count }} news item{{ tag.posts_count === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-bold">
                        <button type="button" class="rounded-lg px-3 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40" @click="startEdit(tag)">Edit</button>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40" @click="removeTag(tag)">Delete</button>
                    </div>
                </div>

                <form v-else class="space-y-3" @submit.prevent="saveEdit(tag)">
                    <input
                        v-model="editName"
                        type="text"
                        maxlength="60"
                        aria-label="Tag name"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-800 dark:bg-slate-800/50"
                    />
                    <OrderColourPicker v-model="editColour" />
                    <p v-if="errors.name" class="text-xs font-semibold text-red-600">{{ errors.name }}</p>
                    <div class="flex gap-2 text-xs font-bold">
                        <button type="submit" :disabled="busy" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-60">Save tag</button>
                        <button type="button" class="rounded-lg px-4 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="editingId = null; errors = {}">Cancel</button>
                    </div>
                </form>
            </li>
        </ul>
        <div v-else class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400 dark:border-slate-800">
            No tags yet. Add your first tag below.
        </div>

        <form class="space-y-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/40" @submit.prevent="addTag">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200" for="new-news-tag">Add a tag</label>
            <input
                id="new-news-tag"
                v-model="newName"
                type="text"
                maxlength="60"
                placeholder="e.g. Masonic Charitable Foundation"
                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-800 dark:bg-slate-900"
            />
            <OrderColourPicker v-model="newColour" />
            <p v-if="errors.name && editingId === null" class="text-xs font-semibold text-red-600">{{ errors.name }}</p>
            <button type="submit" :disabled="busy || !newName.trim()" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60">Add tag</button>
        </form>
    </div>
</template>
