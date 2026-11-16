<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import StatusAlert from '../../../Components/StatusAlert.vue';

defineProps({ groups: { type: Array, default: () => [] } });

const page = usePage();
const form = useForm({ title: '', description: '' });
const suggest = () => form.post('/roadmap', { preserveScroll: true, onSuccess: () => form.reset() });
const vote = (item) => router.post(`/roadmap/${item.id}/vote`, {}, { preserveScroll: true });
const votable = (status) => ['idea', 'planned', 'building'].includes(status);
</script>

<template>
    <Head :title="$t('Roadmap')" />
    <div class="lp-card">
        <h1>{{ $t('Roadmap') }}</h1>
        <p class="lp-muted">{{ $t('What we are building, what is planned, and ideas you can vote for.') }}</p>
        <StatusAlert />
    </div>
    <template v-for="group in groups" :key="group.status">
        <section v-if="group.items.length" class="lp-card">
            <h2>{{ group.label }}</h2>
            <ul class="lp-list">
                <li v-for="item in group.items" :key="item.id">
                    <span><strong>{{ item.title }}</strong><template v-if="item.description"><br><span class="lp-muted">{{ item.description }}</span></template></span>
                    <template v-if="votable(group.status)">
                        <button v-if="page.props.auth" :class="['lp-btn', { 'lp-btn--quiet': !item.voted }]" type="button" :aria-pressed="item.voted" @click="vote(item)">{{ $tc(':count vote|:count votes', item.votes) }}</button>
                        <span v-else class="lp-muted">{{ $tc(':count vote|:count votes', item.votes) }}</span>
                    </template>
                </li>
            </ul>
        </section>
    </template>
    <div class="lp-card">
        <h2>{{ $t('Suggest an idea') }}</h2>
        <form v-if="page.props.auth" @submit.prevent="suggest">
            <FormField id="title" v-model="form.title" :label="$t('Your idea')" maxlength="160" :error="form.errors.title" />
            <div class="lp-field">
                <label for="description">{{ $t('More detail (optional)') }}</label>
                <textarea id="description" v-model="form.description" maxlength="2000" rows="3" />
                <p v-if="form.errors.description" class="lp-error">{{ form.errors.description }}</p>
            </div>
            <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Suggest it') }}</button>
        </form>
        <p v-else><Link href="/login">{{ $t('Sign in to suggest an idea or vote.') }}</Link></p>
    </div>
</template>
