<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

defineProps({
    club: { type: Object, required: true },
    // One page of the club's activity (a Laravel paginator), newest first: { by, summary, at }.
    events: { type: Object, required: true },
});

const when = (iso) => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <AdminLayout title="Activity" :club="club" active-tab="activity">
        <Head title="Activity" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">Activity</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Who joined or left the club, and whose role or status changed.</p>
            </div>

            <p v-if="!events.data.length" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Nothing has happened yet. Changes to members will show here.</p>
            <ul v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                <li v-for="event in events.data" :key="event.id" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 text-sm">
                    <span><strong class="text-slate-900 dark:text-white">{{ event.by }}</strong> {{ event.summary }}</span>
                    <time :datetime="event.at" class="text-xs text-slate-500 dark:text-slate-400">{{ when(event.at) }}</time>
                </li>
            </ul>

            <Pagination :links="events.links" />
        </div>
    </AdminLayout>
</template>
