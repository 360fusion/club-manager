<script setup>
// Picks one of the builder's icons: a button showing the current icon opens a searchable grid. Emits the icon key
// through v-model ("" for none).
import { computed, ref } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';
import { BLOCK_ICONS } from '@/Support/icons';
import { SMALL_INPUT } from './styles';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Icon' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');

const entries = Object.entries(BLOCK_ICONS).map(([key, icon]) => ({ key, label: icon.label }));
const shown = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term ? entries.filter((e) => e.key.includes(term) || e.label.toLowerCase().includes(term)) : entries;
});

const pick = (key) => {
    emit('update:modelValue', key);
    open.value = false;
    search.value = '';
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex h-9 w-full items-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-2.5 text-left text-xs font-semibold text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200 cursor-pointer"
            :aria-expanded="open"
            :aria-label="`${label}: ${modelValue ? BLOCK_ICONS[modelValue]?.label || modelValue : 'none'}`"
            @click="open = !open"
        >
            <span class="flex h-5 w-5 items-center justify-center text-base text-amber-600 dark:text-amber-400"><Icon v-if="modelValue" :name="modelValue" /><span v-else class="text-slate-400">–</span></span>
            <span class="truncate">{{ modelValue ? BLOCK_ICONS[modelValue]?.label : 'No icon' }}</span>
        </button>

        <div v-if="open" class="absolute left-0 z-30 mt-1 w-72 max-w-[85vw] space-y-2 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <input v-model="search" type="search" placeholder="Search icons, e.g. heart" :class="SMALL_INPUT" aria-label="Search icons" />
            <div class="grid max-h-56 grid-cols-6 gap-1 overflow-y-auto" role="listbox" :aria-label="`${label} choices`">
                <button
                    type="button"
                    class="flex h-9 items-center justify-center rounded-lg border border-dashed border-slate-300 text-[10px] font-bold text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800 cursor-pointer"
                    title="No icon"
                    @click="pick('')"
                >None</button>
                <button
                    v-for="entry in shown"
                    :key="entry.key"
                    type="button"
                    role="option"
                    :aria-selected="entry.key === modelValue"
                    :title="entry.label"
                    :class="['flex h-9 items-center justify-center rounded-lg text-lg text-slate-700 hover:bg-amber-50 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer', entry.key === modelValue ? 'bg-amber-100 ring-1 ring-amber-500 dark:bg-amber-950/40' : '']"
                    @click="pick(entry.key)"
                ><Icon :name="entry.key" /><span class="sr-only">{{ entry.label }}</span></button>
            </div>
            <p v-if="!shown.length" class="px-1 text-[11px] text-slate-400">No icon matches “{{ search }}”.</p>
        </div>
    </div>
</template>
