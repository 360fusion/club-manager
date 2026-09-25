<script setup>
// The top of a news article page: a link back to the news, the date and author, the title, summary and cover photo.
// The server builds this block for /site/{club}/news/{article}; it is not something an editor adds to a page.
import { Link } from '@inertiajs/vue3';

defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusLg: { type: String, default: 'rounded-3xl' },
});
</script>

<template>
    <section class="mx-auto w-full max-w-3xl space-y-5">
        <component :is="interactive ? Link : 'span'" :href="interactive ? resolveUrl(block.back_url) : undefined" :class="['inline-block text-xs font-semibold uppercase tracking-[0.15em] hover:underline', theme.accentText]">← {{ block.back_label || 'News' }}</component>
        <p :class="['text-xs font-medium', theme.bodyText]">
            <span v-if="block.published_at">{{ block.published_at }}</span><span v-if="block.published_at && block.author_name"> · </span><span v-if="block.author_name">By {{ block.author_name }}</span>
        </p>
        <h1 :class="['text-3xl font-semibold leading-tight tracking-tight sm:text-5xl', theme.headingText]">{{ block.title }}</h1>
        <p v-if="block.excerpt" :class="['border-l-4 border-[var(--cm-accent)] pl-4 text-lg italic leading-relaxed', theme.bodyText]">{{ block.excerpt }}</p>
        <img v-if="block.cover_image_url" :src="block.cover_image_url" :alt="block.title" :class="['max-h-[28rem] w-full object-cover', radiusLg]" />
    </section>
</template>
