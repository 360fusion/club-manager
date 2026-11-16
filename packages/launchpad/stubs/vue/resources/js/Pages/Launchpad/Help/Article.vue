<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StatusAlert from '../../../Components/StatusAlert.vue';

const props = defineProps({
    article: { type: Object, required: true },
    category: { type: Object, default: null },
    related: { type: Array, default: () => [] },
});

const form = useForm({ helpful: '1', comment: '' });
const answer = (helpful) => {
    form.helpful = helpful ? '1' : '0';
    form.post(`/help/${props.article.slug}/feedback`, { preserveScroll: true, onSuccess: () => form.reset('comment') });
};
</script>

<template>
    <Head :title="article.title"><meta name="description" :content="article.description"></Head>
    <article class="lp-card lp-prose">
        <p><Link href="/help">{{ $t('Help centre') }}</Link><template v-if="category"> / <Link :href="`/help/topic/${category.slug}`">{{ category.title }}</Link></template></p>
        <h1>{{ article.title }}</h1>
        <StatusAlert />
        <div v-html="article.html" />
        <template v-if="related.length">
            <h2>{{ $t('Related') }}</h2>
            <ul><li v-for="item in related" :key="item.slug"><Link :href="`/help/${item.slug}`">{{ item.title }}</Link></li></ul>
        </template>
    </article>
    <div class="lp-card">
        <h2>{{ $t('Was this helpful?') }}</h2>
        <form @submit.prevent>
            <div class="lp-field">
                <label for="comment">{{ $t('Anything to add? (optional)') }}</label>
                <textarea id="comment" v-model="form.comment" rows="3" maxlength="1000" />
            </div>
            <button class="lp-btn" type="button" :disabled="form.processing" @click="answer(true)">{{ $t('Yes') }}</button>
            <button class="lp-btn lp-btn--quiet" type="button" :disabled="form.processing" @click="answer(false)">{{ $t('No') }}</button>
        </form>
    </div>
</template>
