<script setup>
// A pop-up with a checkbox for each page in a list, used to pick which pages a menu shows. `selected` is the list of
// chosen page ids, or null meaning "all of them"; applying with every page ticked gives null back, so pages added to the
// site later are included automatically. With `single` it picks exactly one page (radio buttons) and applies its id
// alone; `selected` is then [id] or null.
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Choose pages' },
    intro: { type: String, default: '' },
    pages: { type: Array, default: () => [] },
    selected: { type: Array, default: null },
    single: { type: Boolean, default: false },
    emptyText: { type: String, default: 'No pages are in the top menu yet. Tick "Show in Top Header Menu" on a page first.' },
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
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm" @click.self="emit('close')" @keydown.esc="emit('close')">
            <div role="dialog" aria-modal="true" aria-labelledby="page-chooser-title" class="w-full max-w-md max-h-[85vh] flex flex-col bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl">
                <div class="flex items-start justify-between gap-4 p-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 id="page-chooser-title" class="text-lg font-bold text-slate-900 dark:text-white">{{ title }}</h2>
                        <p v-if="intro" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ intro }}</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1 cursor-pointer" aria-label="Close" @click="emit('close')">✕</button>
                </div>

                <div v-if="!single" class="px-6 pt-4 flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-500 dark:text-slate-400">{{ ticked.size }} of {{ pages.length }} selected</span>
                    <span class="flex items-center gap-3">
                        <button type="button" class="font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" :disabled="allTicked" @click="tickAll">Select all</button>
                        <button type="button" class="font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" :disabled="ticked.size === 0" @click="tickNone">Clear</button>
                    </span>
                </div>

                <ul class="p-4 overflow-y-auto space-y-1.5">
                    <li v-if="!pages.length" class="text-xs text-slate-400 italic p-2">{{ emptyText }}</li>
                    <li v-for="page in pages" :key="page.id">
                        <label class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer select-none text-sm font-semibold text-slate-800 dark:text-slate-100">
                            <input :type="single ? 'radio' : 'checkbox'" name="page-chooser" :checked="ticked.has(page.id)" class="w-4 h-4 rounded accent-blue-600 cursor-pointer" @change="toggle(page.id)" />
                            <span class="truncate">{{ page.title }}</span>
                            <span v-if="page.is_homepage" class="ml-auto text-[10px] font-bold uppercase tracking-wider text-slate-400">Home</span>
                            <span v-else-if="page.note" class="ml-auto text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ page.note }}</span>
                        </label>
                    </li>
                </ul>

                <div class="flex items-center justify-end gap-2 p-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer" @click="emit('close')">Cancel</button>
                    <button type="button" :disabled="single && ticked.size === 0" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" @click="apply">{{ single ? 'Use this page' : 'Done' }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
