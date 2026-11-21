<script setup>
import { debounce } from '@/Utils/debounce';
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';
import { paginationLabel } from '@/Utils/paginationLabel';

const props = defineProps({
    lodges: { type: Object, required: true },
    filters: { type: Object, required: true },
    provinces: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    // The distance search: the place that was found, or why not.
    near: { type: Object, default: () => ({ place: null, error: null }) },
    // Lodges a signed-in member might follow (their hall, their lodges' sister and visiting lists).
    suggested: { type: Array, default: () => [] },
});

const form = reactive({ ...props.filters });
const signedIn = computed(() => Boolean(usePage().props.auth?.user));
// The extra filters stay folded away unless one is in use.
const advanced = ref(['province', 'order', 'day', 'when', 'near'].some((key) => props.filters[key]));
const searching = computed(() => ['q', 'province', 'order', 'day', 'when', 'near'].some((key) => props.filters[key]));

const following = reactive({});
function follow(lodge) {
    following[lodge.slug] = true;
    router.post(route('lodges.follow', lodge.slug), {}, { preserveScroll: true, onFinish: () => { following[lodge.slug] = false; } });
}

// The place is only looked up when this is sent, never while typing (OpenStreetMap's usage policy).
function clearNear() {
    form.near = '';
    search();
}

function search() {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value));
    router.get(route('lodges.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => form.q, debounce(() => search(), 300));

const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950';
</script>

<template>
    <PublicLayout title="Directory" description="Find a lodge or chapter, see where and when it meets, and plan a visit.">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Directory</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ total }} lodges and chapters: where and when they meet.</p>
            </div>

            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="find-heading">
                <h2 id="find-heading" class="text-base font-semibold text-slate-900 dark:text-white">Find a lodge</h2>
                <form class="flex gap-2" role="search" @submit.prevent="search">
                    <label for="directory-search" class="sr-only">Name, number, hall, town or postcode</label>
                    <input id="directory-search" v-model="form.q" type="search" enterkeyhint="search" autocomplete="off" placeholder="Name, number, hall or town" :class="[inputClass, 'min-w-0 flex-1']" />
                    <Button type="submit">Find</Button>
                </form>
                <button type="button" class="inline-flex min-h-11 items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400" :aria-expanded="advanced" aria-controls="advanced-search" @click="advanced = !advanced">
                    {{ advanced ? 'Hide advanced search' : 'Advanced search' }}
                </button>

                <div v-show="advanced" id="advanced-search" class="grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800">
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
                    <form class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4" @submit.prevent="search">
                        <input v-model="form.near" type="search" placeholder="Near a postcode or town" :class="[inputClass, 'min-w-48 flex-1']" aria-label="Near a postcode or town" />
                        <select v-model="form.within" :class="[inputClass, 'w-auto']" aria-label="Distance">
                            <option v-for="miles in ['5', '10', '25', '50']" :key="miles" :value="miles">Within {{ miles }} miles</option>
                        </select>
                        <Button type="submit" variant="secondary">Search nearby</Button>
                        <Button v-if="filters.near" type="button" variant="ghost" @click="clearNear">Clear</Button>
                    </form>
                    <p v-if="near.error" class="text-sm text-rose-600 sm:col-span-2 lg:col-span-4 dark:text-rose-400">{{ near.error }}</p>
                    <p v-else-if="near.place" class="text-sm text-slate-500 sm:col-span-2 lg:col-span-4 dark:text-slate-400">Nearest first, from {{ near.place }}.</p>
                </div>
            </section>

            <section v-if="signedIn && suggested.length && !searching" class="space-y-2" aria-labelledby="suggested-heading">
                <div>
                    <h2 id="suggested-heading" class="text-lg font-semibold">Lodges you might follow</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">From your hall and your lodges' sister and visiting lists. Following puts their meeting dates in your calendar.</p>
                </div>
                <ul class="grid gap-3 md:grid-cols-2">
                    <li v-for="lodge in suggested" :key="lodge.slug" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="min-w-0">
                            <Link :href="route('lodges.show', lodge.slug)" class="block font-semibold hover:text-blue-600 dark:hover:text-blue-300">{{ lodge.name }}</Link>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ lodge.reason }}<template v-if="lodge.order"> · {{ lodge.order }}</template></p>
                        </div>
                        <Button size="sm" class="min-w-20 shrink-0" :disabled="following[lodge.slug]" @click="follow(lodge)">Follow</Button>
                    </li>
                </ul>
            </section>

            <h2 v-if="signedIn && suggested.length && !searching" class="text-lg font-semibold">All lodges</h2>

            <div v-if="lodges.data.length === 0" class="rounded-xl border border-dashed border-slate-300 p-5 sm:p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                No lodges match. Try fewer filters.
            </div>

            <ul v-else class="grid gap-3 md:grid-cols-2">
                <li v-for="lodge in lodges.data" :key="lodge.slug" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="route('lodges.show', lodge.slug)" class="block truncate text-base font-semibold hover:text-blue-600 dark:hover:text-blue-400">{{ lodge.name }}</Link>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span><span v-if="lodge.province"> · {{ lodge.province }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <Button v-if="signedIn && lodge.own_club_slug" size="sm" class="min-w-20" :href="route('member.dashboard', { slug: lodge.own_club_slug })">Open</Button>
                            <Badge v-else-if="lodge.following" variant="info">Following</Badge>
                            <Button v-else-if="signedIn" size="sm" class="min-w-20" :disabled="following[lodge.slug]" @click="follow(lodge)">Follow</Button>
                            <Badge v-if="lodge.is_managed" variant="success">On ClubManager</Badge>
                        </div>
                    </div>

                    <p v-if="lodge.hall" class="mt-3 text-sm">
                        <Link :href="route('lodges.hall', lodge.hall.slug)" class="font-medium hover:underline">{{ lodge.hall.name }}</Link>
                        <span class="text-slate-500 dark:text-slate-400"> · {{ lodge.hall.town }} {{ lodge.hall.postcode }}</span>
                        <span v-if="lodge.miles !== null && lodge.miles !== undefined" class="text-slate-500 dark:text-slate-400"> · {{ lodge.miles }} miles</span>
                    </p>

                    <p v-if="lodge.next.length" class="mt-2 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Next expected: </span>
                        <span class="font-medium">{{ formatMeetingDate(lodge.next[0].date, lodge.next[0].time) }}</span>
                        <Badge v-if="lodge.next[0].installation" variant="warning" class="ml-1">Installation</Badge>
                    </p>
                    <p v-else-if="lodge.meets_text" class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ lodge.meets_text }}</p>
                    <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Meeting times not listed yet.</p>
                </li>
            </ul>

            <nav v-if="lodges.links.length > 3" class="flex flex-wrap gap-1.5" aria-label="Pages">
                <template v-for="link in lodges.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        :class="['rounded-xl border px-3 py-1.5 text-sm', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800']"><span>{{ paginationLabel(link.label) }}</span></Link>
                    <span v-else class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-500 dark:text-slate-400 dark:border-slate-800">{{ paginationLabel(link.label) }}</span>
                </template>
            </nav>
        </div>
    </PublicLayout>
</template>
