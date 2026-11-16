<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ categories: { type: Array, required: true } });

const chosen = reactive(Object.fromEntries(props.categories.map((c) => [c.key, c.granted])));
const save = () => router.post('/cookie-consent', { choice: 'custom', categories: Object.keys(chosen).filter((key) => chosen[key]) });
</script>

<template>
    <Head :title="$t('Cookie preferences')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Cookie preferences') }}</h1>
            <p class="lp-muted">
                <Trans k="Strictly necessary cookies are always on: without them you could not sign in. The others only run if you allow them. See the :policy.">
                    <template #policy><a href="/cookies">{{ $t('Cookie Policy') }}</a></template>
                </Trans>
            </p>
            <form @submit.prevent="save">
                <div v-for="category in categories" :key="category.key" class="lp-check">
                    <input :id="`c-${category.key}`" v-model="chosen[category.key]" type="checkbox"><label :for="`c-${category.key}`">{{ category.label }}</label>
                </div>
                <button class="lp-btn" type="submit">{{ $t('Save my choice') }}</button>
            </form>
        </div>
    </div>
</template>
