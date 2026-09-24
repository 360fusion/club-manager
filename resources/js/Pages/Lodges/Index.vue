<script setup>
import { reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';

const props = defineProps({
    lodges: { type: Object, required: true },
    filters: { type: Object, required: true },
    provinces: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
});

const form = reactive({ ...props.filters });
let timer = null;

function search() {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value));
    router.get(route('lodges.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => form.q, () => {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
});

const inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900';
</script>

<template>
    <PublicLayout title="Find a lodge" description="Find a lodge or chapter, see where and when it meets, and plan a visit.">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Find a lodge</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ total }} lodges and chapters. Search by name, number, town or postcode, then see where and when they meet.
                </p>
            </div>

            <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
                <input v-model="form.q" type="search" placeholder="Name, number, town or postcode" :class="[inputClass, 'lg:col-span-2']" aria-label="Search" />
                <select v-model="form.province" @change="search" :class="inputClass" aria-label="Province">
                    <option value="">All provinces</option>
                    <option v-for="p in provinces" :key="p.code" :value="p.code">{{ p.name }} ({{ p.count }})</option>
                </select>
                <select v-model="form.order" @change="search" :class="inputClass" aria-label="Order">
                    <option value="">All orders</option>
                    <option v-for="o in orders" :key="o.code" :value="o.code">{{ o.name }} ({{ o.count }})</option>
                </select>
                <select v-model="form.day" @change="search" :class="inputClass" aria-label="Day of the week">
                    <option value="">Any day</option>
                    <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
                </select>
                <select v-model="form.when" @change="search" :class="inputClass" aria-label="When">
                    <option value="">Any time</option>
                    <option value="week">Meeting in the next 7 days</option>
                    <option value="month">Meeting in the next month</option>
                </select>
            </div>

            <div v-if="lodges.data.length === 0" class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                No lodges match. Try fewer filters.
            </div>

            <ul v-else class="grid gap-3 md:grid-cols-2">
                <li v-for="lodge in lodges.data" :key="lodge.slug" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="route('lodges.show', lodge.slug)" class="block truncate text-base font-semibold hover:text-blue-600 dark:hover:text-blue-400">{{ lodge.name }}</Link>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span><span v-if="lodge.province"> · {{ lodge.province }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <Badge v-if="lodge.following" variant="info">Following</Badge>
                            <Badge v-if="lodge.is_managed" variant="success">On ClubManager</Badge>
                        </div>
                    </div>

                    <p v-if="lodge.hall" class="mt-3 text-sm">
                        <Link :href="route('lodges.hall', lodge.hall.slug)" class="font-medium hover:underline">{{ lodge.hall.name }}</Link>
                        <span class="text-slate-500 dark:text-slate-400"> · {{ lodge.hall.town }} {{ lodge.hall.postcode }}</span>
                    </p>

                    <p v-if="lodge.next.length" class="mt-2 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Next expected: </span>
                        <span class="font-medium">{{ formatMeetingDate(lodge.next[0].date, lodge.next[0].time) }}</span>
                        <Badge v-if="lodge.next[0].installation" variant="warning" class="ml-1">Installation</Badge>
                    </p>
                    <p v-else-if="lodge.meets_text" class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ lodge.meets_text }}</p>
                    <p v-else class="mt-2 text-sm text-slate-400">Meeting times not listed yet.</p>
                </li>
            </ul>

            <nav v-if="lodges.links.length > 3" class="flex flex-wrap gap-1.5" aria-label="Pages">
                <template v-for="link in lodges.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        :class="['rounded-lg border px-3 py-1.5 text-sm', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800']"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800" v-html="link.label" />
                </template>
            </nav>
        </div>
    </PublicLayout>
</template>
