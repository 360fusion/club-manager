<script setup>
import { router } from '@inertiajs/vue3';

// The live announcements for this person (Launchpad\Site\Announcements), from the shared `announcements` prop. `html` is staff-written Markdown
// rendered on the server with raw HTML stripped, the same as the site's pages.
defineProps({ items: { type: Array, default: () => [] } });
</script>

<template>
    <section v-for="a in items" :key="a.id" class="lp-alert" :class="{ 'lp-alert--warning': a.level === 'warning' }" :aria-labelledby="`lp-announcement-${a.id}`">
        <h2 :id="`lp-announcement-${a.id}`" class="lp-alert__title">{{ a.title }}</h2>
        <div v-html="a.html" />
        <button v-if="a.dismissible" class="lp-btn lp-btn--quiet" type="button" @click="router.post(`/announcements/${a.id}/dismiss`, {}, { preserveScroll: true })">{{ $t('Hide') }}</button>
    </section>
</template>
