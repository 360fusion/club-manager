<script setup>
// A row of links between the pages of one area (Meetings, Communications, Members). Each item is
// { key, label, href, reload? }: `reload` makes it a full page load, for pages that are not Inertia (the Livewire ones).
// resources/views/components/tabs.blade.php is the same bar for Livewire views; keep the two in step.
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, required: true },
    active: { type: String, default: '' },
    label: { type: String, default: 'Sections' },
});

const tabClass = (isActive) => [
    'inline-flex items-center rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
    isActive
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];
</script>

<template>
    <nav :aria-label="label" class="max-w-full overflow-x-auto">
        <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
            <template v-for="item in items" :key="item.key">
                <a v-if="item.reload" :href="item.href" :class="tabClass(item.key === active)" :aria-current="item.key === active ? 'page' : undefined">{{ item.label }}</a>
                <Link v-else :href="item.href" :class="tabClass(item.key === active)" :aria-current="item.key === active ? 'page' : undefined">{{ item.label }}</Link>
            </template>
        </div>
    </nav>
</template>
