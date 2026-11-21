<script setup>
// A row of links between the pages of one area (Meetings, Communications, Members). Each item is
// { key, label, href, reload? }: `reload` makes it a full page load, for pages that are not Inertia (the Livewire ones).
// resources/views/components/tabs.blade.php is the same bar for Livewire views; keep the two in step.
// On a phone the tabs are a list, one per line with light lines between and the current one highlighted across
// the full width; from tablet width they sit in one row.
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, required: true },
    active: { type: String, default: '' },
    label: { type: String, default: 'Sections' },
});

const tabClass = (isActive) => [
    'flex w-full items-center rounded-xl px-4 py-3 text-sm font-semibold transition-colors sm:inline-flex sm:w-auto sm:whitespace-nowrap sm:py-2 sm:text-xs',
    isActive
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];
</script>

<template>
    <nav :aria-label="label" class="max-w-full sm:overflow-x-auto">
        <div class="flex flex-col divide-y divide-slate-300/70 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 sm:w-fit sm:flex-row sm:items-center sm:gap-1 sm:divide-y-0 dark:divide-slate-700/70 dark:border-slate-700/60 dark:bg-slate-800/80">
            <template v-for="item in items" :key="item.key">
                <a v-if="item.reload" :href="item.href" :class="tabClass(item.key === active)" :aria-current="item.key === active ? 'page' : undefined">{{ item.label }}</a>
                <Link v-else :href="item.href" :class="tabClass(item.key === active)" :aria-current="item.key === active ? 'page' : undefined">{{ item.label }}</Link>
            </template>
        </div>
    </nav>
</template>
