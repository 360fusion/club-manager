<script setup>
// The page editor's title bar with the Edit builder / Live preview switch, in its own file so the page stays readable.
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    club: { type: Object, required: true },
    form: { type: Object, required: true },
    activeNavSelection: { type: String, default: '' },
    pageViewMode: { type: String, default: 'edit' },
    selectionUrl: { type: Function, required: true },
    navClick: { type: Function, required: true },
});
</script>

<template>
    <!-- View Mode Selector Header Card -->
    <Card padding="none" class="px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                {{ form.id ? form.title : 'New page' }}
            </h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">/site/{{ club.slug }}/{{ form.slug }}</span>
        </div>

        <!-- Segmented Pill Control: Edit vs Live Preview -->
        <div class="inline-flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
            <a
                :href="selectionUrl(activeNavSelection, 'edit')"
                :aria-current="pageViewMode === 'edit' ? 'true' : undefined"
                @click="navClick($event, activeNavSelection, 'edit')"
                :class="[
                    'px-4 py-2 rounded-xl text-xs font-medium transition-colors flex items-center gap-2 cursor-pointer',
                    pageViewMode === 'edit' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                ]"
            >
                <UiIcon name="pencil" />
                <span>Edit builder</span>
            </a>

            <a
                :href="selectionUrl(activeNavSelection, 'preview')"
                :aria-current="pageViewMode === 'preview' ? 'true' : undefined"
                @click="navClick($event, activeNavSelection, 'preview')"
                :class="[
                    'px-4 py-2 rounded-xl text-xs font-medium transition-colors flex items-center gap-2 cursor-pointer',
                    pageViewMode === 'preview' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                ]"
            >
                <UiIcon name="eye" />
                <span>Live preview</span>
            </a>
        </div>
    </Card>
</template>
