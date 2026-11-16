<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ name: { type: String, required: true }, tagline: { type: String, default: null } });
</script>

<template>
    <Head :title="tagline ?? name" />
    <section class="lp-card lp-hero">
        <h1>{{ tagline ?? $t('Welcome to :name', { name }) }}</h1>
        <p class="lp-muted">{{ $t(':name is ready to be made yours: replace this page with your own.', { name }) }}</p>
        <p>
            <Link class="lp-btn" href="/register">{{ $t('Start free') }}</Link>
            <Link class="lp-btn lp-btn--quiet" href="/pricing">{{ $t('See pricing') }}</Link>
        </p>
    </section>
</template>
