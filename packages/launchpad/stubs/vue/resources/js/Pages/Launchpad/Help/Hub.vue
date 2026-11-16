<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SearchBox from '../../../Components/SearchBox.vue';

defineProps({ categories: { type: Array, required: true } });
</script>

<template>
    <Head :title="$t('Help centre')">
        <meta name="description" :content="$t('Guides and answers for your account, your team and billing.')">
    </Head>
    <div class="lp-card">
        <h1>{{ $t('How can we help?') }}</h1>
        <SearchBox />
        <p class="lp-muted"><Link href="/faq">{{ $t('Frequently asked questions') }}</Link> &middot; <Link href="/contact">{{ $t('Contact us') }}</Link></p>
    </div>
    <div class="lp-plans">
        <section v-for="category in categories" :key="category.slug" class="lp-card" :aria-labelledby="`cat-${category.slug}`">
            <h2 :id="`cat-${category.slug}`"><Link :href="`/help/topic/${category.slug}`">{{ category.title }}</Link></h2>
            <p class="lp-muted">{{ category.description }}</p>
            <ul><li v-for="article in category.articles" :key="article.slug"><Link :href="`/help/${article.slug}`">{{ article.title }}</Link></li></ul>
        </section>
    </div>
</template>
