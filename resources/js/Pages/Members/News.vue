<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChips from '@/Components/ClubChips.vue';

const props = defineProps({
    posts: { type: Object, required: true },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const urlScoped = computed(() => Boolean(route().params.slug));
const ago = (iso) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <MembersLayout title="News" :club="urlScoped ? scopeClub : null" active-tab="news">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">News</h1>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.news" />

            <Card v-if="posts.data.length" padding="none">
                <ul>
                    <li v-for="post in posts.data" :key="post.id" class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ post.club.name }} · <time :datetime="post.at">{{ ago(post.at) }}</time></p>
                        <Link :href="route('member.posts.show', { slug: post.club.slug, id: post.id })" class="mt-1 block text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ post.title }}</Link>
                        <p v-if="post.excerpt" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ post.excerpt }}</p>
                    </li>
                </ul>
            </Card>
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No news yet.</p></Card>

            <div v-if="posts.prev_page_url || posts.next_page_url" class="flex justify-between">
                <Link v-if="posts.prev_page_url" :href="posts.prev_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="posts.next_page_url" :href="posts.next_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
