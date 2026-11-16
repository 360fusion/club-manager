<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    action: { type: String, default: '/help/search' },
    query: { type: String, default: '' },
    // the sentence to show on the box; the help centre's own when the page gives none
    label: { type: String, default: null },
});
const q = ref(props.query);
const search = () => router.get(props.action, { q: q.value });
</script>

<template>
    <form class="lp-search" role="search" @submit.prevent="search">
        <label class="lp-skip" for="q">{{ label ?? $t('Search the help centre') }}</label>
        <input id="q" v-model="q" name="q" type="search" :placeholder="label ?? $t('Search the help centre')" autocomplete="off">
        <button class="lp-btn" type="submit">{{ $t('Search') }}</button>
    </form>
</template>
