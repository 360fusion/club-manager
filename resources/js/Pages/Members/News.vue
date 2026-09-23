<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChip from '@/Components/Ui/ClubChip.vue';
import Input from '@/Components/Ui/Input.vue';
import NewsTagPill from '@/Components/NewsTagPill.vue';
import Select from '@/Components/Ui/Select.vue';
import { orderColour } from '@/Utils/orderColour';
import DateTile from '@/Components/Ui/DateTile.vue';

const props = defineProps({
    posts: { type: Object, required: true },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', tags: [], from: null, to: null, club: null }) },
    tagOptions: { type: Array, default: () => [] },
});

const urlScoped = computed(() => Boolean(route().params.slug));

const search = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const club = ref(urlScoped.value ? '' : (props.filters.club ?? ''));
const tags = ref([...(props.filters.tags ?? [])]);

const hasFilters = computed(() => Boolean(search.value || from.value || to.value || club.value || tags.value.length));

const clubSelectOptions = computed(() => [{ value: '', label: 'All clubs' }, ...props.clubOptions.map((option) => ({ value: option.slug, label: option.name }))]);

// Filters live in the query string so a filtered list can be linked to and survives paging.
const apply = () => {
    const query = {
        q: search.value.trim() || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        club: urlScoped.value ? undefined : (club.value || undefined),
        tags: tags.value.length ? tags.value : undefined,
    };
    const url = urlScoped.value ? route('member.news', { slug: route().params.slug }) : route('members.news');

    // Nothing to fetch when the filters already match what is showing (e.g. after a tag link was followed).
    const current = props.filters;
    if (
        (query.q ?? '') === (current.q ?? '') && (query.from ?? '') === (current.from ?? '') && (query.to ?? '') === (current.to ?? '')
        && (query.club ?? '') === (urlScoped.value ? '' : (current.club ?? '')) && JSON.stringify(query.tags ?? []) === JSON.stringify(current.tags ?? [])
    ) {
        return;
    }

    router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
};

// Links elsewhere on the page (tag pills) change the filters without going through this form.
watch(() => props.filters, (next) => {
    search.value = next.q ?? '';
    from.value = next.from ?? '';
    to.value = next.to ?? '';
    club.value = urlScoped.value ? '' : (next.club ?? '');
    tags.value = [...(next.tags ?? [])];
});

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch([from, to, club], apply);

const toggleTag = (slug) => {
    tags.value = tags.value.includes(slug) ? tags.value.filter((t) => t !== slug) : [...tags.value, slug];
    apply();
};

const clearFilters = () => {
    clearTimeout(timer);
    search.value = '';
    from.value = '';
    to.value = '';
    club.value = '';
    tags.value = [];
    apply();
};

const tagChip = (active) => ['inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors cursor-pointer', active ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'];

const rtf = new Intl.RelativeTimeFormat('en-GB', { numeric: 'auto' });

const ago = (iso) => {
    const days = Math.round((new Date(iso) - new Date()) / 86400000);

    if (Math.abs(days) < 1) return 'today';
    if (Math.abs(days) < 30) return rtf.format(days, 'day');
    if (Math.abs(days) < 365) return rtf.format(Math.round(days / 30), 'month');

    return rtf.format(Math.round(days / 365), 'year');
};

// Group into months so a long list has clear breaks.
const groups = computed(() => {
    const byMonth = new Map();

    for (const post of props.posts.data) {
        const label = new Date(post.at).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });

        if (!byMonth.has(label)) byMonth.set(label, []);
        byMonth.get(label).push(post);
    }

    return [...byMonth].map(([label, items]) => ({ label, items }));
});
</script>

