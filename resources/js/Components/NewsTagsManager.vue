<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import OrderColourPicker from '@/Components/OrderColourPicker.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
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

const tagToDelete = ref(null);
const removeTag = () => {
    router.delete(route('admin.settings.news_tags.destroy', { clubSlug: props.clubSlug, id: tagToDelete.value.id }), {
        ...options,
        onFinish: () => { busy.value = false; tagToDelete.value = null; },
    });
};

const usedOn = (count) => `${count} news item${count === 1 ? '' : 's'}`;
</script>

<template>
    <div class="space-y-6">
        <ul v-if="tags.length" class="divide-y divide-slate-200 rounded-2xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            <li v-for="tag in tags" :key="tag.id" class="p-4">
                <div v-if="editingId !== tag.id" class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span :class="['rounded-full px-3 py-1 text-xs font-medium', orderColour(tag.color).soft]">{{ tag.name }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Used on {{ usedOn(tag.posts_count) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="secondary" size="sm" :aria-label="`Edit ${tag.name}`" @click="startEdit(tag)">Edit</Button>
                        <Button variant="ghost" size="sm" :aria-label="`Delete ${tag.name}`" @click="tagToDelete = tag">Delete</Button>
                    </div>
                </div>

                <form v-else class="space-y-3" @submit.prevent="saveEdit(tag)">
                    <Input v-model="editName" label="Tag name" maxlength="60" :error="errors.name" />
                    <OrderColourPicker v-model="editColour" label="Tag colour" />
                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :loading="busy">Save tag</Button>
                        <Button variant="secondary" size="sm" @click="editingId = null; errors = {}">Cancel</Button>
                    </div>
                </form>
            </li>
        </ul>
        <p v-else class="rounded-2xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            No tags yet. Add your first tag below.
        </p>

        <form class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800" @submit.prevent="addTag">
            <Input v-model="newName" label="Add a tag" maxlength="60" placeholder="e.g. Masonic Charitable Foundation" :error="editingId === null ? errors.name : ''" />
            <OrderColourPicker v-model="newColour" label="Tag colour" />
            <Button type="submit" size="sm" :loading="busy" :disabled="!newName.trim()">Add tag</Button>
        </form>

        <ConfirmDialog :open="Boolean(tagToDelete)" title="Delete this tag?" confirm-label="Delete tag" :processing="busy" @confirm="removeTag" @close="tagToDelete = null">
            The tag "{{ tagToDelete?.name }}" will be deleted.<template v-if="tagToDelete?.posts_count > 0"> It will be removed from {{ usedOn(tagToDelete.posts_count) }}.</template>
        </ConfirmDialog>
    </div>
</template>
