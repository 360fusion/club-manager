<script setup>
import { Head, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';

// How long pages take on members' own devices (Support/pageTimings.js): slowest pages first.
const props = defineProps({
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ days: 7, device: '' }) },
});

const KIND = { load: 'Opened fresh', visit: 'Tap in the app' };
const ms = (value) => (value === null || value === undefined ? '—' : value >= 1000 ? `${(value / 1000).toFixed(1)}s` : `${value}ms`);
const filter = (changes) => router.get(route('superadmin.page_speed.index'), { ...props.filters, ...changes }, { preserveState: true, replace: true });
const selectClass = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950';
</script>

<template>
    <SuperAdminLayout title="Page speed">
        <Head title="Superadmin - Page speed" />

        <div class="space-y-6">
            <PageHeader title="Page speed" description="How long pages take on members' own phones and computers: the typical time (median) and the slow end (three in four are faster). No person or address is recorded." />

            <div class="flex flex-wrap gap-2">
                <select :value="filters.days" :class="selectClass" aria-label="Period" @change="filter({ days: $event.target.value })">
                    <option :value="1">Last day</option>
                    <option :value="7">Last 7 days</option>
                    <option :value="30">Last 30 days</option>
                </select>
                <select :value="filters.device" :class="selectClass" aria-label="Device" @change="filter({ device: $event.target.value || undefined })">
                    <option value="">Phones and computers</option>
                    <option value="phone">Phones</option>
                    <option value="desktop">Computers</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                <StatCard label="Timings" :value="totals.count ?? 0" />
                <StatCard label="Opened fresh, typical" :value="ms(totals.load_median)" />
                <StatCard label="Tap in the app, typical" :value="ms(totals.visit_median)" />
                <StatCard label="Tap in the app, slow end" :value="ms(totals.visit_p75)" />
            </div>

            <Card padding="none" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Page</th>
                                <th class="px-4 py-3 font-medium">How</th>
                                <th class="px-4 py-3 text-right font-medium">Times</th>
                                <th class="px-4 py-3 text-right font-medium">Typical</th>
                                <th class="px-4 py-3 text-right font-medium">Slow end</th>
                                <th class="px-4 py-3 text-right font-medium">Server</th>
                                <th class="px-4 py-3 text-right font-medium">Fetched ahead</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in rows" :key="row.page + row.kind">
                                <td class="px-4 py-2.5 font-medium text-slate-900 dark:text-white">{{ row.page }}</td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ KIND[row.kind] ?? row.kind }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.count }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ ms(row.median) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ ms(row.p75) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ ms(row.server_median) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.kind === 'visit' ? `${row.cached_share}%` : '—' }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No timings yet. They arrive as members use the app.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </SuperAdminLayout>
</template>
