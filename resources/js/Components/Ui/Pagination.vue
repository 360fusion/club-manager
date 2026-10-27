<script setup>
import { Link } from '@inertiajs/vue3';

// Page links from a Laravel paginator (`links` is its `links` array). Renders nothing when there is only one page.
defineProps({
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1.5" aria-label="Pages">
        <template v-for="link in links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                :class="['rounded-lg border px-3 py-1.5 text-sm', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800']"
            ><span v-html="link.label" /></Link>
            <span v-else class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800"><span v-html="link.label" /></span>
        </template>
    </nav>
</template>
