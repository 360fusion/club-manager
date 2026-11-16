<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ faq: { type: Object, required: true } });
</script>

<template>
    <Head :title="faq.title" />
    <article class="lp-card lp-prose">
        <p><Link href="/faq">{{ $t('Frequently asked questions') }}</Link></p>
        <h1>{{ faq.title }}</h1>
        <div v-html="faq.html" />
    </article>
</template>
