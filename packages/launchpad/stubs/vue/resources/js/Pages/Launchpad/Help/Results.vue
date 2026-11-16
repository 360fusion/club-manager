<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SearchBox from '../../../Components/SearchBox.vue';

defineProps({ query: { type: String, default: '' }, results: { type: Array, default: () => [] } });
</script>

<template>
    <Head :title="query ? $t('Results for :query', { query }) : $t('Search')"><meta name="robots" content="noindex"></Head>
    <div class="lp-card">
        <h1>{{ $t('Search the help centre') }}</h1>
        <SearchBox :query="query" />
        <template v-if="query">
            <p v-for="result in results" :key="`${result.kind}-${result.slug}`">
                <Link :href="result.kind === 'faq' ? `/faq/${result.slug}` : `/help/${result.slug}`"><strong>{{ result.title }}</strong></Link>
                <span class="lp-muted"> {{ result.kind === 'faq' ? $t('FAQ') : $t('Guide') }}</span><br>
                <span class="lp-muted">{{ result.snippet }}</span>
            </p>
            <p v-if="!results.length">
                <Trans k="Nothing matched &quot;:query&quot;. Try other words, or :ask." :values="{ query }">
                    <template #ask><Link href="/contact">{{ $t('ask us') }}</Link></template>
                </Trans>
            </p>
        </template>
    </div>
</template>
