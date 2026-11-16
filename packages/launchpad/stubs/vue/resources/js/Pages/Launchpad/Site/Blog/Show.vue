<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { locale } from '../../../../i18n';

// the HTML was made on the server from the post's Markdown, with raw HTML and unsafe links removed
defineProps({ post: { type: Object, required: true } });

const day = (iso) => new Date(iso).toLocaleDateString(locale(), { day: 'numeric', month: 'long', year: 'numeric' });
</script>

<template>
    <Head :title="post.title">
        <meta name="description" :content="post.summary ?? ''">
    </Head>
    <article class="lp-card lp-prose">
        <p><Link href="/blog">{{ $t('Blog') }}</Link></p>
        <h1>{{ post.title }}</h1>
        <p class="lp-muted"><time :datetime="post.published">{{ day(post.published) }}</time><template v-if="post.author"> · {{ post.author }}</template></p>
        <div v-html="post.html" />
    </article>
</template>
