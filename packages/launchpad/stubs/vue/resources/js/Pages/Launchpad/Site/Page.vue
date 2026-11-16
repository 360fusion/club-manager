<script setup>
import { Head } from '@inertiajs/vue3';
import { locale } from '../../../i18n';

// A page of text from a Markdown file. The HTML was made on the server from our own file, with raw HTML and unsafe links removed.
defineProps({ page: { type: Object, required: true }, showTemplateNotice: Boolean });

const formatDate = (iso) => new Date(iso).toLocaleDateString(locale(), { day: 'numeric', month: 'long', year: 'numeric' });
</script>

<template>
    <Head :title="page.title">
        <meta name="description" :content="page.description">
        <meta v-if="page.noindex" name="robots" content="noindex">
    </Head>
    <article class="lp-card lp-prose">
        <div v-if="showTemplateNotice" class="lp-alert lp-alert--error" role="note">
            <strong>{{ $t('Template text.') }}</strong> {{ $t('This page has not been read and approved yet. It is shown like this only outside production, and the doctor check stops a production site that still has it.') }}
        </div>
        <h1>{{ page.title }}</h1>
        <p v-if="page.updated" class="lp-muted">{{ $t('Last updated :date', { date: formatDate(page.updated) }) }}</p>
        <div v-html="page.html" />
    </article>
</template>
