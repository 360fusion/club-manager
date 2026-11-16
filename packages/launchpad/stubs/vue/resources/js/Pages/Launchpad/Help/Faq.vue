<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SearchBox from '../../../Components/SearchBox.vue';

defineProps({ query: { type: String, default: '' }, faqs: { type: Array, default: () => [] } });
</script>

<template>
    <Head :title="$t('Frequently asked questions')"><meta name="description" :content="$t('Short answers to the questions we are asked most.')"></Head>
    <div class="lp-card">
        <h1>{{ $t('Frequently asked questions') }}</h1>
        <SearchBox action="/faq" :query="query" :label="$t('Search the questions')" />
        <details v-for="faq in faqs" :key="faq.slug">
            <summary>{{ faq.title }}</summary>
            <div class="lp-prose"><div v-html="faq.html" /><p><Link :href="`/faq/${faq.slug}`">{{ $t('Link to this answer') }}</Link></p></div>
        </details>
        <p v-if="!faqs.length">
            <Trans k="No question matched. :ask.">
                <template #ask><Link href="/contact">{{ $t('Ask us') }}</Link></template>
            </Trans>
        </p>
    </div>
</template>
