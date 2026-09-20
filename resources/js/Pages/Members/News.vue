<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChip from '@/Components/Ui/ClubChip.vue';
import ClubChips from '@/Components/ClubChips.vue';
import DateTile from '@/Components/Ui/DateTile.vue';

const props = defineProps({
    posts: { type: Object, required: true },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const urlScoped = computed(() => Boolean(route().params.slug));

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

            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.news" />

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
                                    <div class="flex flex-wrap items-center gap-2 text-xs">
                                        <ClubChip :name="post.club.name" :colour="post.club.colour" />
                                        <Badge v-if="post.is_new" variant="success">New</Badge>
                                        <Badge v-if="post.visibility.value === 'public'" variant="neutral">Public</Badge>
                                        <span class="text-slate-500 dark:text-slate-400">{{ ago(post.at) }}</span>
                                    </div>

                                    <h3 class="text-lg font-semibold leading-snug text-slate-900 dark:text-white">
                                        <Link :href="route('member.posts.show', { slug: post.club.slug, id: post.id })" class="hover:text-blue-600 dark:hover:text-blue-300">{{ post.title }}</Link>
                                    </h3>

                                    <p v-if="post.excerpt" class="line-clamp-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ post.excerpt }}</p>

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
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No news yet.</p></Card>

            <div v-if="posts.prev_page_url || posts.next_page_url" class="flex justify-between">
                <Link v-if="posts.prev_page_url" :href="posts.prev_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="posts.next_page_url" :href="posts.next_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
