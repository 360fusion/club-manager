<script setup>
// Edit form for the Downloads block. Uploaded files are stored privately and served by the site's download
// route (which applies "Members only"); link items point anywhere and cannot be protected. Mutates `block` in place.
import { ref } from 'vue';
import { postForm } from '@/Utils/postForm';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    club: { type: Object, required: true },
});

const emit = defineEmits(['media']);

const MAX_ITEMS = 100;

if (!Array.isArray(props.block.items)) props.block.items = [];

const uploading = ref(false);
const status = ref('');
const error = ref('');
const fileInput = ref(null);

const newId = () => `file-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;

const size = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const upload = async (event) => {
    const files = [...event.target.files];
    event.target.value = '';
    error.value = '';
    uploading.value = true;

    for (const [n, file] of files.entries()) {
        if (props.block.items.length >= MAX_ITEMS) {
            error.value = `A block can list up to ${MAX_ITEMS} documents.`;
            break;
        }

        status.value = `Uploading ${n + 1} of ${files.length}: ${file.name}…`;
        const form = new FormData();
        form.append('file', file);
        const { ok, data } = await postForm(route('admin.pages.downloads.upload', { clubSlug: props.club.slug }), form);

        if (!ok || !data.success) {
            error.value = `"${file.name}": ${data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'the upload failed.')}`;
            break;
        }

        props.block.items.push({
            id: newId(), source: 'upload', title: data.title, description: '', group: '',
            media_id: data.media_id, url: '', file_name: data.file_name, mime_type: data.mime_type, size: data.size, added_at: data.added_at,
        });
    }

    status.value = '';
    uploading.value = false;
};

const addLink = () => {
    if (props.block.items.length < MAX_ITEMS) {
        props.block.items.push({ id: newId(), source: 'link', title: '', description: '', group: '', media_id: null, url: '', file_name: '', mime_type: '', size: 0, added_at: '' });
    }
};
const removeItem = (i) => props.block.items.splice(i, 1);
const move = (i, by) => {
    const j = i + by;
    if (j < 0 || j >= props.block.items.length) return;
    const list = props.block.items;
    [list[i], list[j]] = [list[j], list[i]];
};
</script>

<template>
    <div class="space-y-3 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label :for="`block-${index}-dl-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-dl-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Documents and forms" :class="[INPUT, 'font-bold']" />
            </div>
            <div>
                <label :for="`block-${index}-dl-intro`" :class="LABEL">Intro <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-dl-intro`" v-model="block.intro" type="text" maxlength="500" :class="INPUT" />
            </div>
        </div>

        <div :class="[CARD, 'grid grid-cols-2 sm:grid-cols-4 gap-3 items-end']">
            <div>
                <label :for="`block-${index}-dl-layout`" :class="LABEL">Layout</label>
                <select :id="`block-${index}-dl-layout`" v-model="block.layout" :class="SELECT">
                    <option value="list">List</option>
                    <option value="cards">Two-column cards</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-dl-sort`" :class="LABEL">Order</label>
                <select :id="`block-${index}-dl-sort`" v-model="block.sort" :class="SELECT">
                    <option value="manual">As listed below</option>
                    <option value="newest">Newest first</option>
                    <option value="name">A to Z</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-dl-open`" :class="LABEL">Opens</label>
                <select :id="`block-${index}-dl-open`" v-model="block.open_in" :class="SELECT">
                    <option value="new_tab">In a new tab (PDFs show in the browser)</option>
                    <option value="download">As a download</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-dl-button`" :class="LABEL">Button label</label>
                <input :id="`block-${index}-dl-button`" v-model="block.button_label" type="text" maxlength="60" placeholder="Download" :class="SMALL_INPUT" />
            </div>
            <label :class="CHECK_LABEL"><input v-model="block.show_type" type="checkbox" :class="CHECK" /> File type</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_size" type="checkbox" :class="CHECK" /> File size</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_date" type="checkbox" :class="CHECK" /> Date added</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_search" type="checkbox" :class="CHECK" /> Search box (over 6 items)</label>
        </div>

        <div :class="[CARD, 'border-amber-300 dark:border-amber-800/60 bg-amber-50/40 dark:bg-amber-950/20 space-y-1']">
            <label :class="CHECK_LABEL"><input v-model="block.members_only" type="checkbox" :class="CHECK" /> 🔒 Members only</label>
            <p :class="HINT">Only signed-in active members of this club can open the <strong>uploaded</strong> files or even see this list. Visitors see a "log in" notice instead. Link items point elsewhere, so this cannot protect them.</p>
        </div>

        <div v-for="(item, i) in block.items" :key="item.id" :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-500">
                    {{ item.source === 'upload' ? '📎 Uploaded file' : '🔗 Link' }}
                    <span v-if="item.source === 'upload'" class="font-normal">· {{ item.file_name }} <span v-if="size(item.size)">({{ size(item.size) }})</span></span>
                </span>
                <div class="flex items-center gap-1.5">
                    <button type="button" :class="MINI_BUTTON" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">▲</button>
                    <button type="button" :class="MINI_BUTTON" :disabled="i === block.items.length - 1" aria-label="Move down" @click="move(i, 1)">▼</button>
                    <button type="button" :class="MINI_DANGER" aria-label="Remove" @click="removeItem(i)">🗑️</button>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <input v-model="item.title" type="text" maxlength="200" placeholder="Title" :class="[SMALL_INPUT, 'font-bold sm:col-span-2']" :aria-label="`Title of document ${i + 1}`" />
                <input v-model="item.group" type="text" maxlength="100" placeholder="Group, e.g. Minutes" :class="SMALL_INPUT" :aria-label="`Group of document ${i + 1}`" />
            </div>
            <input v-model="item.description" type="text" maxlength="500" placeholder="Description (optional)" :class="SMALL_INPUT" :aria-label="`Description of document ${i + 1}`" />
            <div v-if="item.source === 'link'" class="flex items-center gap-2">
                <input v-model="item.url" type="text" placeholder="https://... link to the document" :class="[SMALL_INPUT, 'font-mono text-[11px]']" :aria-label="`Link of document ${i + 1}`" />
                <button type="button" :class="[MINI_BUTTON, 'shrink-0']" @click="emit('media', 'download_link', item)">📁 Media Library</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <input ref="fileInput" type="file" multiple class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.rtf,.zip,.jpg,.jpeg,.png,.gif,.webp" @change="upload" />
            <button type="button" :disabled="uploading || block.items.length >= MAX_ITEMS" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-[11px] cursor-pointer disabled:opacity-50" @click="fileInput.click()">⬆️ Upload files</button>
            <button type="button" :disabled="block.items.length >= MAX_ITEMS" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-600 dark:text-slate-300 font-bold text-[11px] cursor-pointer disabled:opacity-40" @click="addLink">🔗 Add a link</button>
            <span v-if="status" class="text-[11px] text-slate-500" role="status">{{ status }}</span>
        </div>
        <p v-if="error" class="text-[11px] font-bold text-rose-600 dark:text-rose-400" role="alert">{{ error }}</p>
        <p :class="HINT">Uploads are stored privately (up to 10 MB each, counted in your storage). Removing an item here does not delete its file from storage.</p>
    </div>
</template>
