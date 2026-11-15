<script setup>
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { BLOCK_ICONS, iconButton } from '@/Support/pageEditorFields';

// An insert point between the page's elements, with the menu of elements to add there.
const activeInsertIndex = defineModel('activeInsertIndex', { type: [Number, null], default: null });

defineProps({
    index: { type: Number, required: true },
    label: { type: String, default: 'Insert an element here' },
    blockColumns: { type: Array, required: true },
    insertMenuPlacement: { type: String, default: 'down' },
    insertMenuShift: { type: Number, default: 0 },
    addBlock: { type: Function, required: true },
    toggleInsertMenu: { type: Function, required: true },
});
</script>

<template>
    <div class="relative py-1 flex items-center justify-center insert-menu-container">
        <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800"></div>
        </div>
        <div class="relative flex justify-center">
            <button
                type="button"
                @click.stop="toggleInsertMenu(index, $event)"
                class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 rounded-full text-xs font-medium shadow-sm transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            >
                <UiIcon name="plus" :size="14" class="text-blue-600 dark:text-blue-400" />
                <span>{{ label }}</span>
            </button>

            <!-- Insert menu -->
            <div
                v-if="activeInsertIndex === index"
                :class="[
                    'absolute z-40 w-[1100px] max-w-[calc(100vw-3.5rem)] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                    insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                ]"
                :style="{ translate: `calc(-50% + ${insertMenuShift}px) 0` }"
            >
                <div class="px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                    <span>Insert an element here</span>
                    <button type="button" aria-label="Close" @click="activeInsertIndex = null" :class="iconButton"><UiIcon name="x" /></button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-1">
                    <div v-for="(col, cIdx) in blockColumns" :key="cIdx" class="space-y-1.5">
                        <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                            {{ col.title }}
                        </div>
                        <div class="space-y-1">
                            <button
                                v-for="item in col.items"
                                :key="item.type"
                                type="button"
                                @click="addBlock(item.type, index)"
                                class="w-full flex items-start gap-2.5 p-2 text-left text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-blue-200 dark:hover:border-blue-800/60 rounded-xl transition-colors cursor-pointer group"
                            >
                                <span class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-slate-100 text-blue-600 dark:bg-slate-800 dark:text-blue-400">
                                    <UiIcon :name="BLOCK_ICONS[item.type]" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block font-medium text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">{{ item.label }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight block line-clamp-2">{{ item.desc }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