<template>
    <MembersLayout title="News" :club="urlScoped ? scopeClub : null" active-tab="news">
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">News</h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ posts.total }} {{ posts.total === 1 ? 'article' : 'articles' }} from {{ scopeClub ? scopeClub.name : 'your clubs' }}</p>
                </div>
            </div>

            <form role="search" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="apply">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))_minmax(0,1.2fr)]">
                    <Input v-model="search" type="search" label="Search" placeholder="Search news…" />
                    <Input v-model="from" type="date" label="From" />
                    <Input v-model="to" type="date" label="To" />
                    <Select v-if="!urlScoped && clubOptions.length > 1" v-model="club" label="Lodge / club" :options="clubSelectOptions" />
                </div>

                <div v-if="tagOptions.length" class="space-y-1.5">
                    <p class="text-xs font-medium text-slate-700 dark:text-slate-300">Tags</p>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by tag">
                        <button v-for="tag in tagOptions" :key="tag.slug" type="button" :aria-pressed="tags.includes(tag.slug)" :class="tagChip(tags.includes(tag.slug))" @click="toggleTag(tag.slug)">
                            <span :class="['h-1.5 w-1.5 rounded-full', orderColour(tag.color).dot]" aria-hidden="true" />{{ tag.name }}<span class="opacity-60">{{ tag.count }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="hasFilters" class="text-right">
                    <button type="button" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="clearFilters">Clear filters</button>
                </div>
            </form>

            <div v-if="groups.length" class="space-y-8">
                <section v-for="group in groups" :key="group.label" :aria-label="group.label" class="space-y-4">
                    <h2 class="flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ group.label }}
                        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true" />
                    </h2>

                    <ul class="space-y-4">
                        <li v-for="post in group.items" :key="post.id">
                            <article class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900 sm:gap-5 sm:p-5">
                                <DateTile :date="post.at" :colour="post.club.colour" />

                                <div class="min-w-0 flex-1 space-y-2">
                                    <h3 class="text-lg font-semibold leading-snug text-slate-900 dark:text-white">
                                        <Link :href="route('member.posts.show', { slug: post.club.slug, id: post.id })" class="hover:text-blue-600 dark:hover:text-blue-300">{{ post.title }}</Link>
                                    </h3>

                                    <div class="flex flex-wrap items-center gap-2 text-xs">
                                        <ClubChip :name="post.club.name" :colour="post.club.colour" />
                                        <Badge v-if="post.is_new" variant="success">New</Badge>
                                        <Badge v-if="post.visibility.value === 'public'" variant="neutral">Public</Badge>
                                        <span class="text-slate-500 dark:text-slate-400">{{ ago(post.at) }}</span>
                                    </div>

                                    <p v-if="post.excerpt" class="line-clamp-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ post.excerpt }}</p>

                                    <div v-if="post.tags.length" class="flex flex-wrap gap-1.5">
                                        <NewsTagPill v-for="tag in post.tags" :key="tag.slug" :tag="tag" :club-slug="urlScoped ? post.club.slug : null" />
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs text-slate-500 dark:text-slate-400">
                                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <span v-if="post.author">By {{ post.author }}</span>
                                            <span>{{ post.reading_minutes }} min read</span>
                                            <span v-if="post.attachments">{{ post.attachments }} {{ post.attachments === 1 ? 'attachment' : 'attachments' }}</span>
                                        </p>
                                        <Link :href="route('member.posts.show', { slug: post.club.slug, id: post.id })" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">Read article →</Link>
                                    </div>
                                </div>

                                <Link v-if="post.cover" :href="route('member.posts.show', { slug: post.club.slug, id: post.id })" class="hidden h-28 w-40 shrink-0 overflow-hidden rounded-xl bg-slate-100 md:block dark:bg-slate-800" tabindex="-1" aria-hidden="true">
                                    <img :src="post.cover" alt="" class="h-full w-full object-cover transition-transform group-hover:scale-105" loading="lazy" />
                                </Link>
                            </article>
                        </li>
                    </ul>
                </section>
            </div>
            <Card v-else>
                <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ hasFilters ? 'Nothing matches those filters.' : 'No news yet.' }}</p>
            </Card>

            <div v-if="posts.prev_page_url || posts.next_page_url" class="flex justify-between">
                <Link v-if="posts.prev_page_url" :href="posts.prev_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="posts.next_page_url" :href="posts.next_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
