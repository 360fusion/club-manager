<script setup>
// A pop-up with a checkbox for each page in a list, used to pick which pages a menu shows. `selected` is the list of
// chosen page ids, or null meaning "all of them"; applying with every page ticked gives null back, so pages added to the
// site later are included automatically. With `single` it picks exactly one page (radio buttons) and applies its id
// alone; `selected` is then [id] or null.
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Choose pages' },
    intro: { type: String, default: '' },
    pages: { type: Array, default: () => [] },
    selected: { type: Array, default: null },
    single: { type: Boolean, default: false },
    emptyText: { type: String, default: 'No pages are in the top menu yet. Tick "Show in top header menu" on a page first.' },
});

const emit = defineEmits(['close', 'apply']);

const ticked = ref(new Set());

watch(() => props.show, (open) => {
    if (open) ticked.value = new Set(props.selected === null ? (props.single ? [] : props.pages.map((p) => p.id)) : props.selected);
});

const allTicked = computed(() => props.pages.length > 0 && props.pages.every((p) => ticked.value.has(p.id)));

const toggle = (id) => {
    if (props.single) {
        ticked.value = new Set([id]);
        return;
    }

    const next = new Set(ticked.value);
    if (!next.delete(id)) next.add(id);
    ticked.value = next;
};

const tickAll = () => { ticked.value = new Set(props.pages.map((p) => p.id)); };
const tickNone = () => { ticked.value = new Set(); };

const apply = () => {
    if (props.single) {
        emit('apply', [...ticked.value]);
        return;
    }

    // Keep the site's own order, and treat "everything ticked" as "all pages".
    emit('apply', allTicked.value ? null : props.pages.filter((p) => ticked.value.has(p.id)).map((p) => p.id));
};
</script>

<template>
    <Teleport to="body">
        <!-- Above the builder's own z-[100] overlays, as before. -->
        <div v-if="show" class="relative z-[100]">
            <Modal :open="show" :title="title" :subtitle="intro" @close="emit('close')">
                <div v-if="!single" class="mb-3 flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-500 dark:text-slate-400">{{ ticked.size }} of {{ pages.length }} selected</span>
                    <span class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" :disabled="allTicked" @click="tickAll">Select all</Button>
                        <Button variant="ghost" size="sm" :disabled="ticked.size === 0" @click="tickNone">Clear</Button>
                    </span>
                </div>

                <ul class="space-y-1.5">
                    <li v-if="!pages.length" class="text-xs text-slate-400 italic p-2">{{ emptyText }}</li>
                    <li v-for="page in pages" :key="page.id">
                        <label class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer select-none text-sm font-medium text-slate-800 dark:text-slate-100">
                            <input :type="single ? 'radio' : 'checkbox'" name="page-chooser" :checked="ticked.has(page.id)" class="w-4 h-4 rounded border-slate-300 accent-blue-600 cursor-pointer dark:border-slate-700" @change="toggle(page.id)" />
                            <span class="truncate">{{ page.title }}</span>
                            <span v-if="page.is_homepage" class="ml-auto text-[10px] font-semibold uppercase tracking-wider text-slate-400">Home</span>
                            <span v-else-if="page.note" class="ml-auto text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ page.note }}</span>
                        </label>
                    </li>
                </ul>

                <template #footer>
                    <Button variant="secondary" @click="emit('close')">Cancel</Button>
                    <Button :disabled="single && ticked.size === 0" @click="apply">{{ single ? 'Use this page' : 'Done' }}</Button>
                </template>
            </Modal>
        </div>
    </Teleport>
</template>
