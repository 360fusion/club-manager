<script setup>
defineProps({
    groups: { type: Array, default: () => [] },
    checkedOn: { type: String, default: null },
});
</script>

<template>
    <section v-if="groups.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Where this comes from</h2>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Listings are collected from these pages and can be out of date. Confirm the day, time and place before you travel<span v-if="checkedOn"> (last checked {{ checkedOn }})</span>.
        </p>
        <div v-for="group in groups" :key="group.tier" class="mt-4">
            <h3 class="font-medium">{{ group.title }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ group.note }}</p>
            <ul class="mt-2 space-y-2">
                <li v-for="link in group.links" :key="link.url">
                    <a :href="link.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">{{ link.label }} ↗</a>
                    <span v-if="link.is_search" class="block text-xs text-slate-500 dark:text-slate-400">A search: pick the lodge from the results.</span>
                </li>
            </ul>
        </div>
    </section>
</template>
