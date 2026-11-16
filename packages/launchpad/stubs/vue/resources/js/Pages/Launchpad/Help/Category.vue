<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ category: { type: Object, required: true }, articles: { type: Array, required: true } });
</script>

<template>
    <Head :title="category.title"><meta name="description" :content="category.description"></Head>
    <div class="lp-card">
        <p><Link href="/help">{{ $t('Help centre') }}</Link></p>
        <h1>{{ category.title }}</h1>
        <p class="lp-muted">{{ category.description }}</p>
        <ul>
            <li v-for="article in articles" :key="article.slug">
                <Link :href="`/help/${article.slug}`">{{ article.title }}</Link><br><span class="lp-muted">{{ article.description }}</span>
            </li>
        </ul>
    </div>
</template>
