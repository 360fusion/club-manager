<script setup>
// The "Latest news" list beside a news article: the newest other articles, each with its date and photo, and a link
// to all the news. Drawn with the site's own theme classes so it matches whichever theme the club uses.
import { Link } from '@inertiajs/vue3';

defineProps({
    sidebar: { type: Object, required: true },
    theme: { type: Object, required: true },
    resolveUrl: { type: Function, default: (url) => url },
});
</script>

<template>
    <aside v-if="sidebar.items?.length" aria-labelledby="latest-news-heading" :class="['space-y-5 border p-6', theme.radiusLg || 'rounded-2xl', theme.cardBg]">
        <h2 id="latest-news-heading" :class="['text-sm font-bold uppercase tracking-[0.15em]', theme.accentText]">{{ sidebar.heading || 'Latest news' }}</h2>

        <ul class="space-y-5">
            <li v-for="item in sidebar.items" :key="item.id">
                <Link :href="resolveUrl(item.url)" class="group flex gap-3">
                    <img v-if="item.cover_image_url" :src="item.cover_image_url" alt="" loading="lazy" :class="['h-16 w-16 shrink-0 object-cover', theme.radiusMd || 'rounded-xl']" />
                    <span class="min-w-0">
                        <span :class="['block text-sm font-semibold leading-snug group-hover:underline', theme.headingText]">{{ item.title }}</span>
                        <span v-if="item.published_at" :class="['mt-1 block text-xs', theme.bodyText]">{{ item.published_at }}</span>
                    </span>
                </Link>
            </li>
        </ul>

        <Link v-if="sidebar.all_url" :href="resolveUrl(sidebar.all_url)" :class="['inline-block text-xs font-bold uppercase tracking-[0.15em] hover:underline', theme.accentText]">{{ sidebar.all_label || 'All news' }} →</Link>
    </aside>
</template>
