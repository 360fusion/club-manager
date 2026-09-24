<script setup>
import { Link } from '@inertiajs/vue3';
import ReferenceLinks from '@/Components/ReferenceLinks.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    hall: { type: Object, required: true },
    lodges: { type: Array, default: () => [] },
});
</script>

<template>
    <PublicLayout :title="hall.name" :description="`${hall.name}, ${hall.address}: the lodges and chapters that meet here.`">
        <div class="space-y-6">
            <div>
                <Link :href="route('lodges.index')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">← All lodges</Link>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ hall.name }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ hall.kind }}<span v-if="hall.province"> · {{ hall.province }}</span></p>
                <p class="mt-2 text-sm">{{ hall.address }}</p>
                <div class="mt-2 flex gap-4 text-sm">
                    <a v-if="hall.map_url" :href="hall.map_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">Open in maps ↗</a>
                    <a v-if="hall.website_url" :href="hall.website_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">Website ↗</a>
                </div>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Meeting here ({{ lodges.length }})</h2>

                <p v-if="!lodges.length" class="mt-3 text-sm text-slate-500">No lodges are listed at this hall yet.</p>

                <ul v-else class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="lodge in lodges" :key="lodge.slug" class="py-3">
                        <Link :href="route('lodges.show', lodge.slug)" class="font-medium hover:underline">{{ lodge.name }}</Link>
                        <span class="text-xs text-slate-500 dark:text-slate-400"> · {{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span></span>
                        <p v-if="lodge.meets_text" class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ lodge.meets_text }}</p>
                    </li>
                </ul>
            </section>

            <ReferenceLinks :groups="hall.references" />
        </div>
    </PublicLayout>
</template>
