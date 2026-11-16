<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { locale } from '../../../../i18n';

defineProps({ posts: { type: Array, default: () => [] }, newerUrl: { type: String, default: null }, olderUrl: { type: String, default: null } });

const day = (iso) => new Date(iso).toLocaleDateString(locale(), { day: 'numeric', month: 'long', year: 'numeric' });
</script>

<template>
    <Head :title="$t('Blog')">
        <link rel="alternate" type="application/atom+xml" :title="$t('Blog')" href="/blog/feed.xml">
    </Head>
    <div class="lp-card lp-prose">
        <h1>{{ $t('Blog') }}</h1>
        <article v-for="post in posts" :key="post.slug">
            <h2><Link :href="`/blog/${post.slug}`">{{ post.title }}</Link></h2>
            <p class="lp-muted"><time :datetime="post.published">{{ day(post.published) }}</time></p>
            <p v-if="post.summary">{{ post.summary }}</p>
        </article>
        <p v-if="!posts.length">{{ $t('Nothing here yet.') }}</p>
        <p>
            <Link v-if="newerUrl" :href="newerUrl">{{ $t('Newer') }}</Link>
            <Link v-if="olderUrl" :href="olderUrl">{{ $t('Older') }}</Link>
        </p>
    </div>
</template>
